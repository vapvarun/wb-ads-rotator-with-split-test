<?php
/**
 * Analytics REST API
 *
 * Handles REST API routes for analytics data from the wbam_analytics table.
 *
 * @package WB_Ad_Manager
 * @since   2.8.0
 */

namespace WBAM\API;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analytics API class.
 */
class Analytics_API {

	/**
	 * REST namespace.
	 *
	 * @var non-falsy-string
	 */
	private const REST_NAMESPACE = 'wbam/v1';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		// Admin: overview summary stats.
		register_rest_route(
			self::REST_NAMESPACE,
			'/analytics/overview',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_overview' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'start_date' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'end_date'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit'      => array(
						'type'              => 'integer',
						'default'           => 10,
						'minimum'           => 1,
						'maximum'           => 50,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Admin: per-ad stats with date range.
		register_rest_route(
			self::REST_NAMESPACE,
			'/analytics/ads/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_ad_analytics' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'start_date' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'end_date'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Admin: daily time-series data.
		register_rest_route(
			self::REST_NAMESPACE,
			'/analytics/daily',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_daily_stats' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'start_date' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'end_date'   => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'ad_id'      => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Public: track event (alternative path to /ads/track).
		register_rest_route(
			self::REST_NAMESPACE,
			'/analytics/track',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'track_event' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ad_id'      => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'event_type' => array(
						'type'              => 'string',
						'required'          => true,
						'enum'              => array( 'impression', 'click' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'placement'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check admin permission.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_admin_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to perform this action.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * GET /analytics/overview — Summary stats (admin).
	 *
	 * Returns total impressions, clicks, CTR, and top-performing ads.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_overview( $request ) {
		return rest_ensure_response(
			\WBAM\Core\Analytics_Rollup::overview(
				sanitize_text_field( (string) $request['start_date'] ),
				sanitize_text_field( (string) $request['end_date'] ),
				absint( $request['limit'] )
			)
		);
	}

	/**
	 * GET /analytics/ads/{id} — Per-ad stats with optional date range (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_ad_analytics( $request ) {
		$ad_id = absint( $request['id'] );
		$post  = get_post( $ad_id );

		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return new \WP_Error(
				'ad_not_found',
				__( 'Ad not found.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 404 )
			);
		}

		$stats = \WBAM\Core\Analytics_Rollup::ad_stats( $ad_id, sanitize_text_field( (string) $request['start_date'] ), sanitize_text_field( (string) $request['end_date'] ) );

		return rest_ensure_response(
			array(
				'ad_id' => $ad_id,
				'title' => $post->post_title,
			) + $stats
		);
	}

	/**
	 * GET /analytics/daily — Daily time-series data (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_daily_stats( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wbam_analytics';

		$start_date = sanitize_text_field( $request['start_date'] );
		$end_date   = sanitize_text_field( $request['end_date'] );
		$ad_id      = absint( $request['ad_id'] );

		$where  = array( 'created_at >= %s', 'created_at <= %s' );
		$values = array( $start_date . ' 00:00:00', $end_date . ' 23:59:59' );

		if ( $ad_id > 0 ) {
			$where[]  = 'ad_id = %d';
			$values[] = $ad_id;
		}

		$where_sql = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- WBAM custom table name from $wpdb->prefix, not user input.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders interpolated via  /  fragments; safe.
				"SELECT DATE(created_at) as day, event_type, COUNT(*) as count FROM {$table} WHERE {$where_sql} GROUP BY day, event_type ORDER BY day ASC",
				$values
			)
		);

		$daily = array();
		foreach ( $rows as $row ) {
			$day = $row->day;
			if ( ! isset( $daily[ $day ] ) ) {
				$daily[ $day ] = array(
					'date'        => $day,
					'impressions' => 0,
					'clicks'      => 0,
					'ctr'         => 0,
				);
			}
			if ( 'impression' === $row->event_type ) {
				$daily[ $day ]['impressions'] = (int) $row->count;
			} elseif ( 'click' === $row->event_type ) {
				$daily[ $day ]['clicks'] = (int) $row->count;
			}
		}

		// Calculate daily CTR.
		foreach ( $daily as &$d ) {
			$d['ctr'] = $d['impressions'] > 0 ? round( ( $d['clicks'] / $d['impressions'] ) * 100, 2 ) : 0;
		}
		unset( $d );

		return rest_ensure_response(
			array(
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'ad_id'      => ! empty( $ad_id ) ? $ad_id : null,
				'daily'      => array_values( $daily ),
			)
		);
	}

	/**
	 * POST /analytics/track — Track event (public).
	 *
	 * Alternative path to /ads/track for clarity in analytics contexts.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function track_event( $request ) {
		$ad_id      = absint( $request['ad_id'] );
		$event_type = sanitize_text_field( $request['event_type'] );
		$placement  = sanitize_text_field( $request['placement'] );

		// IP-based rate limiting: max 60 events per minute per IP.
		$ip = $this->get_client_ip();
		if ( ! $this->check_rate_limit( 'rest_analytics_' . md5( $ip ), 60, 60 ) ) {
			return new \WP_Error(
				'rate_limited',
				__( 'Too many requests. Please try again later.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 429 )
			);
		}

		$post = get_post( $ad_id );
		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return new \WP_Error(
				'ad_not_found',
				__( 'Ad not found.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 404 )
			);
		}

		$frontend = \WBAM\Frontend\Frontend::get_instance();
		$frontend->record_analytics( $ad_id, $event_type, $placement );

		do_action( 'wbam_rest_event_tracked', $ad_id, $event_type, $placement );

		return rest_ensure_response( array( 'tracked' => true ) );
	}

	/**
	 * Check rate limit using transients.
	 *
	 * @param string $key    Unique rate limit key.
	 * @param int    $limit  Maximum number of requests allowed.
	 * @param int    $window Time window in seconds.
	 * @return bool True if under limit, false if limit exceeded.
	 */
	private function check_rate_limit( $key, $limit, $window ) {
		$transient_key = 'wbam_rl_' . md5( $key );
		$current       = (int) get_transient( $transient_key );

		if ( $current >= $limit ) {
			return false;
		}

		if ( 0 === $current ) {
			set_transient( $transient_key, 1, $window );
		} else {
			set_transient( $transient_key, $current + 1, $window );
		}

		return true;
	}

	/**
	 * Get the client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip    = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$parts = explode( ',', $ip );
			$ip    = trim( $parts[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}
}
