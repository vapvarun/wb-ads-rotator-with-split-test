<?php
/**
 * Analytics roll-up and retention for the free plugin.
 *
 * @package WB_Ad_Manager
 * @since   3.2.0
 */

namespace WBAM\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rolls raw `wbam_analytics` events older than the retention window into
 * `wbam_analytics_daily` (one row per ad per day) and deletes them.
 *
 * Lazy: the first analytics write of the day arms a single run a day out.
 * A run handles one batch and queues the next only while old rows remain,
 * through Action Scheduler when it is loaded, WP-Cron otherwise. Nothing
 * runs on a site without traffic.
 *
 * With Pro active this stays idle: Pro's own daily aggregation and
 * retention own the same tables.
 */
class Analytics_Rollup {

	/**
	 * Cron / Action Scheduler hook.
	 */
	const HOOK = 'wbam_analytics_rollup';

	/**
	 * Raw rows handled per run.
	 */
	const BATCH_SIZE = 5000;

	/**
	 * Register the job. Called on every request so cron can run it.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	/**
	 * Arm the next run if none is pending. Called after an analytics write;
	 * reads the autoloaded cron option, writes it at most once a day.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + DAY_IN_SECONDS, self::HOOK );
		}
	}

	/**
	 * Days raw events are kept before they are rolled up.
	 *
	 * @return int
	 */
	public static function retention_days() {
		/**
		 * Filters how many days raw analytics events are kept.
		 *
		 * Older events are summed into wbam_analytics_daily and deleted, so
		 * lifetime totals are unchanged.
		 *
		 * @since 3.2.0
		 * @param int $days Default 90.
		 */
		return max( 1, (int) apply_filters( 'wbam_analytics_raw_retention_days', 90 ) );
	}

	/**
	 * Impressions and clicks per ad: raw events plus the rolled-up daily
	 * totals, so a range past the raw retention still counts.
	 *
	 * The one reader of both tables for per-ad totals: the ads list, the
	 * comparison box, the stats REST endpoints and the analytics abilities
	 * all go through it.
	 *
	 * @since 3.2.0
	 *
	 * @param int[]  $ad_ids Ad IDs; empty for every ad.
	 * @param string $start  First day (Y-m-d), or '' for no lower bound.
	 * @param string $end    Last day (Y-m-d), or '' for no upper bound.
	 * @return array<int, array<string, int>> Keyed by ad ID (impression, click); ads with no events are absent.
	 */
	public static function event_totals( array $ad_ids = array(), $start = '', $end = '' ) {
		global $wpdb;

		list( $raw_where, $raw_args )     = self::range_where( $ad_ids, 'created_at', $start ? $start . ' 00:00:00' : '', $end ? $end . ' 23:59:59' : '' );
		list( $daily_where, $daily_args ) = self::range_where( $ad_ids, 'date', $start, $end );

		$raw_sql   = "SELECT ad_id, event_type, COUNT(*) AS total FROM {$wpdb->prefix}wbam_analytics WHERE event_type IN ('impression','click'){$raw_where} GROUP BY ad_id, event_type";
		$daily_sql = "SELECT ad_id, SUM(impressions) AS impression, SUM(clicks) AS click FROM {$wpdb->prefix}wbam_analytics_daily WHERE 1=1{$daily_where} GROUP BY ad_id";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- plugin tables; every value bound through prepare(); callers cache.
		$raw   = $wpdb->get_results( $raw_args ? $wpdb->prepare( $raw_sql, $raw_args ) : $raw_sql );
		$daily = $wpdb->get_results( $daily_args ? $wpdb->prepare( $daily_sql, $daily_args ) : $daily_sql );
		// phpcs:enable

		$zero   = array(
			'impression' => 0,
			'click'      => 0,
		);
		$totals = array();
		foreach ( (array) $raw as $row ) {
			$id = (int) $row->ad_id;
			if ( ! isset( $totals[ $id ] ) ) {
				$totals[ $id ] = $zero;
			}
			$totals[ $id ][ $row->event_type ] += (int) $row->total;
		}
		foreach ( (array) $daily as $row ) {
			$id = (int) $row->ad_id;
			if ( ! isset( $totals[ $id ] ) ) {
				$totals[ $id ] = $zero;
			}
			$totals[ $id ]['impression'] += (int) $row->impression;
			$totals[ $id ]['click']      += (int) $row->click;
		}

		return $totals;
	}

	/**
	 * Site-wide totals and the top ads by impressions for a date range.
	 *
	 * @since 3.2.0
	 *
	 * @param string $start First day (Y-m-d), or ''.
	 * @param string $end   Last day (Y-m-d), or ''.
	 * @param int    $limit Top ads to return.
	 * @return array{impressions:int, clicks:int, ctr:float, top_ads:array<int, array{ad_id:int, title:string, impressions:int}>}
	 */
	public static function overview( $start = '', $end = '', $limit = 10 ) {
		$totals = self::event_totals( array(), $start, $end );
		$views  = wp_list_pluck( $totals, 'impression' );
		$clicks = array_sum( wp_list_pluck( $totals, 'click' ) );

		arsort( $views );
		$top_ads = array();
		foreach ( array_slice( $views, 0, max( 1, (int) $limit ), true ) as $ad_id => $impressions ) {
			if ( $impressions > 0 ) {
				$top_ads[] = array(
					'ad_id'       => (int) $ad_id,
					'title'       => get_the_title( (int) $ad_id ),
					'impressions' => (int) $impressions,
				);
			}
		}

		return array(
			'impressions' => (int) array_sum( $views ),
			'clicks'      => (int) $clicks,
			'ctr'         => self::ctr( (int) array_sum( $views ), (int) $clicks ),
			'top_ads'     => $top_ads,
		);
	}

	/**
	 * One ad's totals for a date range, with the placement breakdown.
	 *
	 * The breakdown reads raw events only: rolled-up days keep no placement,
	 * so on a range past the raw retention it covers the recent days alone.
	 *
	 * @since 3.2.0
	 *
	 * @param int    $ad_id Ad ID.
	 * @param string $start First day (Y-m-d), or ''.
	 * @param string $end   Last day (Y-m-d), or ''.
	 * @return array{impressions:int, clicks:int, ctr:float, by_placement:array<int, array{placement:string, impressions:int, clicks:int, ctr:float}>}
	 */
	public static function ad_stats( $ad_id, $start = '', $end = '' ) {
		global $wpdb;

		$ad_id  = (int) $ad_id;
		$totals = self::event_totals( array( $ad_id ), $start, $end );
		$totals = $totals[ $ad_id ] ?? array(
			'impression' => 0,
			'click'      => 0,
		);

		list( $where, $args ) = self::range_where( array( $ad_id ), 'created_at', $start ? $start . ' 00:00:00' : '', $end ? $end . ' 23:59:59' : '' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- plugin table; every value bound through prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT placement, event_type, COUNT(*) AS total FROM {$wpdb->prefix}wbam_analytics WHERE event_type IN ('impression','click'){$where} GROUP BY placement, event_type", $args ) );
		// phpcs:enable

		$placements = array();
		foreach ( (array) $rows as $row ) {
			$key = ! empty( $row->placement ) ? (string) $row->placement : 'unknown';
			if ( ! isset( $placements[ $key ] ) ) {
				$placements[ $key ] = array(
					'placement'   => $key,
					'impressions' => 0,
					'clicks'      => 0,
					'ctr'         => 0.0,
				);
			}
			$placements[ $key ][ 'click' === $row->event_type ? 'clicks' : 'impressions' ] += (int) $row->total;
			$placements[ $key ]['ctr'] = self::ctr( $placements[ $key ]['impressions'], $placements[ $key ]['clicks'] );
		}

		return array(
			'impressions'  => $totals['impression'],
			'clicks'       => $totals['click'],
			'ctr'          => self::ctr( $totals['impression'], $totals['click'] ),
			'by_placement' => array_values( $placements ),
		);
	}

	/**
	 * Click-through rate as a percentage, two decimals.
	 *
	 * @param int $impressions Impressions.
	 * @param int $clicks      Clicks.
	 * @return float
	 */
	private static function ctr( $impressions, $clicks ) {
		return $impressions > 0 ? round( $clicks / $impressions * 100, 2 ) : 0.0;
	}

	/**
	 * WHERE fragment (starting " AND ") and its bound values for an ad set
	 * and an inclusive range on one column.
	 *
	 * @param int[]  $ad_ids Ad IDs; empty for every ad.
	 * @param string $column Date column.
	 * @param string $from   Lower bound, or ''.
	 * @param string $to     Upper bound, or ''.
	 * @return array{0:string, 1:array<int, int|string>}
	 */
	private static function range_where( array $ad_ids, $column, $from, $to ) {
		$sql  = '';
		$args = array();

		$ad_ids = array_values( array_filter( array_map( 'absint', $ad_ids ) ) );
		if ( $ad_ids ) {
			$sql .= ' AND ad_id IN (' . implode( ',', array_fill( 0, count( $ad_ids ), '%d' ) ) . ')';
			$args = $ad_ids;
		}
		if ( '' !== $from ) {
			$sql   .= " AND {$column} >= %s";
			$args[] = $from;
		}
		if ( '' !== $to ) {
			$sql   .= " AND {$column} <= %s";
			$args[] = $to;
		}

		return array( $sql, $args );
	}

	/**
	 * Cron callback.
	 *
	 * @return void
	 */
	public static function run() {
		if ( defined( 'WBAM_PRO_VERSION' ) ) {
			return;
		}

		if ( ! self::rollup_batch() ) {
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array(), 'wbam' );
		} else {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::HOOK );
		}
	}

	/**
	 * Roll up one batch of expired raw events.
	 *
	 * The batch is an id range, so the sum and the delete cover exactly the
	 * same rows, inside one transaction: a run that dies half-way cannot
	 * count a row twice. Unique counts take the larger of two batches that
	 * share a day, so they are a lower bound when a day spans batches.
	 *
	 * @return bool True when more expired rows remain.
	 */
	public static function rollup_batch() {
		global $wpdb;

		$raw    = $wpdb->prefix . 'wbam_analytics';
		$daily  = $wpdb->prefix . 'wbam_analytics_daily';
		$cutoff = wp_date( 'Y-m-d 00:00:00', time() - self::retention_days() * DAY_IN_SECONDS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- plugin tables from $wpdb->prefix; values bound.
		$max_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(id) FROM ( SELECT id FROM {$raw} WHERE created_at < %s ORDER BY id LIMIT %d ) batch",
				$cutoff,
				self::BATCH_SIZE
			)
		);

		if ( ! $max_id ) {
			return false;
		}

		$wpdb->query( 'START TRANSACTION' );

		$summed = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$daily} ( ad_id, campaign_id, date, impressions, clicks, unique_impressions, unique_clicks )
				SELECT ad_id, MAX(campaign_id), DATE(created_at),
					SUM( event_type = 'impression' ), SUM( event_type = 'click' ),
					COUNT( DISTINCT CASE WHEN event_type = 'impression' THEN COALESCE( NULLIF( visitor_hash, '' ), ip_hash ) END ),
					COUNT( DISTINCT CASE WHEN event_type = 'click' THEN COALESCE( NULLIF( visitor_hash, '' ), ip_hash ) END )
				FROM {$raw}
				WHERE id <= %d AND created_at < %s
				GROUP BY ad_id, DATE(created_at)
				ON DUPLICATE KEY UPDATE
					impressions = impressions + VALUES(impressions),
					clicks = clicks + VALUES(clicks),
					unique_impressions = GREATEST( unique_impressions, VALUES(unique_impressions) ),
					unique_clicks = GREATEST( unique_clicks, VALUES(unique_clicks) )",
				$max_id,
				$cutoff
			)
		);

		$deleted = false === $summed ? false : $wpdb->query( $wpdb->prepare( "DELETE FROM {$raw} WHERE id <= %d AND created_at < %s", $max_id, $cutoff ) );

		if ( false === $deleted ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$wpdb->query( 'COMMIT' );

		$more = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$raw} WHERE created_at < %s LIMIT 1", $cutoff ) );
		// phpcs:enable

		return $more;
	}
}
