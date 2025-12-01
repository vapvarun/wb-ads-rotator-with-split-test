<?php
/**
 * Link Manager Class
 *
 * Handles CRUD operations for links.
 *
 * @package WB_Ad_Manager
 * @since   2.1.0
 */

namespace WBAM\Modules\Links;

use WBAM\Core\Singleton;

/**
 * Link Manager class.
 */
class Link_Manager {

	use Singleton;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Categories table name.
	 *
	 * @var string
	 */
	private $categories_table;

	/**
	 * Clicks table name.
	 *
	 * @var string
	 */
	private $clicks_table;

	/**
	 * Constructor.
	 */
	protected function __construct() {
		global $wpdb;
		$this->table            = $wpdb->prefix . 'wbam_links';
		$this->categories_table = $wpdb->prefix . 'wbam_link_categories';
		$this->clicks_table     = $wpdb->prefix . 'wbam_link_clicks';
	}

	/**
	 * Create a new link.
	 *
	 * @param array $data Link data.
	 * @return int|false Link ID on success, false on failure.
	 */
	public function create( $data ) {
		global $wpdb;

		$defaults = array(
			'name'             => '',
			'destination_url'  => '',
			'slug'             => '',
			'link_type'        => 'affiliate',
			'cloaking_enabled' => 1,
			'nofollow'         => 1,
			'sponsored'        => 0,
			'new_tab'          => 1,
			'category_id'      => 0,
			'description'      => '',
			'parameters'       => '',
			'redirect_type'    => 307,
			'status'           => 'active',
			'expires_at'       => null,
			'payment_amount'   => 0.00,
			'payment_type'     => 'one_time',
			'payment_currency' => 'USD',
			'payment_status'   => 'unpaid',
			'commission_rate'  => 0.00,
			'total_revenue'    => 0.00,
			'created_by'       => get_current_user_id(),
		);

		$data = wp_parse_args( $data, $defaults );

		// Generate slug if empty.
		if ( empty( $data['slug'] ) && ! empty( $data['name'] ) ) {
			$data['slug'] = $this->generate_unique_slug( $data['name'] );
		}

		// Sanitize data.
		$data = $this->sanitize_link_data( $data );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			array(
				'name'             => $data['name'],
				'destination_url'  => $data['destination_url'],
				'slug'             => $data['slug'],
				'link_type'        => $data['link_type'],
				'cloaking_enabled' => $data['cloaking_enabled'],
				'nofollow'         => $data['nofollow'],
				'sponsored'        => $data['sponsored'],
				'new_tab'          => $data['new_tab'],
				'category_id'      => $data['category_id'],
				'description'      => $data['description'],
				'parameters'       => $data['parameters'],
				'redirect_type'    => $data['redirect_type'],
				'status'           => $data['status'],
				'expires_at'       => $data['expires_at'],
				'payment_amount'   => $data['payment_amount'],
				'payment_type'     => $data['payment_type'],
				'payment_currency' => $data['payment_currency'],
				'payment_status'   => $data['payment_status'],
				'commission_rate'  => $data['commission_rate'],
				'total_revenue'    => $data['total_revenue'],
				'created_by'       => $data['created_by'],
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%f', '%f', '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		$link_id = $wpdb->insert_id;

		// Update category count.
		if ( ! empty( $data['category_id'] ) ) {
			$this->update_category_count( $data['category_id'] );
		}

		do_action( 'wbam_link_created', $link_id, $data );

		return $link_id;
	}

	/**
	 * Get a link by ID.
	 *
	 * @param int $id Link ID.
	 * @return Link|null
	 */
	public function get( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$id
			)
		);

		if ( ! $row ) {
			return null;
		}

		return new Link( $row );
	}

	/**
	 * Get a link by slug.
	 *
	 * @param string $slug Link slug.
	 * @return Link|null
	 */
	public function get_by_slug( $slug ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE slug = %s",
				$slug
			)
		);

		if ( ! $row ) {
			return null;
		}

		return new Link( $row );
	}

	/**
	 * Get links with optional filters.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of Link objects.
	 */
	public function get_links( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'      => '',
			'link_type'   => '',
			'category_id' => 0,
			'search'      => '',
			'orderby'     => 'created_at',
			'order'       => 'DESC',
			'limit'       => 20,
			'offset'      => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['link_type'] ) ) {
			$where[]  = 'link_type = %s';
			$values[] = $args['link_type'];
		}

		if ( ! empty( $args['category_id'] ) ) {
			$where[]  = 'category_id = %d';
			$values[] = $args['category_id'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(name LIKE %s OR destination_url LIKE %s OR slug LIKE %s)';
			$search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		$allowed_orderby = array( 'id', 'name', 'click_count', 'created_at', 'updated_at', 'status' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$sql = "SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $where );
		$sql .= " ORDER BY {$orderby} {$order}";
		$sql .= ' LIMIT %d OFFSET %d';

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		$links = array();
		foreach ( $rows as $row ) {
			$links[] = new Link( $row );
		}

		return $links;
	}

	/**
	 * Count links with optional filters.
	 *
	 * @param array $args Query arguments.
	 * @return int
	 */
	public function count_links( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'      => '',
			'link_type'   => '',
			'category_id' => 0,
			'search'      => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['link_type'] ) ) {
			$where[]  = 'link_type = %s';
			$values[] = $args['link_type'];
		}

		if ( ! empty( $args['category_id'] ) ) {
			$where[]  = 'category_id = %d';
			$values[] = $args['category_id'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(name LIKE %s OR destination_url LIKE %s OR slug LIKE %s)';
			$search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		$sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode( ' AND ', $where );

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Update a link.
	 *
	 * @param int   $id   Link ID.
	 * @param array $data Link data.
	 * @return bool
	 */
	public function update( $id, $data ) {
		global $wpdb;

		$existing = $this->get( $id );
		if ( ! $existing ) {
			return false;
		}

		// Sanitize data.
		$data = $this->sanitize_link_data( $data );

		$update_data   = array();
		$update_format = array();

		$allowed_fields = array(
			'name'             => '%s',
			'destination_url'  => '%s',
			'slug'             => '%s',
			'link_type'        => '%s',
			'cloaking_enabled' => '%d',
			'nofollow'         => '%d',
			'sponsored'        => '%d',
			'new_tab'          => '%d',
			'category_id'      => '%d',
			'description'      => '%s',
			'parameters'       => '%s',
			'redirect_type'    => '%d',
			'status'           => '%s',
			'expires_at'       => '%s',
			'payment_amount'   => '%f',
			'payment_type'     => '%s',
			'payment_currency' => '%s',
			'payment_status'   => '%s',
			'commission_rate'  => '%f',
			'total_revenue'    => '%f',
		);

		foreach ( $allowed_fields as $field => $format ) {
			if ( isset( $data[ $field ] ) ) {
				$update_data[ $field ] = $data[ $field ];
				$update_format[]       = $format;
			}
		}

		if ( empty( $update_data ) ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			$update_data,
			array( 'id' => $id ),
			$update_format,
			array( '%d' )
		);

		// Update category counts if category changed.
		if ( isset( $data['category_id'] ) && $data['category_id'] !== $existing->category_id ) {
			if ( $existing->category_id ) {
				$this->update_category_count( $existing->category_id );
			}
			if ( $data['category_id'] ) {
				$this->update_category_count( $data['category_id'] );
			}
		}

		do_action( 'wbam_link_updated', $id, $data );

		return false !== $result;
	}

	/**
	 * Delete a link.
	 *
	 * @param int $id Link ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$link = $this->get( $id );
		if ( ! $link ) {
			return false;
		}

		do_action( 'wbam_before_link_delete', $id, $link );

		// Delete click records.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$this->clicks_table,
			array( 'link_id' => $id ),
			array( '%d' )
		);

		// Delete the link.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$this->table,
			array( 'id' => $id ),
			array( '%d' )
		);

		// Update category count.
		if ( $link->category_id ) {
			$this->update_category_count( $link->category_id );
		}

		do_action( 'wbam_link_deleted', $id );

		return false !== $result;
	}

	/**
	 * Increment click count for a link.
	 *
	 * @param int $id Link ID.
	 * @return bool
	 */
	public function increment_clicks( $id ) {
		global $wpdb;

		// Update click count on link.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table} SET click_count = click_count + 1 WHERE id = %d",
				$id
			)
		);

		// Record click.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->clicks_table,
			array( 'link_id' => $id ),
			array( '%d' )
		);

		do_action( 'wbam_link_clicked', $id );

		return false !== $result;
	}

	/**
	 * Generate a unique slug.
	 *
	 * @param string $name Base name.
	 * @return string
	 */
	public function generate_unique_slug( $name ) {
		$slug = sanitize_title( $name );

		if ( empty( $slug ) ) {
			$slug = 'link-' . wp_rand( 1000, 9999 );
		}

		$original_slug = $slug;
		$counter       = 1;

		while ( $this->slug_exists( $slug ) ) {
			$slug = $original_slug . '-' . $counter;
			++$counter;
		}

		return $slug;
	}

	/**
	 * Check if slug exists.
	 *
	 * @param string   $slug    Slug to check.
	 * @param int|null $exclude Link ID to exclude.
	 * @return bool
	 */
	public function slug_exists( $slug, $exclude = null ) {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$this->table} WHERE slug = %s";
		$values = array( $slug );

		if ( $exclude ) {
			$sql .= ' AND id != %d';
			$values[] = $exclude;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) ) > 0;
	}

	/**
	 * Sanitize link data.
	 *
	 * @param array $data Raw data.
	 * @return array Sanitized data.
	 */
	private function sanitize_link_data( $data ) {
		if ( isset( $data['name'] ) ) {
			$data['name'] = sanitize_text_field( $data['name'] );
		}

		if ( isset( $data['destination_url'] ) ) {
			$data['destination_url'] = esc_url_raw( $data['destination_url'] );
		}

		if ( isset( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['slug'] );
		}

		if ( isset( $data['link_type'] ) ) {
			$valid_types = array_keys( Link::get_link_types() );
			if ( ! in_array( $data['link_type'], $valid_types, true ) ) {
				$data['link_type'] = 'affiliate';
			}
		}

		if ( isset( $data['redirect_type'] ) ) {
			$valid_redirects = array_keys( Link::get_redirect_types() );
			if ( ! in_array( (int) $data['redirect_type'], $valid_redirects, true ) ) {
				$data['redirect_type'] = 307;
			}
		}

		if ( isset( $data['status'] ) ) {
			$valid_statuses = array_keys( Link::get_statuses() );
			if ( ! in_array( $data['status'], $valid_statuses, true ) ) {
				$data['status'] = 'active';
			}
		}

		if ( isset( $data['description'] ) ) {
			$data['description'] = sanitize_textarea_field( $data['description'] );
		}

		if ( isset( $data['parameters'] ) && is_array( $data['parameters'] ) ) {
			$data['parameters'] = wp_json_encode( $data['parameters'] );
		}

		// Cast boolean fields.
		$bool_fields = array( 'cloaking_enabled', 'nofollow', 'sponsored', 'new_tab' );
		foreach ( $bool_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (int) (bool) $data[ $field ];
			}
		}

		// Cast integer fields.
		$int_fields = array( 'category_id', 'redirect_type', 'created_by' );
		foreach ( $int_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (int) $data[ $field ];
			}
		}

		// Sanitize payment fields.
		if ( isset( $data['payment_type'] ) ) {
			$valid_payment_types = array_keys( Link::get_payment_types() );
			if ( ! in_array( $data['payment_type'], $valid_payment_types, true ) ) {
				$data['payment_type'] = 'one_time';
			}
		}

		if ( isset( $data['payment_status'] ) ) {
			$valid_payment_statuses = array_keys( Link::get_payment_statuses() );
			if ( ! in_array( $data['payment_status'], $valid_payment_statuses, true ) ) {
				$data['payment_status'] = 'unpaid';
			}
		}

		if ( isset( $data['payment_currency'] ) ) {
			$data['payment_currency'] = strtoupper( sanitize_text_field( $data['payment_currency'] ) );
			if ( strlen( $data['payment_currency'] ) !== 3 ) {
				$data['payment_currency'] = 'USD';
			}
		}

		// Cast float fields (payment amounts).
		$float_fields = array( 'payment_amount', 'commission_rate', 'total_revenue' );
		foreach ( $float_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = max( 0, (float) $data[ $field ] );
			}
		}

		return $data;
	}

	// -------------------------------------------------------------------------
	// Category Methods
	// -------------------------------------------------------------------------

	/**
	 * Create a category.
	 *
	 * @param array $data Category data.
	 * @return int|false Category ID on success, false on failure.
	 */
	public function create_category( $data ) {
		global $wpdb;

		$defaults = array(
			'name'        => '',
			'slug'        => '',
			'description' => '',
			'parent_id'   => 0,
		);

		$data = wp_parse_args( $data, $defaults );

		$data['name'] = sanitize_text_field( $data['name'] );

		if ( empty( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		} else {
			$data['slug'] = sanitize_title( $data['slug'] );
		}

		// Ensure unique slug.
		$original_slug = $data['slug'];
		$counter       = 1;
		while ( $this->category_slug_exists( $data['slug'] ) ) {
			$data['slug'] = $original_slug . '-' . $counter;
			++$counter;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->categories_table,
			array(
				'name'        => $data['name'],
				'slug'        => $data['slug'],
				'description' => sanitize_textarea_field( $data['description'] ),
				'parent_id'   => (int) $data['parent_id'],
			),
			array( '%s', '%s', '%s', '%d' )
		);

		return false !== $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get a category.
	 *
	 * @param int $id Category ID.
	 * @return object|null
	 */
	public function get_category( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->categories_table} WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Get all categories.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_categories( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'parent_id' => null,
			'orderby'   => 'name',
			'order'     => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( null !== $args['parent_id'] ) {
			$where[]  = 'parent_id = %d';
			$values[] = (int) $args['parent_id'];
		}

		$allowed_orderby = array( 'id', 'name', 'slug', 'count', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'name';
		$order           = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		$sql = "SELECT * FROM {$this->categories_table} WHERE " . implode( ' AND ', $where );
		$sql .= " ORDER BY {$orderby} {$order}";

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql );
	}

	/**
	 * Update a category.
	 *
	 * @param int   $id   Category ID.
	 * @param array $data Category data.
	 * @return bool
	 */
	public function update_category( $id, $data ) {
		global $wpdb;

		$update_data   = array();
		$update_format = array();

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
			$update_format[]     = '%s';
		}

		if ( isset( $data['slug'] ) ) {
			$update_data['slug'] = sanitize_title( $data['slug'] );
			$update_format[]     = '%s';
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = sanitize_textarea_field( $data['description'] );
			$update_format[]            = '%s';
		}

		if ( isset( $data['parent_id'] ) ) {
			$update_data['parent_id'] = (int) $data['parent_id'];
			$update_format[]          = '%d';
		}

		if ( empty( $update_data ) ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->categories_table,
			$update_data,
			array( 'id' => $id ),
			$update_format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a category.
	 *
	 * @param int $id Category ID.
	 * @return bool
	 */
	public function delete_category( $id ) {
		global $wpdb;

		// Set links in this category to uncategorized.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			array( 'category_id' => 0 ),
			array( 'category_id' => $id ),
			array( '%d' ),
			array( '%d' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$this->categories_table,
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Check if category slug exists.
	 *
	 * @param string   $slug    Slug to check.
	 * @param int|null $exclude Category ID to exclude.
	 * @return bool
	 */
	private function category_slug_exists( $slug, $exclude = null ) {
		global $wpdb;

		$sql    = "SELECT COUNT(*) FROM {$this->categories_table} WHERE slug = %s";
		$values = array( $slug );

		if ( $exclude ) {
			$sql     .= ' AND id != %d';
			$values[] = $exclude;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) ) > 0;
	}

	/**
	 * Update category link count.
	 *
	 * @param int $category_id Category ID.
	 */
	private function update_category_count( $category_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE category_id = %d",
				$category_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->categories_table,
			array( 'count' => $count ),
			array( 'id' => $category_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Get click statistics for a link.
	 *
	 * @param int    $link_id Link ID.
	 * @param string $period  Period (today, week, month, all).
	 * @return int
	 */
	public function get_click_count( $link_id, $period = 'all' ) {
		global $wpdb;

		$where  = 'link_id = %d';
		$values = array( $link_id );

		switch ( $period ) {
			case 'today':
				$where   .= ' AND DATE(clicked_at) = CURDATE()';
				break;
			case 'week':
				$where   .= ' AND clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
				break;
			case 'month':
				$where   .= ' AND clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
				break;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->clicks_table} WHERE {$where}",
				$values
			)
		);
	}

	/**
	 * Get all active links for dropdown/selection.
	 *
	 * @return array
	 */
	public function get_active_links_for_select() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT id, name, destination_url FROM {$this->table} WHERE status = 'active' ORDER BY name ASC"
		);

		$options = array();
		foreach ( $rows as $row ) {
			$options[ $row->id ] = $row->name;
		}

		return $options;
	}
}
