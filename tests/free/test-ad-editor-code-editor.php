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

	public function tear_down(): void {
		wp_dequeue_script( 'wbam-admin' );
		wp_deregister_script( 'wbam-admin' );
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
