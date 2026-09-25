<?php
/**
 * Pro_Plugin::register_frontend_styles() must never declare a dependency on
 * a handle that is not actually registered. In production 'wbam-toast' (the
 * free plugin's handle, registered on init@1) is always there first - but a
 * style registry that gets rebuilt after that hook already ran (Free
 * reactivating mid-request, or a test process resetting $wp_styles, as
 * Test_Admin_Style_Deps deliberately does) must degrade to "no toast
 * toolkit on this load" instead of tripping WP 6.9.1's
 * "dependencies that are not registered" notice the first time anything
 * prints the queued style.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Plugin;

class Test_Portal_Style_Toast_Dependency extends Pro_Test_Case {

	public function tear_down(): void {
		global $wp_styles;
		$wp_styles = null;
		parent::tear_down();
	}

	public function test_portal_style_skips_toast_dependency_when_toast_is_not_registered(): void {
		global $wp_styles;
		$wp_styles = null;
		wp_styles(); // Fresh, empty registry - nothing registered yet.

		Pro_Plugin::register_frontend_styles();

		$portal = wp_styles()->registered['wbam-pro-portal'];
		$this->assertNotContains( 'wbam-toast', $portal->deps, 'A missing wbam-toast must not be declared as a dependency.' );
	}

	public function test_portal_style_declares_toast_dependency_when_toast_is_registered(): void {
		global $wp_styles;
		$wp_styles = null;
		wp_styles();
		wp_register_style( 'wbam-toast', false, array() );

		Pro_Plugin::register_frontend_styles();

		$portal = wp_styles()->registered['wbam-pro-portal'];
		$this->assertContains( 'wbam-toast', $portal->deps, 'wbam-toast must still be declared when it really is registered.' );
	}

	public function test_no_incorrect_usage_notice_when_the_queued_style_is_printed(): void {
		global $wp_styles;
		$wp_styles = null;
		wp_styles(); // Toast not (yet) registered, same as a rebuilt registry.

		Pro_Plugin::register_frontend_styles();
		wp_enqueue_style( 'wbam-pro-portal' );

		// Resolve the dependency graph for just this handle - the same
		// walk wp_print_styles() would do - without the unrelated
		// wp_print_styles hook machinery (emoji styles etc.) a full call
		// pulls in.
		ob_start();
		wp_styles()->do_items( array( 'wbam-pro-portal' ) );
		ob_end_clean();

		$this->assertSame( array(), $this->caught_doing_it_wrong, 'Printing the queued style must not trigger a missing-dependency notice.' );
	}
}
