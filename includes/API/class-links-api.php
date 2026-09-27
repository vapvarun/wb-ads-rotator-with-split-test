<?php
/**
 * Links REST API
 *
 * Handles REST API routes for links, link categories, and partnerships.
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
 * Links API class.
 */
class Links_API {

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
		// Admin: list + create links.
		register_rest_route(
			self::REST_NAMESPACE,
			'/links',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_links' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'status'      => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'link_type'   => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'category_id' => array(
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'search'      => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'per_page'    => array(
							'type'              => 'integer',
							'default'           => 20,
							'minimum'           => 1,
							'maximum'           => 100,
							'sanitize_callback' => 'absint',
						),
						'page'        => array(
							'type'              => 'integer',
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_link' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_link_args(),
				),
			)
		);

		// Admin: link categories.
		register_rest_route(
			self::REST_NAMESPACE,
			'/links/categories',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_categories' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_category' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'name'        => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'slug'        => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_title',
						),
						'description' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		// Admin: partnerships list.
		register_rest_route(
			self::REST_NAMESPACE,
			'/partnerships',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_partnerships' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'status'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
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
			)
		);

		// Admin: update partnership status.
		register_rest_route(
			self::REST_NAMESPACE,
			'/partnerships/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_partnership' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'status'      => array(
						'type'              => 'string',
						'required'          => true,
						'enum'              => array( 'pending', 'accepted', 'rejected', 'spam' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'admin_notes' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		// Admin: single link CRUD.
		register_rest_route(
			self::REST_NAMESPACE,
			'/links/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_link' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_link' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_link_args( true ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_link' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		// Admin: link click stats.
		register_rest_route(
			self::REST_NAMESPACE,
			'/links/(?P<id>\d+)/stats',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_link_stats' ),
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

		// Public: track link click.
		register_rest_route(
			self::REST_NAMESPACE,
			'/links/(?P<id>\d+)/track',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'track_link_click' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'referrer' => array(
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
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
	 * GET /links — List links (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_links( $request ) {
		$manager  = \WBAM\Modules\Links\Link_Manager::get_instance();
		$per_page = absint( $request['per_page'] );
		$page     = absint( $request['page'] );

		$args = array(
			'status'      => $request['status'] ?? '',
			'link_type'   => $request['link_type'] ?? '',
			'category_id' => absint( $request['category_id'] ?? 0 ),
			'search'      => $request['search'] ?? '',
			'limit'       => $per_page,
			'offset'      => ( $page - 1 ) * $per_page,
		);

		$links = $manager->get_links( $args );
		$total = $manager->count_links( $args );

		$data = array();
		foreach ( $links as $link ) {
			$data[] = $this->prepare_link_for_response( $link );
		}

		return rest_ensure_response(
			array(
				'links'       => $data,
				'total'       => $total,
				'total_pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
				'page'        => $page,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * GET /links/{id} — Get single link (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_link( $request ) {
		$manager = \WBAM\Modules\Links\Link_Manager::get_instance();
		$link    = $manager->get( absint( $request['id'] ) );

		if ( ! $link ) {
			return new \WP_Error(
				'link_not_found',
				__( 'Link not found.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $this->prepare_link_for_response( $link ) );
	}

	/**
	 * POST /links — Create link (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_link( $request ) {
		$manager = \WBAM\Modules\Links\Link_Manager::get_instance();

		$data = array(
			'name'            => sanitize_text_field( $request['name'] ),
			'destination_url' => esc_url_raw( $request['destination_url'] ),
		);

		$optional_fields = array(
			'slug',
			'link_type',
			'description',
			'status',
			'payment_type',
			'payment_currency',
			'payment_status',
		);
		foreach ( $optional_fields as $field ) {
			if ( isset( $request[ $field ] ) ) {
				$data[ $field ] = sanitize_text_field( $request[ $field ] );
			}
		}

		$bool_fields = array( 'cloaking_enabled', 'nofollow', 'sponsored', 'new_tab' );
		foreach ( $bool_fields as $field ) {
			if ( isset( $request[ $field ] ) ) {
				$data[ $field ] = (bool) $request[ $field ] ? 1 : 0;
			}
		}

		$int_fields = array( 'category_id', 'redirect_type' );
		foreach ( $int_fields as $field ) {
			if ( isset( $request[ $field ] ) ) {
				$data[ $field ] = absint( $request[ $field ] );
			}
		}

		$float_fields = array( 'payment_amount', 'commission_rate' );
		foreach ( $float_fields as $field ) {
			if ( isset( $request[ $field ] ) ) {
				$data[ $field ] = (float) $request[ $field ];
			}
		}

		$link_id = $manager->create( $data );

		if ( ! $link_id ) {
			return new \WP_Error(
				'create_failed',
				__( 'Could not create link.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 500 )
			);
		}

		$link = $manager->get( $link_id );
		return rest_ensure_response( $this->prepare_link_for_response( $link ) );
	}

	/**
	 * PUT /links/{id} — Update link (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_link( $request ) {
		$manager = \WBAM\Modules\Links\Link_Manager::get_instance();
		$id      = absint( $request['id'] );
		$link    = $manager->get( $id );

		if ( ! $link ) {
			return new \WP_Error(
				'link_not_found',
				__( 'Link not found.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 404 )
			);
		}

		$data = array();

		if ( isset( $request['name'] ) ) {
			$data['name'] = sanitize_text_field( $request['name'] );
		}
		if ( isset( $request['destination_url'] ) ) {
			$data['destination_url'] = esc_url_raw( $request['destination_url'] );
		}

		$string_fields = array( 'slug', 'link_type', 'description', 'status', 'payment_type', 'payment_currency', 'payment_status' );
		foreach ( $string_fields as $field ) {
			if ( isset( $request[ $field ] ) ) {
				$data[ $field ] = sanitize_text_field( $request[ $field ] );
			}
		}

		$bool_fields = array( 'cloaking_enabled', 'nofollow', 'sponsored', 'new_tab' );
		foreach ( $bool_fields as $field ) {
			if ( isset( $request[ $field ] ) ) {
				$data[ $field ] = (bool) $request[ $field ] ? 1 : 0;
			}
		}

		$int_fields = array( 'category_id', 'redirect_type' );
		foreach ( $int_fields as $field ) {
			if ( isset( $request[ $field ] ) ) {
				$data[ $field ] = absint( $request[ $field ] );
			}
		}

		$float_fields = array( 'payment_amount', 'commission_rate' );
		foreach ( $float_fields as $field ) {
			if ( isset( $request[ $field ] ) ) {
				$data[ $field ] = (float) $request[ $field ];
			}
		}

		$result = $manager->update( $id, $data );

		if ( ! $result ) {
			return new \WP_Error(
				'update_failed',
				__( 'Could not update link.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 500 )
			);
		}

		$link = $manager->get( $id );
		return rest_ensure_response( $this->prepare_link_for_response( $link ) );
	}

	/**
	 * DELETE /links/{id} — Delete link (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_link( $request ) {
		$manager = \WBAM\Modules\Links\Link_Manager::get_instance();
		$id      = absint( $request['id'] );

		$link = $manager->get( $id );
		if ( ! $link ) {
			return new \WP_Error(
				'link_not_found',
				__( 'Link not found.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 404 )
			);
		}

		$result = $manager->delete( $id );

		if ( ! $result ) {
			return new \WP_Error(
				'delete_failed',
				__( 'Could not delete link.', 'wb-ads-rotator-with-split-test' ),
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
	 * GET /links/{id}/stats — Link click stats (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_link_stats( $request ) {
		global $wpdb;
		$id      = absint( $request['id'] );
		$manager = \WBAM\Modules\Links\Link_Manager::get_instance();
		$link    = $manager->get( $id );

		if ( ! $link ) {
			return new \WP_Error(
				'link_not_found',
				__( 'Link not found.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 404 )
			);
		}

		$clicks_table = $wpdb->prefix . 'wbam_link_clicks';

		$where  = array( 'link_id = %d' );
		$values = array( $id );

		if ( ! empty( $request['start_date'] ) ) {
			$where[]  = 'clicked_at >= %s';
			$values[] = sanitize_text_field( $request['start_date'] ) . ' 00:00:00';
		}

		if ( ! empty( $request['end_date'] ) ) {
			$where[]  = 'clicked_at <= %s';
			$values[] = sanitize_text_field( $request['end_date'] ) . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- WBAM custom table name from $wpdb->prefix, not user input.
		$total_clicks = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders interpolated via  /  fragments; safe.
				"SELECT COUNT(*) FROM {$clicks_table} WHERE {$where_sql}",
				$values
			)
		);

		// Unique clicks by IP.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- WBAM custom table name from $wpdb->prefix, not user input.
		$unique_clicks = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders interpolated via  /  fragments; safe.
				"SELECT COUNT(DISTINCT visitor_hash) FROM {$clicks_table} WHERE {$where_sql}",
				$values
			)
		);

		return rest_ensure_response(
			array(
				'link_id'       => $id,
				'total_clicks'  => $total_clicks,
				'unique_clicks' => $unique_clicks,
			)
		);
	}

	/**
	 * POST /links/{id}/track — Track link click (public).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function track_link_click( $request ) {
		$id      = absint( $request['id'] );
		$manager = \WBAM\Modules\Links\Link_Manager::get_instance();
		$link    = $manager->get( $id );

		if ( ! $link ) {
			return new \WP_Error(
				'link_not_found',
				__( 'Link not found.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 404 )
			);
		}

		// Rate limiting: 30 requests per minute per IP.
		$ip = $this->get_client_ip();
		if ( ! $this->check_rate_limit( 'rest_link_track_' . md5( $ip ), 30, 60 ) ) {
			return new \WP_Error(
				'rate_limited',
				__( 'Too many requests. Please try again later.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 429 )
			);
		}

		global $wpdb;
		$clicks_table = $wpdb->prefix . 'wbam_link_clicks';

		$visitor_hash = '';
		if ( ! empty( $ip ) ) {
			$daily_salt   = gmdate( 'Y-m-d' );
			$visitor_hash = wp_hash( $ip . $daily_salt );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$clicks_table,
			array(
				'link_id'      => $id,
				'visitor_hash' => $visitor_hash,
				'referrer'     => isset( $request['referrer'] ) ? esc_url_raw( $request['referrer'] ) : '',
				'clicked_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		/**
		 * Fires after a REST-tracked partnership link click is recorded.
		 *
		 * @since 2.7.0
		 * @param int    $id   Link ID.
		 * @param object $link Link row object.
		 */
		do_action( 'wbam_link_clicked', $id, $link );

		return rest_ensure_response( array( 'tracked' => true ) );
	}

	/**
	 * GET /links/categories — List link categories (admin).
	 *
	 * @param \WP_REST_Request $request Request object (unused; required by REST callback contract).
	 * @return \WP_REST_Response
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature required by REST callback contract.
	public function get_categories( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wbam_link_categories';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- WBAM custom table name from $wpdb->prefix, not user input.
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" );

		$categories = array();
		foreach ( $rows as $row ) {
			$categories[] = array(
				'id'          => (int) $row->id,
				'name'        => $row->name,
				'slug'        => $row->slug,
				'description' => isset( $row->description ) ? $row->description : '',
				// Column is `count` in the schema; expose it as link_count in the API.
				'link_count'  => isset( $row->count ) ? (int) $row->count : 0,
			);
		}

		return rest_ensure_response( array( 'categories' => $categories ) );
	}

	/**
	 * POST /links/categories — Create link category (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_category( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wbam_link_categories';

		$name        = sanitize_text_field( $request['name'] );
		$slug        = ! empty( $request['slug'] ) ? sanitize_title( $request['slug'] ) : sanitize_title( $name );
		$description = isset( $request['description'] ) ? sanitize_textarea_field( $request['description'] ) : '';

		if ( empty( $name ) ) {
			return new \WP_Error(
				'missing_name',
				__( 'Category name is required.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 400 )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => $description,
				'count'       => 0,
				'created_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error(
				'create_failed',
				__( 'Could not create category.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 500 )
			);
		}

		$category_id = $wpdb->insert_id;

		return rest_ensure_response(
			array(
				'id'          => $category_id,
				'name'        => $name,
				'slug'        => $slug,
				'description' => $description,
				'link_count'  => 0,
			)
		);
	}

	/**
	 * GET /partnerships — List partnerships (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_partnerships( $request ) {
		$manager  = \WBAM\Modules\Links\Partnership_Manager::get_instance();
		$per_page = absint( $request['per_page'] );
		$page     = absint( $request['page'] );

		$args = array(
			'status' => $request['status'] ?? '',
			'limit'  => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
		);

		$partnerships = $manager->get_partnerships( $args );
		$total        = $manager->count_partnerships( $args );

		$data = array();
		foreach ( $partnerships as $partnership ) {
			$data[] = $this->prepare_partnership_for_response( $partnership );
		}

		return rest_ensure_response(
			array(
				'partnerships' => $data,
				'total'        => $total,
				'total_pages'  => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
				'page'         => $page,
				'per_page'     => $per_page,
			)
		);
	}

	/**
	 * PUT /partnerships/{id} — Update partnership status (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_partnership( $request ) {
		$manager = \WBAM\Modules\Links\Partnership_Manager::get_instance();
		$id      = absint( $request['id'] );

		$partnership = $manager->get( $id );
		if ( ! $partnership ) {
			return new \WP_Error(
				'partnership_not_found',
				__( 'Partnership not found.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 404 )
			);
		}

		$status = sanitize_text_field( $request['status'] );
		$notes  = isset( $request['admin_notes'] ) ? sanitize_textarea_field( $request['admin_notes'] ) : '';

		// Route status transitions through the same methods the admin workflow
		// uses so their side effects (accept/reject emails, spam handling) fire.
		// A bare update() would set the column but skip all of that.
		switch ( $status ) {
			case 'accepted':
				$result = $manager->accept( $id, $notes );
				break;
			case 'rejected':
				$result = $manager->reject( $id, $notes );
				break;
			case 'spam':
				$result = $manager->mark_as_spam( $id );
				break;
			default:
				$data = array( 'status' => $status );
				if ( '' !== $notes ) {
					$data['admin_notes'] = $notes;
				}
				$result = $manager->update( $id, $data );
				break;
		}

		if ( ! $result ) {
			return new \WP_Error(
				'update_failed',
				__( 'Could not update partnership.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 500 )
			);
		}

		$partnership = $manager->get( $id );
		return rest_ensure_response( $this->prepare_partnership_for_response( $partnership ) );
	}

	/**
	 * Prepare link object for REST response.
	 *
	 * @param \WBAM\Modules\Links\Link $link Link object.
	 * @return array
	 */
	private function prepare_link_for_response( $link ) {
		return array(
			'id'               => (int) $link->id,
			'name'             => $link->name,
			'destination_url'  => $link->destination_url,
			'slug'             => $link->slug,
			'link_type'        => $link->link_type,
			'status'           => $link->status,
			'category_id'      => (int) $link->category_id,
			'click_count'      => (int) $link->click_count,
			'nofollow'         => (bool) $link->nofollow,
			'sponsored'        => (bool) $link->sponsored,
			'new_tab'          => (bool) $link->new_tab,
			'cloaking_enabled' => (bool) $link->cloaking_enabled,
			'created_at'       => $link->created_at,
		);
	}

	/**
	 * Prepare partnership object for REST response.
	 *
	 * @param object $partnership Partnership object.
	 * @return array
	 */
	private function prepare_partnership_for_response( $partnership ) {
		return array(
			'id'               => (int) $partnership->id,
			'name'             => $partnership->name,
			'email'            => $partnership->email,
			'website_url'      => $partnership->website_url,
			'partnership_type' => $partnership->partnership_type,
			'anchor_text'      => $partnership->anchor_text,
			'message'          => $partnership->message,
			'status'           => $partnership->status,
			'admin_notes'      => $partnership->admin_notes,
			'created_at'       => $partnership->created_at,
		);
	}

	/**
	 * Get args schema for link create/update.
	 *
	 * @param bool $is_update Is an update request.
	 * @return array
	 */
	private function get_link_args( $is_update = false ) {
		return array(
			'name'             => array(
				'type'              => 'string',
				'required'          => ! $is_update,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'destination_url'  => array(
				'type'              => 'string',
				'required'          => ! $is_update,
				'sanitize_callback' => 'esc_url_raw',
			),
			'slug'             => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
			),
			'link_type'        => array(
				'type'              => 'string',
				'enum'              => array( 'affiliate', 'sponsored', 'internal', 'external' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status'           => array(
				'type'              => 'string',
				'enum'              => array( 'active', 'inactive', 'expired' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'category_id'      => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'nofollow'         => array(
				'type' => 'boolean',
			),
			'sponsored'        => array(
				'type' => 'boolean',
			),
			'new_tab'          => array(
				'type' => 'boolean',
			),
			'cloaking_enabled' => array(
				'type' => 'boolean',
			),
			'redirect_type'    => array(
				'type' => 'integer',
				'enum' => array( 301, 302, 307 ),
			),
			'description'      => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'payment_amount'   => array(
				'type'    => 'number',
				'minimum' => 0,
			),
			'commission_rate'  => array(
				'type'    => 'number',
				'minimum' => 0,
				'maximum' => 100,
			),
		);
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
