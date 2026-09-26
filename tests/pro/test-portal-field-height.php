<?php
/**
 * Portal inputs and selects share one 44px height.
 *
 * Inputs were 42px and selects 50px across the portal, the classified and
 * ad wizards and the profile. One rule now sets 44px for single-line
 * inputs and selects, and no other field rule sets its own height floor
 * (card 10344005566).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Portal_Field_Height extends Pro_Test_Case {

	public function test_one_44px_rule_for_inputs_and_selects(): void {
		$css = file_get_contents( WBAM_PRO_PATH . 'assets/css/portal.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );

		preg_match_all( '/([^{}]+)\{[^}]*(?<![-\w])height:\s*44px/', $css, $rules );
		$field_rules = array_values( array_filter( $rules[1], static fn( $sel ) => (bool) preg_match( '/\b(input|select)\b/', $sel ) ) );
		$this->assertCount( 1, $field_rules, 'Exactly one field rule sets the 44px height.' );
		$m = array( 1 => $field_rules[0] );
		foreach ( array( 'text', 'email', 'number', 'url', 'search', 'date' ) as $type ) {
			$this->assertStringContainsString( '[type="' . $type . '"]', $m[1], "The rule covers {$type} inputs." );
		}
		$this->assertStringContainsString( 'select', $m[1] );
		$this->assertDoesNotMatchRegularExpression( '/min-height:\s*42px/', $css, 'No field rule keeps its own 42px floor.' );
	}
}
