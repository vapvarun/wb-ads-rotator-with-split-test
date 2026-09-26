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

	/**
	 * The QA fixture scenario: a WP user row removed directly (not through
	 * wp_delete_user(), whose 'deleted_user' hook would also remove the
	 * advertiser row) leaves the advertiser row in place with no company
	 * name and no WP account to read a display name from.
	 */
	public function test_top_advertisers_labels_a_deleted_wp_user_by_id(): void {
		$today = gmdate( 'Y-m-d' );

		$charge = Credits_Bridge::adjust( $this->advertiser->id, 15.00, 'x', Revenue_Ledger::SOURCE_OFFLINE_PAYMENT );
		$this->assertNotWPError( $charge );

		global $wpdb;
		$wpdb->delete( $wpdb->users, array( 'ID' => $this->user ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test-only simulation of a row removed outside wp_delete_user().

		$rows = Revenue_Query::top_advertisers( $today, $today );

		$this->assertCount( 1, $rows );
		$this->assertSame( "Deleted user #{$this->user}", $rows[0]['name'] );
	}

	/**
	 * Same scenario on recent() — Recent Transactions' "Advertiser" column.
	 */
	public function test_recent_labels_a_deleted_wp_user_by_id(): void {
		$today = gmdate( 'Y-m-d' );

		$charge = Credits_Bridge::adjust( $this->advertiser->id, 15.00, 'x', Revenue_Ledger::SOURCE_OFFLINE_PAYMENT );
		$this->assertNotWPError( $charge );

		global $wpdb;
		$wpdb->delete( $wpdb->users, array( 'ID' => $this->user ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test-only simulation of a row removed outside wp_delete_user().

		$rows = Revenue_Query::recent( $today, $today );

		$this->assertCount( 1, $rows );
		$this->assertSame( "Deleted user #{$this->user}", $rows[0]['advertiser'] );
	}

	/**
	 * wp_delete_user() removes the advertiser row too; the ledger keeps only
	 * the advertiser id. Label it by that id instead of "Unknown".
	 */
	public function test_rows_of_a_deleted_advertiser_are_labelled_by_advertiser_id(): void {
		$today = gmdate( 'Y-m-d' );

		$charge = Credits_Bridge::adjust( $this->advertiser->id, 15.00, 'x', Revenue_Ledger::SOURCE_OFFLINE_PAYMENT );
		$this->assertNotWPError( $charge );

		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'wbam_advertisers', array( 'id' => $this->advertiser->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test-only: the advertiser row is gone.
		$wpdb->delete( $wpdb->users, array( 'ID' => $this->user ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test-only.

		$expected = "Deleted advertiser #{$this->advertiser->id}";
		$this->assertSame( $expected, Revenue_Query::top_advertisers( $today, $today )[0]['name'] );
		$this->assertSame( $expected, Revenue_Query::recent( $today, $today )[0]['advertiser'] );
	}

	/**
	 * A negative amount on a usage source (a listing/campaign/plan refund,
	 * which shares its charge's own source rather than getting a dedicated
	 * refund constant) is prefixed "Refund: "; the positive charge on the
	 * same source is not.
	 */
	public function test_recent_prefixes_a_usage_refund_but_not_its_charge(): void {
		$today = gmdate( 'Y-m-d' );

		$charge = Credits_Bridge::charge( $this->advertiser->id, 3.00, 501, 'listing', false, Revenue_Ledger::SOURCE_CLASSIFIED_LISTING );
		$this->assertNotWPError( $charge );
		$refund = Credits_Bridge::credit( $this->advertiser->id, 3.00, 501, 'listing refunded', Revenue_Ledger::SOURCE_CLASSIFIED_LISTING );
		$this->assertNotWPError( $refund );

		$rows = Revenue_Query::recent( $today, $today );
		$this->assertCount( 2, $rows );

		$by_amount = array();
		foreach ( $rows as $row ) {
			$by_amount[ $row['amount'] > 0 ? 'charge' : 'refund' ] = $row;
		}

		$this->assertSame( 'Classified listing', $by_amount['charge']['source_label'] );
		$this->assertSame( 'Refund: Classified listing', $by_amount['refund']['source_label'] );
	}

	/**
	 * by_placement(): an ad permanently deleted (its post gone, but the
	 * ledger row's item_id survives) is "Unattributed", never confused with
	 * an ad that genuinely runs in more than one placement.
	 */
	public function test_by_placement_labels_a_deleted_ad_as_unattributed(): void {
		$today = gmdate( 'Y-m-d' );

		$deleted_ad_id = 9999999; // Never created — nothing to resolve.
		$charge        = Credits_Bridge::charge( $this->advertiser->id, 10.00, $deleted_ad_id, 'x', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertNotWPError( $charge );

		$rows = Revenue_Query::by_placement( $today, $today );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'Unattributed (deleted ad or campaign)', $rows[0]['placement'] );
	}

	/**
	 * by_placement(): an ad that resolves and genuinely runs in more than
	 * one placement keeps the "Multiple placements" label.
	 */
	public function test_by_placement_keeps_multiple_placements_for_a_real_multi_placement_ad(): void {
		$today = gmdate( 'Y-m-d' );

		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $ad_id, '_wbam_placements', array( 'header', 'sidebar' ) );

		$charge = Credits_Bridge::charge( $this->advertiser->id, 10.00, $ad_id, 'x', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertNotWPError( $charge );

		$rows = Revenue_Query::by_placement( $today, $today );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'Multiple placements', $rows[0]['placement'] );
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
