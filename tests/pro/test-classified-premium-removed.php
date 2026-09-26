<?php
/**
 * Owner decision: the 'premium' listing type is gone. Upgrade 4.3.16 turns
 * premium listings into featured ones (their featured end date kept), and
 * backfills the featured upgrade row a 3.1.1 Promote never wrote, so the
 * 3.2.0 expiry cron can end it. No API path can store 'premium' any more.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Cron_Manager;
use WBAM_Pro\Core\Installer;
use WBAM_Pro\Core\Pro_Abilities;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_API;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Classified_Premium_Removed extends Pro_Test_Case {

	private int $user;
	private object $advertiser;
	private int $term;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 100000, 'seed' );

		$term       = wp_insert_term( 'Premium gone ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );
		$this->term = (int) $term['term_id'];
	}

	private function listing( string $title ): int {
		$classified = Classified_Manager::get_instance()->submit(
			$this->advertiser,
			array(
				'title'        => $title,
				'categories'   => array( $this->term ),
				'listing_type' => 'premium',
			)
		);
		$this->assertNotWPError( $classified );
		return (int) $classified->id;
	}

	/** Write the listing columns directly, the way 3.1.1 left them. */
	private function set_row( int $id, string $type, ?string $featured_expires_at ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wbam_classifieds',
			array(
				'listing_type'        => $type,
				'featured_fee_status' => 'paid',
				'featured_expires_at' => $featured_expires_at,
				'status'              => 'active',
			),
			array( 'id' => $id )
		);
	}

	private function row( int $id ): object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT listing_type, featured_expires_at FROM {$wpdb->prefix}wbam_classifieds WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	private function upgrade_rows( int $id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wbam_classified_upgrades WHERE classified_id = %d AND upgrade_type = 'featured'", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	private function run_upgrade(): void {
		$method = new \ReflectionMethod( Installer::class, 'upgrade_to_4_3_16' );
		$method->invoke( null );
	}

	public function test_upgrade_turns_premium_into_featured_and_keeps_the_end_date(): void {
		$dated   = $this->listing( 'Dated premium' );
		$undated = $this->listing( 'Undated premium' );
		$this->set_row( $dated, 'premium', '2031-05-01 10:00:00' );
		$this->set_row( $undated, 'premium', null );

		$this->run_upgrade();

		$this->assertSame( 'featured', $this->row( $dated )->listing_type );
		$this->assertSame( '2031-05-01 10:00:00', $this->row( $dated )->featured_expires_at );
		$this->assertSame( 'featured', $this->row( $undated )->listing_type );
		$this->assertNull( $this->row( $undated )->featured_expires_at, 'No end date before, none after.' );
		$this->assertCount( 0, $this->upgrade_rows( $undated ), 'A featured listing with no end date stays open-ended.' );
	}

	public function test_311_promote_gets_an_upgrade_row_the_expiry_cron_can_end(): void {
		$id = $this->listing( 'Promoted in 3.1.1' );
		$this->set_row( $id, 'featured', '2020-01-01 00:00:00' );

		$this->run_upgrade();
		$rows = $this->upgrade_rows( $id );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'active', $rows[0]->status );
		$this->assertSame( '2020-01-01 00:00:00', $rows[0]->expires_at );

		$this->run_upgrade();
		$this->assertCount( 1, $this->upgrade_rows( $id ), 'A second run inserts nothing.' );

		Cron_Manager::get_instance()->expire_upgrades();
		$this->assertSame( 'standard', $this->row( $id )->listing_type );
	}

	public function test_core_rest_field_ignores_premium(): void {
		$id = $this->listing( 'REST edited' );
		$this->set_row( $id, 'featured', '2031-01-01 00:00:00' );
		$post_id = (int) Classified_Manager::get_instance()->get( $id )->post_id;

		// The classified_meta REST field's update callback (registered by
		// the admin meta box for the block editor).
		\WBAM_Pro\Admin\Classified_Meta_Box::get_instance()->update_classified_rest_data( array( 'listing_type' => 'premium' ), get_post( $post_id ) );

		$this->assertSame( 'featured', $this->row( $id )->listing_type, 'premium is ignored, not stored and not a downgrade.' );
	}

	public function test_plugin_rest_and_ability_create_never_store_premium(): void {
		wp_set_current_user( $this->user );

		$request = new \WP_REST_Request( 'POST', '/wbam-pro/v1/my/listings' );
		$request->set_param( 'title', 'REST premium attempt' );
		$request->set_param( 'description', 'Body' );
		$request->set_param( 'category_id', $this->term );
		$request->set_param( 'listing_type', 'premium' );
		$response = ( new Classified_API() )->create_classified( $request );
		$this->assertNotWPError( $response );
		$data = $response instanceof \WP_REST_Response ? $response->get_data() : (array) $response;
		$rest_id = (int) ( $data['id'] ?? 0 );
		$this->assertGreaterThan( 0, $rest_id );
		$this->assertSame( 'standard', $this->row( $rest_id )->listing_type );

		$result = ( new Pro_Abilities() )->execute_create_classified(
			array(
				'title'        => 'Ability premium attempt',
				'description'  => 'Body',
				'categories'   => array( $this->term ),
				'listing_type' => 'premium',
			)
		);
		$this->assertNotWPError( $result );
		$this->assertSame( 'standard', $this->row( (int) $result['id'] )->listing_type );
	}
}
