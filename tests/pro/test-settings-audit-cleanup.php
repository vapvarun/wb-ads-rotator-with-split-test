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
	 * FILTER: featured billing defaults to ONE-TIME for a fresh install
	 * (nothing stored), but an upgraded site that already saved 'recurring'
	 * keeps recurring.
	 */
	public function test_featured_recurring_defaults_one_time_on_fresh_install(): void {
		delete_option( 'wbam_pro_classifieds_settings' );

		$this->assertFalse( Settings_Helper::is_featured_recurring() );
	}

	public function test_featured_recurring_honours_an_upgraded_sites_stored_value(): void {
		update_option( 'wbam_pro_classifieds_settings', array( 'featured_billing_model' => 'recurring' ) );

		$this->assertTrue( Settings_Helper::is_featured_recurring() );
	}

	/** FILTER: the container_class field is gone, but the stored value still applies. */
	public function test_container_class_filter_defaults_to_the_stored_value(): void {
		update_option( 'wbam_settings', array( 'container_class' => 'my-ad-wrapper' ) );

		$this->assertSame( 'my-ad-wrapper', apply_filters( 'wbam_ad_container_class', \WBAM\Core\Settings_Helper::get( 'container_class', '' ) ) );

		delete_option( 'wbam_settings' );
	}
}
