<?php
/**
 * Credits_Bridge unit conversion delegates to the SDK Money helper.
 *
 * Regression guard for Basecamp card 10127901025: the bridge's private
 * to_minor()/to_major() predate the SDK's Money helper and duplicated
 * its logic. They now delegate to Money — these tests pin that the
 * adoption is behaviour-identical: cents survive exactly and a
 * charge/refund round-trips to the same balance. If a future SDK
 * bundle or refactor changes the scale, existing ledgers corrupt —
 * this suite fails first.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Credits_Money_Adoption extends Pro_Test_Case {

	public function test_charge_with_cents_writes_exact_minor_units(): void {
		global $wpdb;

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $user, 100000, 'seed' );

		$result = Credits_Bridge::charge( $advertiser->id, 147.35, 424242, 'cents-exactness', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertNotWPError( $result );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT amount FROM {$wpdb->prefix}wbam_credit_ledger WHERE user_id = %d AND item_id = 424242",
				$user
			)
		);
		$this->assertSame( -14735, (int) $row->amount, '147.35 must store as exactly -14735 minor units - not -14734 (float truncation) and not -147.' );
	}

	public function test_charge_and_refund_round_trip_to_the_same_balance(): void {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $user, 100000, 'seed' );
		$before = \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $user );

		Credits_Bridge::charge( $advertiser->id, 147.35, 424243, 'round-trip charge', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		Credits_Bridge::credit( $advertiser->id, 147.35, 424243, 'round-trip refund', Revenue_Ledger::SOURCE_AD_PACKAGE );

		$this->assertSame( $before, \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $user ) );
	}
}
