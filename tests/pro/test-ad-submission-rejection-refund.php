<?php
/**
 * Ad-submission rejection refunds only what was actually charged.
 *
 * Regression guard for Basecamp card 10166396087: submissions are
 * charged on APPROVAL, but reject() refunded today's package price
 * unconditionally - so every rejected-but-never-charged submission
 * minted the package price from nothing, repeatably (the
 * over-correction of card 9393412827, which reported paid rejections
 * getting NO refund). The refund now nets the ledger rows tied to the
 * submission's ad: unpaid nets zero, paid refunds the actual charge,
 * and a settled submission never refunds twice.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;

class Test_Ad_Submission_Rejection_Refund extends Pro_Test_Case {

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
				'name'          => 'Rejection Refund Flat',
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
				'title'     => 'Refund guard ad',
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/x.png',
				'link_url'  => 'https://example.com',
			),
			$this->package_id
		);
		$this->assertNotWPError( $submission );

		return $submission;
	}

	public function test_rejecting_an_uncharged_submission_mints_nothing(): void {
		$submission = $this->submit();
		$before     = \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $this->user );

		Ad_Submission_Manager::get_instance()->reject( (int) $submission->id, 'guard' );

		$this->assertSame(
			$before,
			\Wbcom\Credits\Credits::get_balance( 'wbam-pro', $this->user ),
			'Submissions are charged on approval; rejecting an uncharged one must not create credits from nothing.'
		);
	}

	public function test_reject_cycles_do_not_accumulate(): void {
		$start = \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $this->user );

		for ( $i = 0; $i < 3; $i++ ) {
			$submission = $this->submit();
			Ad_Submission_Manager::get_instance()->reject( (int) $submission->id, 'guard cycle' );
		}

		$this->assertSame( $start, \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $this->user ), 'QA proved +4,900 minor units per cycle; cycles must net zero.' );
	}

	public function test_charged_submission_refunds_the_actual_charge(): void {
		$submission = $this->submit();
		Credits_Bridge::charge( $this->advertiser->id, 49.00, (int) $submission->ad_id, 'Package purchase: Rejection Refund Flat', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		$charged = \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $this->user );

		Ad_Submission_Manager::get_instance()->reject( (int) $submission->id, 'guard paid' );

		$this->assertSame( $charged + 4900, \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $this->user ), 'A genuinely charged submission still refunds in full - card 9393412827 must not regress.' );
	}
}
