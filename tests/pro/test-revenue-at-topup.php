<?php
/**
 * Revenue is counted once, at top-up (owner decision 2026-09-26).
 *
 * Paid top-ups and offline payments are revenue; spending credits is usage
 * ("Credits used"); complimentary credit is neither revenue nor a refund; a
 * cash refund of a top-up reduces revenue; an admin debit is not revenue.
 * QA repro: +$200 offline, $49 package, $25 complimentary read NET $224 and
 * REFUNDS $25 - expected NET $200.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Core\Revenue_Query;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Revenue_At_Topup extends Pro_Test_Case {

	private int $user;
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );

		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'wbam_revenue' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation.
	}

	private function totals(): array {
		return Revenue_Query::totals( gmdate( 'Y-m-d', strtotime( '-1 day' ) ), gmdate( 'Y-m-d', strtotime( '+1 day' ) ) );
	}

	public function test_offline_payment_then_package_is_counted_once(): void {
		$manager = Advertiser_Manager::get_instance();
		$this->assertTrue( $manager->adjust_balance( $this->advertiser->id, 200, 'Bank transfer', Revenue_Ledger::SOURCE_OFFLINE_PAYMENT ) );
		$this->assertNotWPError( Credits_Bridge::charge( $this->advertiser->id, 49, 7, 'Starter package', false, Revenue_Ledger::SOURCE_AD_PACKAGE ) );
		$this->assertTrue( $manager->adjust_balance( $this->advertiser->id, 25, 'Welcome gift', Revenue_Ledger::SOURCE_COMPLIMENTARY_CREDIT ) );
		$this->assertTrue( $manager->adjust_balance( $this->advertiser->id, -10, 'Admin debit adjustment' ) );

		$totals = $this->totals();
		$this->assertSame( 200.0, $totals['net'], 'Revenue is the $200 received, not $200 + the $49 spent from it.' );
		$this->assertSame( 200.0, $totals['gross'] );
		$this->assertSame( 0.0, $totals['refunds'], 'Complimentary credit is never a refund.' );
		$this->assertSame( 49.0, $totals['credits_used'] );
	}

	public function test_paid_topup_is_revenue_and_its_refund_reduces_it(): void {
		// Every adapter and gateway tops up through Credits::topup().
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 10000, 'Credits from WooCommerce order #41' );
		\Wbcom\Credits\Credits::topup_money( 'wbam-pro', $this->user, 30, '', 'gateway:stripe:cs_test_1' );

		$ledger_id = (int) \Wbcom\Credits\Credits::adjust_money( 'wbam-pro', $this->user, -30, '', 'gateway:stripe:refund:cs_test_1' );
		do_action( 'wbcom_credits_gateway_refund', 'wbam-pro', $this->user, 30, $ledger_id, 'stripe', 'cs_test_1' );

		$totals = $this->totals();
		$this->assertSame( 130.0, $totals['gross'] );
		$this->assertSame( 30.0, $totals['refunds'] );
		$this->assertSame( 100.0, $totals['net'] );
	}

	public function test_admin_grant_is_not_revenue(): void {
		Credits_Bridge::topup( $this->advertiser->id, 50, 'Initial credit balance set at advertiser creation' );

		$this->assertSame( 0.0, $this->totals()['net'] );
	}

	public function test_revenue_sources_filter(): void {
		Advertiser_Manager::get_instance()->adjust_balance( $this->advertiser->id, 100, 'Cash', Revenue_Ledger::SOURCE_OFFLINE_PAYMENT );

		add_filter(
			'wbam_pro_revenue_sources',
			static function () {
				return array( Revenue_Ledger::SOURCE_TOPUP );
			}
		);
		wp_cache_set_last_changed( Revenue_Ledger::CACHE_GROUP );

		$this->assertSame( 0.0, $this->totals()['net'] );
	}

	public function test_upgrade_books_existing_topups_and_refunds_once(): void {
		global $wpdb;

		// Ledger rows written before revenue moved to cash-in: no revenue row.
		$woo    = (int) \Wbcom\Credits\Ledger::insert( 'wbam', $this->user, 'topup', 1000, 0, 'Credits from WooCommerce order #55' );
		$gw     = (int) \Wbcom\Credits\Ledger::insert( 'wbam', $this->user, 'topup', 2500, 0, 'gateway:paypal:ORDER-1' );
		$rev    = (int) \Wbcom\Credits\Ledger::insert( 'wbam', $this->user, 'deduction', -1000, 0, 'Credits reversed: WooCommerce order #55 refunded or cancelled' );
		$manual = (int) \Wbcom\Credits\Ledger::insert( 'wbam', $this->user, 'topup', 4000, 0, 'Offline payment (bank transfer)' );

		$this->assertSame( 3, Revenue_Ledger::backfill_topups() );
		$this->assertSame( 0, Revenue_Ledger::backfill_topups(), 'A rerun adds nothing.' );

		$sources = $wpdb->get_results( 'SELECT ledger_id, source, amount FROM ' . Revenue_Ledger::table_name(), OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->assertSame( 'topup', $sources[ $woo ]->source );
		$this->assertSame( 'topup', $sources[ $gw ]->source );
		$this->assertSame( 'topup_refund', $sources[ $rev ]->source );
		$this->assertSame( '-1000', $sources[ $rev ]->amount );
		$this->assertArrayNotHasKey( $manual, $sources, 'A free-text admin grant is never guessed to be a payment.' );
		$this->assertSame( 25.0, $this->totals()['net'] );
	}
}
