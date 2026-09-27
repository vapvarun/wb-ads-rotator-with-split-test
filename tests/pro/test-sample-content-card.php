<?php
/**
 * One 'Sample content' card (owner decision 2026-10-03, card 10344381767):
 * Pro's card lists the setup wizard's samples next to its demo set, and
 * Free's own card steps aside while Pro is active.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Admin\Demo_Data_Cleaner;
use WBAM_Pro\Core\Pro_Admin;

class Test_Sample_Content_Card extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $ad_id, Demo_Data_Cleaner::META_FLAG, '1' );
		update_option( Demo_Data_Cleaner::OPTION_IDS, array( 'ads' => array( $ad_id ) ) );
	}

	public function test_one_card_lists_the_wizard_samples(): void {
		$admin = new Pro_Admin(); // Registers wbam_sample_content_card_owned.

		ob_start();
		Demo_Data_Cleaner::render_clear_button_section();
		$this->assertSame( '', (string) ob_get_clean(), 'With Pro active, Free prints no second card.' );

		ob_start();
		$admin->render_tools_page( true );
		$html = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $html, '>Sample content</h2>' ) );
		$this->assertStringContainsString( 'The setup wizard added 1 sample item.', $html );
		$this->assertStringContainsString( 'Remove All Sample Content', $html );
		$this->assertSame( 1, Demo_Data_Cleaner::count() );
	}
}
