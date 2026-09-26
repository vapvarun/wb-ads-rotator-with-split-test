<?php
/**
 * Every FREE-plugin-owned registered script/style handle loads its .min
 * file unless SCRIPT_DEBUG is on.
 *
 * Drives the real frontend and admin enqueue hooks (not a hand-picked
 * subset) so a future enqueue call that skips wbam_asset_url() and
 * hardcodes a plain file is caught here rather than in a browser network
 * tab. Card 10340188730.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;

class Test_Asset_Suffix_Registered_Handles extends WP_UnitTestCase {

	/**
	 * The registries this test replaces with a clean slate; other tests in
	 * the same process need their handles back.
	 *
	 * @var \WP_Scripts|null
	 */
	private $saved_scripts;

	/**
	 * @var \WP_Styles|null
	 */
	private $saved_styles;

	public function set_up(): void {
		parent::set_up();
		$this->saved_scripts = $GLOBALS['wp_scripts'] ?? null;
		$this->saved_styles  = $GLOBALS['wp_styles'] ?? null;
	}

	public function tear_down(): void {
		$GLOBALS['wp_scripts'] = $this->saved_scripts;
		$GLOBALS['wp_styles']  = $this->saved_styles;
		// An admin screen makes is_admin() true for every later test.
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * Admin hooks gated purely on the $hook string - firing
	 * admin_enqueue_scripts with each reaches every hook-only-gated
	 * registration in the plugin.
	 *
	 * @return string[]
	 */
	private static function admin_hooks(): array {
		return array(
			'wbam-ad_page_wbam-help',
			'wbam-ad_page_wbam-link-categories',
			'wbam-ad_page_wbam-links',
			'wbam-ad_page_wbam-upgrade',
			'links_page_wbam-partnerships',
		);
	}

	public function test_registered_handles_are_minified_by_default(): void {
		global $wp_scripts, $wp_styles;
		$wp_scripts = null;
		$wp_styles  = null;

		// Real requests always have these two registered by the time
		// wp_enqueue_scripts/admin_enqueue_scripts runs (both hook onto
		// init@1); re-seed them since nulling the registry above wiped them.
		\WBAM\Core\Plugin::get_instance()->register_shared_assets();
		wbam_register_lucide();

		// Frontend: ad rotation, link click tracking, partnership form.
		set_current_screen( 'front' );
		do_action( 'wp_enqueue_scripts' );

		// Admin pages gated by $hook alone.
		foreach ( self::admin_hooks() as $hook ) {
			do_action( 'admin_enqueue_scripts', $hook );
		}

		// The ad edit screen, gated on get_current_screen()->post_type.
		set_current_screen( 'post.php' );
		get_current_screen()->post_type = 'wbam-ad';
		do_action( 'admin_enqueue_scripts', 'post.php' );

		// The Settings screen's sub-nav script, gated on $_GET['page'].
		$_GET['page'] = 'wbam-settings';
		do_action( 'admin_enqueue_scripts', 'wbam-ad_page_wbam-settings' );
		unset( $_GET['page'] );

		$this->assert_plugin_handles_minified( wp_styles()->registered, 'style' );
		$this->assert_plugin_handles_minified( wp_scripts()->registered, 'script' );
	}

	/**
	 * @param array<string,\_WP_Dependency> $registered
	 */
	private function assert_plugin_handles_minified( array $registered, string $type ): void {
		$checked = 0;

		foreach ( $registered as $handle => $dependency ) {
			if ( 0 !== strpos( (string) $dependency->src, WBAM_URL ) ) {
				continue; // Not a FREE-plugin-owned asset (core, jQuery, a third-party handle, etc.).
			}

			++$checked;
			$expected_ext = 'style' === $type ? '.min.css' : '.min.js';
			$this->assertStringEndsWith(
				$expected_ext,
				$dependency->src,
				"Handle '{$handle}' ({$type}) must load its .min file when SCRIPT_DEBUG is off: {$dependency->src}"
			);
		}

		$this->assertGreaterThan( 0, $checked, "No FREE-plugin {$type} handles were registered - the test drove nothing." );
	}
}
