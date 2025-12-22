<?php
/**
 * Partnership Manager Class
 *
 * Handles CRUD operations for link partnerships.
 *
 * @package WB_Ad_Manager
 * @since   2.2.0
 */

namespace WBAM\Modules\Links;

use WBAM\Core\Singleton;
use WBAM\Core\Privacy_Helper;

/**
 * Partnership Manager class.
 */
class Partnership_Manager {

	use Singleton;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 */
	protected function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'wbam_link_partnerships';
	}

	/**
	 * Create a new partnership inquiry.
	 *
	 * @param array $data Partnership data.
	 * @return Partnership|false Partnership object on success, false on failure.
	 */
	public function create( $data ) {
		global $wpdb;

		$defaults = array(
			'name'             => '',
			'email'            => '',
			'website_url'      => '',
			'partnership_type' => 'paid_link',
			'target_post_id'   => null,
			'anchor_text'      => '',
			'message'          => '',
			'budget_min'       => null,
			'budget_max'       => null,
			'status'           => 'pending',
			'admin_notes'      => '',
			'ip_address'       => $this->get_client_ip(),
		);

		$data = wp_parse_args( $data, $defaults );

		// Sanitize data.
		$data = $this->sanitize_data( $data );

		// Validate required fields.
		if ( empty( $data['name'] ) || empty( $data['email'] ) || empty( $data['website_url'] ) ) {
			return false;
		}

		// Validate email.
		if ( ! is_email( $data['email'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			array(
				'name'             => $data['name'],
				'email'            => $data['email'],
				'website_url'      => $data['website_url'],
				'partnership_type' => $data['partnership_type'],
				'target_post_id'   => $data['target_post_id'],
				'anchor_text'      => $data['anchor_text'],
				'message'          => $data['message'],
				'budget_min'       => $data['budget_min'],
				'budget_max'       => $data['budget_max'],
				'status'           => $data['status'],
				'admin_notes'      => $data['admin_notes'],
				'ip_address'       => $data['ip_address'],
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%f', '%f', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		$partnership_id = $wpdb->insert_id;
		$partnership    = $this->get( $partnership_id );

		do_action( 'wbam_partnership_created', $partnership );

		return $partnership;
	}

	/**
	 * Get a partnership by ID.
	 *
	 * @param int $id Partnership ID.
	 * @return Partnership|null
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

		return new Partnership( $row );
	}

	/**
	 * Get partnerships with optional filters.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of Partnership objects.
	 */
	public function get_partnerships( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'           => '',
			'partnership_type' => '',
			'search'           => '',
			'orderby'          => 'created_at',
			'order'            => 'DESC',
			'limit'            => 20,
			'offset'           => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['partnership_type'] ) ) {
			$where[]  = 'partnership_type = %s';
			$values[] = $args['partnership_type'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(name LIKE %s OR email LIKE %s OR website_url LIKE %s)';
			$search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		$allowed_orderby = array( 'id', 'name', 'email', 'created_at', 'responded_at', 'status', 'partnership_type' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$sql = "SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $where );
		$sql .= " ORDER BY {$orderby} {$order}";
		$sql .= ' LIMIT %d OFFSET %d';

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		$partnerships = array();
		foreach ( $rows as $row ) {
			$partnerships[] = new Partnership( $row );
		}

		return $partnerships;
	}

	/**
	 * Count partnerships with optional filters.
	 *
	 * @param array $args Query arguments.
	 * @return int
	 */
	public function count_partnerships( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'           => '',
			'partnership_type' => '',
			'search'           => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['partnership_type'] ) ) {
			$where[]  = 'partnership_type = %s';
			$values[] = $args['partnership_type'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(name LIKE %s OR email LIKE %s OR website_url LIKE %s)';
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
	 * Get status counts.
	 *
	 * @return array
	 */
	public function get_status_counts() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$this->table} GROUP BY status"
		);

		$counts = array(
			'all'      => 0,
			'pending'  => 0,
			'accepted' => 0,
			'rejected' => 0,
			'spam'     => 0,
		);

		foreach ( $results as $row ) {
			$counts[ $row->status ] = (int) $row->count;
			$counts['all']         += (int) $row->count;
		}

		return $counts;
	}

	/**
	 * Update a partnership.
	 *
	 * @param int   $id   Partnership ID.
	 * @param array $data Partnership data.
	 * @return bool
	 */
	public function update( $id, $data ) {
		global $wpdb;

		$existing = $this->get( $id );
		if ( ! $existing ) {
			return false;
		}

		// Sanitize data.
		$data = $this->sanitize_data( $data );

		$update_data   = array();
		$update_format = array();

		$allowed_fields = array(
			'name'             => '%s',
			'email'            => '%s',
			'website_url'      => '%s',
			'partnership_type' => '%s',
			'target_post_id'   => '%d',
			'anchor_text'      => '%s',
			'message'          => '%s',
			'budget_min'       => '%f',
			'budget_max'       => '%f',
			'status'           => '%s',
			'admin_notes'      => '%s',
			'responded_at'     => '%s',
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

		if ( false !== $result ) {
			$updated_partnership = $this->get( $id );
			do_action( 'wbam_partnership_updated', $updated_partnership, $existing );
		}

		return false !== $result;
	}

	/**
	 * Accept a partnership.
	 *
	 * @param int    $id    Partnership ID.
	 * @param string $notes Admin notes (optional).
	 * @return bool
	 */
	public function accept( $id, $notes = '' ) {
		$result = $this->update(
			$id,
			array(
				'status'       => 'accepted',
				'admin_notes'  => $notes,
				'responded_at' => current_time( 'mysql' ),
			)
		);

		if ( $result ) {
			$partnership = $this->get( $id );
			do_action( 'wbam_partnership_accepted', $partnership );
		}

		return $result;
	}

	/**
	 * Reject a partnership.
	 *
	 * @param int    $id    Partnership ID.
	 * @param string $notes Admin notes (optional).
	 * @return bool
	 */
	public function reject( $id, $notes = '' ) {
		$result = $this->update(
			$id,
			array(
				'status'       => 'rejected',
				'admin_notes'  => $notes,
				'responded_at' => current_time( 'mysql' ),
			)
		);

		if ( $result ) {
			$partnership = $this->get( $id );
			do_action( 'wbam_partnership_rejected', $partnership );
		}

		return $result;
	}

	/**
	 * Mark as spam.
	 *
	 * @param int $id Partnership ID.
	 * @return bool
	 */
	public function mark_as_spam( $id ) {
		return $this->update(
			$id,
			array(
				'status'       => 'spam',
				'responded_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Delete a partnership.
	 *
	 * @param int $id Partnership ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$partnership = $this->get( $id );
		if ( ! $partnership ) {
			return false;
		}

		do_action( 'wbam_before_partnership_delete', $id, $partnership );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$this->table,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false !== $result ) {
			do_action( 'wbam_partnership_deleted', $id );
		}

		return false !== $result;
	}

	/**
	 * Sanitize partnership data.
	 *
	 * @param array $data Raw data.
	 * @return array Sanitized data.
	 */
	private function sanitize_data( $data ) {
		if ( isset( $data['name'] ) ) {
			$data['name'] = sanitize_text_field( $data['name'] );
		}

		if ( isset( $data['email'] ) ) {
			$data['email'] = sanitize_email( $data['email'] );
		}

		if ( isset( $data['website_url'] ) ) {
			$data['website_url'] = esc_url_raw( $data['website_url'] );
		}

		if ( isset( $data['partnership_type'] ) ) {
			$valid_types = array_keys( Partnership::get_partnership_types() );
			if ( ! in_array( $data['partnership_type'], $valid_types, true ) ) {
				$data['partnership_type'] = 'paid_link';
			}
		}

		if ( isset( $data['target_post_id'] ) ) {
			$data['target_post_id'] = $data['target_post_id'] ? absint( $data['target_post_id'] ) : null;
		}

		if ( isset( $data['anchor_text'] ) ) {
			$data['anchor_text'] = sanitize_text_field( $data['anchor_text'] );
		}

		if ( isset( $data['message'] ) ) {
			$data['message'] = sanitize_textarea_field( $data['message'] );
		}

		if ( isset( $data['budget_min'] ) ) {
			$data['budget_min'] = $data['budget_min'] !== '' ? max( 0, (float) $data['budget_min'] ) : null;
		}

		if ( isset( $data['budget_max'] ) ) {
			$data['budget_max'] = $data['budget_max'] !== '' ? max( 0, (float) $data['budget_max'] ) : null;
		}

		if ( isset( $data['status'] ) ) {
			$valid_statuses = array_keys( Partnership::get_statuses() );
			if ( ! in_array( $data['status'], $valid_statuses, true ) ) {
				$data['status'] = 'pending';
			}
		}

		if ( isset( $data['admin_notes'] ) ) {
			$data['admin_notes'] = sanitize_textarea_field( $data['admin_notes'] );
		}

		return $data;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		// GDPR: Use Privacy_Helper for anonymized IP based on settings.
		return Privacy_Helper::get_storage_ip();
	}

	/**
	 * Check for duplicate submission (spam prevention).
	 *
	 * @param string $email      Email address.
	 * @param string $website    Website URL.
	 * @param int    $hours_back Hours to look back.
	 * @return bool True if duplicate exists.
	 */
	public function has_recent_submission( $email, $website, $hours_back = 24 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table}
				WHERE (email = %s OR website_url = %s)
				AND created_at > DATE_SUB(NOW(), INTERVAL %d HOUR)",
				$email,
				$website,
				$hours_back
			)
		);

		return (int) $count > 0;
	}
}
