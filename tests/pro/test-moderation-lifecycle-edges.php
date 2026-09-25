<?php
/**
 * Regression guards for Basecamp card 10340186779 "[Pro] Moderation
 * lifecycle edges: orphans, suspend, re-moderation, REST paths" - the
 * status paths around the 3.2.0 moderation loop that skipped a notice, a
 * guard or a side effect.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;

class Test_Moderation_Lifecycle_Edges extends Pro_Test_Case {

	private int $user;
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		$enabled['campaigns']   = true;
		$enabled['messaging']   = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );
		$this->advertiser = Advertiser_Manager::get_instance()->get( (int) $this->advertiser->id );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 100000, 'seed' );
	}

	private function balance(): int {
		return (int) \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $this->user );
	}

	/**
	 * A pending submission with a $49 flat package that requires review.
	 */
	private function submit_flat_package_ad( string $title ): object {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'              => $title . ' package',
				'price'             => 49.00,
				'pricing_model'     => 'flat',
				'requires_approval' => 1,
				'status'            => 'active',
				'created_at'        => current_time( 'mysql' ),
			)
		);

		$submission = Ad_Submission_Manager::get_instance()->submit_ad(
			$this->advertiser->id,
			array(
				'title'     => $title,
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/x.png',
				'link_url'  => 'https://example.com',
			),
			(int) $wpdb->insert_id
		);
		$this->assertNotWPError( $submission );

		return $submission;
	}

	// ---------------------------------------------------------------------
	// Ad submissions.
	// ---------------------------------------------------------------------

	/**
	 * Steps "Replaying an ad reject re-sends the email" and "Replayed ad
	 * reject also overwrites admin_notes and writes a second audit row".
	 */
	public function test_replayed_reject_is_refused_and_keeps_the_first_reason(): void {
		$manager    = Ad_Submission_Manager::get_instance();
		$submission = $this->submit_flat_package_ad( 'Replay reject ad' );

		$fired = 0;
		add_action(
			'wbam_pro_ad_submission_rejected',
			function () use ( &$fired ) {
				++$fired;
			}
		);

		$this->assertTrue( $manager->reject( (int) $submission->id, 'first reason' ) );
		$this->assertWPError( $manager->reject( (int) $submission->id, 'replayed reason' ), 'A second reject on a rejected submission must be refused.' );

		$this->assertSame( 1, $fired, 'The "not approved" email hook must fire once.' );
		$this->assertSame( 'first reason', $manager->get( (int) $submission->id )->admin_notes );
	}

	/**
	 * Reject on an approved submission is the takedown path and must keep
	 * working behind the new guard.
	 */
	public function test_reject_still_takes_down_an_approved_submission(): void {
		$manager    = Ad_Submission_Manager::get_instance();
		$submission = $this->submit_flat_package_ad( 'Takedown ad' );
		$this->assertTrue( $manager->approve( (int) $submission->id ) );

		$this->assertTrue( $manager->reject( (int) $submission->id, 'takedown' ) );
		$this->assertSame( 'rejected', $manager->get( (int) $submission->id )->status );
	}

	/**
	 * Step "Advertiser deletes a pending ad: the submission stays pending
	 * and its Starter Campaign shows Pending $49.00".
	 */
	public function test_deleting_a_pending_ad_cancels_its_submission_and_campaign(): void {
		$submission = $this->submit_flat_package_ad( 'Deleted pending ad' );
		$this->assertNotEmpty( $submission->campaign_id );

		wp_delete_post( (int) $submission->ad_id, true );

		$this->assertSame( 'cancelled', Ad_Submission_Manager::get_instance()->get( (int) $submission->id )->status );
		$this->assertSame( 'cancelled', Campaign_Manager::get_instance()->get( (int) $submission->campaign_id )->status );
	}

	/**
	 * Same step, money side: a live CPM campaign's reservation comes back
	 * when its ad is deleted.
	 */
	public function test_deleting_a_live_cpm_ad_returns_the_reserved_budget(): void {
		global $wpdb;
		$submission = $this->submit_flat_package_ad( 'Deleted cpm ad' );

		$campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id'  => $this->advertiser->id,
				'ad_id'          => (int) $submission->ad_id,
				'name'           => 'CPM reservation',
				'pricing_model'  => 'cpm',
				'price_per_unit' => 1.0,
				'budget'         => 25.0,
				'status'         => 'draft',
			)
		);
		$this->assertNotWPError( $campaign );
		$wpdb->update( $wpdb->prefix . 'wbam_ad_submissions', array( 'campaign_id' => (int) $campaign->id ), array( 'id' => (int) $submission->id ) );

		$before = $this->balance();
		$this->assertTrue( Campaign_Manager::get_instance()->activate( (int) $campaign->id ) );
		$this->assertSame( $before - 2500, $this->balance(), 'Activation reserves the budget.' );

		wp_delete_post( (int) $submission->ad_id, true );

		$this->assertSame( $before, $this->balance(), 'Deleting the ad must release the reservation.' );
	}

	/**
	 * Step "An advertiser editing a live approved ad skips re-moderation".
	 */
	public function test_editing_a_live_ad_sends_it_back_to_review_without_a_second_charge(): void {
		$manager    = Ad_Submission_Manager::get_instance();
		$submission = $this->submit_flat_package_ad( 'Live edit ad' );
		$before     = $this->balance();

		$this->assertTrue( $manager->approve( (int) $submission->id ) );
		$this->assertSame( $before - 4900, $this->balance() );

		$this->assertTrue(
			$manager->send_back_to_review(
				(int) $submission->id,
				array(
					'title'   => 'Swapped creative',
					'ad_type' => 'image',
				)
			)
		);

		$this->assertSame( 'pending', $manager->get( (int) $submission->id )->status );
		$this->assertSame( 'pending', get_post_status( (int) $submission->ad_id ), 'The edited ad must leave the air until reviewed.' );
		$this->assertSame( '0', get_post_meta( (int) $submission->ad_id, '_wbam_enabled', true ) );

		$this->assertTrue( $manager->approve( (int) $submission->id ) );
		$this->assertSame( $before - 4900, $this->balance(), 'Re-approving an edited ad must not charge the package again.' );
		$this->assertSame( 'publish', get_post_status( (int) $submission->ad_id ) );
	}

	/**
	 * Package set up without review: an edit stays live, same rule as a new
	 * submission.
	 */
	public function test_editing_an_auto_approved_package_ad_stays_live(): void {
		global $wpdb;
		$manager    = Ad_Submission_Manager::get_instance();
		$submission = $this->submit_flat_package_ad( 'Auto package edit ad' );
		$wpdb->update( $wpdb->prefix . 'wbam_packages', array( 'requires_approval' => 0 ), array( 'id' => (int) $submission->package_id ) );
		$this->assertTrue( $manager->approve( (int) $submission->id ) );

		$this->assertFalse(
			$manager->send_back_to_review(
				(int) $submission->id,
				array(
					'title'   => 'Edited',
					'ad_type' => 'image',
				)
			)
		);
		$this->assertSame( 'approved', $manager->get( (int) $submission->id )->status );
	}

	/**
	 * Step "Editor Publish on a pending portal ad with a short wallet shows
	 * 'Post submitted.' while the ad silently stays pending".
	 */
	public function test_failed_editor_publish_replaces_post_submitted_with_the_reason(): void {
		$submission = $this->submit_flat_package_ad( 'Short wallet publish ad' );
		$admin      = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		// Drain the wallet so approve() cannot charge the package.
		\Wbcom\Credits\Credits::adjust( 'wbam-pro', $this->user, -1 * $this->balance(), 'drain' );
		self::flush_credits_balance_cache();

		wp_update_post(
			array(
				'ID'          => (int) $submission->ad_id,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( 'pending', get_post_status( (int) $submission->ad_id ) );

		$location = apply_filters( 'redirect_post_location', admin_url( 'post.php?post=' . $submission->ad_id . '&action=edit&message=8' ), (int) $submission->ad_id );
		$this->assertStringNotContainsString( 'message=8', $location, '"Post submitted." must not be shown for a Publish that failed.' );
		$this->assertStringContainsString( 'wbam_publish_failed=1', $location );

		$_GET['wbam_publish_failed'] = '1';
		ob_start();
		Ad_Submission_Manager::get_instance()->render_publish_failed_notice();
		$notice = (string) ob_get_clean();
		unset( $_GET['wbam_publish_failed'] );

		$this->assertStringContainsString( 'Not published', $notice );
		$this->assertStringContainsString( 'charge credits', $notice, 'The notice must carry the approval failure reason.' );
	}

	/**
	 * Step "Dead code: render_edit_ad_form()/process_ad_update() have zero
	 * callers; hook wbam_ad_submission_changes_requested has no listener".
	 */
	public function test_dead_edit_form_and_duplicate_hook_are_gone(): void {
		$this->assertFalse( method_exists( \WBAM_Pro\Modules\Advertisers\Advertiser_Shortcodes::class, 'process_ad_update' ) );
		$this->assertFalse( method_exists( \WBAM_Pro\Modules\Advertisers\Advertiser_Shortcodes::class, 'render_edit_ad_form' ) );

		$source = (string) file_get_contents( WBAM_PRO_PATH . 'includes/Modules/AdSubmissions/class-ad-submission-manager.php' );
		$this->assertStringNotContainsString( "do_action( 'wbam_ad_submission_changes_requested'", $source );
	}
}
