<?php
/**
 * On a fresh install the emails promise only what the site can do.
 *
 * - "Wallet Credited" was on, yet an admin credit of $200 (Adjust Balance)
 *   sent nothing: the SDK's adjust path fires no top-up event.
 * - The welcome email of an application still pending review said "Your
 *   Account is Ready!".
 * - The approval email told the advertiser to add funds when the site has
 *   no way to buy credits.
 * - The ad-approved email suggested A/B testing, which advertisers cannot do.
 * - Every email ended "contact our support team" with no link.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Notifications\Email_Notifications;

class Test_Fresh_Install_Email_Copy extends Pro_Test_Case {

	private array $sent = array();
	private int $user;
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();
		$this->user       = (int) self::factory()->user->create();
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		$this->sent       = array();
		add_filter( 'pre_wp_mail', array( $this, 'capture' ), 10, 2 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture' ), 10 );
		parent::tear_down();
	}

	public function capture( $short_circuit, $atts ) {
		$this->sent[] = $atts;
		return true;
	}

	public function test_an_admin_credit_sends_the_credits_added_email(): void {
		Advertiser_Manager::get_instance()->adjust_balance( (int) $this->advertiser->id, 200, 'Welcome credit' );

		$this->assertCount( 1, $this->sent );
		$this->assertStringContainsString( 'Credits added', $this->sent[0]['subject'] );
		$this->assertStringContainsString( '+' . wbam_format_price( 200 ), $this->sent[0]['message'] );
	}

	public function test_an_admin_debit_sends_nothing(): void {
		Credits_Bridge::topup( (int) $this->advertiser->id, 50, 'Seed' );
		$this->sent = array();

		Advertiser_Manager::get_instance()->adjust_balance( (int) $this->advertiser->id, -20, 'Correction' );

		$this->assertCount( 0, $this->sent );
	}

	public function test_a_pending_application_is_not_told_its_account_is_ready(): void {
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'pending' );

		Email_Notifications::get_instance()->send_advertiser_welcome( $this->user, '' );

		$this->assertCount( 1, $this->sent );
		$this->assertStringNotContainsString( 'Your Account is Ready', $this->sent[0]['message'] );
		$this->assertStringContainsString( 'pending approval', $this->sent[0]['message'] );
	}

	public function test_approval_email_does_not_offer_funds_the_site_cannot_take(): void {
		add_filter( 'wbam_pro_can_purchase_credits', '__return_false' );
		$this->assertFalse( Credits_Bridge::can_purchase() );

		Email_Notifications::get_instance()->send_advertiser_approved( $this->advertiser );

		$this->assertStringNotContainsString( 'Add funds', $this->sent[0]['message'] );
	}

	public function test_ad_approved_does_not_suggest_ab_testing(): void {
		$html = Email_Notifications::get_template(
			'ad-approved',
			array(
				'advertiser' => $this->advertiser,
				'ad'         => get_post( self::factory()->post->create() ),
				'submission' => (object) array( 'reviewed_at' => current_time( 'mysql' ) ),
			)
		);

		$this->assertStringNotContainsString( 'A/B test', $html );
	}

	public function test_footer_support_line_links_to_a_contact_route(): void {
		$html = Email_Notifications::get_template( 'subscription-renewed', array( 'user_name' => 'Ann', 'plan_name' => 'Gold', 'renewal_date' => '' ) );

		$this->assertStringContainsString( 'href="' . esc_url( wbam_pro_get_invitation_contact_url() ) . '"', $html );
	}
}
