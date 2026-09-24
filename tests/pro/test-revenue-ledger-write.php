<?php
/**
 * Credits_Bridge::charge()/credit() write exactly one wbam_revenue row per
 * successful ledger insert, signed opposite the ledger row (a charge is
 * positive revenue, a credit/refund is negative revenue).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Revenue_Ledger_Write extends Pro_Test_Case {

	private int $user;
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 100000, 'seed' );

		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'wbam_revenue' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation on a known plugin table.
	}

	private function revenue_row_for_ledger( int $ledger_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . 'wbam_revenue WHERE ledger_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
				$ledger_id
			)
		);
	}

	public function test_charge_writes_a_positive_revenue_row(): void {
		$ledger_id = Credits_Bridge::charge( $this->advertiser->id, 49.00, 777, 'Package purchase: Starter', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertNotWPError( $ledger_id );

		$row = $this->revenue_row_for_ledger( (int) $ledger_id );
		$this->assertNotNull( $row, 'A charge must write a matching revenue row.' );
		$this->assertSame( (string) $this->advertiser->id, $row->advertiser_id );
		$this->assertSame( Revenue_Ledger::SOURCE_AD_PACKAGE, $row->source );
		$this->assertSame( 'ad', $row->item_type );
		$this->assertSame( '777', $row->item_id );
		$this->assertSame( '4900', $row->amount, 'A charge writes POSITIVE revenue even though the ledger row is negative.' );
	}

	public function test_credit_writes_a_negative_revenue_row(): void {
		$ledger_id = Credits_Bridge::credit( $this->advertiser->id, 10.00, 4321, 'Refund', Revenue_Ledger::SOURCE_CLASSIFIED_LISTING );
		$this->assertNotWPError( $ledger_id );

		$row = $this->revenue_row_for_ledger( (int) $ledger_id );
		$this->assertNotNull( $row, 'A credit must write a matching revenue row.' );
		$this->assertSame( Revenue_Ledger::SOURCE_CLASSIFIED_LISTING, $row->source );
		$this->assertSame( 'classified', $row->item_type );
		$this->assertSame( '-1000', $row->amount, 'A credit/refund writes NEGATIVE revenue even though the ledger row is positive.' );
	}

	public function test_empty_source_records_unclassified_and_warns(): void {
		$this->setExpectedIncorrectUsage( 'Credits_Bridge::charge()/credit()' );

		$ledger_id = Credits_Bridge::charge( $this->advertiser->id, 5.00, 1, 'no source given' );
		$this->assertNotWPError( $ledger_id );

		$row = $this->revenue_row_for_ledger( (int) $ledger_id );
		$this->assertSame( Revenue_Ledger::SOURCE_UNCLASSIFIED, $row->source );
		$this->assertSame( '', $row->item_type );
	}

	public function test_ledger_id_is_the_idempotency_key(): void {
		global $wpdb;

		$ledger_id = Credits_Bridge::charge( $this->advertiser->id, 1.00, 1, 'x', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertNotWPError( $ledger_id );

		// Calling record() again for the same ledger row must not duplicate it
		// (UNIQUE KEY ledger_id + INSERT IGNORE) — this is exactly what the
		// backfill relies on to be safely re-runnable.
		Revenue_Ledger::record( (int) $ledger_id, (int) $this->advertiser->id, Revenue_Ledger::SOURCE_AD_PACKAGE, 1, 100 );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wbam_revenue WHERE ledger_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $ledger_id
			)
		);
		$this->assertSame( 1, $count, 'A ledger_id must never produce more than one revenue row.' );
	}
}
