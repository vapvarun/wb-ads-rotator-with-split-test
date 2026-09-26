<?php
/**
 * Featured / upgrade follow-ups from the owner review of card 10343726590:
 * one UTC clock for every upgrade row, events only after commit, one
 * wbam_classified_featured_started / _ended contract on every path, two
 * filters, and Promote links named after their listing.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Core\Cron_Manager;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_API;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Shortcodes;
use WBAM_Pro\Modules\Memberships\Membership_Manager;

class Test_Featured_Events_And_Clocks extends Pro_Test_Case {

	private object $advertiser;

	private int $user_id;

	/** @var array<int,array> */
	private array $started = array();

	/** @var array<int,array> */
	private array $ended = array();

	private string $db_time_zone = 'SYSTEM';

	public function set_up(): void {
		// add_upgrades() commits its own transaction, which would commit
		// these settings past the test rollback.
		$this->snapshot_options( array( 'wbam_pro_settings', 'wbam_pro_classifieds_settings', 'wbam_credits_payment_method', 'timezone_string' ) );
		parent::set_up();

		global $wpdb;
		// The +05:30 test changes the DB session clock; restore it after.
		$this->db_time_zone = (string) $wpdb->get_var( 'SELECT @@session.time_zone' ); // phpcs:ignore WordPress.DB
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_classifieds" ); // phpcs:ignore WordPress.DB -- test isolation, see Test_Classified_Featured_Upgrade_Fee.
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_classified_upgrades" ); // phpcs:ignore WordPress.DB -- test isolation.

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		$enabled['memberships'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
		update_option( 'wbam_credits_payment_method', 'manual' );
		Settings_Helper::update_module( 'classifieds', 'featured_price', 5 );
		Settings_Helper::update_module( 'classifieds', 'upgrade_duration', 7 );
		Settings_Helper::update_module( 'classifieds', 'require_approval', false );

		$this->user_id    = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user_id );
		Factory::topup_user( $this->user_id, 10000 );

		$this->started = array();
		$this->ended   = array();
		add_action(
			'wbam_classified_featured_started',
			function ( $classified, $source, $amount ) {
				$this->started[] = array( (int) $classified->id, $source, (float) $amount );
			},
			10,
			3
		);
		add_action(
			'wbam_classified_featured_ended',
			function ( $classified, $reason ) {
				$this->ended[] = array( (int) $classified->id, $reason );
			},
			10,
			2
		);
	}

	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'SET time_zone = %s', $this->db_time_zone ) ); // phpcs:ignore WordPress.DB -- restore the session clock.
		parent::tear_down();
	}

	private function active_listing() {
		$manager    = Classified_Manager::get_instance();
		$classified = $manager->create(
			array(
				'title'         => 'Event probe ' . wp_generate_password( 4, false ),
				'description'   => 'Probe.',
				'advertiser_id' => $this->advertiser->id,
			)
		);
		$this->assertNotWPError( $classified );
		$manager->update( (int) $classified->id, array( 'status' => 'active' ) );
		return $manager->get( (int) $classified->id );
	}

	private function row( int $classified_id, string $type ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wbam_classified_upgrades WHERE classified_id = %d AND upgrade_type = %s ORDER BY id DESC LIMIT 1", $classified_id, $type ) ); // phpcs:ignore WordPress.DB
	}

	/** Every upgrade row is stored and compared in UTC, on a +05:30 site with a +05:30 DB clock. */
	public function test_upgrade_rows_use_utc_on_a_plus_0530_site(): void {
		global $wpdb;
		update_option( 'timezone_string', 'Asia/Kolkata' );
		$wpdb->query( "SET time_zone = '+05:30'" ); // phpcs:ignore WordPress.DB -- the QA machine's DB clock.

		$classified = $this->active_listing();
		$this->assertTrue( Classified_Manager::get_instance()->add_upgrades( (int) $classified->id, array( 'highlighted' ) ) );

		$row = $this->row( (int) $classified->id, 'highlighted' );
		$this->assertEqualsWithDelta( time(), strtotime( $row->starts_at . ' UTC' ), 120, 'starts_at is UTC.' );
		$this->assertEqualsWithDelta( time() + 7 * DAY_IN_SECONDS, strtotime( $row->expires_at . ' UTC' ), 120, 'expires_at is UTC.' );

		// Ends in one UTC hour: still running, and the cron must leave it.
		$wpdb->update( $wpdb->prefix . 'wbam_classified_upgrades', array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) ), array( 'id' => $row->id ) ); // phpcs:ignore WordPress.DB
		$this->assertArrayHasKey( 'highlighted', $classified->get_active_upgrades(), 'SQL NOW() at +05:30 must not end it 5.5 hours early.' );
		( new \ReflectionClass( Cron_Manager::class ) )->newInstanceWithoutConstructor()->expire_upgrades();
		$this->assertSame( 'active', $this->row( (int) $classified->id, 'highlighted' )->status, 'The cron must compare in UTC, not site-local time.' );

		// Ended one UTC hour ago: the cron expires it.
		$wpdb->update( $wpdb->prefix . 'wbam_classified_upgrades', array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ), array( 'id' => $row->id ) ); // phpcs:ignore WordPress.DB
		( new \ReflectionClass( Cron_Manager::class ) )->newInstanceWithoutConstructor()->expire_upgrades();
		$this->assertSame( 'expired', $this->row( (int) $classified->id, 'highlighted' )->status );
	}

	/** A failed charge rolls everything back and no listener hears about an upgrade that never happened. */
	public function test_no_event_when_the_charge_fails(): void {
		$heard = 0;
		add_action(
			'wbam_classified_upgraded',
			function () use ( &$heard ) {
				++$heard;
			}
		);
		$classified = $this->active_listing();

		$result = Classified_Manager::get_instance()->add_upgrades(
			(int) $classified->id,
			array( 'featured' ),
			array(
				'source' => 'promote',
				'amount' => 5.0,
				'charge' => static function () {
					return new \WP_Error( 'debit_failed', 'No.' );
				},
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 0, $heard, 'wbam_classified_upgraded fires only after COMMIT.' );
		$this->assertSame( array(), $this->started );
		$this->assertNull( $this->row( (int) $classified->id, 'featured' ), 'The row is rolled back.' );
		$this->assertSame( 'standard', Classified_Manager::get_instance()->get( (int) $classified->id )->listing_type );
	}

	/** Promote: started once, source promote, the amount charged; the old action still fires, deprecated. */
	public function test_promote_fires_started_once(): void {
		$this->setExpectedDeprecated( 'wbam_classified_featured_upgraded' );
		add_action( 'wbam_classified_featured_upgraded', '__return_null' );

		$classified = $this->active_listing();
		$this->assertTrue( $classified->upgrade_to_featured( 1, 5.0, true ) );

		$this->assertSame( array( array( (int) $classified->id, 'promote', 5.0 ) ), $this->started );
		$this->assertNotFalse( has_action( 'wbam_classified_featured_started', array( \WBAM_Pro\Modules\Classifieds\Classified_Billing::class, 'notify_featured_started' ) ), 'The confirmation email hangs off _started.' );
	}

	/** Posting (paid) and posting on a plan credit both fire started. */
	public function test_posting_and_plan_fire_started(): void {
		$term = wp_insert_term( 'Events ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );
		$input = array(
			'title'           => 'Posted featured',
			'description'     => 'Posted.',
			'categories'      => array( (int) $term['term_id'] ),
			'listing_package' => 0,
			'upgrades'        => array( 'featured' ),
		);

		$paid = Classified_Manager::get_instance()->submit( $this->advertiser, $input );
		$this->assertNotWPError( $paid, is_wp_error( $paid ) ? $paid->get_error_message() : '' );

		$members = Membership_Manager::get_instance();
		$members->save_plan(
			array(
				'name'          => 'Featured plan',
				'price'         => 0,
				'billing_cycle' => 'monthly',
				'max_listings'  => 0,
				'max_featured'  => 1,
				'status'        => 'active',
			)
		);
		$plans = $members->get_plans();
		$this->assertNotWPError( $members->subscribe( $this->advertiser->id, end( $plans )->id ) );
		$plan = Classified_Manager::get_instance()->submit( $this->advertiser, $input );
		$this->assertNotWPError( $plan );

		$this->assertSame(
			array(
				array( (int) $paid->id, 'posting', 5.0 ),
				array( (int) $plan->id, 'plan', 0.0 ),
			),
			$this->started
		);
	}

	/** REST: started with source rest, and the charge happens inside the same commit. */
	public function test_rest_fires_started(): void {
		wp_set_current_user( $this->user_id );
		$classified = $this->active_listing();
		$request    = new \WP_REST_Request( 'POST', '/wbam-pro/v1/classifieds/' . $classified->id . '/upgrades' );
		$request->set_param( 'id', $classified->id );
		$request->set_param( 'upgrades', array( 'featured' ) );

		$response = ( new \ReflectionClass( Classified_API::class ) )->newInstanceWithoutConstructor()->add_upgrade( $request );

		$this->assertNotWPError( $response );
		$this->assertSame( array( array( (int) $classified->id, 'rest', 5.0 ) ), $this->started );
	}

	/** The expiry cron ends Featured with reason 'expired'. */
	public function test_cron_expiry_fires_ended(): void {
		global $wpdb;
		$classified = $this->active_listing();
		Classified_Manager::get_instance()->add_upgrades( (int) $classified->id, array( 'featured' ) );
		$wpdb->update( $wpdb->prefix . 'wbam_classified_upgrades', array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ), array( 'classified_id' => $classified->id ) ); // phpcs:ignore WordPress.DB

		( new \ReflectionClass( Cron_Manager::class ) )->newInstanceWithoutConstructor()->expire_upgrades();

		$this->assertSame( array( array( (int) $classified->id, 'expired' ) ), $this->ended );
	}

	/** New filters with unchanged defaults. */
	public function test_price_and_can_upgrade_filters(): void {
		$classified = $this->active_listing();
		$this->assertSame( 5.0, Settings_Helper::classified_upgrade_price( 'featured', $classified ) );

		$price = static function ( $p, $c ) use ( $classified ) {
			return $c && (int) $c->id === (int) $classified->id ? 2.5 : $p;
		};
		add_filter( 'wbam_classified_featured_price', $price, 10, 2 );
		$this->assertSame( 2.5, $classified->get_featured_cost() );
		remove_filter( 'wbam_classified_featured_price', $price, 10 );

		$deny = static function ( $ok, $c, $type ) {
			return 'featured' === $type ? new \WP_Error( 'nope', 'Not here.' ) : $ok;
		};
		add_filter( 'wbam_classified_can_upgrade', $deny, 10, 3 );
		$result = Classified_Manager::get_instance()->add_upgrades( (int) $classified->id, array( 'featured' ) );
		remove_filter( 'wbam_classified_can_upgrade', $deny, 10 );
		$this->assertWPError( $result );
		$this->assertSame( 'nope', $result->get_error_code() );
	}

	/** The icon-only Promote links (shortcode list and portal tab) say which listing they promote. */
	public function test_promote_links_name_the_listing(): void {
		$classified = $this->active_listing();
		$label      = 'aria-label="Promote: ' . esc_attr( $classified->get_title() ) . '"';

		$shortcodes = ( new \ReflectionClass( Classified_Shortcodes::class ) )->newInstanceWithoutConstructor();
		ob_start();
		( new \ReflectionMethod( $shortcodes, 'render_classifieds_list' ) )->invoke( $shortcodes, $this->advertiser, array( 'limit' => 10 ), home_url( '/' ) );
		$this->assertStringContainsString( $label, (string) ob_get_clean() );

		$portal = ( new \ReflectionClass( \WBAM_Pro\Modules\Advertisers\Advertiser_Shortcodes::class ) )->newInstanceWithoutConstructor();
		ob_start();
		( new \ReflectionMethod( $portal, 'render_tab_classifieds' ) )->invoke( $portal, $this->advertiser );
		$this->assertStringContainsString( $label, (string) ob_get_clean() );
	}
}
