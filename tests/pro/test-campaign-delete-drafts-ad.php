<?php
/**
 * Deleting the campaign that paid for a live ad drafts the ad, with no
 * Resume button - not a paused-but-refused dead end.
 *
 * Card 10343726590 (owner decision): the moder3 change (Pro d4d1f07/2fbb25e)
 * unlinked and paused a deleted campaign's ad, so the portal's Resume
 * refused it forever with no way back other than a brand-new submission.
 * Reusing the existing expired-campaign path (Campaign_Manager's private
 * disable_campaign_ad()) instead moves it to draft, the same state any
 * other unpublished ad is in - consistent, and with no Resume button to
 * even show.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;

class Test_Campaign_Delete_Drafts_Ad extends Pro_Test_Case {

	public function test_deleting_the_paying_campaign_drafts_the_ad(): void {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update_status( (int) $advertiser->id, 'active' );

		$ad_id = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_status', 'active' );

		$campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id' => $advertiser->id,
				'name'          => 'Delete guard',
				'pricing_model' => 'flat',
				'budget'        => 0,
				'status'        => 'draft',
				'ad_id'         => $ad_id,
			)
		);
		$this->assertNotWPError( $campaign );
		update_post_meta( $ad_id, '_wbam_campaign_id', $campaign->id );

		$result = Campaign_Manager::get_instance()->delete( $campaign->id );

		$this->assertTrue( $result );
		$this->assertSame( 'draft', get_post_status( $ad_id ), 'A deleted campaign\'s paid ad must be drafted, not left publish+paused.' );
		$this->assertSame( 'campaign_deleted', get_post_meta( $ad_id, '_wbam_disable_reason', true ) );
		$this->assertSame( '', (string) get_post_meta( $ad_id, '_wbam_campaign_id', true ), 'The link to the deleted campaign must be gone.' );
	}

	/**
	 * QA wave 4: a paused ad whose campaign is deleted still showed Resume,
	 * which errored - disable_campaign_ad() moved the ad to Draft but left
	 * the advertiser's earlier _wbam_status = 'paused' meta in place, and
	 * the portal's My Ads tab prefers that meta over post_status. Clearing
	 * it lets post_status ('draft') read through, so there is nothing left
	 * to resume.
	 */
	public function test_a_paused_ad_has_no_stale_status_meta_after_its_campaign_is_deleted(): void {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update_status( (int) $advertiser->id, 'active' );

		$ad_id = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_enabled', '0' );
		update_post_meta( $ad_id, '_wbam_status', 'paused' );

		$campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id' => $advertiser->id,
				'name'          => 'Delete guard - paused',
				'pricing_model' => 'flat',
				'budget'        => 0,
				'status'        => 'draft',
				'ad_id'         => $ad_id,
			)
		);
		$this->assertNotWPError( $campaign );
		update_post_meta( $ad_id, '_wbam_campaign_id', $campaign->id );

		Campaign_Manager::get_instance()->delete( $campaign->id );

		$this->assertSame( 'draft', get_post_status( $ad_id ) );
		$this->assertSame( '', get_post_meta( $ad_id, '_wbam_status', true ), 'The stale paused meta must be cleared - post_status draft is now the only source of truth, so Resume has nothing left to show.' );
	}

	public function test_a_taken_down_rejected_ad_keeps_its_rejected_status_when_its_campaign_is_deleted(): void {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update_status( (int) $advertiser->id, 'active' );

		// A takedown: post_status stays publish, but _wbam_status is 'rejected'
		// (Ad_Submission_Manager::reject(), which never changes post_status).
		$ad_id = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_enabled', '0' );
		update_post_meta( $ad_id, '_wbam_status', 'rejected' );

		$campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id' => $advertiser->id,
				'name'          => 'Delete guard - taken down',
				'pricing_model' => 'flat',
				'budget'        => 0,
				'status'        => 'draft',
				'ad_id'         => $ad_id,
			)
		);
		$this->assertNotWPError( $campaign );
		update_post_meta( $ad_id, '_wbam_campaign_id', $campaign->id );

		Campaign_Manager::get_instance()->delete( $campaign->id );

		$this->assertSame( 'publish', get_post_status( $ad_id ), 'A takedown must not be relabelled as a campaign-deleted draft.' );
		$this->assertSame( 'rejected', get_post_meta( $ad_id, '_wbam_status', true ) );
	}
}
