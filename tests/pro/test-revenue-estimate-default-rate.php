<?php
/**
 * One default-rate set (card 10343726490, step 4). Pricing used CPM 2.00 /
 * CPC 0.50 while the revenue-estimate section had its own, contradicting
 * fallback of 2.50 / 0.10. The estimate now falls back to
 * Pricing_Model::default_rate() - the same billing-defaults source pricing
 * reads - so the two numbers can never drift again. An owner who already
 * saved their own estimate rates keeps them untouched.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Revenue_Dashboard;
use WBAM_Pro\Core\Enums\Pricing_Model;
use WBAM_Pro\Core\Settings_Helper;

class Test_Revenue_Estimate_Default_Rate extends Pro_Test_Case {

	private function estimate_rates(): array {
		$method = new \ReflectionMethod( Revenue_Dashboard::class, 'estimate_rates' );
		$method->setAccessible( true );
		return $method->invoke( Revenue_Dashboard::get_instance() );
	}

	public function tear_down(): void {
		delete_option( Revenue_Dashboard::ESTIMATE_OPTION );
		parent::tear_down();
	}

	public function test_estimate_defaults_match_the_pricing_defaults_not_its_own_literals(): void {
		delete_option( Revenue_Dashboard::ESTIMATE_OPTION );

		$rates = $this->estimate_rates();

		$this->assertSame( Pricing_Model::DEFAULT_RATES[ Pricing_Model::CPM ], $rates['default_cpm'], 'No second, contradicting CPM default (2.50).' );
		$this->assertSame( Pricing_Model::DEFAULT_RATES[ Pricing_Model::CPC ], $rates['default_cpc'], 'No second, contradicting CPC default (0.10).' );
	}

	/** The owner's own "Default rate" setting (Settings > Advertising) is the one source both surfaces read. */
	public function test_estimate_follows_the_owners_custom_default_rate(): void {
		delete_option( Revenue_Dashboard::ESTIMATE_OPTION );
		Settings_Helper::update( 'default_pricing_model', 'cpm' );
		Settings_Helper::update( 'default_price_per_unit', 3.25 );

		$rates = $this->estimate_rates();

		$this->assertSame( 3.25, $rates['default_cpm'], "The estimate follows the owner's saved default rate, not a hardcoded literal." );
	}

	/** An owner-saved estimate override is never replaced by the shared default. */
	public function test_an_owners_saved_estimate_override_is_kept(): void {
		update_option( Revenue_Dashboard::ESTIMATE_OPTION, array( 'default_cpm' => 9.99, 'default_cpc' => 1.23 ) );

		$rates = $this->estimate_rates();

		$this->assertSame( 9.99, $rates['default_cpm'] );
		$this->assertSame( 1.23, $rates['default_cpc'] );
	}
}
