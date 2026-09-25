<?php
/**
 * Video ads get no page placements.
 *
 * Video runs through MediaShield's in-stream player, not a page slot, but
 * Ad_Submission_Manager::submit_ad() used to inherit every placement from
 * the chosen package (or the full registry when there was none) onto a
 * video ad anyway, and the portal review step then showed "Placements:
 * Default (all)" for a creative that has no placements at all.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;

class Test_Video_Ad_Placements extends Pro_Test_Case {

	private int $user;
	private object $advertiser;
	private int $package_id;

	public function set_up(): void {
		parent::set_up();

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );
		$this->advertiser = Advertiser_Manager::get_instance()->get( (int) $this->advertiser->id );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 100000, 'seed' );

		global $wpdb;
		// Placements deliberately set, non-empty, so a pass here proves the
		// video branch overrides package inheritance rather than the package
		// just happening to have none.
		$wpdb->insert(
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'              => 'Video Placements Flat',
				'price'             => 25.00,
				'pricing_model'     => 'flat',
				'requires_approval' => 0,
				'placements'        => maybe_serialize( array( 'header' ) ),
				'status'            => 'active',
				'created_at'        => current_time( 'mysql' ),
			)
		);
		$this->package_id = (int) $wpdb->insert_id;
	}

	public function tear_down(): void {
		unset( $_POST['video_url'] );
		parent::tear_down();
	}

	public function test_video_submission_gets_no_placements(): void {
		// Video_Ad_Portal::save_fields() reads the video URL straight off
		// $_POST (the wbam_pro_before_submit_ad filter contract), matching
		// how the real form submission works.
		$_POST['video_url'] = 'https://example.com/spot.mp4';

		$submission = Ad_Submission_Manager::get_instance()->submit_ad(
			$this->advertiser->id,
			array(
				'title'   => 'Video ad, no placements',
				'ad_type' => 'video',
			),
			$this->package_id
		);
		$this->assertNotWPError( $submission );

		$this->assertSame(
			array(),
			get_post_meta( (int) $submission->ad_id, '_wbam_placements', true ),
			'A video ad must never inherit page placements, from a package or the registry default.'
		);
	}

	public function test_image_submission_on_the_same_package_still_gets_placements(): void {
		$submission = Ad_Submission_Manager::get_instance()->submit_ad(
			$this->advertiser->id,
			array(
				'title'     => 'Image ad, package placements',
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/x.png',
			),
			$this->package_id
		);
		$this->assertNotWPError( $submission );

		$this->assertSame(
			array( 'header' ),
			get_post_meta( (int) $submission->ad_id, '_wbam_placements', true ),
			'Regression guard: the video-only fix must not affect other ad types.'
		);
	}
}
