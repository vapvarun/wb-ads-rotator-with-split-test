<?php
/**
 * Regression guard: every admin stylesheet's dependency array must resolve
 * to a handle that is actually registered by the time the page prints.
 *
 * 5ce37c3 made `wbam-links-pro-admin` depend on `wbam-pro-backend`, but
 * `wbam-pro-backend` only registers inside Pro_Admin::enqueue_assets(),
 * which bails out before reaching that point on the Links submenu (its
 * hooks are not in that method's page list). An unregistered dependency
 * makes WP_Dependencies drop the entire depending stylesheet from the
 * print queue, not just fall back to unstyled — the page renders with no
 * CSS at all. See card 10339874920.
 *
 * Calls each module's enqueue method directly (rather than firing
 * `admin_enqueue_scripts` and hoping the right class got bootstrapped):
 * `Admin`/`Pro_Admin` only self-register when `is_admin()` is true, which
 * the CLI test runner never is, so nothing would fire.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Admin\Admin as Free_Admin;
use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Modules\ABTesting\AB_Test_Admin;
use WBAM_Pro\Modules\Links\Links_Pro_Module;

/**
 * @group pro
 * @group admin-style-deps
 */
class Test_Admin_Style_Deps extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		global $wp_styles;
		$wp_styles = null;
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * @dataProvider provider_admin_hooks
	 */
	public function test_every_style_enqueued_on_the_hook_actually_prints( string $hook, string $module ): void {
		set_current_screen( $hook );

		// A real page load starts with an empty WP_Styles registry; a handle
		// this hook's callbacks never register is genuinely never registered.
		// Sharing one PHPUnit process, `registered` otherwise carries a
		// handle over from an earlier row that DID register it, masking the
		// exact bug this test exists to catch.
		global $wp_styles;
		$wp_styles = null;
		$styles    = wp_styles();

		// `wbam-admin-tokens` / `wbam-admin-family`: the handle every admin
		// stylesheet in both plugins depends on. A real request registers it
		// at admin_enqueue_scripts priority 5, before any module's own call.
		Free_Admin::get_instance()->enqueue_admin_tokens( $hook );

		switch ( $module ) {
			case 'links':
				Links_Pro_Module::get_instance()->enqueue_admin_scripts( $hook );
				break;
			case 'pro_admin':
				( new Pro_Admin() )->enqueue_assets( $hook );
				break;
			case 'ab_testing':
				AB_Test_Admin::get_instance()->enqueue_assets( $hook );
				break;
		}

		$new_handles = $styles->queue;
		$this->assertNotEmpty( $new_handles, "Hook '$hook' enqueued no styles — dataset entry may be stale." );

		foreach ( $new_handles as $handle ) {
			$this->assertContains(
				$handle,
				$styles->all_deps( array( $handle ) ) ? $styles->to_do : array(),
				"Style '$handle' enqueued on '$hook' was dropped by WP_Dependencies because one of its deps is never registered there — it will not print."
			);
		}
	}

	public function provider_admin_hooks(): array {
		return array(
			'Links: Link Analytics' => array( 'links_page_wbam-link-analytics', 'links' ),
			'Links: Keywords'       => array( 'links_page_wbam-link-keywords', 'links' ),
			'Links: Health'         => array( 'links_page_wbam-link-health', 'links' ),
			'Ad Folders'            => array( 'wbam-ad_page_wbam-folders', 'pro_admin' ),
			'Ad Analytics'          => array( 'wbam-ad_page_wbam-analytics', 'pro_admin' ),
			'A/B Testing'           => array( 'wbam-ad_page_wbam-ab-testing', 'ab_testing' ),
		);
	}
}
