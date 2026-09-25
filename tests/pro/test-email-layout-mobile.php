<?php
/**
 * The shared email layout fits a 390px mail client and its text is legible.
 *
 * header.php's .email-wrapper had width 100% + max-width 600px + 20px
 * padding with no box-sizing and no media query, so every templated email
 * was 640px wide at 600 and 430-473px wide on a 390px phone. The footer
 * text (#6c757d on #f8f9fa, 4.45:1) and the green Renew button (#fff on
 * #28a745, 3.13:1) were under the 4.5:1 WCAG AA minimum, as were the green
 * and amber status labels.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Notifications\Email_Notifications;

class Test_Email_Layout_Mobile extends Pro_Test_Case {

	private static function luminance( string $hex ): float {
		$hex = ltrim( $hex, '#' );
		$sum = 0.0;
		foreach ( array( 0.2126, 0.7152, 0.0722 ) as $i => $weight ) {
			$c    = hexdec( substr( $hex, $i * 2, 2 ) ) / 255;
			$c    = $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
			$sum += $weight * $c;
		}
		return $sum;
	}

	private static function contrast( string $a, string $b ): float {
		$l = array( self::luminance( $a ), self::luminance( $b ) );
		return ( max( $l ) + 0.05 ) / ( min( $l ) + 0.05 );
	}

	public function test_layout_is_border_box_and_collapses_on_small_screens(): void {
		$html = Email_Notifications::get_template( 'subscription-renewed', array( 'user_name' => 'Ann', 'plan_name' => 'Gold', 'renewal_date' => '' ) );

		$this->assertMatchesRegularExpression( '/class="email-wrapper"[^>]*style="[^"]*box-sizing:\s*border-box/', $html, 'Padding must sit inside the 600px, not add to it.' );
		$this->assertMatchesRegularExpression( '/@media[^{]*max-width:\s*480px/', $html, 'Small screens get tighter padding.' );
	}

	public function test_footer_and_status_colours_meet_aa_contrast(): void {
		$html = Email_Notifications::get_template( 'subscription-renewed', array( 'user_name' => 'Ann', 'plan_name' => 'Gold', 'renewal_date' => '' ) );
		$this->assertSame( 1, preg_match( '/<td style="[^"]*background-color:\s*(#f8f9fa);[^"]*?(?<![-\w])color:\s*(#[0-9a-f]{6})/i', $html, $m ), 'Footer cell styles found.' );
		$this->assertGreaterThanOrEqual( 4.5, self::contrast( $m[1], $m[2] ), 'Footer text contrast.' );

		// Status labels and buttons: no text colour below 4.5:1 on the light boxes they sit in.
		$dir = WBAM_PRO_PATH . 'templates/emails/';
		foreach ( glob( $dir . '*.php' ) as $file ) {
			$source = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			preg_match_all( '/(?<![-\w])color:\s*(#[0-9a-f]{6})/i', $source, $colors );
			foreach ( array_unique( $colors[1] ) as $color ) {
				if ( in_array( strtolower( $color ), array( '#ffffff', '#fff' ), true ) ) {
					continue;
				}
				$this->assertGreaterThanOrEqual( 4.5, self::contrast( $color, '#f8f9fa' ), basename( $file ) . ' text ' . $color );
			}
			if ( preg_match_all( '/class="btn"[^>]*background-color:\s*(#[0-9a-f]{6});\s*color:\s*(#[0-9a-f]{6})/i', $source, $buttons, PREG_SET_ORDER ) ) {
				foreach ( $buttons as $button ) {
					$this->assertGreaterThanOrEqual( 4.5, self::contrast( $button[1], $button[2] ), basename( $file ) . ' button ' . $button[1] );
				}
			}
		}
	}
}
