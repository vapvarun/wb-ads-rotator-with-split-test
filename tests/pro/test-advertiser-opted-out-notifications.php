<?php
/**
 * BC#10339750662 item 7: three Profile tab notification checkboxes
 * (campaign_approved, low_balance, campaign_budget) saved into
 * notification_settings under those keys, but every sender checked a
 * different key ('ad_status', 'campaign_status') or none at all — so the
 * advertiser's choice was silently ignored and the toggle just did nothing.
 *
 * Advertiser::opted_out() is now the single gate every optional sender
 * reads, keyed off exactly the Profile form's own keys.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser;
use WBAM_Pro\Modules\Advertisers\Advertiser_Email_Notifications;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Notifications\Email_Notifications;

class Test_Advertiser_Opted_Out_Notifications extends Pro_Test_Case {

	private int $user;
	private Advertiser $advertiser;

	public function set_up(): void {
		parent::set_up();

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
	}

	/**
	 * Count outgoing mail without actually sending it. pre_wp_mail short-
	 * circuits wp_mail() when the filter returns anything other than null.
	 *
	 * @return array{0: callable, 1: \ArrayObject} Filter callback (for removal) and the running count.
	 */
	private function count_mail(): array {
		$count = new \ArrayObject( array( 'sent' => 0 ) );
		$cb    = function ( $short_circuit ) use ( $count ) {
			$count['sent']++;
			return true; // non-null -> wp_mail() returns early, no real send.
		};
		add_filter( 'pre_wp_mail', $cb, 10, 1 );
		return array( $cb, $count );
	}

	// -------------------------------------------------------------------
	// Advertiser::opted_out() — the gate itself, no mail involved.
	// -------------------------------------------------------------------

	public function test_opted_out_defaults_to_false_when_no_preference_saved(): void {
		$this->advertiser->notification_settings = array();

		foreach ( array( 'campaign_approved', 'low_balance', 'campaign_budget', 'weekly_report' ) as $type ) {
			$this->assertFalse( $this->advertiser->opted_out( $type ), "An unset '$type' preference must default to notify (not opted out)." );
		}
	}

	public function test_opted_out_reflects_an_explicit_false(): void {
		$this->advertiser->notification_settings = array( 'campaign_approved' => false );
		$this->assertTrue( $this->advertiser->opted_out( 'campaign_approved' ) );
	}

	public function test_opted_out_reflects_an_explicit_true(): void {
		$this->advertiser->notification_settings = array( 'campaign_approved' => true );
		$this->assertFalse( $this->advertiser->opted_out( 'campaign_approved' ) );
	}

	// -------------------------------------------------------------------
	// check_low_balance() — 'low_balance' gate, full send path.
	// -------------------------------------------------------------------

	public function test_low_balance_sender_skips_mail_when_opted_out(): void {
		$this->advertiser->notification_settings = array( 'low_balance' => false );
		$this->advertiser->save();

		list( $cb, $count ) = $this->count_mail();
		Advertiser_Email_Notifications::get_instance()->check_low_balance( $this->advertiser->id, 5.00, 'test debit' );
		remove_filter( 'pre_wp_mail', $cb, 10 );

		$this->assertSame( 0, $count['sent'], 'An advertiser who unchecked "Low wallet balance alerts" must get zero low-balance mail.' );
	}

	public function test_low_balance_sender_sends_mail_when_not_opted_out(): void {
		$this->advertiser->notification_settings = array( 'low_balance' => true );
		$this->advertiser->save();

		list( $cb, $count ) = $this->count_mail();
		Advertiser_Email_Notifications::get_instance()->check_low_balance( $this->advertiser->id, 5.00, 'test debit' );
		remove_filter( 'pre_wp_mail', $cb, 10 );

		$this->assertSame( 1, $count['sent'], 'An advertiser with the toggle on and a below-threshold balance must get exactly one mail.' );
	}

	// -------------------------------------------------------------------
	// campaign_budget_depleted() — 'campaign_budget' gate.
	// -------------------------------------------------------------------

	private function create_campaign(): int {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_campaigns',
			array(
				'advertiser_id' => $this->advertiser->id,
				'name'          => 'Test Campaign',
				'budget'        => 50.00,
				'spent'         => 50.00,
				'status'        => 'active',
			)
		);

		return (int) $wpdb->insert_id;
	}

	public function test_campaign_budget_depleted_skips_mail_when_opted_out(): void {
		$this->advertiser->notification_settings = array( 'campaign_budget' => false );
		$this->advertiser->save();
		$campaign_id = $this->create_campaign();

		list( $cb, $count ) = $this->count_mail();
		Advertiser_Email_Notifications::get_instance()->campaign_budget_depleted( $campaign_id, $this->advertiser );
		remove_filter( 'pre_wp_mail', $cb, 10 );

		$this->assertSame( 0, $count['sent'], 'An advertiser who unchecked "Campaign budget exhausted alerts" must get zero budget-depleted mail.' );
	}

	public function test_campaign_budget_depleted_sends_mail_when_not_opted_out(): void {
		$this->advertiser->notification_settings = array( 'campaign_budget' => true );
		$this->advertiser->save();
		$campaign_id = $this->create_campaign();

		list( $cb, $count ) = $this->count_mail();
		Advertiser_Email_Notifications::get_instance()->campaign_budget_depleted( $campaign_id, $this->advertiser );
		remove_filter( 'pre_wp_mail', $cb, 10 );

		$this->assertSame( 1, $count['sent'] );
	}

	// -------------------------------------------------------------------
	// Email_Notifications::send_low_balance_notification() — the SDK-bridge
	// sender (wbam_advertiser_low_balance), also gated by 'low_balance'.
	// -------------------------------------------------------------------

	public function test_bridge_low_balance_sender_skips_mail_when_opted_out(): void {
		$this->advertiser->notification_settings = array( 'low_balance' => false );
		$this->advertiser->save();

		list( $cb, $count ) = $this->count_mail();
		Email_Notifications::get_instance()->send_low_balance_notification( $this->advertiser, 5.00 );
		remove_filter( 'pre_wp_mail', $cb, 10 );

		$this->assertSame( 0, $count['sent'] );
	}

	public function test_bridge_low_balance_sender_sends_mail_when_not_opted_out(): void {
		$this->advertiser->notification_settings = array( 'low_balance' => true );
		$this->advertiser->save();

		list( $cb, $count ) = $this->count_mail();
		Email_Notifications::get_instance()->send_low_balance_notification( $this->advertiser, 5.00 );
		remove_filter( 'pre_wp_mail', $cb, 10 );

		$this->assertSame( 1, $count['sent'] );
	}
}
