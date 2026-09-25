<?php
/**
 * Help & Docs promises "Remove the samples any time from Tools", but
 * Demo_Data_Cleaner::render_clear_button() had no caller - the button
 * never actually appeared there (card 10342783654, step 8).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Demo_Data_Cleaner;
use WP_UnitTestCase;

class Test_Demo_Data_Cleaner_Tools_Section extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Demo_Data_Cleaner::OPTION_IDS );
		parent::tear_down();
	}

	public function test_renders_nothing_when_no_demo_data_is_tracked(): void {
		delete_option( Demo_Data_Cleaner::OPTION_IDS );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		ob_start();
		Demo_Data_Cleaner::render_clear_button_section();
		$this->assertSame( '', (string) ob_get_clean() );
	}

	public function test_renders_the_button_when_demo_ads_are_tracked(): void {
		update_option( Demo_Data_Cleaner::OPTION_IDS, array( 'ads' => array( 123 ) ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		ob_start();
		Demo_Data_Cleaner::render_clear_button_section();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'wbam-demo-clear-form', $html );
		$this->assertStringContainsString( 'Remove demo data', $html );
	}
}
