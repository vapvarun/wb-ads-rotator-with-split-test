<?php
/**
 * Card 10342783654, step 9: Pro used to register Classifieds and
 * Advertisers as their own top-level admin menus - alongside the WB Ad
 * Manager menu and Free's own Links menu, 4 top-level entries for one
 * plugin family. Both now live as submenus under the WB Ad Manager (wbam-ad
 * CPT) menu, with every page slug unchanged.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Admin;

class Test_Admin_Menu_Consolidation extends Pro_Test_Case {

	private const PARENT = 'edit.php?post_type=wbam-ad';

	public function set_up(): void {
		parent::set_up();
		// Explicit stored values always win over the site-mode bundle
		// (Settings_Helper::is_module_enabled()), so this doesn't depend on
		// which mode the test suite's install happens to be in.
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
				),
			)
		);
	}

	public function tear_down(): void {
		delete_option( 'wbam_pro_settings' );
		global $menu, $submenu;
		$menu    = array();
		$submenu = array();
		parent::tear_down();
	}

	public function test_classifieds_and_advertisers_are_not_top_level_menus(): void {
		global $menu, $submenu;
		$menu    = array();
		$submenu = array();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		( new Pro_Admin() )->add_menu_items();

		$top_level_slugs = wp_list_pluck( $menu, 2 );
		$this->assertNotContains( 'wbam-classifieds', $top_level_slugs, 'Classifieds must not be its own top-level menu.' );
		$this->assertNotContains( 'wbam-advertisers', $top_level_slugs, 'Advertisers must not be its own top-level menu.' );
	}

	/**
	 * @dataProvider consolidated_pages
	 */
	public function test_page_is_a_submenu_of_wb_ad_manager( string $slug ): void {
		global $menu, $submenu;
		$menu    = array();
		$submenu = array();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		( new Pro_Admin() )->add_menu_items();

		$this->assertArrayHasKey( self::PARENT, $submenu, 'The WB Ad Manager submenu array must exist.' );
		$child_slugs = wp_list_pluck( $submenu[ self::PARENT ], 2 );
		$this->assertContains( $slug, $child_slugs, "'{$slug}' must be a submenu of " . self::PARENT . ', so the page is still reachable at admin.php?page=' . $slug );
	}

	public function consolidated_pages(): array {
		return array(
			'classifieds'  => array( 'wbam-classifieds' ),
			'advertisers'  => array( 'wbam-advertisers' ),
			'packages'     => array( 'wbam-packages' ),
			'transactions' => array( 'wbam-transactions' ),
			'reviews'      => array( 'wbam-reviews' ),
			'memberships'  => array( 'wbam-membership-plans' ),
		);
	}
}
