<?php
/**
 * Card 10339876480, step 9: the Keywords screen let an owner add and save
 * keywords with no hint that the master Auto-Linking switch (Settings >
 * Links) was off, so nothing they configured here was actually applied.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Links\Links_Pro_Module;

class Test_Link_Keywords_Auto_Linking_Notice extends Pro_Test_Case {

	public function tear_down(): void {
		delete_option( 'wbam_pro_settings' );
		parent::tear_down();
	}

	public function test_warns_when_auto_linking_is_off(): void {
		update_option( 'wbam_pro_settings', array( 'enable_auto_linking' => 0 ) );

		ob_start();
		Links_Pro_Module::get_instance()->render_keywords_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Auto-Linking is turned off', $html );
	}

	public function test_no_warning_when_auto_linking_is_on(): void {
		update_option( 'wbam_pro_settings', array( 'enable_auto_linking' => 1 ) );

		ob_start();
		Links_Pro_Module::get_instance()->render_keywords_page();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'Auto-Linking is turned off', $html );
	}
}
