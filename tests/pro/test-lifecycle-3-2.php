<?php
/**
 * 3.2.0 lifecycle and money regressions: takedowns stay down, campaigns
 * expire and start on approval, one submit and one renew path, plan
 * featured credits.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Memberships\Membership_Manager;

class Test_Lifecycle_3_2 extends Pro_Test_Case {

	private int $user;
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		$enabled['campaigns']   = true;
		$enabled['memberships'] = true;
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

	private function category(): int {
		$term = wp_insert_term( 'Lifecycle ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );
		return (int) $term['term_id'];
	}

	/** A site that sells ads without packages (owner turned the rule off). */
	private function allow_package_free_ads(): void {
		$caps = Settings_Helper::get( 'capabilities', array() );
		$caps['require_package_for_submission'] = false;
		Settings_Helper::update( 'capabilities', $caps );
	}

	public function test_rejecting_an_approved_ad_without_campaign_stops_serving(): void {
		$this->allow_package_free_ads();
		$manager    = Ad_Submission_Manager::get_instance();
		$submission = $manager->submit_ad(
			$this->advertiser->id,
			array(
				'title'     => 'No-package ad',
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/x.png',
				'link_url'  => 'https://example.com',
			)
		);
		$this->assertNotWPError( $submission );
		$this->assertTrue( $manager->approve( (int) $submission->id ) );
		$this->assertSame( '1', get_post_meta( $submission->ad_id, '_wbam_enabled', true ) );

		$manager->reject( (int) $submission->id, 'takedown' );
		$this->assertSame( '0', get_post_meta( $submission->ad_id, '_wbam_enabled', true ) );
	}

	public function test_editing_a_paused_ad_does_not_resume_it(): void {
		$this->allow_package_free_ads();
		$manager    = Ad_Submission_Manager::get_instance();
		$submission = $manager->submit_ad(
			$this->advertiser->id,
			array(
				'title'     => 'Paused ad',
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/x.png',
				'link_url'  => 'https://example.com',
			)
		);
		update_post_meta( $submission->ad_id, '_wbam_enabled', '0' );

		$manager->update_ad_meta( $submission->ad_id, array( 'title' => 'Paused ad, edited' ) );

		$this->assertSame( '0', get_post_meta( $submission->ad_id, '_wbam_enabled', true ) );
	}

	public function test_campaigns_past_their_end_date_expire_on_the_cron(): void {
		$campaigns = Campaign_Manager::get_instance();
		$campaign  = $campaigns->create(
			array(
				'advertiser_id' => $this->advertiser->id,
				'name'              => 'Ended',
				'pricing_model'     => 'flat',
				'budget'            => 49,
				'impressions_limit' => 10000,
				'status'        => 'active',
				'start_date'    => gmdate( 'Y-m-d H:i:s', time() - 10 * DAY_IN_SECONDS ),
				'end_date'      => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
			)
		);
		$this->assertNotWPError( $campaign );

		do_action( 'wbam_do_check_campaign_budgets' );

		$this->assertSame( 'expired', $campaigns->get( $campaign->id )->status );
	}

	public function test_package_window_starts_at_approval_not_submission(): void {
		$campaigns = Campaign_Manager::get_instance();
		$submitted = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 3 * DAY_IN_SECONDS );
		$campaign  = $campaigns->create(
			array(
				'advertiser_id' => $this->advertiser->id,
				'name'          => 'Waited in review',
				'pricing_model' => 'flat',
				'status'        => 'pending',
				'start_date'    => $submitted,
				'end_date'      => gmdate( 'Y-m-d H:i:s', strtotime( $submitted ) + 30 * DAY_IN_SECONDS ),
			)
		);
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'wbam_campaigns', array( 'created_at' => $submitted ), array( 'id' => $campaign->id ) );

		$this->assertTrue( $campaigns->activate( $campaign->id ) );

		$live = $campaigns->get( $campaign->id );
		$this->assertLessThan( 120, abs( strtotime( $live->start_date ) - strtotime( current_time( 'mysql' ) ) ), 'Starts at approval.' );
		$this->assertSame( 30 * DAY_IN_SECONDS, strtotime( $live->end_date ) - strtotime( $live->start_date ), 'Keeps the paid length.' );
	}

	public function test_submit_charges_the_package_once_and_refuses_short_balance(): void {
		$manager = Classified_Manager::get_instance();
		$before  = $this->balance();

		$listing = $manager->submit(
			$this->advertiser,
			array(
				'title'           => 'Shared path listing',
				'categories'      => array( $this->category() ),
				'listing_package' => 1, // Default "Standard": $5.
			)
		);
		$this->assertNotWPError( $listing );
		$this->assertSame( $before - 500, $this->balance() );

		\WBAM_Pro\Core\Credits_Bridge::charge( $this->advertiser->id, $this->balance() / 100, 0, 'drain', true, \WBAM_Pro\Core\Revenue_Ledger::SOURCE_UNCLASSIFIED );
		$short = $manager->submit(
			$this->advertiser,
			array(
				'title'           => 'Cannot afford',
				'categories'      => array( $this->category() ),
				'listing_package' => 1,
			)
		);
		$this->assertWPError( $short );
		$this->assertSame( 402, $short->get_error_data()['status'] );
		$this->assertArrayHasKey( 'purchase_url', $short->get_error_data() );
	}

	public function test_seller_renewal_charges_the_price_and_adds_the_listing_duration(): void {
		$settings                     = get_option( 'wbam_pro_classifieds_settings', array() );
		$settings['renewal_price']    = 2.5;
		$settings['listing_duration'] = 45;
		update_option( 'wbam_pro_classifieds_settings', $settings );

		$manager = Classified_Manager::get_instance();
		$listing = $manager->create(
			array(
				'title'         => 'Renew me',
				'advertiser_id' => $this->advertiser->id,
			)
		);
		$listing->status = 'active'; // Approved listing (create() applies moderation).
		$listing->save();
		$before  = $this->balance();

		$this->assertSame( 45, $manager->renew_by_seller( $listing->id, $this->advertiser->id ) );
		$this->assertSame( $before - 250, $this->balance(), 'Cents survive: $2.50, not $2.' );

		$manager->mark_sold( $listing->id );
		$this->assertWPError( $manager->renew_by_seller( $listing->id, $this->advertiser->id ) );
		$this->assertSame( $before - 250, $this->balance(), 'A refused renewal charges nothing.' );
	}

	public function test_an_expired_listing_stays_expired_and_can_be_renewed(): void {
		$manager = Classified_Manager::get_instance();
		$listing = $manager->create(
			array(
				'title'         => 'Expire me',
				'advertiser_id' => $this->advertiser->id,
			)
		);
		$listing->status = 'active';
		$listing->save();
		wp_update_post(
			array(
				'ID'          => $listing->post_id,
				'post_status' => 'publish',
			)
		);

		$this->assertTrue( $manager->expire( $listing->id ) );
		$this->assertSame( 'expired', $manager->get( $listing->id )->status, 'Unpublishing must not rewrite expired to draft.' );

		$this->assertTrue( $manager->renew( $listing->id, 30 ) );
		$this->assertSame( 'active', $manager->get( $listing->id )->status );
	}

	public function test_plan_featured_credits_are_spent_once_per_period(): void {
		$members = Membership_Manager::get_instance();
		$members->save_plan(
			array(
				'name'          => 'Featured plan',
				'price'         => 0,
				'billing_cycle' => 'monthly',
				'max_listings'  => 0,
				'max_featured'  => 1,
				'status'        => 'active',
			)
		);
		$plans = $members->get_plans();
		$plan  = end( $plans );
		$this->assertNotWPError( $members->subscribe( $this->advertiser->id, $plan->id ) );

		$this->assertSame( 1, $members->featured_credits_left( $this->advertiser->id ) );
		$this->assertTrue( $members->use_featured_credit( $this->advertiser->id ) );
		$this->assertSame( 0, $members->featured_credits_left( $this->advertiser->id ) );
		$this->assertFalse( $members->use_featured_credit( $this->advertiser->id ) );
	}
}
