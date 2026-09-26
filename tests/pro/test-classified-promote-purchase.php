<?php
/**
 * Promote upgrades can be bought and are applied (card 10340185077).
 *
 * QA repro: [wbam_my_classifieds]?action=promote, Purchase Urgent -> 403
 * "-1": the button's nonce was wbam_purchase_upgrade_{id} while the handler
 * checks wbam_classified, and the JS sent upgrade_type while the handler
 * reads upgrades[]. Urgent and bump bought with a new listing were charged
 * and never applied. The portal's My Classifieds had no way to reach Promote.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Shortcodes;
use WBAM_Pro\Modules\Classifieds\Shortcodes\Ajax_Handler;

class Test_Classified_Promote_Purchase extends Pro_Test_Case {

	private object $advertiser;
	private int $classified_id;
	private array $post;
	private array $get;

	public function set_up(): void {
		parent::set_up();
		$this->post = $_POST;
		$this->get  = $_GET;

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_classifieds" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation.

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $user, 10000, 'seed' );

		$manager             = Classified_Manager::get_instance();
		$classified          = $manager->create(
			array(
				'title'         => 'Promote probe',
				'description'   => 'Promote me.',
				'advertiser_id' => $this->advertiser->id,
			)
		);
		$this->classified_id = (int) $classified->id;
		$manager->update( $this->classified_id, array( 'status' => 'active' ) );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {
					throw new \RuntimeException( 'wp_die' );
				};
			}
		);
	}

	public function tear_down(): void {
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_die_ajax_handler' );
		$_POST = $this->post;
		$_GET  = $this->get;
		parent::tear_down();
	}

	private function promote_html(): string {
		ob_start();
		Classified_Shortcodes::get_instance()->render_promote( $this->advertiser, $this->classified_id, home_url( '/' ) );
		return (string) ob_get_clean();
	}

	public function test_the_purchase_button_buys_and_applies_urgent(): void {
		$this->assertMatchesRegularExpression( '/data-upgrade-type="urgent"\s+data-price="[^"]*"\s+data-nonce="([^"]+)"/', $this->promote_html() );
		preg_match( '/data-upgrade-type="urgent"\s+data-price="[^"]*"\s+data-nonce="([^"]+)"/', $this->promote_html(), $m );

		// What classified.js posts.
		$this->assertStringContainsString( 'upgrades: [upgradeType]', (string) file_get_contents( WBAM_PRO_PATH . 'assets/js/classified.js' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a plugin file in a test.
		$_POST = array(
			'nonce'         => $m[1],
			'classified_id' => $this->classified_id,
			'upgrades'      => array( 'urgent' ),
		);
		$_REQUEST = array_merge( $_REQUEST, $_POST );

		ob_start();
		try {
			( new Ajax_Handler() )->purchase_upgrade();
		} catch ( \RuntimeException $e ) {
			unset( $e );
		}
		$response = (array) json_decode( (string) ob_get_clean(), true );

		$this->assertTrue( $response['success'] ?? false, wp_json_encode( $response ) );
		$this->assertTrue( Classified_Manager::get_instance()->get( $this->classified_id )->has_upgrade( 'urgent' ) );
	}

	public function test_urgent_bought_with_a_new_listing_is_applied(): void {
		$settings                     = get_option( 'wbam_pro_classifieds_settings', array() );
		$settings['require_approval'] = false;
		update_option( 'wbam_pro_classifieds_settings', $settings );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );
		$advertiser = Advertiser_Manager::get_instance()->get( (int) $this->advertiser->id );
		$term       = wp_insert_term( 'Promote ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );

		$listing = Classified_Manager::get_instance()->submit(
			$advertiser,
			array(
				'title'      => 'Urgent sale',
				'categories' => array( (int) $term['term_id'] ),
				'upgrades'   => array( 'urgent' ),
			)
		);

		$this->assertNotWPError( $listing );
		$this->assertSame( 'active', $listing->status );
		$this->assertTrue( Classified_Manager::get_instance()->get( (int) $listing->id )->has_upgrade( 'urgent' ), 'Urgent was charged, so it must be applied.' );
	}

	public function test_portal_my_classifieds_reaches_promote(): void {
		$shortcodes = new \WBAM_Pro\Modules\Advertisers\Advertiser_Shortcodes();
		$render     = new \ReflectionMethod( $shortcodes, 'render_tab_classifieds' );
		$render->setAccessible( true );

		ob_start();
		$render->invoke( $shortcodes, $this->advertiser );
		$list = (string) ob_get_clean();
		$this->assertStringContainsString( 'action=promote', $list );

		$_GET['action']        = 'promote';
		$_GET['classified_id'] = (string) $this->classified_id;
		ob_start();
		$render->invoke( $shortcodes, $this->advertiser );
		$promote = (string) ob_get_clean();
		$this->assertStringContainsString( 'wbam-promote-wrapper', $promote );
	}
}
