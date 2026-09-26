<?php
/**
 * Card 10339876480, step 16: the Default rate field's help text said
 * "leaving this at zero means new campaigns start with nothing to
 * charge", but Pricing_Model::default_rate() never returns 0 for an
 * unset rate on a non-flat model - it falls back to the built-in rate.
 * The copy was flatly wrong. This test pins the actual behavior the
 * corrected copy now describes.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Enums\Pricing_Model;

class Test_Default_Rate_Help_Copy_Matches_Behavior extends Pro_Test_Case {

	public function tear_down(): void {
		delete_option( 'wbam_pro_settings' );
		parent::tear_down();
	}

	public function test_an_empty_default_rate_falls_back_to_the_built_in_rate_not_zero(): void {
		update_option(
			'wbam_pro_settings',
			array(
				'default_pricing_model'  => 'cpm',
				'default_price_per_unit' => 0,
			)
		);

		$this->assertSame(
			Pricing_Model::DEFAULT_RATES['cpm'],
			Pricing_Model::default_rate( 'cpm' ),
			'A campaign never actually gets a $0 rate from an unset default - it uses the built-in rate.'
		);
	}
}
