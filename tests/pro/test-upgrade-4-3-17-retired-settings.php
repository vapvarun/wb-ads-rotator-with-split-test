<?php
/**
 * 4.3.17: settings keys the 3.2.0 settings audit retired are unset.
 *
 * Owner review of card 10343706274: removed settings were still stored
 * (e.g. enable_rotation). Dead keys go unconditionally; enable_rotation is
 * still the wbam_pro_rotation_enabled filter default, so it goes only when
 * it is on (the same as the fallback) and a site that saved it off keeps it.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Installer;
use WBAM_Pro\Core\Pro_Admin;

class Test_Upgrade_4_3_17_Retired_Settings extends Pro_Test_Case {

	/**
	 * Options this test writes, snapshotted.
	 *
	 * @var array
	 */
	private $snapshot = array();

	public function set_up(): void {
		parent::set_up();
		foreach ( array( 'wbam_pro_settings', 'wbam_pro_geolocation_settings' ) as $option ) {
			$this->snapshot[ $option ] = get_option( $option );
		}
	}

	public function tear_down(): void {
		remove_all_filters( 'sanitize_option_wbam_pro_settings' );
		remove_all_filters( 'sanitize_option_wbam_pro_geolocation_settings' );
		foreach ( $this->snapshot as $option => $value ) {
			false === $value ? delete_option( $option ) : update_option( $option, $value );
		}
		parent::tear_down();
	}

	private function run_step(): void {
		// The real upgrade runs on admin_init with the settings sanitizer
		// registered, which merges the stored array back in on every write.
		( new Pro_Admin() )->register_settings();
		$method = new \ReflectionMethod( Installer::class, 'upgrade_to_4_3_17' );
		$method->invoke( null );
	}

	public function test_retired_keys_are_unset_kept_keys_untouched_and_a_rerun_is_a_no_op(): void {
		update_option(
			'wbam_pro_settings',
			array(
				'enable_rotation'       => true,
				'rotation_reset_period' => 'daily',
				'rotation_model'        => 'weighted',
				'enabled_modules'       => array(
					'rotation'    => true,
					'classifieds' => true,
				),
				'admin_as_advertiser'   => false,
			)
		);
		update_option(
			'wbam_pro_geolocation_settings',
			array(
				'enable_map_view' => false,
				'default_radius'  => 25,
			)
		);

		$this->run_step();

		$this->assertSame(
			array(
				'rotation_model'      => 'weighted',
				'enabled_modules'     => array( 'classifieds' => true ),
				'admin_as_advertiser' => false,
			),
			get_option( 'wbam_pro_settings' )
		);
		$this->assertSame( array( 'default_radius' => 25 ), get_option( 'wbam_pro_geolocation_settings' ) );

		$writes = 0;
		$count  = static function ( $value ) use ( &$writes ) {
			++$writes;
			return $value;
		};
		add_filter( 'pre_update_option_wbam_pro_settings', $count );
		add_filter( 'pre_update_option_wbam_pro_geolocation_settings', $count );
		$this->run_step();
		remove_filter( 'pre_update_option_wbam_pro_settings', $count );
		remove_filter( 'pre_update_option_wbam_pro_geolocation_settings', $count );

		$this->assertSame( 0, $writes, 'A second run must not write anything.' );
	}

	public function test_rotation_saved_off_is_kept_as_the_filter_default(): void {
		update_option(
			'wbam_pro_settings',
			array(
				'enable_rotation'       => false,
				'rotation_reset_period' => 'daily',
			)
		);

		$this->run_step();

		$this->assertSame( array( 'enable_rotation' => false ), get_option( 'wbam_pro_settings' ) );
		$this->assertFalse( \WBAM_Pro\Core\Settings_Helper::rotation_enabled() );
	}
}
