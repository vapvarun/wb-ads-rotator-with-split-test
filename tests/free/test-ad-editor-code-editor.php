<?php
/**
 * The HTML code editor (CodeMirror) must initialise on the ad edit screen
 * so code ads can be authored. admin_enqueue_scripts passes the real hook
 * suffix ('post.php' / 'post-new.php'), not the screen base ('post' /
 * 'post-new') that the old comparison checked - so the editor never loaded.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Admin;
use WP_UnitTestCase;

class Test_Ad_Editor_Code_Editor extends WP_UnitTestCase {

	/** @var \WP_Scripts|null */
	private $saved_scripts;

	/** @var \WP_Styles|null */
	private $saved_styles;

	public function set_up(): void {
		parent::set_up();
		// enqueue_assets() enqueues the whole admin set; restore both
		// registries whole so nothing leaks into a later test that prints.
		$this->saved_scripts = isset( $GLOBALS['wp_scripts'] ) ? clone $GLOBALS['wp_scripts'] : null;
		$this->saved_styles  = isset( $GLOBALS['wp_styles'] ) ? clone $GLOBALS['wp_styles'] : null;
	}

	public function tear_down(): void {
		$GLOBALS['wp_scripts'] = $this->saved_scripts;
		$GLOBALS['wp_styles']  = $this->saved_styles;
		set_current_screen( 'front' );
		parent::tear_down();
	}

	public function test_code_editor_settings_localize_on_post_php(): void {
		set_current_screen( 'post.php' );
		get_current_screen()->post_type = 'wbam-ad';

		Admin::get_instance()->enqueue_assets( 'post.php' );

		$data = wp_scripts()->get_data( 'wbam-admin', 'data' );
		$this->assertIsString( $data );
		$this->assertStringContainsString( 'wbamCodeEditor', $data, 'The code editor settings must localize on the ad edit screen.' );
	}

	public function test_code_editor_settings_localize_on_post_new_php(): void {
		set_current_screen( 'post-new.php' );
		get_current_screen()->post_type = 'wbam-ad';

		Admin::get_instance()->enqueue_assets( 'post-new.php' );

		$data = wp_scripts()->get_data( 'wbam-admin', 'data' );
		$this->assertIsString( $data );
		$this->assertStringContainsString( 'wbamCodeEditor', $data, 'The code editor settings must localize on the new-ad screen.' );
	}
}
