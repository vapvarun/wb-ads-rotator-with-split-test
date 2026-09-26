<?php
/**
 * Leftovers from the Featured owner review (card 10343726590):
 * - listing expires_at is UTC; every comparison uses a UTC value from PHP,
 *   never SQL NOW() (the QA DB runs at +05:30) or site-local time;
 * - an admin tick/untick of Featured goes through add_upgrades() /
 *   downgrade_to_standard(), so it gets a row the cron can end, and a
 *   custom end date is followed;
 * - a new Featured period clears the renew-reminder flag.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Classified_Meta_Box;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Classified_Utc_And_Admin_Featured extends Pro_Test_Case {

	private object $advertiser;

	private string $db_time_zone = 'SYSTEM';

	/** @var array<int,array> */
	private array $events = array();

	/** @var array */
	private array $post = array();

	public function set_up(): void {
		// add_upgrades() commits its own transaction; keep these settings from leaking.
		$this->snapshot_options( array( 'wbam_pro_settings', 'wbam_pro_classifieds_settings', 'timezone_string' ) );
		parent::set_up();

		global $wpdb;
		$this->db_time_zone = (string) $wpdb->get_var( 'SELECT @@session.time_zone' ); // phpcs:ignore WordPress.DB
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_classifieds" ); // phpcs:ignore WordPress.DB -- test isolation.
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_classified_upgrades" ); // phpcs:ignore WordPress.DB -- test isolation.

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
		Settings_Helper::update_module( 'classifieds', 'upgrade_duration', 7 );

		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		$this->post       = $_POST;

		$this->events = array();
		add_action(
			'wbam_classified_featured_started',
			function ( $c, $source ) {
				$this->events[] = array( 'started', (int) $c->id, $source );
			},
			10,
			2
		);
		add_action(
			'wbam_classified_featured_ended',
			function ( $c, $reason ) {
				$this->events[] = array( 'ended', (int) $c->id, $reason );
			},
			10,
			2
		);
	}

	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'SET time_zone = %s', $this->db_time_zone ) ); // phpcs:ignore WordPress.DB -- restore the session clock.
		$_POST = $this->post;
		parent::tear_down();
	}

	private function listing( string $status = 'active', ?string $expires_at = null ) {
		$manager    = Classified_Manager::get_instance();
		$classified = $manager->create(
			array(
				'title'         => 'UTC probe ' . wp_generate_password( 4, false ),
				'description'   => 'Probe.',
				'advertiser_id' => $this->advertiser->id,
			)
		);
		$this->assertNotWPError( $classified );
		$data = array( 'status' => $status );
		$manager->update( (int) $classified->id, $data );
		if ( null !== $expires_at ) {
			global $wpdb;
			$wpdb->update( $wpdb->prefix . 'wbam_classifieds', array( 'expires_at' => $expires_at ), array( 'id' => $classified->id ) ); // phpcs:ignore WordPress.DB
		}
		return $manager->get( (int) $classified->id );
	}

	private function featured_row( int $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wbam_classified_upgrades WHERE classified_id = %d AND upgrade_type = 'featured' ORDER BY id DESC LIMIT 1", $id ) ); // phpcs:ignore WordPress.DB
	}

	/** Item 1: a listing ending in one UTC hour is still live, on a +05:30 site with a +05:30 DB clock. */
	public function test_listing_expiry_is_compared_in_utc(): void {
		global $wpdb;
		update_option( 'timezone_string', 'Asia/Kolkata' );
		$wpdb->query( "SET time_zone = '+05:30'" ); // phpcs:ignore WordPress.DB -- the QA machine's DB clock.

		$live = $this->listing( 'active', gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) );
		$gone = $this->listing( 'active', gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) );

		$ids = array_map( 'intval', wp_list_pluck( (array) Classified_Manager::get_instance()->get_classifieds( array( 'status' => 'active' ) )['items'], 'id' ) );
		$this->assertContains( (int) $live->id, $ids, 'SQL NOW() at +05:30 must not hide it 5.5 hours early.' );
		$this->assertNotContains( (int) $gone->id, $ids );

		Classified_Manager::get_instance()->expire_classifieds();
		$this->assertSame( 'active', Classified_Manager::get_instance()->get( (int) $live->id )->status, 'The expiry cron compares in UTC, not site-local time.' );
		$this->assertSame( 'expired', Classified_Manager::get_instance()->get( (int) $gone->id )->status );

		// Renewing an expired listing starts from UTC now.
		Classified_Manager::get_instance()->renew( (int) $gone->id, 30 );
		$this->assertEqualsWithDelta( time() + 30 * DAY_IN_SECONDS, strtotime( Classified_Manager::get_instance()->get( (int) $gone->id )->expires_at . ' UTC' ), 120 );
	}

	private function save_meta_box( $classified, array $fields ): void {
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = array_merge( array( 'wbam_classified_nonce' => wp_create_nonce( 'wbam_classified_meta_box' ) ), $fields );
		Classified_Meta_Box::get_instance()->save_meta_box( (int) $classified->post_id, get_post( (int) $classified->post_id ) );
	}

	/** Item 2: an admin tick gets the same row and period as every other path; untick ends it the same way. */
	public function test_admin_tick_and_untick_go_through_the_one_path(): void {
		$classified = $this->listing();

		$this->save_meta_box( $classified, array( 'wbam_is_featured' => '1' ) );
		$row = $this->featured_row( (int) $classified->id );
		$this->assertNotNull( $row, 'An admin tick writes the upgrade row the expiry cron ends.' );
		$this->assertEqualsWithDelta( time() + 7 * DAY_IN_SECONDS, strtotime( $row->expires_at . ' UTC' ), 120 );

		$this->save_meta_box( $classified, array() );
		$this->assertSame( 'expired', $this->featured_row( (int) $classified->id )->status, 'Untick ends the row too.' );
		$this->assertSame( 'standard', Classified_Manager::get_instance()->get( (int) $classified->id )->listing_type );
		$this->assertSame(
			array(
				array( 'started', (int) $classified->id, 'admin' ),
				array( 'ended', (int) $classified->id, 'admin' ),
			),
			$this->events
		);
	}

	/** Item 2: a custom end date set by the admin is the row's end date, on tick and when changed later. */
	public function test_admin_custom_end_date_is_followed(): void {
		$classified = $this->listing();
		$first      = gmdate( 'Y-m-d\TH:i', time() + 3 * DAY_IN_SECONDS );
		$this->save_meta_box(
			$classified,
			array(
				'wbam_is_featured'         => '1',
				'wbam_featured_expires_at' => $first,
			)
		);
		$this->assertSame( gmdate( 'Y-m-d H:i:00', strtotime( $first . ' UTC' ) ), $this->featured_row( (int) $classified->id )->expires_at );

		$later = gmdate( 'Y-m-d\TH:i', time() + 20 * DAY_IN_SECONDS );
		$this->save_meta_box(
			$classified,
			array(
				'wbam_is_featured'         => '1',
				'wbam_featured_expires_at' => $later,
			)
		);
		$this->assertSame( gmdate( 'Y-m-d H:i:00', strtotime( $later . ' UTC' ) ), $this->featured_row( (int) $classified->id )->expires_at );
	}

	/** Item 3: a new Featured period clears the renew-reminder flag, so it gets its own reminder. */
	public function test_new_period_clears_the_renew_reminder_flag(): void {
		$classified = $this->listing();
		update_post_meta( (int) $classified->post_id, '_wbam_expiration_warning_sent', '2026-09-01 00:00:00' );

		$this->assertTrue( Classified_Manager::get_instance()->add_upgrades( (int) $classified->id, array( 'featured' ) ) );

		$this->assertSame( '', get_post_meta( (int) $classified->post_id, '_wbam_expiration_warning_sent', true ) );
	}
}
