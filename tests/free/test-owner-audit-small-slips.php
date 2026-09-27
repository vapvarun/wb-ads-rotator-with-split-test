<?php
/**
 * Small slips (card 10344381767, owner-seat audit, step 10).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Admin;
use WBAM\Admin\Demo_Data_Cleaner;
use WBAM\Admin\Setup_Wizard;
use WP_UnitTestCase;

class Test_Owner_Audit_Small_Slips extends WP_UnitTestCase {

	public function test_an_ad_says_ad_published(): void {
		$messages = Admin::get_instance()->ad_updated_messages( array() );
		$this->assertSame( 'Ad published.', $messages['wbam-ad'][6] );
		$this->assertSame( 'Ad draft updated.', $messages['wbam-ad'][10] );
	}

	public function test_publishing_an_ad_with_nothing_to_show_warns(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$ad_id    = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		$original = $_POST;
		$_POST    = array(
			'wbam_nonce' => wp_create_nonce( 'wbam_save_ad' ),
			'wbam_data'  => array( 'type' => 'image' ),
		);
		Admin::get_instance()->save_meta( $ad_id, get_post( $ad_id ) );
		$_POST = $original;

		$notices = (array) get_transient( 'wbam_save_notice_' . get_current_user_id() . '_' . $ad_id );
		$text    = implode( ' ', wp_list_pluck( $notices, 'message' ) );
		$this->assertStringContainsString( 'nothing to show yet', $text );
		$this->assertStringContainsString( 'No placement is ticked', $text );
		$this->assertSame( 'publish', get_post_status( $ad_id ), 'A warning, never a block.' );
	}

	public function test_the_setup_notice_is_for_admins_only(): void {
		delete_option( 'wbam_setup_complete' );
		delete_option( 'wbam_setup_dismissed' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		set_current_screen( 'dashboard' );

		ob_start();
		( new Setup_Wizard() )->show_setup_notice();
		$this->assertSame( '', (string) ob_get_clean() );
		set_current_screen( 'front' );
	}

	public function test_removing_samples_removes_their_widget(): void {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $ad_id, Demo_Data_Cleaner::META_FLAG, '1' );
		update_option( Demo_Data_Cleaner::OPTION_IDS, array( 'ads' => array( $ad_id ) ) );
		update_option(
			'widget_wbam_ad_widget',
			array(
				2              => array( 'title' => '', 'ad_id' => $ad_id ),
				3              => array( 'title' => '', 'ad_id' => 0 ),
				'_multiwidget' => 1,
			)
		);
		wp_set_sidebars_widgets( array( 'sidebar-1' => array( 'wbam_ad_widget-2', 'wbam_ad_widget-3' ) ) );

		Demo_Data_Cleaner::clear();

		$instances = get_option( 'widget_wbam_ad_widget' );
		$this->assertArrayNotHasKey( 2, $instances, 'The sample widget goes with its ad.' );
		$this->assertArrayHasKey( 3, $instances, 'The owner\'s own widget stays.' );
		$this->assertSame( array( 'wbam_ad_widget-3' ), wp_get_sidebars_widgets()['sidebar-1'] );
	}
}
