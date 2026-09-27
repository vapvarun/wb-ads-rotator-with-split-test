<?php
/**
 * Date and time helpers: store UTC, show and read in the site's time zone.
 *
 * The rule (docs/standards/dates.md): a moment is stored in UTC, written
 * from PHP with current_time( 'mysql', true ), and shown or picked in the
 * Settings > General time zone. A calendar day with no time (a daily report
 * bucket, an ad's schedule day) is a site-calendar Y-m-d.
 *
 * @package WB_Ad_Manager
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wbam_format_datetime' ) ) {
	/**
	 * Show a stored UTC moment in the site's time zone (Settings > General).
	 *
	 * Every stored moment is UTC (docs/standards/dates.md). date_i18n() on
	 * such a value prints UTC, not the site's time; this converts it.
	 *
	 * @since 3.2.0
	 * @param string|null $utc    A 'Y-m-d H:i:s' UTC value, as stored.
	 * @param string      $format PHP date format; empty means the site's date
	 *                            and time formats.
	 * @return string The formatted date, or '' for an empty or invalid value.
	 */
	function wbam_format_datetime( $utc, $format = '' ) {
		if ( empty( $utc ) || '0000-00-00 00:00:00' === $utc ) {
			return '';
		}
		$timestamp = strtotime( $utc . ' UTC' );
		if ( false === $timestamp ) {
			return '';
		}
		if ( '' === $format ) {
			$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}

		return (string) wp_date( $format, $timestamp );
	}
}

if ( ! function_exists( 'wbam_format_day' ) ) {
	/**
	 * Show a site-calendar day (a 'Y-m-d' with no time, such as a daily
	 * report bucket or an ad's schedule day) without shifting it across a
	 * time zone.
	 *
	 * @since 3.2.0
	 * @param string|null $day    Site-calendar 'Y-m-d'.
	 * @param string      $format PHP date format; empty means the site's date format.
	 * @return string The formatted day, or '' for an empty or invalid value.
	 */
	function wbam_format_day( $day, $format = '' ) {
		$timestamp = empty( $day ) ? false : strtotime( substr( (string) $day, 0, 10 ) . ' 12:00:00 UTC' );
		if ( false === $timestamp ) {
			return '';
		}

		// Noon UTC of that day, formatted in UTC: the same calendar day in every zone.
		return (string) wp_date( '' === $format ? (string) get_option( 'date_format' ) : $format, $timestamp, new DateTimeZone( 'UTC' ) );
	}
}

if ( ! function_exists( 'wbam_site_to_utc' ) ) {
	/**
	 * Convert a date or time a person picked (in the site's time zone) to the
	 * UTC value that is stored. A bare 'Y-m-d' becomes the start of that site
	 * day, or its last second when $end_of_day is true.
	 *
	 * @since 3.2.0
	 * @param string|null $value      'Y-m-d', 'Y-m-d H:i', 'Y-m-d\TH:i' or 'Y-m-d H:i:s' in the site zone.
	 * @param bool        $end_of_day For a bare date, use 23:59:59 instead of 00:00:00.
	 * @return string|null UTC 'Y-m-d H:i:s', or null for an empty or invalid value.
	 */
	function wbam_site_to_utc( $value, $end_of_day = false ) {
		$value = trim( str_replace( 'T', ' ', (string) $value ) );
		if ( '' === $value ) {
			return null;
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			$value .= $end_of_day ? ' 23:59:59' : ' 00:00:00';
		}
		try {
			$local = new DateTime( $value, wp_timezone() );
		} catch ( Exception $e ) {
			return null;
		}

		return $local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'wbam_site_utc_offset' ) ) {
	/**
	 * The site's current offset from UTC in seconds (DST-aware).
	 *
	 * @since 3.2.0
	 * @return int
	 */
	function wbam_site_utc_offset() {
		$tz = wp_timezone();
		return (int) $tz->getOffset( new DateTime( 'now', $tz ) );
	}
}

if ( ! function_exists( 'wbam_site_day_utc_bounds' ) ) {
	/**
	 * UTC bounds of an inclusive range of site-calendar days, for querying
	 * UTC moments by the site's days (index-friendly: col BETWEEN %s AND %s).
	 *
	 * @since 3.2.0
	 * @param string $start_date Site-calendar Y-m-d.
	 * @param string $end_date   Site-calendar Y-m-d.
	 * @return array{0:string,1:string} UTC 'Y-m-d H:i:s' start and end.
	 */
	function wbam_site_day_utc_bounds( $start_date, $end_date ) {
		$tz = wp_timezone();
		try {
			$start = new DateTime( (string) $start_date . ' 00:00:00', $tz );
			$end   = new DateTime( (string) $end_date . ' 23:59:59', $tz );
		} catch ( Exception $e ) {
			$start = new DateTime( 'today', $tz );
			$end   = new DateTime( 'today 23:59:59', $tz );
		}
		$utc = new DateTimeZone( 'UTC' );

		return array( $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ), $end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) );
	}
}

if ( ! function_exists( 'wbam_period_start' ) ) {
	/**
	 * Where a reporting period starts, as a UTC moment (for moment columns
	 * such as clicked_at) and as a site-calendar day (for daily buckets).
	 * 'today' starts at the site's midnight; 'week', 'month' and 'year' are
	 * rolling 7, 30 and 365 days back from now.
	 *
	 * @since 3.2.0
	 * @param string|int $period today|week|month|year|all, or a number of days.
	 * @return array{utc:string,day:string}|null Null for 'all' or an unknown period.
	 */
	function wbam_period_start( $period ) {
		$tz   = wp_timezone();
		$days = array(
			'week'  => 7,
			'month' => 30,
			'year'  => 365,
		);

		if ( 'today' === $period ) {
			$start = new DateTime( 'today', $tz );
		} elseif ( isset( $days[ $period ] ) || ( is_numeric( $period ) && (int) $period > 0 ) ) {
			$n     = isset( $days[ $period ] ) ? $days[ $period ] : (int) $period;
			$start = new DateTime( '-' . $n . ' days', $tz );
		} else {
			return null;
		}

		$day = $start->format( 'Y-m-d' );

		return array(
			'utc' => $start->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			'day' => $day,
		);
	}
}

if ( ! function_exists( 'wbam_sql_site_date' ) ) {
	/**
	 * SQL expression for the site-calendar day of a UTC moment column, for
	 * grouping by the site's days: DATE(col + INTERVAL offset SECOND).
	 *
	 * @since 3.2.0
	 * @param string $column A column reference (trusted code, never input).
	 * @return string
	 */
	function wbam_sql_site_date( $column ) {
		return sprintf( 'DATE(%s + INTERVAL %d SECOND)', $column, wbam_site_utc_offset() );
	}
}

if ( ! function_exists( 'wbam_drop_on_update_clock' ) ) {
	/**
	 * Stop a column from taking the MySQL server clock on every UPDATE.
	 * The code sets it in UTC instead (docs/standards/dates.md). Idempotent.
	 *
	 * @since 3.2.0
	 * @param string $table  Table name without the prefix.
	 * @param string $column Column name.
	 * @return void
	 */
	function wbam_drop_on_update_clock( $table, $column ) {
		global $wpdb;

		$full = $wpdb->prefix . $table;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- one-time migration on the plugin's own table; names are code constants.
		$extra = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$full} LIKE %s", $column ), 5 );
		if ( is_string( $extra ) && false !== stripos( $extra, 'on update' ) ) {
			$wpdb->query( "ALTER TABLE {$full} MODIFY {$column} DATETIME NULL DEFAULT NULL" );
		}
		// phpcs:enable
	}
}

if ( ! function_exists( 'wbam_convert_columns_to_utc' ) ) {
	/**
	 * One-time conversion of stored moments to UTC (docs/standards/dates.md).
	 *
	 * Each column is tagged by the clock that wrote it:
	 * - 'server': MySQL's clock (NOW(), DEFAULT CURRENT_TIMESTAMP), shifted by
	 *   the server's offset from UTC, measured once.
	 * - 'local': the site's zone (current_time( 'mysql' ), a picked time),
	 *   shifted by the site's offset. A zone without daylight saving is one
	 *   UPDATE; a zone with it is converted row by row, so each row gets the
	 *   offset in force on its own date.
	 *
	 * Progress is kept in $state_option, so a column is never converted twice
	 * and a large table resumes where it stopped.
	 *
	 * @since 3.2.0
	 * @param array<string,array<string,string>> $plan         table (no prefix) => array( column => 'server'|'local' ).
	 * @param string                             $state_option Option that records progress.
	 * @param int                                $time_budget  Seconds to work before returning.
	 * @return bool True when work remains (call again later).
	 */
	function wbam_convert_columns_to_utc( array $plan, $state_option, $time_budget = 20 ) {
		global $wpdb;

		$started = microtime( true );
		$state   = get_option( $state_option, array() );
		$state   = is_array( $state ) ? $state : array();
		if ( ! isset( $state['done'], $state['cursor'] ) ) {
			// Measure the server clock once, so a resumed run shifts by the same amount.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reads the server clock offset.
			$state = array(
				'done'          => array(),
				'cursor'        => array(),
				'server_offset' => (int) $wpdb->get_var( 'SELECT TIMESTAMPDIFF( SECOND, UTC_TIMESTAMP(), NOW() )' ),
			);
		}

		$tz          = wp_timezone();
		$now         = time();
		$transitions = $tz->getTransitions( $now - 20 * YEAR_IN_SECONDS, $now );
		$site_fixed  = ! is_array( $transitions ) || count( array_unique( wp_list_pluck( $transitions, 'offset' ) ) ) <= 1;
		$site_offset = (int) $tz->getOffset( new DateTime( 'now', $tz ) );
		$utc         = new DateTimeZone( 'UTC' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- one-time migration on the plugin's own tables; table and column names are code constants, values bound.
		foreach ( $plan as $table => $columns ) {
			$full = $wpdb->prefix . $table;
			// A table this site never created (a module never enabled) is skipped.
			$quiet  = $wpdb->suppress_errors( true );
			$exists = false !== $wpdb->query( "SELECT 1 FROM {$full} LIMIT 0" );
			$wpdb->suppress_errors( $quiet );
			if ( ! $exists ) {
				continue;
			}

			foreach ( $columns as $column => $clock ) {
				$key = $table . '.' . $column;
				if ( ! empty( $state['done'][ $key ] ) ) {
					continue;
				}

				$offset = 'server' === $clock ? (int) $state['server_offset'] : $site_offset;
				if ( 'server' === $clock || $site_fixed ) {
					if ( 0 !== $offset ) {
						$wpdb->query( $wpdb->prepare( "UPDATE {$full} SET {$column} = DATE_SUB( {$column}, INTERVAL %d SECOND ) WHERE {$column} IS NOT NULL AND {$column} > '1000-01-01'", $offset ) );
					}
					$state['done'][ $key ] = true;
					update_option( $state_option, $state, false );
					continue;
				}

				// Site zone with daylight saving: per row, in id batches.
				$cursor = isset( $state['cursor'][ $key ] ) ? (int) $state['cursor'][ $key ] : 0;
				do {
					$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, {$column} AS v FROM {$full} WHERE id > %d AND {$column} IS NOT NULL AND {$column} > '1000-01-01' ORDER BY id LIMIT 500", $cursor ) );
					// The batch and its cursor commit together, so a request that
					// dies mid-batch never converts a row twice on resume.
					$wpdb->query( 'START TRANSACTION' );
					foreach ( (array) $rows as $row ) {
						try {
							$value = ( new DateTime( (string) $row->v, $tz ) )->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
							$wpdb->update( $full, array( $column => $value ), array( 'id' => (int) $row->id ), array( '%s' ), array( '%d' ) );
						} catch ( Exception $e ) {
							// A malformed value stays as it is.
							unset( $e );
						}
						$cursor = (int) $row->id;
					}
					$state['cursor'][ $key ] = $cursor;
					update_option( $state_option, $state, false );
					$wpdb->query( 'COMMIT' );
					if ( ( microtime( true ) - $started ) > $time_budget ) {
						// phpcs:enable
						return true;
					}
				} while ( count( (array) $rows ) === 500 );

				$state['done'][ $key ] = true;
				update_option( $state_option, $state, false );
			}
		}
		// phpcs:enable

		return false;
	}
}

if ( ! function_exists( 'wbam_site_day_range' ) ) {
	/**
	 * The site-calendar days from a start day to today, as 'Y-m-d' keys:
	 * the axis of a daily chart whose data is grouped by site day.
	 *
	 * @since 3.2.0
	 * @param string|null $start_day 'Y-m-d' in the site calendar; empty for the last $days days.
	 * @param int         $days      When $start_day is empty: start this many days before today.
	 * @return string[] Days, oldest first; at most 400.
	 */
	function wbam_site_day_range( $start_day = null, $days = 30 ) {
		$tz    = wp_timezone();
		$today = new DateTimeImmutable( 'now', $tz );
		$start = null;
		if ( $start_day && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $start_day ) ) {
			$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $start_day, $tz );
			$start  = $parsed ? $parsed : null;
		}
		if ( ! $start || $start > $today ) {
			$start = $today->setTime( 0, 0 )->modify( '-' . max( 0, (int) $days ) . ' days' );
		}

		$keys = array();
		$day  = $start->setTime( 0, 0 );
		for ( $i = 0; $day <= $today && $i < 400; $i++ ) {
			$keys[] = $day->format( 'Y-m-d' );
			$day    = $day->modify( '+1 day' );
		}
		return $keys;
	}
}
