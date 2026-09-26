<?php
/**
 * The license script belongs to Settings > Tools & License only. It was gated
 * on any hook containing "wbam", so every WB Ad Manager screen loaded it.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_License_Script_Scope extends Pro_Test_Case {

	/** @var \WP_Scripts|null */
	private $saved_scripts;

	public function set_up(): void {
		parent::set_up();
		$this->saved_scripts = isset( $GLOBALS['wp_scripts'] ) ? clone $GLOBALS['wp_scripts'] : null;
	}

	public function tear_down(): void {
		$GLOBALS['wp_scripts'] = $this->saved_scripts;
		unset( $_GET['section'] );
		parent::tear_down();
	}

	public function test_not_loaded_on_other_plugin_screens(): void {
		\WBAM_Pro_License_Manager::get_instance()->enqueue_scripts( 'wbam-ad_page_wbam-campaigns' );
		$this->assertFalse( wp_script_is( 'wbam-pro-license', 'enqueued' ) );

		$_GET['section'] = 'general';
		\WBAM_Pro_License_Manager::get_instance()->enqueue_scripts( 'wbam-ad_page_wbam-settings' );
		$this->assertFalse( wp_script_is( 'wbam-pro-license', 'enqueued' ) );
	}

	public function test_loaded_on_tools_and_license(): void {
		$_GET['section'] = 'tools';
		\WBAM_Pro_License_Manager::get_instance()->enqueue_scripts( 'wbam-ad_page_wbam-settings' );
		$this->assertTrue( wp_script_is( 'wbam-pro-license', 'enqueued' ) );
	}
}
