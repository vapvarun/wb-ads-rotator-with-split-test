<?php
/**
 * Card 10343712795, page shell: every WB Ad Manager admin page - Free and
 * Pro, custom callback or plain WordPress list-table/term screen - renders
 * the one shared UX::page_header(), so title/description/primary-action
 * placement never drifts back to a one-off per screen.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Admin\Admin;
use WBAM\Admin\Email_Captures;
use WBAM\Admin\Help_Docs;
use WBAM\Admin\Settings;
use WBAM\Modules\Links\Links_Admin;
use WBAM\Modules\Links\Partnership_Admin;
use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Admin\Revenue_Dashboard;
use WBAM_Pro\Modules\ABTesting\AB_Test_Admin;
use WBAM_Pro\Modules\Links\Links_Pro_Module;
use WBAM_Pro\Modules\Classifieds\Report_Admin;

class Test_Admin_Page_Shell extends Pro_Test_Case {

	private const AD_PARENT = 'edit.php?post_type=wbam-ad';

	public function set_up(): void {
		parent::set_up();

		// Every module on, so every optional submenu registers.
		update_option(
			'wbam_pro_settings',
			array(
				'enabled_modules' => array(
					'classifieds'    => true,
					'ad_submissions' => true,
					'campaigns'      => true,
					'packages'       => true,
					'wallet'         => true,
					'reviews'        => true,
					'custom_fields'  => true,
					'memberships'    => true,
					'rotation'       => true,
					'ab_testing'     => true,
					'links'          => true,
				),
			)
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// Pro_Plugin only builds the 'analytics_dashboard' module inside an
		// is_admin() gate (bootstrap assumes a real wp-admin request), which
		// is false in a CLI test run - Pro_Admin::render_analytics_page()
		// delegates entirely to that module and renders nothing without it.
		if ( ! \WBAM_Pro\Core\Pro_Plugin::get_instance()->get_module( 'analytics_dashboard' ) ) {
			$plugin  = \WBAM_Pro\Core\Pro_Plugin::get_instance();
			$prop    = new \ReflectionProperty( \WBAM_Pro\Core\Pro_Plugin::class, 'modules' );
			$prop->setAccessible( true );
			$modules = $prop->getValue( $plugin );
			$modules['analytics_dashboard'] = new \WBAM_Pro\Modules\Analytics\Analytics_Dashboard();
			$prop->setValue( $plugin, $modules );
		}
	}

	public function tear_down(): void {
		delete_option( 'wbam_pro_settings' );
		global $menu, $submenu, $typenow, $taxnow;
		$menu                   = array();
		$submenu                = array();
		$typenow                = '';
		$taxnow                 = '';
		$_GET                   = array();
		$_REQUEST               = array();
		$GLOBALS['hook_suffix'] = null;
		$GLOBALS['pagenow']     = 'index.php';
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * Every custom-callback admin page (both plugins) under the WB Ad
	 * Manager menu renders the shared header.
	 *
	 * Both plugins gate their menu-registering singletons behind
	 * `is_admin()` at bootstrap (see class-pro-plugin.php), which is false
	 * in a CLI test run, so `do_action( 'admin_menu' )` alone registers
	 * nothing here - the same reason test-admin-menu-consolidation.php
	 * instantiates each class and calls its add-menu method directly
	 * instead. This does the same, for every class that registers a page
	 * under the WB Ad Manager menu.
	 */
	public function test_every_custom_admin_page_renders_shared_header(): void {
		global $menu, $submenu;
		$menu    = array();
		$submenu = array();

		( new Email_Captures() )->add_menu();
		Help_Docs::get_instance()->add_menu();
		Settings::get_instance()->add_menu();
		Partnership_Admin::get_instance()->add_admin_menu();
		Links_Admin::get_instance()->add_submenu();
		( new Pro_Admin() )->add_menu_items();
		( new Pro_Admin() )->add_settings_menu_items();
		Revenue_Dashboard::get_instance()->add_menu();
		AB_Test_Admin::get_instance()->add_menu();
		Links_Pro_Module::get_instance()->add_admin_menus();
		Report_Admin::get_instance()->add_menu();

		$this->assertArrayHasKey( self::AD_PARENT, $submenu, 'The WB Ad Manager submenu must exist.' );

		$exercised = array();
		foreach ( $submenu[ self::AD_PARENT ] as $item ) {
			$slug = $item[2];

			// Plain WordPress list-table/term screens (All Ads, Ad Tags,
			// Categories, Locations) point straight at a core file and have
			// no page-callback hookname - covered separately below, since
			// they get the header through in_admin_header, not a callback.
			if ( false !== strpos( $slug, '.php' ) ) {
				continue;
			}

			$hookname = get_plugin_page_hookname( $slug, self::AD_PARENT );
			if ( ! $hookname || ! has_action( $hookname ) ) {
				continue;
			}

			$_GET = array();
			ob_start();
			do_action( $hookname );
			$output = ob_get_clean();

			$this->assertStringContainsString( 'wbam-page-header', $output, "'{$slug}' must render the shared page header." );
			$exercised[] = $slug;
		}

		// A silently-empty loop (e.g. a menu registration regression) would
		// make every assertStringContainsString() above simply never run.
		$this->assertGreaterThanOrEqual( 15, count( $exercised ), 'Expected to actually exercise most WB Ad Manager admin pages, not skip them all: ' . implode( ', ', $exercised ) );
	}

	/**
	 * All Ads / Add New Ad / Ad Tags (Free) get the shared header through
	 * Admin::render_core_screen_header(), hooked to in_admin_header instead
	 * of owning a page callback - WordPress prints its own list table /
	 * add-term form underneath, untouched.
	 *
	 * @dataProvider free_core_screens
	 */
	public function test_free_core_screen_renders_shared_header( string $hook, ?string $post_type, ?string $taxonomy ): void {
		$_GET = array();
		if ( $post_type ) {
			$_GET['post_type'] = $post_type;
		}
		if ( $taxonomy ) {
			$_GET['taxonomy'] = $taxonomy;
		}
		// WP_Screen::get() only reads $_REQUEST['post_type']/['taxonomy'] to
		// infer the screen when called with NO hook name (it trusts an
		// explicit hook name completely instead) - so, like a real admin
		// page load, this drives it through $GLOBALS['hook_suffix'] and
		// leaves the argument empty rather than passing $hook directly.
		$_REQUEST               = $_GET;
		$GLOBALS['hook_suffix'] = $hook;
		$GLOBALS['pagenow']     = $hook;
		set_current_screen();

		ob_start();
		Admin::get_instance()->render_core_screen_header();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'wbam-page-header', $output, "'{$hook}' must render the shared page header." );
		$this->assert_core_screen_header_markup( $output );
	}

	/**
	 * A header printed on in_admin_header sits outside the core screen's own
	 * .wrap: it needs its own .wrap for the page gutter, and it must not add
	 * a second wp-header-end - common.js inserts each notice after EVERY
	 * anchor, so two anchors print every notice twice.
	 *
	 * @param string $output Rendered header.
	 */
	private function assert_core_screen_header_markup( string $output ): void {
		$this->assertStringNotContainsString( 'wp-header-end', $output, 'The core screen keeps its own notice anchor.' );
		$this->assertMatchesRegularExpression( '/class="wrap wbam-core-screen-header"/', $output, 'The header needs the .wrap page gutter.' );
	}

	public function free_core_screens(): array {
		return array(
			'all ads (edit.php)'        => array( 'edit.php', 'wbam-ad', null ),
			'add new ad (post-new.php)' => array( 'post-new.php', 'wbam-ad', null ),
			'ad tags (edit-tags.php)'   => array( 'edit-tags.php', 'wbam-ad', 'wbam_ad_tag' ),
		);
	}

	/**
	 * Classified Categories / Locations (Pro) get the shared header through
	 * Pro_Admin::render_classified_taxonomy_header(), the Pro-side twin of
	 * the Free hook above.
	 *
	 * @dataProvider pro_classified_taxonomy_screens
	 */
	public function test_classified_taxonomy_screen_renders_shared_header( string $taxonomy ): void {
		$_GET = array(
			'post_type' => 'wbam-classified',
			'taxonomy'  => $taxonomy,
		);
		// See test_free_core_screen_renders_shared_header() for why this goes
		// through $GLOBALS['hook_suffix'] with no argument to set_current_screen().
		$_REQUEST                 = $_GET;
		$GLOBALS['hook_suffix'] = 'edit-tags.php';
		set_current_screen();

		ob_start();
		( new Pro_Admin() )->render_classified_taxonomy_header();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'wbam-page-header', $output, "'{$taxonomy}' must render the shared page header." );
		$this->assert_core_screen_header_markup( $output );
	}

	public function pro_classified_taxonomy_screens(): array {
		return array(
			'categories' => array( 'wbam-classified-cat' ),
			'locations'  => array( 'wbam-classified-loc' ),
		);
	}
}
