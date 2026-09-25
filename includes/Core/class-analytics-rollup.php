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
