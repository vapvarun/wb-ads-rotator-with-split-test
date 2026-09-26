<?php
/**
 * A CPM/CPC package has no flat price: what the advertiser pays is the
 * budget reserved on approval (rate x limit). wbam_format_package_terms()
 * printed the empty flat price, so the public Advertise page and the
 * package pickers showed "$0.00" for a $5.00 package.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Package_Terms_Metered_Price extends Pro_Test_Case {

	public function test_metered_package_shows_the_reserved_budget(): void {
		$package = (object) array(
			'pricing_model'     => 'cpc',
			'price'             => 0,
			'price_per_unit'    => 0.5,
			'clicks_limit'      => 10,
			'impressions_limit' => 0,
		);

		$this->assertSame( wbam_format_price( 5 ), wbam_format_package_terms( $package ) );
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
}
