<?php
/**
 * Regression for BC#10339874175 item 2: the admin "Adjust Balance" flow
 * (Credits_Bridge::topup()/adjust()) wrote a ledger row but never a revenue
 * row, so the Revenue screen's "Other" tile and Net revenue never moved even
 * though a transaction plainly happened.
 *
 * Both are recorded as `unclassified` (the Revenue screen's "Other" tile),
 * signed the same way charge()/credit() already are: the revenue row is the
 * ledger row's own signed delta, flipped — see the docblocks on topup() and
 * adjust().
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Credits_Bridge_Manual_Adjustment_Revenue extends Pro_Test_Case {

	private int $user;
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 100000, 'seed' );

		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'wbam_revenue' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation.
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

	public function test_topup_writes_a_negative_unclassified_revenue_row(): void {
		$ledger_id = Credits_Bridge::topup( $this->advertiser->id, 1.00, 'QA Adjust Balance +1' );
		$this->assertNotWPError( $ledger_id );

		$row = $this->revenue_row_for_ledger( (int) $ledger_id );
		$this->assertNotNull( $row, 'A manual top-up must write a matching revenue row (BC#10339874175 item 2).' );
		$this->assertSame( Revenue_Ledger::SOURCE_UNCLASSIFIED, $row->source, 'A manual grant belongs under Other, not a real ad/listing/plan source.' );
		$this->assertSame( '-100', $row->amount, 'A courtesy grant is negative revenue (no cash received), same sign rule as credit().' );
	}

	public function test_adjust_positive_writes_negative_revenue_row(): void {
		$ledger_id = Credits_Bridge::adjust( $this->advertiser->id, 2.50, 'QA Adjust Balance +2.50' );
		$this->assertNotWPError( $ledger_id );

		$row = $this->revenue_row_for_ledger( (int) $ledger_id );
		$this->assertNotNull( $row );
		$this->assertSame( Revenue_Ledger::SOURCE_UNCLASSIFIED, $row->source );
		$this->assertSame( '-250', $row->amount );
	}

	public function test_adjust_negative_writes_positive_revenue_row(): void {
		$ledger_id = Credits_Bridge::adjust( $this->advertiser->id, -2.50, 'QA Adjust Balance -2.50' );
		$this->assertNotWPError( $ledger_id );

		$row = $this->revenue_row_for_ledger( (int) $ledger_id );
		$this->assertNotNull( $row );
		$this->assertSame( Revenue_Ledger::SOURCE_UNCLASSIFIED, $row->source );
		$this->assertSame( '250', $row->amount, 'Taking credits back is positive revenue, opposite of a grant.' );
	}

	public function test_manual_adjustments_are_idempotent_per_ledger_row(): void {
		global $wpdb;

		$ledger_id = Credits_Bridge::topup( $this->advertiser->id, 1.00, 'x' );
		$this->assertNotWPError( $ledger_id );

		// Same idempotency guarantee charge()/credit() rely on: UNIQUE KEY on
		// ledger_id + INSERT IGNORE.
		Revenue_Ledger::record( (int) $ledger_id, (int) $this->advertiser->id, Revenue_Ledger::SOURCE_UNCLASSIFIED, 0, -100 );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wbam_revenue WHERE ledger_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $ledger_id
			)
		);
		$this->assertSame( 1, $count );
	}
}
