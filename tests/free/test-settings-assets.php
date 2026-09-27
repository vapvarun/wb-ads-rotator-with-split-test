<?php
/**
 * The Settings page loads admin.css wherever its menu lives (card
 * 10343706274, wave 6): with Pro it is a top-level page with no post type,
 * and without admin.css the Placements matrix scrolled the page at 390.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Admin;
use WP_UnitTestCase;

class Test_Settings_Assets extends WP_UnitTestCase {

	public function tear_down(): void {
		wp_dequeue_style( 'wbam-admin' );
		wp_deregister_style( 'wbam-admin' );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	public function test_admin_css_loads_on_settings_under_any_menu(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		foreach ( array( 'toplevel_page_wbam-settings', 'wbam-ad_page_wbam-settings' ) as $hook ) {
			wp_dequeue_style( 'wbam-admin' );
			set_current_screen( $hook );
			( new Admin() )->enqueue_assets( $hook );
			$this->assertTrue( wp_style_is( 'wbam-admin', 'enqueued' ), $hook );
		}
	}

	public function test_other_pages_without_the_post_type_are_left_alone(): void {
		set_current_screen( 'toplevel_page_wbam-revenue' );
		( new Admin() )->enqueue_assets( 'toplevel_page_wbam-revenue' );
		$this->assertFalse( wp_style_is( 'wbam-admin', 'enqueued' ) );
	}
}
