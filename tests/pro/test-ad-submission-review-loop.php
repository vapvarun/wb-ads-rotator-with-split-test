<?php
/**
 * Ad-submission review loop: request changes -> resubmit -> approve once.
 *
 * Regression guard for the 3.2.0 journey walk: after "Request Changes"
 * the submission could never leave changes_requested (resubmit() had no
 * caller and the admin screens hid every action), and approve() had no
 * status guard, so a replayed Approve link or a bulk approve over an
 * approved row charged the package price a second time.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;

class Test_Ad_Submission_Review_Loop extends Pro_Test_Case {

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
		$wpdb->insert(
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'          => 'Review Loop Flat',
				'price'         => 49.00,
				'pricing_model' => 'flat',
				'status'        => 'active',
				'created_at'    => current_time( 'mysql' ),
			)
		);
		$this->package_id = (int) $wpdb->insert_id;
	}

	private function submit(): object {
		$submission = Ad_Submission_Manager::get_instance()->submit_ad(
			$this->advertiser->id,
			array(
				'title'     => 'Review loop ad',
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/x.png',
				'link_url'  => 'https://example.com',
			),
			$this->package_id
		);
		$this->assertNotWPError( $submission );

		return $submission;
	}

	public function test_resubmit_returns_changes_requested_to_pending(): void {
		$manager    = Ad_Submission_Manager::get_instance();
		$submission = $this->submit();

		$manager->request_changes( (int) $submission->id, 'Shorter headline' );
		$this->assertSame( 'changes_requested', get_post_meta( $submission->ad_id, '_wbam_status', true ) );

		$this->assertTrue( $manager->resubmit( (int) $submission->id ) );

		$reloaded = $manager->get( (int) $submission->id );
		$this->assertSame( 'pending', $reloaded->status );
		$this->assertSame( 'Shorter headline', $reloaded->admin_notes, 'The reviewer keeps what they asked for.' );
		$this->assertSame( '', get_post_meta( $submission->ad_id, '_wbam_status', true ), 'Stale portal status must be cleared.' );
	}

	public function test_changes_requested_submission_can_be_approved(): void {
		$manager    = Ad_Submission_Manager::get_instance();
		$submission = $this->submit();
		$manager->request_changes( (int) $submission->id, 'Fix it' );

		$this->assertTrue( $manager->approve( (int) $submission->id ) );
		$this->assertSame( '', get_post_meta( $submission->ad_id, '_wbam_status', true ), 'Approval clears the review marker so the portal shows Active.' );
	}

	public function test_second_approve_does_not_charge_again(): void {
		$manager    = Ad_Submission_Manager::get_instance();
		$submission = $this->submit();

		$this->assertTrue( $manager->approve( (int) $submission->id ) );
		$after_first = \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $this->user );

		$this->assertWPError( $manager->approve( (int) $submission->id ) );
		$this->assertSame( $after_first, \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $this->user ), 'A replayed Approve must not charge the package twice.' );
	}
}
