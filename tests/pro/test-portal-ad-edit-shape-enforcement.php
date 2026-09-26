<?php
/**
 * Portal edit path: every chosen slot must still fit once shape matching is
 * on (owner decision 13, card 10343726460, QA wave 4 fail #2).
 *
 * submit_ad() (create) already enforces this via validate_creative_fit().
 * update_ad_meta() (the advertiser editing an existing ad from the portal)
 * had no equivalent guard at all — an advertiser could edit a live ad and
 * tick a mismatched placement with nothing stopping it.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;

class Test_Portal_Ad_Edit_Shape_Enforcement extends Pro_Test_Case {

	private int $user;
	private object $advertiser;
	private int $ad_id;

	public function set_up(): void {
		parent::set_up();

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );

		$this->ad_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
				'post_author' => $this->user,
			)
		);
		update_post_meta( $this->ad_id, '_wbam_advertiser_id', (int) $this->advertiser->id );
	}

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		parent::tear_down();
	}

	private function edit_square_into( array $placements ) {
		return Ad_Submission_Manager::get_instance()->update_ad_meta(
			$this->ad_id,
			array(
				'ad_type'    => 'image',
				'image_url'  => 'https://example.com/square.png',
				'ad_format'  => 'custom',
				'ad_width'   => 300,
				'ad_height'  => 250,
				'placements' => $placements,
			)
		);
	}

	public function test_editing_an_ad_drops_a_placement_it_no_longer_fits(): void {
		Settings_Helper::update( 'format_matching', true );

		$this->edit_square_into( array( 'header', 'after_paragraph' ) );

		$saved = (array) get_post_meta( $this->ad_id, '_wbam_placements', true );

		$this->assertNotContains( 'header', $saved, 'A 300x250 square does not fit the Banner-shaped header.' );
		$this->assertContains( 'after_paragraph', $saved );
	}

	public function test_editing_an_ad_keeps_todays_behavior_when_matching_is_off(): void {
		Settings_Helper::update( 'format_matching', false );

		$this->edit_square_into( array( 'header', 'after_paragraph' ) );

		$saved = (array) get_post_meta( $this->ad_id, '_wbam_placements', true );

		$this->assertContains( 'header', $saved, "Existing sites keep today's edit behavior until they opt in." );
	}
}
