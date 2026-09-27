<?php
/**
 * Dates: store UTC, show and read in the site's time zone
 * (docs/standards/dates.md, owner decision 2026-09-27, card 10344269919).
 *
 * Every test runs with the site on Asia/Kolkata (+05:30), far enough from
 * UTC that a missing conversion lands on the wrong day.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;

class Test_Dates_Utc extends WP_UnitTestCase {

	/** @var array{0:mixed,1:mixed} The site's zone before the test. */
	private $saved_zone;

	public function set_up(): void {
		parent::set_up();
		$this->saved_zone = array( get_option( 'timezone_string' ), get_option( 'gmt_offset' ) );
		update_option( 'timezone_string', 'Asia/Kolkata' );
		update_option( 'gmt_offset', '' );
	}

	public function test_a_stored_moment_is_shown_in_the_site_zone(): void {
		// 20:00 UTC is 01:30 the next day in Kolkata.
		$this->assertSame( '2026-09-28 01:30', wbam_format_datetime( '2026-09-27 20:00:00', 'Y-m-d H:i' ) );
		$this->assertSame( '', wbam_format_datetime( '' ) );
		$this->assertSame( '', wbam_format_datetime( '0000-00-00 00:00:00' ) );
	}

	public function test_a_calendar_day_is_never_shifted(): void {
		$this->assertSame( '2026-09-27', wbam_format_day( '2026-09-27', 'Y-m-d' ) );
		update_option( 'timezone_string', 'America/Los_Angeles' );
		$this->assertSame( '2026-09-27', wbam_format_day( '2026-09-27', 'Y-m-d' ) );
	}

	public function test_a_picked_time_is_stored_in_utc(): void {
		$this->assertSame( '2026-09-27 04:30:00', wbam_site_to_utc( '2026-09-27 10:00' ) );
		$this->assertSame( '2026-09-27 04:30:00', wbam_site_to_utc( '2026-09-27T10:00' ) );
		// A bare day is the site day's start, or its last second.
		$this->assertSame( '2026-09-26 18:30:00', wbam_site_to_utc( '2026-09-27' ) );
		$this->assertSame( '2026-09-27 18:29:59', wbam_site_to_utc( '2026-09-27', true ) );
		$this->assertNull( wbam_site_to_utc( '' ) );
		$this->assertNull( wbam_site_to_utc( 'not a date' ) );
	}

	public function test_site_days_query_utc_bounds(): void {
		$this->assertSame( array( '2026-09-26 18:30:00', '2026-09-27 18:29:59' ), wbam_site_day_utc_bounds( '2026-09-27', '2026-09-27' ) );
		$this->assertSame( 'DATE(created_at + INTERVAL 19800 SECOND)', wbam_sql_site_date( 'created_at' ) );
	}

	public function test_today_starts_at_the_site_midnight(): void {
		$today = wbam_period_start( 'today' );
		$this->assertSame( wp_date( 'Y-m-d' ), $today['day'] );
		$this->assertSame( wbam_site_to_utc( wp_date( 'Y-m-d' ) ), $today['utc'] );
		$this->assertNull( wbam_period_start( 'all' ) );
	}

	public function test_an_impression_is_counted_on_its_site_day(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wbam_analytics';
		// 20:00 UTC on the 27th is the 28th in Kolkata.
		$wpdb->insert(
			$table,
			array(
				'ad_id'      => 424242,
				'event_type' => 'impression',
				'created_at' => '2026-09-27 20:00:00',
			)
		);
		$day = $wpdb->get_var( $wpdb->prepare( 'SELECT ' . wbam_sql_site_date( 'created_at' ) . " FROM {$table} WHERE ad_id = %d", 424242 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->assertSame( '2026-09-28', $day );
	}

	public function test_the_formatter_class_follows_the_rule(): void {
		$this->assertSame( '2026-09-28 01:30', \WBAM\Core\Formatter::datetime( '2026-09-27 20:00:00', 'Y-m-d H:i' ) );
		$this->assertSame( '2026-09-27', \WBAM\Core\Formatter::date( '2026-09-27', 'Y-m-d' ) );
	}

	public function test_conversion_shifts_each_clock_once(): void {
		global $wpdb;
		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wbam_utc_probe ( id bigint(20) unsigned NOT NULL AUTO_INCREMENT, local_at datetime DEFAULT NULL, server_at datetime DEFAULT NULL, PRIMARY KEY (id) )" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->insert(
			$wpdb->prefix . 'wbam_utc_probe',
			array(
				'local_at'  => '2026-09-27 10:00:00',
				'server_at' => '2026-09-27 10:00:00',
			)
		);
		$server_offset = (int) $wpdb->get_var( 'SELECT TIMESTAMPDIFF( SECOND, UTC_TIMESTAMP(), NOW() )' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$plan          = array(
			'wbam_utc_probe' => array(
				'local_at'  => 'local',
				'server_at' => 'server',
			),
		);
		delete_option( 'wbam_utc_probe_state' );

		$this->assertFalse( wbam_convert_columns_to_utc( $plan, 'wbam_utc_probe_state' ) );
		wbam_convert_columns_to_utc( $plan, 'wbam_utc_probe_state' ); // A second run changes nothing.

		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}wbam_utc_probe" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->assertSame( '2026-09-27 04:30:00', $row->local_at, 'Site-local 10:00 in Kolkata is 04:30 UTC.' );
		$this->assertSame( gmdate( 'Y-m-d H:i:s', strtotime( '2026-09-27 10:00:00 UTC' ) - $server_offset ), $row->server_at, 'Server-clock values shift by the MySQL offset.' );

		$wpdb->query( "DROP TABLE {$wpdb->prefix}wbam_utc_probe" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	public function test_daylight_saving_zones_convert_each_row_by_its_own_offset(): void {
		global $wpdb;
		update_option( 'timezone_string', 'America/New_York' );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wbam_utc_probe ( id bigint(20) unsigned NOT NULL AUTO_INCREMENT, local_at datetime DEFAULT NULL, PRIMARY KEY (id) )" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->insert( $wpdb->prefix . 'wbam_utc_probe', array( 'local_at' => '2026-01-15 10:00:00' ) ); // EST, -05:00.
		$wpdb->insert( $wpdb->prefix . 'wbam_utc_probe', array( 'local_at' => '2026-07-15 10:00:00' ) ); // EDT, -04:00.
		delete_option( 'wbam_utc_probe_state' );

		wbam_convert_columns_to_utc( array( 'wbam_utc_probe' => array( 'local_at' => 'local' ) ), 'wbam_utc_probe_state' );

		$values = $wpdb->get_col( "SELECT local_at FROM {$wpdb->prefix}wbam_utc_probe ORDER BY id" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// The per-row path's START TRANSACTION committed this test's open
		// transaction (MySQL does that), so undo the zone change by hand and
		// commit it before asserting; otherwise it leaks into later tests.
		$wpdb->query( "DROP TABLE {$wpdb->prefix}wbam_utc_probe" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		update_option( 'timezone_string', $this->saved_zone[0] );
		update_option( 'gmt_offset', $this->saved_zone[1] );
		delete_option( 'wbam_utc_probe_state' );
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->assertSame( array( '2026-01-15 15:00:00', '2026-07-15 14:00:00' ), $values );
	}

	public function test_the_date_guard_is_clean(): void {
		$guard = dirname( WBAM_PATH ) . '/wb-ad-manager-pro/bin/check-date-clocks.sh';
		if ( ! file_exists( $guard ) ) {
			$this->markTestSkipped( 'Pro is not next to Free here.' );
		}
		exec( 'bash ' . escapeshellarg( $guard ) . ' ' . escapeshellarg( WBAM_PATH ) . ' ' . escapeshellarg( dirname( $guard, 2 ) ) . ' 2>&1', $out, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		$this->assertSame( 0, $code, implode( "\n", $out ) );
	}
}
