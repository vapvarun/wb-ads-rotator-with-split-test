<?php
/**
 * Card 10342783654, step 10: the Plugins-list row had no Settings link
 * (Pro's row already had one).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Admin;
use WP_UnitTestCase;

class Test_Plugin_Action_Links extends WP_UnitTestCase {

	public function test_settings_link_is_added_first(): void {
		$links = Admin::get_instance()->plugin_action_links( array( 'existing' => '<a href="#">Existing</a>' ) );

		$this->assertStringContainsString( 'Settings', $links[0] );
		$this->assertStringContainsString( 'page=wbam-settings', $links[0] );
		$this->assertArrayHasKey( 'existing', $links, 'The filter only prepends, it does not drop what WordPress already built (Deactivate, etc).' );
	}
}
