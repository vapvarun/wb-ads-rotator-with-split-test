<?php
/**
 * An add-on's own section, added through wbam_settings_sections, shows in
 * Settings with Pro active, at any filter priority: Pro rebuilds the list
 * but carries unknown sections through (card 10344031466).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Admin;

/**
 * @group pro
 */
class Test_Addon_Settings_Section extends Pro_Test_Case {

	public function test_addon_section_survives_pro_rebuilding_the_list(): void {
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$sections = ( new Pro_Admin() )->map_settings_sections(
			array(
				'general'  => array(
					'label'  => 'Free general',
					'render' => '__return_null',
				),
				'my-addon' => array(
					'label'  => 'My add-on',
					'render' => '__return_null',
				),
			)
		);

		$this->assertArrayHasKey( 'my-addon', $sections, 'An add-on section is kept.' );
		$this->assertSame( 'My add-on', $sections['my-addon']['label'] );
		$this->assertNotSame( 'Free general', $sections['general']['label'], "Free's own General is still replaced by Pro's." );
	}
}
