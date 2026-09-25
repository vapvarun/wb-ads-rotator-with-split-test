<?php
/**
 * Base test case for pro tests. Skips the whole class if the pro plugin
 * was not loaded for this run (WBAM_RUN_PRO_TESTS != 1).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WP_UnitTestCase;

abstract class Pro_Test_Case extends WP_UnitTestCase {

	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		if ( getenv( 'WBAM_RUN_PRO_TESTS' ) !== '1' || ! defined( 'WBAM_PRO_VERSION' ) ) {
			self::markTestSkippedStatic();
		}
	}

	private static function markTestSkippedStatic(): void {
		// PHPUnit 9 allows static skip via a dummy method on class setup.
		throw new \PHPUnit\Framework\SkippedTestSuiteError( 'Pro tests require WBAM_RUN_PRO_TESTS=1 and the pro plugin installed.' );
	}

	public function set_up(): void {
		parent::set_up();
		self::flush_credits_balance_cache();
		self::truncate_credits_ledger();
		self::truncate_membership_tables();
	}

	public function tear_down(): void {
		self::flush_credits_balance_cache();
		parent::tear_down();
	}

	/**
	 * Empty the SDK ledger tables before each test.
	 *
	 * WP_UnitTestCase wraps each test in a transaction and rolls it back, which
	 * normally makes cleanup unnecessary. It does not work here: the SDK creates
	 * its tables lazily, and DDL causes an implicit COMMIT in MySQL - so the
	 * first test to trigger table creation commits the open transaction and
	 * every ledger row written from then on persists.
	 *
	 * Left alone the table accumulates across runs. It reached 40 rows summing
	 * to 656,565,646, which surfaced as balances like 252525248 where 250 was
	 * expected, and grew on every subsequent run. Nothing was wrong with the
	 * ledger; the tests were reading other tests' leftovers.
	 */
	protected static function truncate_credits_ledger(): void {
		global $wpdb;

		foreach ( array( '_credit_ledger', '_credit_gateway_log', '_credit_processed_events' ) as $suffix ) {
			$table = $wpdb->prefix . 'wbam' . $suffix;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test teardown on a known table name.
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				// DELETE rather than TRUNCATE: TRUNCATE is DDL and would itself
				// commit the surrounding transaction.
				$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
	}

	/**
	 * Empty the membership plan/subscription tables before each test.
	 *
	 * Same DDL-implicit-commit gotcha as truncate_credits_ledger(): both
	 * tables are created lazily via dbDelta on first use, and that CREATE
	 * TABLE commits the surrounding transaction, so a plan/subscription row
	 * written from that point on survives the test's own rollback. A test
	 * later in the same run (fresh advertiser, no subscription of its own)
	 * then inherits a leftover plan's cap - e.g. "You have reached your
	 * limit of 1 listings on the Capped plan plan" on an advertiser that
	 * never subscribed to anything.
	 */
	protected static function truncate_membership_tables(): void {
		global $wpdb;

		foreach ( array( 'wbam_subscriptions', 'wbam_membership_plans' ) as $table_name ) {
			$table = $wpdb->prefix . $table_name;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test teardown on a known table name.
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				// DELETE rather than TRUNCATE: TRUNCATE is DDL and would itself
				// commit the surrounding transaction.
				$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
	}

	/**
	 * Drop the SDK's per-request balance memo between tests.
	 *
	 * Credits::get_balance() memoises into a private static array. That array
	 * is PHP state, so it survives the DB rollback WP_UnitTestCase performs
	 * after each test - while the ledger rows behind it do not. User IDs are
	 * reused after a rollback, so the next test asking for "the balance of user
	 * 5" could be handed a figure computed from rows that no longer exist.
	 *
	 * It produced balances like 2525250 where 250 was expected, which reads as
	 * a broken ledger and is nothing of the kind - the SDK is correct, verified
	 * by driving the same topup/hold/deduct sequence against a live site and
	 * getting exactly 250.
	 *
	 * The SDK exposes no public flush (invalidate_cache() is private and
	 * per-user), so this reaches in. If it ever gains one, use that instead.
	 */
	protected static function flush_credits_balance_cache(): void {
		if ( ! class_exists( '\\Wbcom\\Credits\\Credits' ) ) {
			return;
		}

		try {
			$prop = new \ReflectionProperty( '\\Wbcom\\Credits\\Credits', 'balance_cache' );
			$prop->setAccessible( true );
			$prop->setValue( null, array() );
		} catch ( \ReflectionException $e ) {
			// Property renamed or removed upstream - nothing to flush.
			unset( $e );
		}
	}
}
