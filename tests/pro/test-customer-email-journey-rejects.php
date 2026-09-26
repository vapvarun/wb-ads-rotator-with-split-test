<?php
/**
 * Customer emails describe the event (card 10342623123, journey QA).
 *
 * - A complimentary credit is not a "Credits added successfully" purchase.
 * - "Promote your classified listings" only when there is a listing to promote.
 * - Reply-To survives a comma in the display name (wp_mail() splits on it).
 * - A REST rejection passes its reason to the applicant.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Notifications\Email_Notifications;

class Test_Customer_Email_Journey_Rejects extends Pro_Test_Case {

	private array $sent = array();
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();
		$this->sent = array();
		add_filter(
			'pre_wp_mail',
			function ( $short, $atts ) {
				$this->sent[] = $atts;
				return true;
			},
			10,
			2
		);

		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_wp_mail' );
		parent::tear_down();
	}

	private function last_mail(): array {
		$this->assertNotEmpty( $this->sent );
		return end( $this->sent );
	}

	public function test_complimentary_credit_has_its_own_wording(): void {
		Advertiser_Manager::get_instance()->adjust_balance( $this->advertiser->id, 25, 'Welcome gift', Revenue_Ledger::SOURCE_COMPLIMENTARY_CREDIT );
		$mail = $this->last_mail();

		$this->assertStringContainsString( 'complimentary credit', $mail['subject'] );
		$this->assertStringNotContainsString( 'Credits added successfully', $mail['subject'] );
		$this->assertStringContainsString( 'nothing to pay', $mail['message'] );
		$this->assertStringNotContainsString( 'Promote your classified listings', $mail['message'], 'No live listing, nothing to promote.' );

		Advertiser_Manager::get_instance()->adjust_balance( $this->advertiser->id, 50, 'Bank transfer', Revenue_Ledger::SOURCE_OFFLINE_PAYMENT );
		$this->assertStringContainsString( 'Credits added successfully', $this->last_mail()['subject'] );
	}

	public function test_reply_to_keeps_a_name_with_a_comma(): void {
		Email_Notifications::get_instance()->send_inquiry_to_seller(
			'seller@example.test',
			new class() {
				public function get_title() {
					return 'Blue bike';
				}
			},
			array(
				'name'    => 'Smith, John',
				'email'   => 'john@example.test',
				'message' => 'Still available?',
			)
		);

		$this->assertContains( 'Reply-To: "Smith John" <john@example.test>', $this->last_mail()['headers'] );
	}

	public function test_rest_rejection_sends_its_reason(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'pending' );

		$reasons = array();
		add_action(
			'wbam_advertiser_rejected',
			static function ( $advertiser, $reason ) use ( &$reasons ) {
				$reasons[] = $reason;
			},
			5,
			2
		);

		$request = new \WP_REST_Request( 'PUT', '/wbam-pro/v1/admin/advertisers/' . (int) $this->advertiser->id );
		$request->set_body_params(
			array(
				'status' => 'suspended',
				'reason' => 'Your website link is broken.',
			)
		);
		rest_get_server()->dispatch( $request );

		$this->assertSame( array( 'Your website link is broken.' ), $reasons );
	}
}
