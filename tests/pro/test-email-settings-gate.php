<?php
/**
 * Settings > Emails is read: a notification switched off is not sent, every
 * email carries the configured From name/email, and account mail an owner
 * cannot safely switch off always goes out.
 *
 * The toggles and From fields were saved and never read by any sender.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Notifications\Email_Notifications;

class Test_Email_Settings_Gate extends Pro_Test_Case {

	private array $sent = array();
	private int $user;

	public function set_up(): void {
		parent::set_up();

		$this->user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->sent = array();
		add_filter( 'pre_wp_mail', array( $this, 'capture' ), 10, 2 );

		update_option(
			'wbam_pro_email_settings',
			array(
				'from_name'       => 'Ad Desk',
				'from_email'      => 'ads@example.org',
				'wallet_credited' => false,
				'ad_approved'     => false,
			)
		);
	}

	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture' ), 10 );
		delete_option( 'wbam_pro_email_settings' );
		parent::tear_down();
	}

	public function capture( $short_circuit, $atts ) {
		$this->sent[] = $atts;
		return true;
	}

	public function test_a_notification_switched_off_is_not_sent(): void {
		Email_Notifications::get_instance()->send_wallet_credit_notification( $this->user, 10, 'Top up' );

		$this->assertCount( 0, $this->sent );
	}

	public function test_a_notification_left_on_uses_the_configured_from(): void {
		Email_Notifications::get_instance()->send_advertiser_approved( Advertiser_Manager::get_instance()->get_or_create( $this->user ) );

		$this->assertCount( 1, $this->sent, 'Advertiser Approved defaults to on.' );
		$this->assertContains( 'From: Ad Desk <ads@example.org>', $this->sent[0]['headers'] );
	}

	public function test_advertiser_approved_and_rejected_respect_their_own_toggle(): void {
		update_option(
			'wbam_pro_email_settings',
			array(
				'advertiser_approved' => false,
				'advertiser_rejected' => false,
			)
		);
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );

		Email_Notifications::get_instance()->send_advertiser_approved( $advertiser );
		Email_Notifications::get_instance()->send_advertiser_rejected( $advertiser, 'Incomplete profile' );

		$this->assertCount( 0, $this->sent, 'Both toggles are off, so neither application-status email goes out.' );
	}

	public function test_deliver_honours_toggles_and_a_callers_content_type(): void {
		$this->assertFalse( Email_Notifications::deliver( 'a@example.org', 'Subject', 'Body', array(), 'ad_approved' ) );
		$this->assertTrue( Email_Notifications::deliver( 'a@example.org', 'Subject', 'Body', array( 'Content-Type: text/plain; charset=UTF-8' ) ) );
		$this->assertContains( 'Content-Type: text/plain; charset=UTF-8', $this->sent[0]['headers'] );
		$this->assertNotContains( 'Content-Type: text/html; charset=UTF-8', $this->sent[0]['headers'] );
	}
}
