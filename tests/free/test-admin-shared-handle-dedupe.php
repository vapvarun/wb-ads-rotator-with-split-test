<?php
/**
 * 'wbam-admin' (script + style) has exactly one registration - Admin's own
 * enqueue_assets(). Links_Admin and List_Empty_States used to re-register
 * the same handle with different (narrower) dependencies; whichever ran
 * first won, so the deps a page actually got depended on hook-add order,
 * not on what the page needed. Proven here by firing the Links-page
 * enqueue callback BEFORE Admin's, the order that exposed the bug.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Admin;
use WBAM\Modules\Links\Links_Admin;
use WP_UnitTestCase;

class Test_Admin_Shared_Handle_Dedupe extends WP_UnitTestCase {

	public function tear_down(): void {
		wp_dequeue_script( 'wbam-admin' );
		wp_deregister_script( 'wbam-admin' );
		wp_dequeue_style( 'wbam-admin' );
		wp_deregister_style( 'wbam-admin' );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	public function test_links_page_gets_admins_full_dependency_set(): void {
		set_current_screen( 'edit.php' );
		get_current_screen()->post_type = 'wbam-ad';

		// Worst-case order: the Links-page-specific callback runs first.
		Links_Admin::get_instance()->enqueue_scripts( 'wbam-ad_page_wbam-links' );
		Admin::get_instance()->enqueue_assets( 'wbam-ad_page_wbam-links' );

		$script = wp_scripts()->registered['wbam-admin'] ?? null;
		$this->assertNotNull( $script, 'wbam-admin script must be registered.' );
		$this->assertContains( 'media-editor', $script->deps, 'The Links page must not lose the media-editor dependency.' );
		$this->assertContains( 'wbam-toast', $script->deps );

		$style = wp_styles()->registered['wbam-admin'] ?? null;
		$this->assertNotNull( $style, 'wbam-admin style must be registered.' );
		$this->assertContains( 'wbam-admin-tokens', $style->deps, 'The Links page must not lose the design-tokens dependency.' );
	}
}
