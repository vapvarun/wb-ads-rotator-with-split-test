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

	private function rest_create( array $body ): array {
		$request = new \WP_REST_Request( 'POST', '/wbam/v1/ads' );
		$request->set_body_params( array_merge( array( 'title' => 'REST ad' ), $body ) );
		return rest_do_request( $request )->get_data();
	}

	public function test_rest_adsense_without_a_slot_id_is_kept_a_draft_with_the_reason(): void {
		$data = $this->rest_create( array( 'status' => 'publish', 'ad_data' => array( 'type' => 'adsense' ) ) );

		$this->assertSame( 'draft', get_post_status( $data['id'] ) );
		$this->assertStringContainsString( 'Ad Slot ID', $data['notice'] );
	}

	public function test_rest_complete_adsense_publishes(): void {
		Settings_Helper::update( 'adsense_publisher_id', 'ca-pub-1234567890123456' );
		$data = $this->rest_create( array( 'ad_data' => array( 'type' => 'adsense', 'slot_id' => '1234567890' ) ) );

		$this->assertSame( 'publish', get_post_status( $data['id'] ) );
		$this->assertArrayNotHasKey( 'notice', $data );
	}

	public function test_rest_create_honours_the_requested_status(): void {
		$data = $this->rest_create( array( 'status' => 'draft', 'ad_data' => array( 'type' => 'rich-content', 'content' => '<p>x</p>' ) ) );
		$this->assertSame( 'draft', get_post_status( $data['id'] ) );

		$data = $this->rest_create( array( 'ad_data' => array( 'type' => 'rich-content', 'content' => '<p>x</p>' ) ) );
		$this->assertSame( 'publish', get_post_status( $data['id'] ), 'No status asked for: publish, as before.' );
	}

	public function test_rest_update_that_empties_the_slot_id_drops_a_live_ad_to_draft(): void {
		Settings_Helper::update( 'adsense_publisher_id', 'ca-pub-1234567890123456' );
		$data = $this->rest_create( array( 'ad_data' => array( 'type' => 'adsense', 'slot_id' => '1234567890' ) ) );
		$this->assertSame( 'publish', get_post_status( $data['id'] ) );

		$request = new \WP_REST_Request( 'PUT', '/wbam/v1/ads/' . $data['id'] );
		$request->set_body_params( array( 'ad_data' => array( 'type' => 'adsense', 'slot_id' => '' ) ) );
		rest_do_request( $request );

		$this->assertSame( 'draft', get_post_status( $data['id'] ) );
	}

	public function test_abilities_create_adsense_without_a_slot_id_is_kept_a_draft(): void {
		$result = ( new \WBAM\Core\Abilities() )->execute_create_ad(
			array(
				'title'   => 'Ability ad',
				'type'    => 'adsense',
				'content' => array( 'publisher_id' => 'ca-pub-1234567890123456' ),
			)
		);

		$this->assertSame( 'draft', get_post_status( $result['id'] ) );
		$this->assertStringContainsString( 'Ad Slot ID', $result['notice'] );
	}

	public function test_an_empty_shortcode_hints_to_editors_and_shows_visitors_nothing(): void {
		$this->assertStringContainsString( 'Add the ad to show', do_shortcode( '[wbam_ad]' ) );
		$this->assertStringContainsString( 'no ad with ID 999991', do_shortcode( '[wbam_ad id="999991"]' ) );

		wp_set_current_user( 0 );
		$this->assertSame( '', do_shortcode( '[wbam_ad]' ) );
		$this->assertSame( '', do_shortcode( '[wbam_ad id="999991"]' ) );
	}

	public function test_quick_or_bulk_edit_publishing_an_incomplete_ad_keeps_it_a_draft(): void {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'draft' ) );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'adsense', 'publisher_id' => 'ca-pub-1234567890123456' ) );

		wp_update_post( array( 'ID' => $ad_id, 'post_status' => 'publish' ) );

		$this->assertSame( 'draft', get_post_status( $ad_id ), 'No form is posted, so the stored data decides.' );
	}

	public function test_a_complete_ad_still_publishes_from_quick_edit(): void {
		Settings_Helper::update( 'adsense_publisher_id', 'ca-pub-1234567890123456' );
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'draft' ) );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'adsense', 'slot_id' => '1234567890' ) );

		wp_update_post( array( 'ID' => $ad_id, 'post_status' => 'publish' ) );

		$this->assertSame( 'publish', get_post_status( $ad_id ) );
	}

	public function test_an_ad_that_is_already_live_is_not_demoted_by_an_unrelated_update(): void {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'adsense' ) );

		wp_update_post( array( 'ID' => $ad_id, 'post_title' => 'Renamed' ) );

		$this->assertSame( 'publish', get_post_status( $ad_id ), 'Only the moment of publishing is judged, so an import or rename cannot take a live ad off the air.' );
	}

	public function test_a_rest_rename_does_not_take_a_live_incomplete_ad_off_the_air(): void {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'adsense' ) );

		$request = new \WP_REST_Request( 'PUT', '/wbam/v1/ads/' . $ad_id );
		$request->set_body_params( array( 'title' => 'Renamed' ) );
		rest_do_request( $request );

		$this->assertSame( 'publish', get_post_status( $ad_id ) );
	}

	public function test_an_ad_block_with_no_ad_hints_to_editors_only(): void {
		$this->assertStringContainsString( 'Add the ad to show', do_blocks( '<!-- wp:wb-ads/ad /-->' ) );

		wp_set_current_user( 0 );
		$this->assertStringNotContainsString( 'Add the ad to show', do_blocks( '<!-- wp:wb-ads/ad /-->' ) );
	}
}
