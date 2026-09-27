<?php
/**
 * AdSense without a Slot ID or any Publisher ID renders nothing, so
 * publishing it keeps it a Draft and says what to add (owner decision,
 * card 10344381767); the Ads list names what is missing.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Core\Settings_Helper;
use WBAM\Modules\AdTypes\AdSense_Ad;
use WP_UnitTestCase;

class Test_AdSense_Incomplete_Stays_Draft extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		Settings_Helper::update( 'adsense_publisher_id', '' );
		// Admin::init() registers this in wp-admin only.
		add_filter( 'wp_insert_post_data', array( \WBAM\Admin\Admin::get_instance(), 'keep_incomplete_ad_draft' ), 10, 2 );
	}

	private function publish_adsense( array $fields ): int {
		$ad_id    = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'draft' ) );
		$original = $_POST;
		$_POST    = array(
			'wbam_nonce' => wp_create_nonce( 'wbam_save_ad' ),
			'wbam_data'  => array_merge( array( 'type' => 'adsense' ), $fields ),
		);
		wp_update_post(
			array(
				'ID'          => $ad_id,
				'post_status' => 'publish',
			)
		);
		$_POST = $original;
		return $ad_id;
	}

	public function test_no_slot_id_stays_a_draft_with_the_reason(): void {
		$ad_id = $this->publish_adsense( array( 'publisher_id' => 'ca-pub-1234567890123456' ) );

		$this->assertSame( 'draft', get_post_status( $ad_id ) );
		$notices = get_transient( 'wbam_save_notice_' . get_current_user_id() . '_' . $ad_id );
		$this->assertStringContainsString( 'Ad Slot ID', $notices[0]['message'] );
	}

	public function test_no_publisher_id_anywhere_stays_a_draft(): void {
		$this->assertSame( 'draft', get_post_status( $this->publish_adsense( array( 'slot_id' => '1234567890' ) ) ) );
	}

	public function test_a_complete_ad_publishes(): void {
		Settings_Helper::update( 'adsense_publisher_id', 'ca-pub-1234567890123456' );
		$this->assertSame( 'publish', get_post_status( $this->publish_adsense( array( 'slot_id' => '1234567890' ) ) ) );
	}

	public function test_the_list_names_what_is_missing(): void {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'adsense' ) );
		$ad = new AdSense_Ad();

		$this->assertFalse( $ad->has_creative( $ad_id ) );
		$this->assertSame( 'Slot ID missing', $ad->get_missing_label( $ad_id ) );
	}
}
