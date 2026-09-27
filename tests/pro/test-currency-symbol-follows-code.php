<?php
/**
 * The currency symbol follows the currency code; the free-text symbol
 * setting is gone (owner decision 2026-10-03, card 10344382158).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;

class Test_Currency_Symbol_Follows_Code extends Pro_Test_Case {

	public function set_up(): void {
		$this->snapshot_options( array( 'wbam_pro_settings' ) );
		parent::set_up();
	}

	public function test_the_symbol_comes_from_the_code_everywhere(): void {
		// A stale symbol from before 3.2.0 no longer wins.
		Settings_Helper::update( 'currency_symbol', '$' );
		Settings_Helper::update( 'currency', 'eur' );

		$this->assertSame( '€', wbam_get_currency_symbol() );
		$this->assertSame( '€12.50', wbam_format_price( 12.5 ) );
		$this->assertSame( '€', Settings_Helper::get_currency()['symbol'] );
		// Free's partnership budget reads the same site currency.
		$this->assertSame( '€', \WBAM\Core\Formatter::get_currency_symbol() );
	}

	public function test_a_site_can_still_change_the_symbol_by_filter(): void {
		Settings_Helper::update( 'currency', 'inr' );
		$swap = static function ( $symbol, $code ) {
			return 'INR' === $code ? 'Rs ' : $symbol;
		};
		add_filter( 'wbam_currency_symbol', $swap, 10, 2 );

		$this->assertSame( 'Rs 10.00', wbam_format_price( 10 ) );

		remove_filter( 'wbam_currency_symbol', $swap, 10 );
	}

	public function test_every_offered_currency_has_a_symbol(): void {
		$admin   = new \WBAM_Pro\Core\Pro_Admin();
		$options = ( new \ReflectionMethod( $admin, 'get_currency_options' ) )->invoke( $admin );

		$table = ( new \ReflectionProperty( \WBAM\Core\Formatter::class, 'currency_symbols' ) )->getValue();

		foreach ( array_keys( $options ) as $code ) {
			$this->assertArrayHasKey( $code, $table, "{$code} is offered but has no symbol." );
		}
	}

	public function test_the_upgrade_retires_the_symbol_setting(): void {
		update_option(
			'wbam_pro_settings',
			array(
				'currency'        => 'gbp',
				'currency_symbol' => 'Rs',
			)
		);

		$upgrade = new \ReflectionMethod( \WBAM_Pro\Core\Installer::class, 'upgrade_to_4_3_20' );
		$upgrade->invoke( null );

		$stored = get_option( 'wbam_pro_settings' );
		$this->assertArrayNotHasKey( 'currency_symbol', $stored );
		$this->assertSame( 'gbp', $stored['currency'] );
	}
}
