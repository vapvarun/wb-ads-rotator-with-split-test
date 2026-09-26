<?php
/**
 * The form-field border token is a solid colour with 3:1 on every surface.
 *
 * It was color-mix( --wbam-text 60%, transparent ): 3.17:1 on white and
 * under 3:1 on the grey surfaces a field sits on (WCAG 1.4.11). It is now a
 * solid colour in light and in dark mode (card 10344005566).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

class Test_Input_Border_Token_Contrast extends \WP_UnitTestCase {

	/**
	 * WCAG relative luminance of a #rrggbb colour.
	 *
	 * @param string $hex Colour.
	 * @return float
	 */
	private function luminance( $hex ) {
		$sum = 0.0;
		foreach ( array( 0.2126, 0.7152, 0.0722 ) as $i => $weight ) {
			$c    = hexdec( substr( ltrim( $hex, '#' ), $i * 2, 2 ) ) / 255;
			$c    = $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
			$sum += $weight * $c;
		}
		return $sum;
	}

	private function ratio( $a, $b ) {
		$la = $this->luminance( $a );
		$lb = $this->luminance( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/**
	 * The last literal colour in a token's declaration (its plugin fallback).
	 *
	 * @param string $block CSS block.
	 * @param string $token Custom property name.
	 * @return string
	 */
	private function literal( $block, $token ) {
		$this->assertMatchesRegularExpression( '/' . preg_quote( $token, '/' ) . ':[^;]*#[0-9a-f]{6}/i', $block, "{$token} ends in a solid hex colour." );
		preg_match( '/' . preg_quote( $token, '/' ) . ':([^;]*);/', $block, $m );
		preg_match_all( '/#[0-9a-f]{6}/i', $m[1], $hex );
		return end( $hex[0] );
	}

	public function test_input_border_is_solid_and_reaches_3_to_1_on_every_surface(): void {
		$css   = file_get_contents( WBAM_PATH . 'assets/css/frontend-tokens.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$css   = preg_replace( '#/\*.*?\*/#s', '', $css );
		$root  = substr( $css, strpos( $css, ':root {' ), strpos( $css, '}', strpos( $css, ':root {' ) ) - strpos( $css, ':root {' ) );
		$start = strpos( $css, 'html[data-bx-mode="dark"],' );
		$dark  = substr( $css, $start, strpos( $css, '}', $start ) - $start );

		foreach ( array( 'light' => $root, 'dark' => $dark ) as $mode => $block ) {
			preg_match( '/--wbam-input-border:\s*([^;]+);/', $block, $m );
			$this->assertNotEmpty( $m, "{$mode}: --wbam-input-border is declared." );
			$this->assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/i', trim( $m[1] ), "{$mode}: the field border is one solid colour." );

			foreach ( array( '--wbam-bg', '--wbam-surface', '--wbam-card-bg', '--wbam-surface-alt' ) as $surface ) {
				$ratio = $this->ratio( trim( $m[1] ), $this->literal( $block, $surface ) );
				$this->assertGreaterThanOrEqual( 3.0, $ratio, "{$mode}: field border on {$surface} is " . round( $ratio, 2 ) . ':1.' );
			}
		}
	}
}
