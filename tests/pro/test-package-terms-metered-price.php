<?php
/**
 * A CPM/CPC package has no flat price: what the advertiser pays is the
 * budget reserved on approval (rate x limit). wbam_format_package_terms()
 * printed the empty flat price, so the public Advertise page and the
 * package pickers showed "$0.00" for a $5.00 package.
 *
 * Card 10343726490 step 2 (owner decision, one price format everywhere):
 * a metered package with its limit set states the rate AND the total
 * ("$0.50 per click, 10 clicks = $5.00"), not the rate alone (the wizard's
 * old wording) or the total alone (the Advertise page's old wording).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Package_Terms_Metered_Price extends Pro_Test_Case {

	public function test_metered_package_shows_the_rate_and_the_reserved_budget(): void {
		$package = (object) array(
			'pricing_model'     => 'cpc',
			'price'             => 0,
			'price_per_unit'    => 0.5,
			'clicks_limit'      => 10,
			'impressions_limit' => 0,
		);

		$this->assertSame(
			wbam_format_price( 0.5 ) . ' per click, 10 clicks = ' . wbam_format_price( 5 ),
			wbam_format_package_terms( $package )
		);
	}

	public function test_cpm_package_shows_the_rate_and_the_reserved_budget(): void {
		$package = (object) array(
			'pricing_model'     => 'cpm',
			'price'             => 0,
			'price_per_unit'    => 2.0,
			'clicks_limit'      => 0,
			'impressions_limit' => 50000,
		);

		$this->assertSame(
			wbam_format_price( 2.0 ) . ' per 1,000 impressions, 50,000 impressions = ' . wbam_format_price( 100 ),
			wbam_format_package_terms( $package )
		);
	}

	/** A CPC package with no clicks_limit set yet has nothing to multiply the rate by - falls back to its flat price. */
	public function test_cpc_package_with_no_limit_falls_back_to_the_flat_price(): void {
		$package = (object) array(
			'pricing_model'     => 'cpc',
			'price'             => 25,
			'price_per_unit'    => 0.5,
			'clicks_limit'      => 0,
			'impressions_limit' => 0,
		);

		$this->assertSame( wbam_format_price( 25 ), wbam_format_package_terms( $package ) );
	}

	public function test_flat_package_shows_its_price(): void {
		$package = (object) array(
			'pricing_model'     => 'flat',
			'price'             => 49,
			'price_per_unit'    => 0,
			'clicks_limit'      => 0,
			'impressions_limit' => 10000,
		);

		$this->assertSame( wbam_format_price( 49 ), wbam_format_package_terms( $package ) );
	}

	/** A real Package object exposes get_duration_label(), so a flat package states its price AND its run length - the one format the wizard, the Advertise page and admin all show. */
	public function test_flat_package_with_duration_states_price_and_run_length(): void {
		$package = new \WBAM_Pro\Modules\Packages\Package(
			(object) array(
				'pricing_model' => 'flat',
				'price'         => 49,
				'duration_days' => 30,
			)
		);

		$this->assertSame( wbam_format_price( 49 ) . ' / 1 Month', wbam_format_package_terms( $package ) );
	}
}
