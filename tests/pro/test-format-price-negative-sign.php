<?php
/**
 * BC#10339750662 item 4: wbam_format_price() put the minus sign after the
 * currency symbol for a negative amount ("$-5.00") instead of before it
 * ("-$5.00") — number_format() already prints its own leading '-', and the
 * old code concatenated the symbol in front of that.
 *
 * Round 5: that hyphen still disagreed with every other signed-amount
 * surface (Report_Shell::money(), the wizard credit banners), which already
 * used the real minus U+2212. wbam_format_price() is the single source of
 * truth for the sign now, so it emits U+2212 too - see functions.php.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Format_Price_Negative_Sign extends Pro_Test_Case {

	public function test_negative_amount_puts_sign_before_symbol(): void {
		$this->assertSame( "\u{2212}\$5.00", wbam_format_price( -5 ) );
	}

	public function test_positive_amount_is_unaffected(): void {
		$this->assertSame( '$5.00', wbam_format_price( 5 ) );
	}

	public function test_zero_has_no_sign(): void {
		$this->assertSame( '$0.00', wbam_format_price( 0 ) );
	}

	public function test_negative_fraction_rounds_and_signs_correctly(): void {
		$this->assertSame( "\u{2212}\$0.50", wbam_format_price( -0.5 ) );
	}

	public function test_sign_is_the_real_minus_not_a_hyphen(): void {
		$this->assertStringNotContainsString( '-', wbam_format_price( -5 ) );
	}
}
