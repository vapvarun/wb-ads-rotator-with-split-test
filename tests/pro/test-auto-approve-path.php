<?php
/**
 * Auto-approve package path: one approval sends one email, and a submission
 * type the security backstop forces into review (rich-content/code) must
 * not let its campaign jump ahead to active/charged.
 *
 * Regression guard for Basecamp #10335670599 QA rejects on the
 * auto-approve package path:
 *  - submit_ad()'s auto-approve branch called activate_ad() directly,
 *    skipping the $approving guard, so an auto-approved image ad fired the
 *    campaign-started email, the ad-published email, AND the admin
 *    "requires review" email on top of nothing that actually said
 *    "approved" - three emails for one approval.
 *  - Campaign_Manager::create_from_package() activated the campaign purely
 *    off the package's requires_approval flag, ignoring that
 *    determine_approval_status() forces a rich-content/code ad to stay
 *    pending even on a no-review package - the campaign went ACTIVE and
 *    "charged" before the ad was ever approved.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;

class Test_Auto_Approve_Path extends Pro_Test_Case {

	private int $user;
	private object $advertiser;
	private int $package_id;

	/** @var array<int,array> Captured wp_mail() calls, reset per assertion window. */
	private array $mails = array();

	public function set_up(): void {
		parent::set_up();

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );
		$this->advertiser = Advertiser_Manager::get_instance()->get( (int) $this->advertiser->id );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 100000, 'seed' );

		// Subscriber, not the test-runner's default user: determine_approval_status()'s
		// raw-markup backstop keys on current_user_can( 'unfiltered_html' ), which a
		// subscriber never has.
		wp_set_current_user( $this->user );

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'               => 'Auto Approve Flat',
				'price'              => 49.00,
				'pricing_model'      => 'flat',
				'status'             => 'active',
				'requires_approval'  => 0,
				'duration_days'      => 30,
				'created_at'         => current_time( 'mysql' ),
			)
		);
		$this->package_id = (int) $wpdb->insert_id;

		$this->mails = array();
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		parent::tear_down();
	}

	/**
	 * Short-circuits wp_mail() so no real mail transport runs, and records
	 * what would have been sent - the pattern the task calls for ("count
	 * mails via the pre_wp_mail filter").
	 *
	 * @param mixed $return Whatever the filter already returned (always null here).
	 * @param array $atts   wp_mail() call arguments.
	 * @return true Always short-circuits to "sent".
	 */
	public function capture_mail( $return, $atts ) {
		unset( $return );
		$this->mails[] = $atts;
		return true;
	}

	private function balance(): float {
		return (float) \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $this->user );
	}

	public function test_auto_approved_image_ad_sends_exactly_one_approval_email(): void {
		$manager    = Ad_Submission_Manager::get_instance();
		$submission = $manager->submit_ad(
			$this->advertiser->id,
			array(
				'title'     => 'Auto-approve image ad',
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/x.png',
				'link_url'  => 'https://example.com',
			),
			$this->package_id
		);

		$this->assertNotWPError( $submission );
		$this->assertSame( 'approved', $submission->status, 'requires_approval off + image ad auto-approves.' );
		$this->assertSame( 'publish', get_post_status( $submission->ad_id ) );

		$this->assertCount(
			1,
			$this->mails,
			'One approval must write exactly one email, not the old three (campaign-started + ad-published + admin review).'
		);
		$this->assertSame( get_user_by( 'id', $this->user )->user_email, $this->mails[0]['to'] );
		$this->assertStringContainsString( 'approved', $this->mails[0]['subject'] );
	}

	public function test_auto_approved_image_ad_charges_the_package_once(): void {
		$before  = $this->balance();
		$manager = Ad_Submission_Manager::get_instance();
		$manager->submit_ad(
			$this->advertiser->id,
			array(
				'title'     => 'Auto-approve image ad, billed',
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/x.png',
				'link_url'  => 'https://example.com',
			),
			$this->package_id
		);

		$this->assertSame( $before - 4900, $this->balance(), 'The 49.00 flat price is charged exactly once.' );
	}

	public function test_raw_markup_ad_on_auto_approve_package_stays_pending_and_uncharged(): void {
		$before     = $this->balance();
		$manager    = Ad_Submission_Manager::get_instance();
		$submission = $manager->submit_ad(
			$this->advertiser->id,
			array(
				'title'   => 'Code ad on an auto-approve package',
				'ad_type' => 'code',
				'ad_code' => '<script>console.log(1)</script>',
			),
			$this->package_id
		);

		$this->assertNotWPError( $submission );
		$this->assertSame(
			'pending',
			$submission->status,
			'The raw-markup security backstop forces review even though the package skips it.'
		);
		$this->assertSame( 'pending', get_post_status( $submission->ad_id ), 'The ad itself must not go live before review.' );
		$this->assertSame( $before, $this->balance(), 'Nothing is charged before approval.' );

		$campaign = Campaign_Manager::get_instance()->get( (int) $submission->campaign_id );
		$this->assertSame(
			'pending',
			$campaign->status,
			'Reject 2: the campaign must wait with the ad, not go ACTIVE at submit just because the package allows auto-approve.'
		);

		// The admin "requires review" email (the ad genuinely needs it) and the
		// advertiser's "we received your ad" receipt - nothing that says approved.
		$this->assertCount( 2, $this->mails );
		$this->assertStringContainsString( 'requires review', $this->mails[0]['subject'] );
		$this->assertStringContainsString( 'We received your ad', $this->mails[1]['subject'] );
	}

	public function test_approving_the_forced_review_ad_activates_campaign_from_approval_time(): void {
		$manager    = Ad_Submission_Manager::get_instance();
		$submission = $manager->submit_ad(
			$this->advertiser->id,
			array(
				'title'   => 'Code ad, later approved',
				'ad_type' => 'code',
				'ad_code' => '<script>console.log(1)</script>',
			),
			$this->package_id
		);
		$this->assertSame( 'pending', $submission->status );

		$before_balance = $this->balance();
		$this->mails     = array(); // Only care about the approval's own emails from here.

		$this->assertTrue( $manager->approve( (int) $submission->id ) );

		$this->assertSame( $before_balance - 4900, $this->balance(), 'Charged on approval, not before.' );

		$campaign = Campaign_Manager::get_instance()->get( (int) $submission->campaign_id );
		$this->assertSame( 'active', $campaign->status );
		$this->assertLessThan(
			120,
			abs( strtotime( $campaign->start_date ) - strtotime( current_time( 'mysql' ) ) ),
			'The run starts at approval time, not the earlier submit time.'
		);

		$this->assertCount( 1, $this->mails, 'A manual approve() must also send exactly one email.' );
		$this->assertStringContainsString( 'approved', $this->mails[0]['subject'] );
	}
}
