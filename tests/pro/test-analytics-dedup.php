<?php
/**
 * One-time cleanup of duplicate `wbam_analytics` rows left by the old
 * "more than one writer records the same event" bug.
 *
 * Regression guard for Basecamp card 10342784882, steps 3 and 4.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Analytics_Dedup;
use WBAM_Pro\Core\Cron_Manager;

class Test_Analytics_Dedup extends Pro_Test_Case {

	private int $ad_id;

	public function set_up(): void {
		parent::set_up();

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wbam_analytics" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wbam_analytics_daily" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wbam_revenue" );

		delete_option( Analytics_Dedup::OPTION );

		$this->ad_id = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
	}

	public function tear_down(): void {
		delete_option( Analytics_Dedup::OPTION );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $this->lock_name() ) );
		parent::tear_down();
	}

	private function lock_name(): string {
		global $wpdb;
		return substr( $wpdb->prefix . 'wbam_cron_wbam_pro_analytics_dedup', 0, 64 );
	}

	/**
	 * Insert an analytics row directly - bypassing the tracker entirely, the
	 * same way the duplicate rows this migration cleans up were written by
	 * two different code paths.
	 */
	private function insert_row( string $event_type, string $created_at, string $identity, ?int $campaign_id = null ): int {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_analytics',
			array(
				'ad_id'       => $this->ad_id,
				'campaign_id' => $campaign_id,
				'event_type'  => $event_type,
				'ip_hash'     => $identity,
				'created_at'  => $created_at,
			)
		);

		return (int) $wpdb->insert_id;
	}

	private function run_dedup(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$max_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->prefix}wbam_analytics" );

		update_option(
			Analytics_Dedup::OPTION,
			array(
				'cursor' => 0,
				'max_id' => $max_id,
			)
		);

		Analytics_Dedup::run_batch();
	}

	private function analytics_row_count(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wbam_analytics WHERE ad_id = {$this->ad_id}" );
	}

	public function test_exact_duplicate_rows_are_removed_keeping_the_lowest_id(): void {
		$keep = $this->insert_row( 'impression', '2026-01-05 10:00:00', 'visitor-a', 7 );
		$this->insert_row( 'impression', '2026-01-05 10:00:00', 'visitor-a', 7 );
		$this->insert_row( 'impression', '2026-01-05 10:00:00', 'visitor-a', 7 );

		// A different visitor, same second: must never be treated as a
		// duplicate of the group above.
		$other_visitor = $this->insert_row( 'impression', '2026-01-05 10:00:00', 'visitor-b', 7 );

		// A real click a moment later: untouched, different event_type.
		$click = $this->insert_row( 'click', '2026-01-05 10:00:05', 'visitor-a', 7 );

		$this->run_dedup();

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$remaining_ids = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}wbam_analytics WHERE ad_id = {$this->ad_id} ORDER BY id" );

		$this->assertSame(
			array( $keep, $other_visitor, $click ),
			array_map( 'intval', $remaining_ids ),
			'Only the two extra copies of the visitor-a impression must be removed.'
		);
	}

	public function test_daily_rollup_row_is_recomputed_after_cleanup(): void {
		global $wpdb;

		$this->insert_row( 'impression', '2026-01-05 10:00:00', 'visitor-a', 7 );
		$this->insert_row( 'impression', '2026-01-05 10:00:00', 'visitor-a', 7 );
		$this->insert_row( 'impression', '2026-01-05 10:00:00', 'visitor-a', 7 );

		// Pre-existing (already-rolled-up) daily row still carrying the
		// inflated pre-cleanup count.
		$wpdb->insert(
			$wpdb->prefix . 'wbam_analytics_daily',
			array(
				'ad_id'              => $this->ad_id,
				'campaign_id'        => 7,
				'date'               => '2026-01-05',
				'impressions'        => 3,
				'clicks'             => 0,
				'unique_impressions' => 1,
				'unique_clicks'      => 0,
			)
		);

		$this->run_dedup();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wbam_analytics_daily WHERE ad_id = %d AND date = %s",
				$this->ad_id,
				'2026-01-05'
			)
		);

		$this->assertSame( '1', $row->impressions, 'The daily total must reflect the deduplicated raw count, not the pre-cleanup one.' );
		$this->assertSame( '1', $row->unique_impressions );
	}

	public function test_billing_tables_are_never_touched(): void {
		global $wpdb;

		$this->insert_row( 'impression', '2026-01-05 10:00:00', 'visitor-a', 7 );
		$this->insert_row( 'impression', '2026-01-05 10:00:00', 'visitor-a', 7 );

		$wpdb->insert(
			$wpdb->prefix . 'wbam_revenue',
			array(
				'ledger_id'     => 999001,
				'advertiser_id' => 1,
				'source'        => 'campaign_reserve',
				'item_type'     => 'campaign',
				'item_id'       => 7,
				'amount'        => 5000,
				'created_at'    => '2026-01-05 10:00:00',
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wbam_revenue" );

		$this->run_dedup();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wbam_revenue" );

		$this->assertSame( $before, $after, 'The duplicate-analytics cleanup must never touch wbam_revenue.' );
	}

	public function test_running_the_same_batch_twice_is_a_no_op(): void {
		$this->insert_row( 'impression', '2026-01-05 10:00:00', 'visitor-a', 7 );
		$this->insert_row( 'impression', '2026-01-05 10:00:00', 'visitor-a', 7 );

		$this->run_dedup();
		$after_first = $this->analytics_row_count();

		// Re-run the exact same batch again (as a retried cron tick would).
		$this->run_dedup();
		$after_second = $this->analytics_row_count();

		$this->assertSame( 1, $after_first );
		$this->assertSame( $after_first, $after_second, 'Re-running the cleanup must not delete anything further.' );
	}

	/**
	 * Two ticks must never run concurrently - one holding the lock must
	 * block the other, the same guarantee Cron_Manager::run_with_lock()
	 * gives every other cron job in this plugin. GET_LOCK() is per-
	 * connection, so acquiring it through Cron_Manager (which always uses
	 * the global $wpdb) would just re-grant it to this same connection - a
	 * genuinely separate connection plays "the other worker", the same
	 * technique test-credits-charge-concurrency.php uses.
	 */
	public function test_a_second_runner_is_blocked_while_the_lock_is_held(): void {
		global $wpdb;

		$this->insert_row( 'impression', '2026-01-05 10:00:00', 'visitor-a', 7 );
		$this->insert_row( 'impression', '2026-01-05 10:00:00', 'visitor-a', 7 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$max_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->prefix}wbam_analytics" );
		update_option( Analytics_Dedup::OPTION, array( 'cursor' => 0, 'max_id' => $max_id ) );

		// Simulate another worker already running this hook.
		$other = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$acquired = $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s, 0)', $this->lock_name() ) );
		$this->assertSame( '1', (string) $acquired, 'Precondition: the other connection must hold the lock itself.' );

		Cron_Manager::get_instance()->run_analytics_dedup_batch();

		$this->assertSame( 2, $this->analytics_row_count(), 'A runner that could not acquire the lock must not touch the table.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$other->query( $other->prepare( 'SELECT RELEASE_LOCK(%s)', $this->lock_name() ) );
		$other->close();

		Cron_Manager::get_instance()->run_analytics_dedup_batch();

		$this->assertSame( 1, $this->analytics_row_count(), 'Once the lock is free, the batch runs normally.' );
	}
}
