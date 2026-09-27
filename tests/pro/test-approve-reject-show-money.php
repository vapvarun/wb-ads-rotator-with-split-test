<?php
/**
 * Approve and reject always show the money: the review screen previews the
 * charge and the balance before/after, a short balance gets a specific
 * shortfall with the next step, and the notices and emails say what moved
 * (owner decision 9, card 10344382158).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;

class Test_Approve_Reject_Show_Money extends Pro_Test_Case {

	private int $user;
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();
		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );
		$this->advertiser = Advertiser_Manager::get_instance()->get( (int) $this->advertiser->id );
		$this->set_balance( 10000 );
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	private function set_balance( int $minor ): void {
		$now = (int) \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $this->user );
		if ( $minor !== $now ) {
			\Wbcom\Credits\Credits::adjust( 'wbam-pro', $this->user, $minor - $now, 'test' );
		}
		self::flush_credits_balance_cache();
	}

	/** A pending submission on a $49 flat package that needs review. */
	private function submission(): object {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'              => 'Money probe package',
				'price'             => 49.00,
				'pricing_model'     => 'flat',
				'requires_approval' => 1,
				'status'            => 'active',
			)
		);
		$submission = Ad_Submission_Manager::get_instance()->submit_ad(
			$this->advertiser->id,
			array(
				'title'     => 'Money probe',
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/x.png',
				'link_url'  => 'https://example.com',
			),
			(int) $wpdb->insert_id
		);
		$this->assertNotWPError( $submission );

		return $submission;
	}

	public function test_the_review_preview_shows_the_charge_and_both_balances(): void {
		$money = Ad_Submission_Manager::get_instance()->approval_money( $this->submission() );

		$this->assertSame( 'charge', $money['kind'] );
		$this->assertEqualsWithDelta( 49.0, $money['amount'], 0.001 );
		$this->assertEqualsWithDelta( 100.0, $money['balance'], 0.001 );
		$this->assertEqualsWithDelta( 51.0, $money['after'], 0.001 );
		$this->assertEqualsWithDelta( 0.0, $money['short'], 0.001 );
	}

	public function test_a_short_balance_names_the_shortfall_and_the_next_step(): void {
		$submission = $this->submission();
		$this->set_balance( 1000 );

		$result = Ad_Submission_Manager::get_instance()->approve( (int) $submission->id );

		$this->assertWPError( $result );
		$this->assertSame( 'insufficient_balance', $result->get_error_code() );
		$this->assertStringContainsString( 'has $10.00 and this ad needs $49.00, so they are short by $39.00', $result->get_error_message() );
		$this->assertStringContainsString( 'Ask them to add funds', $result->get_error_message() );
		$this->assertSame( 'pending', Ad_Submission_Manager::get_instance()->get( (int) $submission->id )->status );
	}

	public function test_approve_then_reject_say_what_moved(): void {
		$submission = $this->submission();
		$manager    = Ad_Submission_Manager::get_instance();

		$this->assertTrue( $manager->approve( (int) $submission->id ) );
		$this->assertSame( 'Charged $49.00. Balance now $51.00.', Ad_Submission_Manager::money_line( (int) $submission->id ) );

		$this->assertTrue( $manager->reject( (int) $submission->id, 'Taken down' ) );
		$this->assertSame( '$49.00 went back to the balance. Balance now $100.00.', Ad_Submission_Manager::money_line( (int) $submission->id ) );
	}

	public function test_rejecting_before_approval_says_nothing_was_charged(): void {
		$submission = $this->submission();

		$this->assertTrue( Ad_Submission_Manager::get_instance()->reject( (int) $submission->id, 'No' ) );
		$this->assertSame( 'Nothing was charged for this ad.', Ad_Submission_Manager::money_line( (int) $submission->id ) );
	}

	public function test_the_approved_email_carries_the_money_line(): void {
		$submission = $this->submission();
		$sent       = array();
		$capture    = static function ( $pre, $atts ) use ( &$sent ) {
			$sent[] = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $capture, 10, 2 );

		Ad_Submission_Manager::get_instance()->approve( (int) $submission->id );

		remove_filter( 'pre_wp_mail', $capture, 10 );
		$bodies = implode( "\n", wp_list_pluck( $sent, 'message' ) );
		$this->assertStringContainsString( 'Charged $49.00. Balance now $51.00.', $bodies );
	}

	public function test_the_reject_form_preview_matches_what_reject_returns(): void {
		$submission = $this->submission();
		$manager    = Ad_Submission_Manager::get_instance();

		$this->assertEqualsWithDelta( 0.0, $manager->rejection_refund( $submission ), 0.001, 'Nothing charged before approval.' );

		$manager->approve( (int) $submission->id );
		$this->assertEqualsWithDelta( 49.0, $manager->rejection_refund( $manager->get( (int) $submission->id ) ), 0.001 );
	}

	public function test_no_woocommerce_credit_products_means_no_currency_warning(): void {
		update_option( 'wbam-pro_credit_mappings', array() );

		$this->assertSame( '', \WBAM_Pro\Core\Credits_Bridge::woocommerce_currency_mismatch() );
	}
}
