<?php
/**
 * The setup wizard page is reachable but not listed in the admin menu.
 *
 * add_dashboard_page() with an empty title left an item with no text under
 * Dashboard, pointing at the wizard.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Setup_Wizard;
use WP_UnitTestCase;

class Test_Setup_Wizard_Menu extends WP_UnitTestCase {

	public function test_wizard_is_registered_without_a_menu_item(): void {
		global $submenu, $_registered_pages;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		( new Setup_Wizard() )->add_wizard_page();

		$slugs = wp_list_pluck( isset( $submenu['index.php'] ) ? $submenu['index.php'] : array(), 2 );
		$this->assertNotContains( 'wbam-setup', $slugs, 'No empty Dashboard submenu item.' );
		$this->assertArrayHasKey( get_plugin_page_hookname( 'wbam-setup', 'index.php' ), $_registered_pages, 'index.php?page=wbam-setup still resolves.' );
	}
}
