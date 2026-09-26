<?php
/**
 * Plugin buttons size with border-box.
 *
 * At 390px the single listing's buttons are width: 100%. Themes without a
 * global border-box reset (Twenty Twenty-Five) add the padding and border
 * on top, so "View Seller Profile" overflowed the seller card by about
 * 30px. Card 10342783037.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Button_Box_Sizing extends Pro_Test_Case {

	public function test_base_button_rules_use_border_box(): void {
		foreach ( array( 'classified.css', 'portal.css' ) as $file ) {
			$css = file_get_contents( WBAM_PRO_PATH . 'assets/css/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$this->assertMatchesRegularExpression( '/\n\.wbam-btn \{[^}]*box-sizing:\s*border-box/', $css, "{$file}: .wbam-btn must be border-box." );
		}
	}
}
