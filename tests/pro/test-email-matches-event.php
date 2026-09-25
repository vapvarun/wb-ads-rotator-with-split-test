<?php
/**
 * Each email describes the event that actually happened.
 *
 * - admin-new-advertiser said "requires review" for an account that
 *   auto-approve had already made active.
 * - A campaign that spent its budget sent two emails: "completed" and a
 *   "budget depleted" one claiming it was paused and could be resumed.
 * - subscription-expired blamed the wallet balance even when the member
 *   had cancelled.
 * - campaign-started printed the time the email was sent, not the start date.
 * - A listing published without review got its own "live" template and its
 *   seller's followers were never told (they only heard on manual approval).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Memberships\Membership_Manager;
use WBAM_Pro\Modules\Notifications\Email_Notifications;

class Test_Email_Matches_Event extends Pro_Test_Case {

	private array $sent = array();
	private int $user;
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();
		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );
		$this->advertiser = Advertiser_Manager::get_instance()->get( (int) $this->advertiser->id );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 100000, 'seed' );

		$this->sent = array();
		add_filter( 'pre_wp_mail', array( $this, 'capture' ), 10, 2 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture' ), 10 );
		remove_filter( 'wbam_campaign_has_linked_ads', '__return_true' );
		parent::tear_down();
	}

	public function capture( $short_circuit, $atts ) {
		$this->sent[] = $atts;
		return true;
	}

	public function test_admin_is_not_asked_to_review_an_auto_approved_advertiser(): void {
		Email_Notifications::get_instance()->send_admin_new_advertiser( $this->user, $this->advertiser );

		$this->assertCount( 1, $this->sent );
		$this->assertStringNotContainsString( 'requires review', $this->sent[0]['message'] );
		$this->assertStringContainsString( 'approved automatically', $this->sent[0]['message'] );
	}

	public function test_a_campaign_that_spends_its_budget_sends_one_accurate_email(): void {
		add_filter( 'wbam_campaign_has_linked_ads', '__return_true' );
		$campaign                = new Campaign();
		$campaign->advertiser_id = (int) $this->advertiser->id;
		$campaign->name          = 'Spent campaign';
		$campaign->budget        = 10;
		$campaign->status        = 'active';
		$campaign->save();
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'wbam_campaigns', array( 'spent' => 10 ), array( 'id' => $campaign->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$this->sent = array();
		Campaign_Manager::get_instance()->check_all_budgets();

		$this->assertCount( 1, $this->sent, 'One event, one email: ' . implode( ' | ', array_column( $this->sent, 'subject' ) ) );
		$this->assertStringContainsString( 'budget', strtolower( $this->sent[0]['subject'] ) );
		$this->assertStringNotContainsString( 'paused', $this->sent[0]['message'] );
		$this->assertStringContainsString( 'has ended', $this->sent[0]['message'] );
	}

	public function test_expiry_after_a_cancellation_does_not_blame_the_balance(): void {
		$memberships = Membership_Manager::get_instance();
		$plan_id     = $memberships->save_plan(
			array(
				'name'  => 'Gold',
				'price' => 5,
			)
		);
		$sub_id      = $memberships->subscribe( (int) $this->advertiser->id, (int) $plan_id );
		$this->assertIsInt( $sub_id );
		$memberships->cancel_subscription( $sub_id );

		$this->sent = array();
		Email_Notifications::get_instance()->send_subscription_expired( $sub_id, (int) $this->advertiser->id );

		$this->assertCount( 1, $this->sent );
		$this->assertStringNotContainsString( 'wallet balance', $this->sent[0]['message'] );
		$this->assertStringContainsString( 'cancelled', $this->sent[0]['message'] );
	}

	public function test_campaign_started_shows_the_start_date(): void {
		$campaign             = new Campaign();
		$campaign->name       = 'Dated campaign';
		$campaign->start_date = '2026-01-15 09:30:00';

		$html = Email_Notifications::get_template(
			'campaign-started',
			array(
				'user'     => get_user_by( 'id', $this->user ),
				'campaign' => $campaign,
			)
		);

		$this->assertStringContainsString( date_i18n( get_option( 'date_format' ), strtotime( '2026-01-15 09:30:00' ) ), $html );
	}

	public function test_a_listing_published_without_review_tells_seller_and_followers_once(): void {
		$settings                     = get_option( 'wbam_pro_classifieds_settings', array() );
		$settings['require_approval'] = false;
		update_option( 'wbam_pro_classifieds_settings', $settings );

		$follower = (int) self::factory()->user->create();
		$this->advertiser->toggle_follow( $follower );

		$term       = wp_insert_term( 'Live ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );
		$this->sent = array();
		$classified = Classified_Manager::get_instance()->submit(
			$this->advertiser,
			array(
				'title'      => 'Red bike',
				'categories' => array( (int) $term['term_id'] ),
			)
		);
		$this->assertNotWPError( $classified );
		$this->assertSame( 'active', Classified_Manager::get_instance()->get( (int) $classified->id )->status );

		$to = array_column( $this->sent, 'to' );
		$this->assertContains( get_user_by( 'id', $follower )->user_email, $to, 'Followers hear about an auto-published listing.' );

		$seller = array_values( array_filter( $this->sent, fn( $m ) => get_user_by( 'id', $this->user )->user_email === $m['to'] ) );
		$this->assertCount( 1, $seller, 'The seller gets one "live" email.' );
		$approved = Email_Notifications::get_template(
			'classified-approved',
			array(
				'advertiser' => $this->advertiser,
				'classified' => Classified_Manager::get_instance()->get( (int) $classified->id ),
			)
		);
		$this->assertSame( wp_strip_all_tags( $approved ), wp_strip_all_tags( $seller[0]['message'] ), 'Same template as a manual approval.' );
	}
}
