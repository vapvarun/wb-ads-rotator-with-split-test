<?php
/**
 * Classifieds Browse: clicking Apply Filters with every field blank must
 * not leave a bare trailing "?" in the URL (card 10340188014, QA wave 3
 * correction). Disabling every blank field (already in place) stops each
 * one riding along as a literal empty param, but a GET form with nothing
 * left enabled still submits to "action?" - the browser always appends
 * "?" for an empty query string. This is a pure client-side behaviour
 * (there is no PHP request-time flow to intercept before the browser
 * decides the URL), so the test asserts the guard is present in the
 * rendered inline script rather than exercising a browser.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Template_Loader;

class Test_Classifieds_Browse_Blank_Apply_No_Question_Mark extends Pro_Test_Case {

	public function test_all_blank_submit_navigates_without_a_query_string(): void {
		$html = (string) Template_Loader::get_template(
			'classifieds/sidebar-filters',
			array(
				'categories' => array(),
				'locations'  => array(),
			)
		);

		$this->assertStringContainsString(
			'window.location.assign(form.action)',
			$html,
			'A fully-blank Apply submit must be intercepted and sent to the bare action URL, not a GET submit that appends "?".'
		);
		$this->assertStringContainsString( 'if (!hasValue)', $html );
	}
}
