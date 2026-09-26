<?php
/**
 * Email Captures admin screen — the read/export/erase surface for the
 * `wbam_email_submissions` table filled by the Email Capture ad type.
 *
 * The capture form wrote every submission (name / email / IP) to a table with
 * NO way to view, export, or delete it from the admin — a GDPR liability and a
 * dead-end "feature". This screen is that missing read surface (backend read;
 * REST read lives in the Links/Email API). Site owners can list captures,
 * export them to CSV, and delete individual rows to satisfy erasure requests.
 *
 * @package WBAM\Admin
 */

namespace WBAM\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Captures list + export + erase screen.
 */
class Email_Captures {

	const PER_PAGE = 25;

	/**
	 * Hook into admin.
	 *
	 * @since 3.2.0 Rendered inline on the Settings screen's Tools section for
	 *              one release; moved to its own submenu (owner decision,
	 *              card 10343706274) — Tools is demo-data/maintenance
	 *              utilities, not a list-with-search-and-bulk-actions screen,
	 *              and Email Captures is advertiser-lead data, so it now
	 *              sits under Advertisers (see `wbam_admin_menu_section_map`
	 *              in Pro; falls into the unlabelled catch-all on a
	 *              FREE-only site).
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_wbam_export_email_captures', array( $this, 'handle_export' ) );
		add_action( 'admin_post_wbam_delete_email_capture', array( $this, 'handle_delete' ) );
		add_action( 'admin_init', array( $this, 'handle_bulk_delete' ) );
	}

	/**
	 * Register the Email Captures submenu page.
	 *
	 * @since 3.2.0
	 */
	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=wbam-ad',
			__( 'Email Captures', 'wb-ads-rotator-with-split-test' ),
			__( 'Email Captures', 'wb-ads-rotator-with-split-test' ),
			'manage_options',
			'wbam-email-captures',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'wbam_email_submissions';
	}

	/**
	 * WHERE clause and values for the list, its total and the export.
	 *
	 * @param array<string, mixed> $args search (email or name), ad_id.
	 * @return array{0: string, 1: array<int, int|string>}
	 */
	private function where( array $args ) {
		global $wpdb;

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]  = '( email LIKE %s OR name LIKE %s )';
			$values[] = $like;
			$values[] = $like;
		}
		if ( ! empty( $args['ad_id'] ) ) {
			$where[]  = 'ad_id = %d';
			$values[] = absint( $args['ad_id'] );
		}

		return array( implode( ' AND ', $where ), $values );
	}

	/**
	 * Number of captures matching the filters.
	 *
	 * @param array<string, mixed> $args See where().
	 * @return int
	 */
	public function count( array $args = array() ) {
		global $wpdb;
		$table = $this->table();

		list( $where, $values ) = $this->where( $args );
		$sql                    = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name from $wpdb->prefix; values bound via prepare().
		return (int) $wpdb->get_var( $values ? $wpdb->prepare( $sql, $values ) : $sql );
	}

	/**
	 * A page of captures, newest first unless sorted.
	 *
	 * @param int   $page     1-based page number.
	 * @param int   $per_page Rows per page.
	 * @param array<string, mixed> $args See where(), plus orderby (email|created_at) and order.
	 * @return array<int, object>
	 */
	public function get_page( $page = 1, $per_page = self::PER_PAGE, array $args = array() ) {
		global $wpdb;
		$table    = $this->table();
		$page     = max( 1, (int) $page );
		$per_page = max( 1, min( 500, (int) $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		list( $where, $values ) = $this->where( $args );

		$orderby = isset( $args['orderby'] ) && 'email' === $args['orderby'] ? 'email' : 'id';
		$order   = isset( $args['order'] ) && 'asc' === strtolower( (string) $args['order'] ) ? 'ASC' : 'DESC';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name from $wpdb->prefix; allow-listed order; values bound via prepare().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, ad_id, email, name, ip_address, created_at
				 FROM {$table}
				 WHERE {$where}
				 ORDER BY {$orderby} {$order}
				 LIMIT %d OFFSET %d",
				array_merge( $values, array( $per_page, $offset ) )
			)
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Ads that have captures, for the list's ad filter.
	 *
	 * @return array<int, string> Ad ID => title.
	 */
	public function captured_ads() {
		global $wpdb;
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name from $wpdb->prefix; one row per ad.
		$ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT ad_id FROM {$table} WHERE ad_id > 0 LIMIT 200" ) );
		_prime_post_caches( $ids, false, false );

		$ads = array();
		foreach ( $ids as $id ) {
			$ads[ $id ] = get_the_title( $id );
		}
		asort( $ads );

		return $ads;
	}

	/**
	 * The list and export filters from the request.
	 *
	 * @return array{search: string, ad_id: int, orderby: string, order: string}
	 */
	public static function request_args() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters; export and delete verify their own nonces.
		return array(
			'search'  => isset( $_GET['s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : '',
			'ad_id'   => isset( $_GET['capture_ad'] ) ? absint( $_GET['capture_ad'] ) : 0,
			'orderby' => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '',
			'order'   => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Render the Email Captures list screen: its own `.wrap`/page header,
	 * search box, and bulk-action list table.
	 *
	 * @since 3.2.0 Was embedded inline on the Settings screen's Tools
	 *              section; moved to its own submenu — see init()'s docblock.
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$table = new Email_Captures_List_Table( $this );
		$table->prepare_items();
		$total = $this->count();
		?>
		<div class="wrap wbam-admin">
			<?php
			\WBAM\Admin\UX::page_header(
				array(
					'title' => __( 'Email Captures', 'wb-ads-rotator-with-split-test' ),
					'desc'  => sprintf(
						/* translators: %s: number of captured emails */
						_n( '%s captured email address.', '%s captured email addresses.', $total, 'wb-ads-rotator-with-split-test' ),
						number_format_i18n( $total )
					),
				)
			);
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice after the delete redirect.
			$deleted = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0;
			if ( $deleted ) {
				/* translators: %s: number of deleted captures */
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%s capture deleted.', '%s captures deleted.', $deleted, 'wb-ads-rotator-with-split-test' ), number_format_i18n( $deleted ) ) ) . '</p></div>';
			}
			?>
			<form method="get">
				<input type="hidden" name="post_type" value="wbam-ad">
				<input type="hidden" name="page" value="wbam-email-captures">
				<?php
				if ( $total > 0 ) {
					$table->search_box( __( 'Search captures', 'wb-ads-rotator-with-split-test' ), 'wbam-captures' );
				}
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * admin-post: stream the filtered captures as a CSV download, 500 rows
	 * at a time.
	 */
	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export.', 'wb-ads-rotator-with-split-test' ) );
		}
		check_admin_referer( 'wbam_export_email_captures' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="wbam-email-captures-' . gmdate( 'Y-m-d' ) . '.csv"' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			exit;
		}
		$this->stream_csv( $out, self::request_args() );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://output.
		exit;
	}

	/**
	 * Write the filtered captures to a CSV handle, 500 rows at a time.
	 *
	 * @param resource             $handle Writable handle.
	 * @param array<string, mixed> $args   See get_page().
	 * @return void
	 */
	public function stream_csv( $handle, array $args ) {
		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		fputcsv( $handle, array( 'Email', 'Name', 'Ad', 'Ad ID', 'IP', 'Date' ) );

		$page = 1;
		do {
			$rows = $this->get_page( $page, 500, $args );
			_prime_post_caches( array_filter( array_map( 'intval', wp_list_pluck( $rows, 'ad_id' ) ) ), false, false );
			foreach ( $rows as $row ) {
				fputcsv(
					$handle,
					array(
						$row->email,
						$row->name,
						$row->ad_id ? get_the_title( (int) $row->ad_id ) : '',
						(int) $row->ad_id,
						$row->ip_address,
						mysql2date( $date_format, $row->created_at ),
					)
				);
			}
			++$page;
			$fetched = count( $rows );
		} while ( 500 === $fetched );
	}

	/**
	 * Delete bulk-selected captures from the list (GDPR erasure), then
	 * return to it. Hooked on admin_init: the list form is a GET form, as on
	 * every WordPress list.
	 *
	 * @return void
	 */
	public function handle_bulk_delete() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- gatekeeper; the nonce is checked below.
		if ( ! isset( $_GET['page'], $_GET['capture_ids'] ) || 'wbam-email-captures' !== $_GET['page'] ) {
			return;
		}
		$action = isset( $_GET['action'] ) && '-1' !== $_GET['action'] ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ( isset( $_GET['action2'] ) ? sanitize_key( wp_unslash( $_GET['action2'] ) ) : '' );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( 'delete_captures' !== $action ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete captures.', 'wb-ads-rotator-with-split-test' ) );
		}
		check_admin_referer( 'bulk-captures' );

		$ids     = array_filter( array_map( 'absint', (array) wp_unslash( $_GET['capture_ids'] ) ) );
		$deleted = 0;
		if ( $ids ) {
			global $wpdb;
			$table = $this->table();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; IDs bound via prepare().
			$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', $ids ) );
		}

		wp_safe_redirect( \WBAM\Core\Admin_Links::email_captures( array( 'deleted' => $deleted ) ) );
		exit;
	}

	/**
	 * admin-post: delete a single capture (GDPR erasure).
	 */
	public function handle_delete() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete captures.', 'wb-ads-rotator-with-split-test' ) );
		}
		check_admin_referer( 'wbam_delete_email_capture_' . $id );

		if ( $id > 0 ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no WP API; id is bound as %d.
			$wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
		}

		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		wp_safe_redirect(
			\WBAM\Core\Admin_Links::email_captures(
				array(
					'paged'   => $paged,
					'deleted' => 1,
				)
			)
		);
		exit;
	}
}
