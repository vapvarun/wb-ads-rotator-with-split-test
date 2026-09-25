<?php
/**
 * Custom Fields builder speaks the owner's language: field types have names
 * (Dropdown, not "select"), every input has a heading, and Save starts
 * disabled until something changes.
 *
 * The choices box for Dropdown / Checkboxes / Radio buttons already exists
 * (custom-fields-admin.js toggles .wbam-cf-options-wrap when the type
 * changes); a new row starts as Text, which has no choices.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Admin;

class Test_Custom_Fields_Builder_Copy extends Pro_Test_Case {

	public function tear_down(): void {
		global $wp_scripts;
		$wp_scripts = null;
		set_current_screen( 'front' );
		parent::tear_down();
	}

	public function test_builder_localizes_type_names_and_headings(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$hook = 'wb-ad-manager_page_wbam-custom-fields';
		set_current_screen( $hook );

		( new Pro_Admin() )->enqueue_assets( $hook );
		$data = (string) wp_scripts()->get_data( 'wbam-custom-fields-admin', 'data' );

		$this->assertStringContainsString( '"select":"Dropdown"', $data );
		$this->assertStringContainsString( '"keyHeading":"Key"', $data );
	}

	public function test_save_starts_disabled_and_empty_state_has_no_inline_style(): void {
		ob_start();
		( new Pro_Admin() )->render_custom_fields_page();
		$html = (string) ob_get_clean();

		$this->assertMatchesRegularExpression( '/id="wbam-save-fields-btn"[^>]*disabled/', $html );
		$this->assertMatchesRegularExpression( '/<div class="wbam-cf-empty notice notice-info inline">\s*<p>/', $html );
	}
}
