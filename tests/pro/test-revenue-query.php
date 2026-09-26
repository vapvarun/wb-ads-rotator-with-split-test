<?php
/**
 * Revenue_Query — zero-fill, previous-period comparison, RPM, and cache
 * invalidation via the wp_cache_get_last_changed() incrementor idiom.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Core\Revenue_Query;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Revenue_Query extends Pro_Test_Case {

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

	public function test_series_zero_fills_days_with_no_activity(): void {
		$start = gmdate( 'Y-m-d', strtotime( '-4 days' ) );
		$end   = gmdate( 'Y-m-d' );

		// One charge, today only — the other 4 days in range have no rows.
		$charge = Credits_Bridge::charge( $this->advertiser->id, 10.00, 1, 'x', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertNotWPError( $charge );

		$series = Revenue_Query::series( $start, $end );

		$this->assertCount( 5, $series, 'series() must zero-fill every day in the range, not just days with rows.' );

		$today_bucket = end( $series );
		$this->assertSame( 10.0, $today_bucket['used'], 'A charge is credits used.' );
		$this->assertSame( 0.0, $today_bucket['net'], 'A charge is not revenue: revenue was counted at top-up.' );

		$empty_days = array_slice( $series, 0, 4 );
		foreach ( $empty_days as $bucket ) {
			$this->assertSame( 0.0, $bucket['used'], 'An empty day must report 0.0, not be omitted.' );
		}
	}

	public function test_report_computes_change_pct_against_previous_period(): void {
		$today     = gmdate( 'Y-m-d' );
		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		// Previous period (yesterday): $10 paid in.
		$prev_charge = Credits_Bridge::adjust( $this->advertiser->id, 10.00, 'prev', Revenue_Ledger::SOURCE_OFFLINE_PAYMENT );
		$this->assertNotWPError( $prev_charge );

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'wbam_revenue',
			array( 'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ) ),
			array( 'ledger_id' => (int) $prev_charge )
		);

		// Current period (today): $15 paid in — a 50% increase.
		$current_charge = Credits_Bridge::adjust( $this->advertiser->id, 15.00, 'current', Revenue_Ledger::SOURCE_OFFLINE_PAYMENT );
		$this->assertNotWPError( $current_charge );

		$report = Revenue_Query::report( $today, $today, $yesterday, $yesterday );

		$this->assertSame( 15.0, $report['totals']['net'] );
		$this->assertNotNull( $report['previous'] );
		$this->assertSame( 10.0, $report['previous']['net'] );
		$this->assertSame( 50.0, $report['change_pct'] );
	}

	public function test_change_pct_is_null_when_previous_period_is_zero(): void {
		$today = gmdate( 'Y-m-d' );

		$report = Revenue_Query::report( $today, $today, $today, $today );

		$this->assertNull( $report['change_pct'] );
	}

	public function test_rpm_is_null_with_no_impressions(): void {
		$today = gmdate( 'Y-m-d' );

		$charge = Credits_Bridge::charge( $this->advertiser->id, 10.00, 1, 'x', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertNotWPError( $charge );

		$this->assertNull( Revenue_Query::rpm( $today, $today ) );
	}

	public function test_rpm_divides_ad_revenue_by_impressions(): void {
		global $wpdb;

		$today = gmdate( 'Y-m-d' );

		$charge = Credits_Bridge::charge( $this->advertiser->id, 10.00, 1, 'x', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertNotWPError( $charge );

		$wpdb->insert(
			$wpdb->prefix . 'wbam_analytics_daily',
			array(
				'ad_id'       => 1,
				'date'        => $today,
				'impressions' => 1000,
			)
		);

		// $10 net over 1000 impressions -> RPM 10.
		$this->assertSame( 10.0, Revenue_Query::rpm( $today, $today ) );
	}

	public function test_totals_cache_invalidates_after_a_new_charge(): void {
		$start = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		$end   = gmdate( 'Y-m-d', strtotime( '+1 day' ) );

		$first = Revenue_Query::totals( $start, $end, (int) $this->advertiser->id );
		$this->assertSame( 0.0, $first['credits_used'] );

		$charge = Credits_Bridge::charge( $this->advertiser->id, 25.00, 1, 'x', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertNotWPError( $charge );

		$second = Revenue_Query::totals( $start, $end, (int) $this->advertiser->id );
		$this->assertSame( 25.0, $second['credits_used'], 'A new charge must bump wp_cache_get_last_changed() and invalidate the previous totals() cache entry.' );
	}
}
