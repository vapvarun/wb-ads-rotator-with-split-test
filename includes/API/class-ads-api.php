<?php
/**
 * Ads REST API
 *
 * Handles REST API routes for ads (wbam-ad CPT).
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
 * Ads API class.
 */
class Ads_API {

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
		// Public: list published ads.
		register_rest_route(
			self::REST_NAMESPACE,
			'/ads',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_ads' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 20,
							'minimum'           => 1,
							'maximum'           => 100,
							'sanitize_callback' => 'absint',
						),
						'page'     => array(
							'type'              => 'integer',
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_ad' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_ad_args(),
				),
			)
		);

		// Public: serve ad for a placement.
		register_rest_route(
			self::REST_NAMESPACE,
			'/ads/serve',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'serve_ad' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'placement' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'post_id'   => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'page_url'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		// Public: list available placement types.
		register_rest_route(
			self::REST_NAMESPACE,
			'/ads/placements',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_placement_types' ),
				'permission_callback' => '__return_true',
			)
		);

		// Public: list available ad types.
		register_rest_route(
			self::REST_NAMESPACE,
			'/ads/types',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_ad_types' ),
				'permission_callback' => '__return_true',
			)
		);

		// Public: track event (impression / click).
		register_rest_route(
			self::REST_NAMESPACE,
			'/ads/track',
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

		// Admin: single ad CRUD.
		register_rest_route(
			self::REST_NAMESPACE,
			'/ads/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_ad' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_ad' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_ad_args( true ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_ad' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		// Admin: per-ad stats.
		register_rest_route(
			self::REST_NAMESPACE,
			'/ads/(?P<id>\d+)/stats',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_ad_stats' ),
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

		// Admin: duplicate ad.
		register_rest_route(
			self::REST_NAMESPACE,
			'/ads/(?P<id>\d+)/duplicate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate_ad' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
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
	 * GET /ads — List published ads (public).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_ads( $request ) {
		$per_page = absint( $request['per_page'] );
		$page     = absint( $request['page'] );

		$query = new \WP_Query(
			array(
				'post_type'              => 'wbam-ad',
				'post_status'            => 'publish',
				// This endpoint is public (permission_callback __return_true), so
				// it must not disclose ads the site owner has turned off. Only
				// enabled ads belong in a public listing.
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Single indexed meta key on a small ad set; required to keep disabled ads out of the public response.
					array(
						'key'     => '_wbam_enabled',
						'value'   => '1',
						'compare' => '=',
					),
				),
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				'no_found_rows'          => false,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$ads = array();
		foreach ( $query->posts as $post ) {
			$ads[] = $this->prepare_ad_for_response( $post );
		}

		$response = rest_ensure_response(
			array(
				'ads'         => $ads,
				'total'       => (int) $query->found_posts,
				'total_pages' => (int) $query->max_num_pages,
				'page'        => $page,
				'per_page'    => $per_page,
			)
		);

		return $response;
	}

	/**
	 * GET /ads/{id} — Get single ad (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_ad( $request ) {
		$id   = absint( $request['id'] );
		$post = get_post( $id );

		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return new \WP_Error(
				'ad_not_found',
				__( 'Ad not found.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $this->prepare_ad_for_response( $post, true ) );
	}

	/**
	 * POST /ads — Create ad (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_ad( $request ) {
		$title = sanitize_text_field( $request['title'] );

		if ( empty( $title ) ) {
			return new \WP_Error(
				'missing_title',
				__( 'Ad title is required.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 400 )
			);
		}

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return new \WP_Error(
				'create_failed',
				__( 'Could not create ad.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 500 )
			);
		}

		$this->save_ad_meta( $post_id, $request );

		$post = get_post( $post_id );
		return rest_ensure_response( $this->prepare_ad_for_response( $post, true ) );
	}

	/**
	 * PUT /ads/{id} — Update ad (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_ad( $request ) {
		$id   = absint( $request['id'] );
		$post = get_post( $id );

		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return new \WP_Error(
				'ad_not_found',
				__( 'Ad not found.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 404 )
			);
		}

		$update_data = array(
			'ID' => $id,
		);

		if ( isset( $request['title'] ) ) {
			$update_data['post_title'] = sanitize_text_field( $request['title'] );
		}

		if ( isset( $request['status'] ) ) {
			$allowed_statuses = array( 'publish', 'draft', 'pending' );
			if ( in_array( $request['status'], $allowed_statuses, true ) ) {
				$update_data['post_status'] = $request['status'];
			}
		}

		$result = wp_update_post( $update_data, true );

		if ( is_wp_error( $result ) ) {
			return new \WP_Error(
				'update_failed',
				__( 'Could not update ad.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 500 )
			);
		}

		$this->save_ad_meta( $id, $request );

		$post = get_post( $id );
		return rest_ensure_response( $this->prepare_ad_for_response( $post, true ) );
	}

	/**
	 * DELETE /ads/{id} — Delete ad (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_ad( $request ) {
		$id   = absint( $request['id'] );
		$post = get_post( $id );

		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return new \WP_Error(
				'ad_not_found',
				__( 'Ad not found.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 404 )
			);
		}

		$result = wp_delete_post( $id, true );

		if ( ! $result ) {
			return new \WP_Error(
				'delete_failed',
				__( 'Could not delete ad.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	/**
	 * GET /ads/serve — Serve ad for a placement (public).
	 *
	 * Delegates to Placement_Engine for targeting consistency.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function serve_ad( $request ) {
		$placement = sanitize_text_field( $request['placement'] );
		$post_id   = absint( $request['post_id'] );

		if ( empty( $placement ) ) {
			return new \WP_Error(
				'missing_placement',
				__( 'Placement parameter is required.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 400 )
			);
		}

		// Set up context for targeting engine if post_id provided.
		if ( $post_id > 0 ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentionally setting post context for targeting.
			$GLOBALS['post'] = get_post( $post_id );
			setup_postdata( $GLOBALS['post'] );
		}

		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();
		$ad_ids = $engine->get_ads_for_placement( $placement );

		if ( empty( $ad_ids ) ) {
			return rest_ensure_response(
				array(
					'html'  => '',
					'ads'   => array(),
					'count' => 0,
				)
			);
		}

		$rendered_ads = array();
		$html_parts   = array();

		foreach ( $ad_ids as $ad_id ) {
			$html = $engine->render_ad(
				$ad_id,
				array(
					'placement' => $placement,
					'context'   => 'api',
				)
			);

			if ( ! empty( $html ) ) {
				$html_parts[]   = $html;
				$rendered_ads[] = array(
					'id'        => $ad_id,
					'title'     => get_the_title( $ad_id ),
					'type'      => $this->get_ad_type_from_meta( $ad_id ),
					'placement' => $placement,
				);
			}
		}

		if ( $post_id > 0 ) {
			wp_reset_postdata();
		}

		return rest_ensure_response(
			array(
				'html'      => implode( "\n", $html_parts ),
				'ads'       => $rendered_ads,
				'count'     => count( $rendered_ads ),
				'placement' => $placement,
			)
		);
	}

	/**
	 * POST /ads/track — Track event (public, IP-rate-limited).
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
		if ( ! $this->check_rate_limit( 'rest_track_' . md5( $ip ), 60, 60 ) ) {
			return new \WP_Error(
				'rate_limited',
				__( 'Too many requests. Please try again later.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 429 )
			);
		}

		// Verify the ad exists.
		$post = get_post( $ad_id );
		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return new \WP_Error(
				'ad_not_found',
				__( 'Ad not found.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 404 )
			);
		}

		// Delegate to Frontend::record_analytics() via direct DB write to keep logic consistent.
		$frontend = \WBAM\Frontend\Frontend::get_instance();
		$frontend->record_analytics( $ad_id, $event_type, $placement );

		do_action( 'wbam_rest_event_tracked', $ad_id, $event_type, $placement );

		return rest_ensure_response( array( 'tracked' => true ) );
	}

	/**
	 * GET /ads/{id}/stats — Per-ad performance stats (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_ad_stats( $request ) {
		$id   = absint( $request['id'] );
		$post = get_post( $id );

		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return new \WP_Error(
				'ad_not_found',
				__( 'Ad not found.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 404 )
			);
		}

		$stats = \WBAM\Core\Analytics_Rollup::ad_stats( $id, (string) $request['start_date'], (string) $request['end_date'] );

		return rest_ensure_response(
			array(
				'ad_id'       => $id,
				'impressions' => $stats['impressions'],
				'clicks'      => $stats['clicks'],
				'ctr'         => $stats['ctr'],
			)
		);
	}

	/**
	 * POST /ads/{id}/duplicate — Duplicate ad (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function duplicate_ad( $request ) {
		$id   = absint( $request['id'] );
		$post = get_post( $id );

		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return new \WP_Error(
				'ad_not_found',
				__( 'Ad not found.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 404 )
			);
		}

		// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		$new_id = wp_insert_post(
			array(
				'post_title'  => sprintf(
					/* translators: %s: original ad title */
					__( '%s (Copy)', 'wb-ads-rotator-with-split-test' ),
					$post->post_title
				),
				'post_type'   => 'wbam-ad',
				'post_status' => 'draft',
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			return new \WP_Error(
				'duplicate_failed',
				__( 'Could not duplicate ad.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 500 )
			);
		}

		// Copy meta.
		$meta_keys = array( '_wbam_ad_data', '_wbam_enabled', '_wbam_placements', '_wbam_priority' );
		foreach ( $meta_keys as $meta_key ) {
			$value = get_post_meta( $id, $meta_key, true );
			if ( '' !== $value ) {
				update_post_meta( $new_id, $meta_key, $value );
			}
		}

		do_action( 'wbam_ad_duplicated', $new_id, $id );

		$new_post = get_post( $new_id );
		return rest_ensure_response( $this->prepare_ad_for_response( $new_post, true ) );
	}

	/**
	 * GET /ads/placements — List available placement types (public).
	 *
	 * @param \WP_REST_Request $request Request object (unused; required by REST callback contract).
	 * @return \WP_REST_Response
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature required by REST callback contract.
	public function get_placement_types( $request ) {
		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();
		// Single source of truth — a slot the admin has closed must not be
		// advertised over the API. See plan/ad-slot-control.md §3.1.
		$placements = $engine->get_selectable_placements();

		$data = array();
		foreach ( $placements as $placement ) {
			$data[] = array(
				'id'    => $placement->get_id(),
				'label' => $placement->get_name(),
				'group' => $placement->get_group(),
			);
		}

		return rest_ensure_response( array( 'placements' => $data ) );
	}

	/**
	 * GET /ads/types — List available ad types (public).
	 *
	 * @param \WP_REST_Request $request Request object (unused; required by REST callback contract).
	 * @return \WP_REST_Response
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature required by REST callback contract.
	public function get_ad_types( $request ) {
		$engine   = \WBAM\Modules\Placements\Placement_Engine::get_instance();
		$ad_types = $engine->get_ad_types();

		$data = array();
		foreach ( $ad_types as $ad_type ) {
			$data[] = array(
				'id'    => $ad_type->get_id(),
				'label' => $ad_type->get_name(),
			);
		}

		return rest_ensure_response( array( 'ad_types' => $data ) );
	}

	/**
	 * Prepare ad post for REST response.
	 *
	 * @param \WP_Post $post     Post object.
	 * @param bool     $is_admin Include full meta for admin responses.
	 * @return array
	 */
	private function prepare_ad_for_response( $post, $is_admin = false ) {
		$data = array(
			'id'      => $post->ID,
			'title'   => $post->post_title,
			'type'    => $this->get_ad_type_from_meta( $post->ID ),
			'enabled' => (bool) get_post_meta( $post->ID, '_wbam_enabled', true ),
			'created' => $post->post_date,
			'status'  => $post->post_status,
		);

		$placements         = get_post_meta( $post->ID, '_wbam_placements', true );
		$data['placements'] = is_array( $placements ) ? $placements : array();

		if ( $is_admin ) {
			$ad_data          = get_post_meta( $post->ID, '_wbam_ad_data', true );
			$data['ad_data']  = is_array( $ad_data ) ? $ad_data : array();
			$data['priority'] = (int) get_post_meta( $post->ID, '_wbam_priority', true );
			$data['modified'] = $post->post_modified;
		}

		return $data;
	}

	/**
	 * Save ad meta from request.
	 *
	 * @param int              $post_id Post ID.
	 * @param \WP_REST_Request $request REST request.
	 */
	private function save_ad_meta( $post_id, $request ) {
		if ( isset( $request['ad_data'] ) && is_array( $request['ad_data'] ) ) {
			$ad_data = $this->sanitize_ad_data( $request['ad_data'] );
			update_post_meta( $post_id, '_wbam_ad_data', $ad_data );
		}

		if ( isset( $request['enabled'] ) ) {
			update_post_meta( $post_id, '_wbam_enabled', (bool) $request['enabled'] ? '1' : '0' );
		}

		if ( isset( $request['placements'] ) && is_array( $request['placements'] ) ) {
			$placements = array_map( 'sanitize_text_field', $request['placements'] );
			update_post_meta( $post_id, '_wbam_placements', $placements );
		}

		if ( isset( $request['priority'] ) ) {
			update_post_meta( $post_id, '_wbam_priority', absint( $request['priority'] ) );
		}

		do_action( 'wbam_save_ad_meta', $post_id );
	}

	/**
	 * Sanitize ad_data array recursively.
	 *
	 * @param array $data Raw ad data.
	 * @return array
	 */
	private function sanitize_ad_data( $data ) {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			$key = sanitize_key( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_ad_data( $value );
			} elseif ( 'content' === $key || 'html' === $key ) {
				$sanitized[ $key ] = wp_kses_post( $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Get ad type from _wbam_ad_data meta.
	 *
	 * @param int $ad_id Ad post ID.
	 * @return string
	 */
	private function get_ad_type_from_meta( $ad_id ) {
		$ad_data = get_post_meta( $ad_id, '_wbam_ad_data', true );
		return isset( $ad_data['type'] ) ? sanitize_text_field( $ad_data['type'] ) : '';
	}

	/**
	 * Get args schema for ad create/update.
	 *
	 * @param bool $is_update Is an update request.
	 * @return array
	 */
	private function get_ad_args( $is_update = false ) {
		return array(
			'title'      => array(
				'type'              => 'string',
				'required'          => ! $is_update,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'enabled'    => array(
				'type' => 'boolean',
			),
			'placements' => array(
				'type'  => 'array',
				'items' => array(
					'type' => 'string',
				),
			),
			'priority'   => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 10,
			),
			'ad_data'    => array(
				'type' => 'object',
			),
			'status'     => array(
				'type' => 'string',
				'enum' => array( 'publish', 'draft', 'pending' ),
			),
		);
	}

	/**
	 * Check rate limit using transients.
	 *
	 * @param string $key     Unique rate limit key.
	 * @param int    $limit   Maximum number of requests allowed.
	 * @param int    $window  Time window in seconds.
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
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			// X-Forwarded-For can be a comma-separated list; take the first.
			$parts = explode( ',', $ip );
			$ip    = trim( $parts[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}
}
