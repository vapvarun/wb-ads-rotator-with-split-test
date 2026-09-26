<?php
/**
 * Every PRO-plugin-owned registered script/style handle loads its .min
 * file unless SCRIPT_DEBUG is on.
 *
 * Drives the real frontend and admin enqueue hooks (not a hand-picked
 * subset) so a future enqueue call that skips wbam_pro_asset_url() and
 * hardcodes a plain file is caught here rather than in a browser network
 * tab. Card 10340188730.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Asset_Suffix_Registered_Handles extends Pro_Test_Case {

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
		unset( $_GET['page'], $_GET['section'] );
		parent::tear_down();
	}

	/**
	 * Admin hooks gated purely on the $hook string (or a substring of it) -
	 * firing admin_enqueue_scripts with each reaches every hook-only-gated
	 * registration in the plugin.
	 *
	 * @return string[]
	 */
	private static function admin_hooks(): array {
		return array(
			'wbam-ad_page_wbam-ab-testing',
			'wbam-ad_page_wbam-analytics',
			'wbam-ad_page_wbam-classified-reports',
			'wbam-ad_page_wbam-link-analytics',
			'wbam-ad_page_wbam-link-keywords',
			'wbam-ad_page_wbam-link-health',
			'wbam-ad_page_wbam-revenue',
			'wbam-ad_page_wbam-transactions',
			'wbam-ad_page_wbam-folders',
			'wbam-ad_page_wbam-custom-fields',
		);
	}

	public function test_registered_handles_are_minified_by_default(): void {
		global $wp_scripts, $wp_styles;
		$wp_scripts = null;
		$wp_styles  = null;

		// Real requests always have these two registered by the time
		// wp_enqueue_scripts/admin_enqueue_scripts runs (both hook onto
		// init@1 in the FREE plugin, which boots before Pro on every real
		// request); re-seed them since nulling the registry above wiped them.
		\WBAM\Core\Plugin::get_instance()->register_shared_assets();
		wbam_register_lucide();

		// Frontend: the advertiser portal, classifieds and share-of-voice
		// register on shortcode init, not unconditionally on wp_enqueue_scripts;
		// call the shared registrars directly, exactly as every real caller does.
		set_current_screen( 'front' );
		\WBAM_Pro\Core\Pro_Plugin::register_frontend_styles();
		\WBAM_Pro\Core\Pro_Plugin::register_portal_script();
		\WBAM_Pro\Core\Pro_Plugin::register_classified_script();
		do_action( 'wp_enqueue_scripts' );

		// Admin pages gated by $hook (or hook substring) alone.
		foreach ( self::admin_hooks() as $hook ) {
			do_action( 'admin_enqueue_scripts', $hook );
		}

		// Settings > Tools and > Credits, gated on $_GET['section']; the
		// Field tooltips are gated on the specific hooks whose templates
		// call Field_Tooltips::tip_icon() - Advertisers/Packages plus
		// Report_Shell's own pages - not every wbam-* screen (Settings has
		// no tip icon, and used to load this needlessly).
		// Both hook in from admin-only boot (is_admin() is false under
		// PHPUnit): register them the way a real admin load does.
		// Field_Tooltips::register() runs once per process (static flag)
		// while the test framework resets hooks between tests, so call its
		// enqueue directly rather than rely on the hook still being there.
		require_once WBAM_PRO_PATH . 'includes/Admin/class-field-tooltips.php'; // Admin-only load in production.
		$credits = new \WBAM_Pro\Admin\Credits_Settings();
		$_GET['page'] = 'wbam-settings';
		foreach ( array( 'tools', 'credits' ) as $section ) {
			$_GET['section'] = $section;
			do_action( 'admin_enqueue_scripts', 'wbam-ad_page_wbam-settings' );
		}
		\WBAM_Pro\Admin\Field_Tooltips::enqueue_assets( 'wbam-ad_page_wbam-settings' );
		$this->assertArrayNotHasKey( 'wbam-pro-field-tooltips', wp_scripts()->registered, 'Settings has no tip icon; the gate must not fire here.' );
		\WBAM_Pro\Admin\Field_Tooltips::enqueue_assets( 'wbam-ad_page_wbam-advertisers' );
		$this->assertArrayHasKey( 'wbam-pro-field-tooltips', wp_scripts()->registered, 'The Advertisers page renders a tip icon; the gate must fire here.' );
		$this->assertContains( 'wbam-lucide', wp_scripts()->queue, 'Settings > Credits must enqueue the bundled Lucide.' );
		remove_action( 'admin_enqueue_scripts', array( $credits, 'maybe_enqueue_lucide' ) );

		// No third-party CDN: every script comes from a plugin or core.
		foreach ( wp_scripts()->registered as $handle => $dependency ) {
			$this->assertStringNotContainsString( 'unpkg.com', (string) $dependency->src, "Handle '{$handle}' loads from a CDN." );
		}

		// The ad edit screen, gated on get_current_screen()->post_type.
		set_current_screen( 'post.php' );
		get_current_screen()->post_type = 'wbam-ad';
		do_action( 'admin_enqueue_scripts', 'post.php' );

		$this->assert_plugin_handles_minified( wp_styles()->registered, 'style' );
		$this->assert_plugin_handles_minified( wp_scripts()->registered, 'script' );
	}

	/**
	 * @param array<string,\_WP_Dependency> $registered
	 */
	private function assert_plugin_handles_minified( array $registered, string $type ): void {
		$checked = 0;

		foreach ( $registered as $handle => $dependency ) {
			if ( 0 !== strpos( (string) $dependency->src, WBAM_PRO_URL ) ) {
				continue; // Not a PRO-plugin-owned asset (core, jQuery, a third-party handle, etc.).
			}

			if ( 0 === strpos( (string) $dependency->src, WBAM_PRO_URL . 'libs/wbcom-credits-sdk/' ) ) {
				continue; // Bundled third-party SDK; out of scope, has its own release lifecycle.
			}

			++$checked;
			$expected_ext = 'style' === $type ? '.min.css' : '.min.js';
			$this->assertStringEndsWith(
				$expected_ext,
				$dependency->src,
				"Handle '{$handle}' ({$type}) must load its .min file when SCRIPT_DEBUG is off: {$dependency->src}"
			);
		}

		$this->assertGreaterThan( 0, $checked, "No PRO-plugin {$type} handles were registered - the test drove nothing." );
	}
}
