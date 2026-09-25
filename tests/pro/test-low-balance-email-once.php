<?php
/**
 * Low balance emails go out once per 24h, triggered by a real debit.
 *
 * Advertiser_Email_Notifications::check_low_balance() (listening on
 * wbam_advertiser_balance_debited) and Notifications\Email_Notifications::
 * send_low_balance_notification() both emailed the same crossing. The first
 * listener was removed, but the survivor was fed only by the SDK's
 * wbcom_credits_low, which fires from Credits::hold() alone (never called
 * here) and compares cents to a dollar threshold, so no email went out.
 * Credits_Bridge::charge()'s debit hook is now the single trigger.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Notifications\Email_Notifications;

class Test_Low_Balance_Email_Once extends Pro_Test_Case {

	private int $user;
	private object $advertiser;
	private int $mail_count = 0;

	public function set_up(): void {
		parent::set_up();

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );

		delete_user_meta( $this->advertiser->user_id, '_wbam_low_balance_notified' );

		$this->mail_count = 0;
		add_filter( 'pre_wp_mail', array( $this, 'count_and_short_circuit' ), 10, 2 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'count_and_short_circuit' ), 10 );
		delete_user_meta( $this->advertiser->user_id, '_wbam_low_balance_notified' );
		parent::tear_down();
	}

	public function count_and_short_circuit( $short_circuit, $atts ) {
		++$this->mail_count;
		return true; // Prevent an actual send attempt.
	}

	public function test_duplicate_listener_is_removed(): void {
		$this->assertFalse(
			method_exists( '\\WBAM_Pro\\Modules\\Advertisers\\Advertiser_Email_Notifications', 'check_low_balance' ),
			'The duplicate low-balance listener must be removed, not just unhooked.'
		);
	}

	public function test_one_email_per_crossing_and_cooldown_holds(): void {
		do_action( 'wbam_advertiser_low_balance', (int) $this->advertiser->id, 5 );
		$this->assertSame( 1, $this->mail_count, 'First crossing must send exactly one email.' );

		// Same advertiser, still under threshold, fired again immediately
		// (e.g. a second debit within the same minute) - the 24h cooldown
		// on the surviving listener must swallow this one.
		do_action( 'wbam_advertiser_low_balance', (int) $this->advertiser->id, 4 );
		$this->assertSame( 1, $this->mail_count, 'A second crossing within 24h must not send a second email.' );
	}

	public function test_cooldown_expiry_allows_a_new_email(): void {
		Email_Notifications::get_instance()->send_low_balance_notification( (int) $this->advertiser->id, 5 );
		$this->assertSame( 1, $this->mail_count );

		// Simulate the cooldown having elapsed.
		update_user_meta( $this->advertiser->user_id, '_wbam_low_balance_notified', time() - ( 25 * HOUR_IN_SECONDS ) );

		Email_Notifications::get_instance()->send_low_balance_notification( (int) $this->advertiser->id, 5 );
		$this->assertSame( 2, $this->mail_count, 'A crossing after the cooldown window must email again.' );
	}

	/** Tops the advertiser up to $amount (major units) and zeroes the mail counter. */
	private function fund( float $amount ): void {
		$this->assertNotWPError( Credits_Bridge::topup( (int) $this->advertiser->id, $amount, 'seed' ) );
		$this->mail_count = 0;
	}

	public function test_debit_below_threshold_sends_one_email_per_24h(): void {
		$this->fund( 51 );

		// $51 - $49 Starter approval = $2, under the default $10 threshold.
		Credits_Bridge::charge( (int) $this->advertiser->id, 49, 0, 'Starter', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertSame( 1, $this->mail_count, 'A debit that leaves the balance under threshold must email.' );
		$this->assertNotEmpty( get_user_meta( $this->advertiser->user_id, '_wbam_low_balance_notified', true ) );

		Credits_Bridge::charge( (int) $this->advertiser->id, 1, 0, 'Another debit', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertSame( 1, $this->mail_count, 'A second debit within 24h must not email again.' );
	}

	public function test_debit_above_threshold_sends_nothing(): void {
		$this->fund( 100 );

		Credits_Bridge::charge( (int) $this->advertiser->id, 49, 0, 'Starter', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertSame( 0, $this->mail_count );
	}

	public function test_advertiser_opt_out_is_respected(): void {
		$this->fund( 51 );
		Advertiser_Manager::get_instance()->update(
			(int) $this->advertiser->id,
			array( 'notification_settings' => array( 'low_balance' => false ) )
		);

		Credits_Bridge::charge( (int) $this->advertiser->id, 49, 0, 'Starter', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertSame( 0, $this->mail_count, 'An advertiser who opted out must not get the email.' );
	}

	public function test_sdk_low_hook_is_not_a_second_trigger(): void {
		do_action( 'wbcom_credits_low', 'wbam-pro', $this->user, 200 );
		$this->assertSame( 0, $this->mail_count, 'Only the debit hook may trigger the email.' );
	}
}
