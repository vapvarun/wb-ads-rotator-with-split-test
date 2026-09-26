<?php
/**
 * Portal fields share one type size and selects never clip.
 *
 * Three field rules in portal.css still set 14px (.wbam-input, the form-row
 * select rule, the profile field rule), so Profile fields were 14px beside
 * 17px fields elsewhere. A theme's fixed select height (42px) also clipped
 * the classified wizard's 'Select a category' at the 17px size. Card
 * 10340230879.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Portal_Field_Type_Scale extends Pro_Test_Case {

	/**
	 * Body of the first rule whose selector list starts with $selector.
	 *
	 * @param string $css      Stylesheet.
	 * @param string $selector Selector line the rule starts with.
	 * @return string
	 */
	private function rule( $css, $selector ) {
		$start = strpos( $css, "\n" . $selector );
		$this->assertNotFalse( $start, "Rule {$selector} exists." );
		$open = strpos( $css, '{', $start );
		return substr( $css, $open, strpos( $css, '}', $open ) - $open );
	}

	public function test_field_rules_do_not_override_the_base_size_and_selects_grow(): void {
		$css = file_get_contents( WBAM_PRO_PATH . 'assets/css/portal.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		foreach ( array( '.wbam-input {', '.wbam-form-row select,', '.wbam-profile .wbam-form-row input[type="text"],' ) as $selector ) {
			$this->assertStringNotContainsString( 'font-size', $this->rule( $css, $selector ), "{$selector} must inherit the base field size." );
		}

		$select = $this->rule( $css, '.wbam-form-field.wbam-form-field select:where(:not([multiple])),' . "\n" . '.wbam-form-row.wbam-form-row select' );
		$this->assertStringContainsString( 'height: auto', $select );
		$this->assertStringContainsString( 'min-height: 44px', $select );
	}
}
