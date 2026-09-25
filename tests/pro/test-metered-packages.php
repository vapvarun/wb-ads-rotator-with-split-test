<?php
/**
 * Metered packages are prepaid capped bundles; campaigns start from a real rate.
 *
 * QA found a $0-balance advertiser live on the default "Pay Per Click"
 * package: it had a rate but no click cap, so nothing was reserved.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Enums\Pricing_Model;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Packages\Package_Manager;

class Test_Metered_Packages extends Pro_Test_Case {

	public function test_uncapped_per_click_package_cannot_be_saved(): void {
		$result = Package_Manager::get_instance()->create(
			array(
				'name'           => 'Uncapped PPC',
				'pricing_model'  => 'cpc',
				'price_per_unit' => 0.5,
				'status'         => 'active',
			)
		);
		$this->assertWPError( $result );
		$this->assertSame( 'wbam_package_not_prepayable', $result->get_error_code() );
	}

	public function test_capped_per_click_package_is_prepaid(): void {
		$package = Package_Manager::get_instance()->create(
			array(
				'name'           => 'Capped PPC',
				'pricing_model'  => 'cpc',
				'price_per_unit' => 0.5,
				'clicks_limit'   => 100,
				'status'         => 'active',
			)
		);
		$this->assertNotWPError( $package );
		$this->assertSame( '', $package->metered_prepay_problem() );
		$this->assertEquals( 50.0, \WBAM_Pro\Modules\Campaigns\Campaign_Manager::calculate_package_budget( $package ) );
	}

	public function test_default_rate_is_per_model(): void {
		Settings_Helper::update( 'default_price_per_unit', '' );
		$this->assertEquals( Pricing_Model::DEFAULT_RATES['cpm'], Pricing_Model::default_rate( 'cpm' ) );
		$this->assertEquals( Pricing_Model::DEFAULT_RATES['cpc'], Pricing_Model::default_rate( 'cpc' ) );
		$this->assertEquals( 0.0, Pricing_Model::default_rate( 'flat' ) );

		// The owner's rate applies to their default model only.
		Settings_Helper::update( 'default_pricing_model', 'cpm' );
		Settings_Helper::update( 'default_price_per_unit', 3.5 );
		$this->assertEquals( 3.5, Pricing_Model::default_rate( 'cpm' ) );
		$this->assertEquals( Pricing_Model::DEFAULT_RATES['cpc'], Pricing_Model::default_rate( 'cpc' ) );
	}
}
