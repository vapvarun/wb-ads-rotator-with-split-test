<?php
/**
 * Settings clean-up (card 10343726590, owner decision 16): removed dead
 * fields keep their behaviour retired, and filter-converted fields keep an
 * upgraded site's stored value as the new filter's default.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Installer;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Geolocation\Geolocation_Manager;

class Test_Settings_Audit_Cleanup extends Pro_Test_Case {

	/**
	 * REMOVE: enable_map_view never had a reader (get_localize_data() already
	 * dropped it from the JS payload). get_settings() must no longer resolve
	 * it from a stale option to a default of true - there's nothing left to
	 * check it against.
	 */
	public function test_enable_map_view_is_gone_from_geolocation_settings(): void {
		update_option( 'wbam_pro_geolocation_settings', array( 'provider' => 'openstreetmap' ) );

		$settings = Geolocation_Manager::get_settings();

		$this->assertArrayNotHasKey( 'enable_map_view', $settings );
	}

	/**
	 * REMOVE + upgrade step (4.3.12): a site with the old global "Monthly
	 * Fee" (featured_fee) set but no featured_price at all gets the value
	 * carried across, and the dead keys are dropped from storage.
	 */
	public function test_upgrade_carries_featured_fee_into_featured_price_when_price_is_missing(): void {
		delete_option( 'wbam_pro_classifieds_settings' );
		update_option(
			'wbam_pro_classifieds_settings',
			array(
				'featured_fee'              => 15.0,
				'featured_duration_options' => array( 1, 3 ),
				'featured_default_duration' => 3,
			)
		);

		$method = new \ReflectionMethod( Installer::class, 'upgrade_to_4_3_12' );
		$method->setAccessible( true );
		$method->invoke( null );

		$settings = get_option( 'wbam_pro_classifieds_settings' );
		$this->assertSame( 15.0, $settings['featured_price'] );
		$this->assertArrayNotHasKey( 'featured_fee', $settings );
		$this->assertArrayNotHasKey( 'featured_duration_options', $settings );
		$this->assertArrayNotHasKey( 'featured_default_duration', $settings );
	}

	/** A site that already has both keys must not have its own featured_price overwritten. */
	public function test_upgrade_does_not_touch_an_existing_featured_price(): void {
		update_option(
			'wbam_pro_classifieds_settings',
			array(
				'featured_fee'   => 15.0,
				'featured_price' => 7.5,
			)
		);

		$method = new \ReflectionMethod( Installer::class, 'upgrade_to_4_3_12' );
		$method->setAccessible( true );
		$method->invoke( null );

		$settings = get_option( 'wbam_pro_classifieds_settings' );
		$this->assertSame( 7.5, $settings['featured_price'] );
	}

	/** get_featured_cost() (the live reader) must read featured_price, not the retired featured_fee. */
	public function test_get_featured_cost_reads_featured_price(): void {
		update_option(
			'wbam_pro_classifieds_settings',
			array(
				'featured_fee'   => 999.0,
				'featured_price' => 12.5,
			)
		);

		$classified = new \WBAM_Pro\Modules\Classifieds\Classified( (object) array() );

		$this->assertSame( 12.5, $classified->get_featured_cost() );
	}

	/**
	 * FILTER: rotation stays on by default, but the stored value is
	 * honoured, so a site that already turned it off keeps it off.
	 */
	public function test_rotation_enabled_filter_defaults_to_the_stored_value(): void {
		update_option( 'wbam_pro_settings', array( 'enable_rotation' => false ) );
		$this->assertFalse( Settings_Helper::rotation_enabled() );

		update_option( 'wbam_pro_settings', array( 'enable_rotation' => true ) );
		$this->assertTrue( Settings_Helper::rotation_enabled() );

		delete_option( 'wbam_pro_settings' );
		$this->assertTrue( Settings_Helper::rotation_enabled(), 'No stored value: rotation defaults on.' );
	}

	/** A developer can still override the rotation filter without a field to click. */
	public function test_rotation_enabled_filter_is_overridable(): void {
		update_option( 'wbam_pro_settings', array( 'enable_rotation' => true ) );
		add_filter( 'wbam_pro_rotation_enabled', '__return_false' );

		$this->assertFalse( Settings_Helper::rotation_enabled() );

		remove_filter( 'wbam_pro_rotation_enabled', '__return_false' );
	}

	/**
	 * Featured is one-time only (owner decision, follow-up to card
	 * 10343726590): the recurring billing model and the
	 * wbam_pro_featured_recurring filter are gone. 4.3.14 caps any
	 * classified still marked recurring (unlimited or multi-cycle billing)
	 * to a single cycle and clears its scheduled next billing, so it can
	 * never be charged again - it keeps its current featured_expires_at
	 * until that period naturally ends.
	 */
	public function test_upgrade_to_4_3_14_caps_a_recurring_classified_to_one_cycle(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_classifieds" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation.

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		$manager    = Classified_Manager::get_instance();
		$classified = $manager->create(
			array(
				'title'         => 'Recurring probe',
				'description'   => 'Was mid-subscription before Featured became one-time only.',
				'advertiser_id' => $advertiser->id,
				'price'         => 9.99,
			)
		);
		$this->assertNotWPError( $classified );

		$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( '+10 days' ) );

		// Simulate the pre-fix recurring state the old billing cron left
		// behind: unlimited cycles (0), a next billing date scheduled.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test setup on a custom table.
		$wpdb->update(
			$wpdb->prefix . 'wbam_classifieds',
			array(
				'featured_fee_status'   => 'paid',
				'featured_max_billing'  => 0,
				'featured_next_billing' => gmdate( 'Y-m-d H:i:s', strtotime( '+3 days' ) ),
				'featured_expires_at'   => $expires_at,
			),
			array( 'id' => (int) $classified->id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		$method = new \ReflectionMethod( Installer::class, 'upgrade_to_4_3_14' );
		$method->setAccessible( true );
		$method->invoke( null );

		$row = $manager->get( (int) $classified->id );
		$this->assertSame( 1, (int) $row->featured_max_billing, 'Recurring listing is capped to a single cycle - it is never charged again.' );
		$this->assertNull( $row->featured_next_billing, 'No further billing is ever scheduled.' );
		$this->assertSame( $expires_at, $row->featured_expires_at, 'Its current featured period is untouched - it keeps it until it naturally expires.' );
	}

	/** 4.3.14 also drops the now-dead featured_billing_model settings key. */
	public function test_upgrade_to_4_3_14_drops_the_dead_billing_model_key(): void {
		update_option( 'wbam_pro_classifieds_settings', array( 'featured_billing_model' => 'recurring' ) );

		$method = new \ReflectionMethod( Installer::class, 'upgrade_to_4_3_14' );
		$method->setAccessible( true );
		$method->invoke( null );

		$this->assertArrayNotHasKey( 'featured_billing_model', get_option( 'wbam_pro_classifieds_settings' ) );
	}

	/**
	 * 4.3.12 already drops featured_fee. If it differed from featured_price,
	 * both are saved to wbam_pro_featured_price_notice first so the one-time
	 * admin notice (card 10343726590) can tell the site which price is now
	 * in effect - the old value would otherwise be gone with no trace.
	 */
	public function test_upgrade_to_4_3_12_records_a_price_mismatch_for_the_notice(): void {
		delete_option( 'wbam_pro_featured_price_notice' );
		update_option(
			'wbam_pro_classifieds_settings',
			array(
				'featured_fee'   => 15.0,
				'featured_price' => 9.0,
			)
		);

		$method = new \ReflectionMethod( Installer::class, 'upgrade_to_4_3_12' );
		$method->setAccessible( true );
		$method->invoke( null );

		$notice = get_option( 'wbam_pro_featured_price_notice' );
		$this->assertSame( 15.0, $notice['old_fee'] );
		$this->assertSame( 9.0, $notice['new_price'] );
	}

	/** Matching prices are not a mismatch - no notice is recorded. */
	public function test_upgrade_to_4_3_12_records_no_notice_when_prices_match(): void {
		delete_option( 'wbam_pro_featured_price_notice' );
		update_option(
			'wbam_pro_classifieds_settings',
			array(
				'featured_fee'   => 9.0,
				'featured_price' => 9.0,
			)
		);

		$method = new \ReflectionMethod( Installer::class, 'upgrade_to_4_3_12' );
		$method->setAccessible( true );
		$method->invoke( null );

		$this->assertFalse( get_option( 'wbam_pro_featured_price_notice' ) );
	}

	/** FILTER: the container_class field is gone, but the stored value still applies. */
	public function test_container_class_filter_defaults_to_the_stored_value(): void {
		update_option( 'wbam_settings', array( 'container_class' => 'my-ad-wrapper' ) );

		$this->assertSame( 'my-ad-wrapper', apply_filters( 'wbam_ad_container_class', \WBAM\Core\Settings_Helper::get( 'container_class', '' ) ) );

		delete_option( 'wbam_settings' );
	}
}
