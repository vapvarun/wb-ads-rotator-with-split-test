<?php
/**
 * The Custom Fields "Add Field" button's icon must survive
 * UX::page_header()'s wp_kses() pass on its `actions` HTML (card
 * 10343765758: "The Add Field icon sits above its label" - actually
 * disappeared entirely, since <i data-lucide> was not on the allowed-tags
 * list and wp_kses() silently dropped it, leaving Lucide nothing to
 * hydrate).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Admin;

class Test_Custom_Fields_Add_Field_Icon extends Pro_Test_Case {

	public function test_add_field_button_keeps_its_icon(): void {
		ob_start();
		( new Pro_Admin() )->render_custom_fields_page();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="wbam-add-field-btn"', $html );
		$this->assertMatchesRegularExpression(
			'/<i[^>]+data-lucide="plus-square"[^>]*><\/i>/',
			$html,
			'wp_kses() must not strip the icon element out of the Add Field button.'
		);
	}
}
