<?php
/**
 * Card 10342783654, step 9: a fresh install's Campaign Billing Defaults
 * showed rate 0.00, which reads as "advertising is free" even though a
 * campaign actually falls back to the $2.00 built-in rate at runtime
 * (Pricing_Model::default_rate()). Seed the option so new installs show
 * the number they actually charge - and never touch an existing site's
 * own saved rate, including a deliberate 0.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Installer;

class Test_Installer_Default_Cpm_Rate extends Pro_Test_Case {

	private function create_options(): void {
		$method = new \ReflectionMethod( Installer::class, 'create_options' );
		$method->setAccessible( true );
		$method->invoke( null );
	}

	public function test_fresh_install_seeds_a_sane_default_rate(): void {
		delete_option( 'wbam_pro_settings' );

		$this->create_options();

		$settings = get_option( 'wbam_pro_settings' );
		$this->assertSame( 'cpm', $settings['default_pricing_model'] );
		$this->assertSame( 2.00, $settings['default_price_per_unit'] );
	}

	public function test_an_existing_site_own_rate_is_never_touched(): void {
		update_option(
			'wbam_pro_settings',
			array(
				'default_pricing_model'  => 'cpc',
				'default_price_per_unit' => 0.0,
			)
		);

		$this->create_options();

		$settings = get_option( 'wbam_pro_settings' );
		$this->assertSame( 'cpc', $settings['default_pricing_model'], 'An existing site keeps its own model choice.' );
		$this->assertSame( 0.0, $settings['default_price_per_unit'], 'An existing site\'s deliberate 0 rate is never overwritten.' );
	}
}
