<?php
/**
 * Every classified upgrade-price reader must agree with the seeded
 * defaults (Installer::get_default_settings()) on a fresh install with no
 * saved override. Pricing_Calculator had drifted to urgent=4, bump=2,
 * top=10 while every other reader used the seeded 2/1/4 (card 10343765758:
 * "the calculator's fallback prices differ from the seeded defaults").
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pricing_Calculator;
use WBAM_Pro\Core\Settings_Helper;

class Test_Classified_Upgrade_Price_One_Source extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'wbam_pro_classifieds_settings' );
	}

	public function test_settings_helper_matches_the_seeded_defaults(): void {
		$this->assertSame( 5.0, Settings_Helper::classified_upgrade_price( 'featured' ) );
		$this->assertSame( 3.0, Settings_Helper::classified_upgrade_price( 'highlighted' ) );
		$this->assertSame( 2.0, Settings_Helper::classified_upgrade_price( 'urgent' ) );
		$this->assertSame( 4.0, Settings_Helper::classified_upgrade_price( 'top' ) );
		$this->assertSame( 1.0, Settings_Helper::classified_upgrade_price( 'bump' ) );
	}

	public function test_pricing_calculator_agrees_with_the_shared_defaults(): void {
		$calculator = new Pricing_Calculator();
		$prices     = $this->get_private_upgrade_prices( $calculator );

		$this->assertSame( Settings_Helper::classified_upgrade_price( 'urgent' ), $prices['urgent'] );
		$this->assertSame( Settings_Helper::classified_upgrade_price( 'bump' ), $prices['bump'] );
		$this->assertSame( Settings_Helper::classified_upgrade_price( 'top' ), $prices['top'] );
	}

	private function get_private_upgrade_prices( Pricing_Calculator $calculator ): array {
		$prop = new \ReflectionProperty( Pricing_Calculator::class, 'upgrade_prices' );
		$prop->setAccessible( true );
		return $prop->getValue( $calculator );
	}
}
