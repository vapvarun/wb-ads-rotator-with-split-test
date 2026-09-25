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
	 * @since 3.2.0 No longer registers its own submenu page — the list now
	 *              renders inline on the Settings screen's Tools section
	 *              (see WBAM\Admin\Settings::render_tools_section()). The old
	 *              `wbam-email-captures` URL redirects there.
	 */
	public function init() {
		add_action( 'admin_post_wbam_export_email_captures', array( $this, 'handle_export' ) );
		add_action( 'admin_post_wbam_delete_email_capture', array( $this, 'handle_delete' ) );
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
	 * Total number of captures.
	 *
	 * @return int
	 */
	public function count() {
		global $wpdb;
		$table = $this->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name from $wpdb->prefix.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * A page of captures, newest first.
	 *
	 * @param int $page     1-based page number.
	 * @param int $per_page Rows per page.
	 * @return array<int, object>
	 */
	public function get_page( $page = 1, $per_page = self::PER_PAGE ) {
		global $wpdb;
		$table    = $this->table();
		$page     = max( 1, (int) $page );
		$per_page = max( 1, min( 200, (int) $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name from $wpdb->prefix (identifier, not a value; no user input reaches it); LIMIT/OFFSET are prepared below.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, ad_id, email, name, ip_address, created_at
				 FROM {$table}
				 ORDER BY created_at DESC, id DESC
				 LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Render the Email Captures list, embedded inline on the Settings screen's
	 * Tools section — no `.wrap`/page_header of its own, since the Settings
	 * screen already provides those.
	 *
	 * @since 3.2.0 Replaces the standalone `wbam-email-captures` admin page.
	 */
	public function render_embedded() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$total       = $this->count();
		$per_page    = self::PER_PAGE;
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$page        = isset( $_GET['paged'] ) ? max( 1, min( $total_pages, absint( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$rows        = $this->get_page( $page, $per_page );

		$export_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wbam_export_email_captures' ),
			'wbam_export_email_captures'
		);
		?>
		<div class="wbam-email-captures">
			<div class="wbam-settings-card__head">
				<h2 class="wbam-settings-card__title"><?php esc_html_e( 'Email Captures', 'wb-ads-rotator-with-split-test' ); ?></h2>
				<p class="wbam-settings-card__desc">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of captured emails */
							_n( '%s captured email address.', '%s captured email addresses.', $total, 'wb-ads-rotator-with-split-test' ),
							number_format_i18n( $total )
						)
					);
					?>
				</p>
				<?php if ( $total > 0 ) : ?>
					<p><a href="<?php echo esc_url( $export_url ); ?>" class="wbam-admin-btn wbam-admin-btn--primary"><?php esc_html_e( 'Export CSV', 'wb-ads-rotator-with-split-test' ); ?></a></p>
				<?php endif; ?>
			</div>

			<?php if ( empty( $rows ) ) : ?>
				<?php
				echo wp_kses_post(
					\WBAM\Admin\UX::empty_state(
						array(
							'icon'    => 'mail',
							'title'   => __( 'No email captures yet', 'wb-ads-rotator-with-split-test' ),
							'message' => __( 'Submissions from the Email Capture ad type appear here.', 'wb-ads-rotator-with-split-test' ),
						)
					)
				);
				?>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Email', 'wb-ads-rotator-with-split-test' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Name', 'wb-ads-rotator-with-split-test' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Ad', 'wb-ads-rotator-with-split-test' ); ?></th>
							<th scope="col"><?php esc_html_e( 'IP', 'wb-ads-rotator-with-split-test' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Date', 'wb-ads-rotator-with-split-test' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Action', 'wb-ads-rotator-with-split-test' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$ad_title   = $row->ad_id ? get_the_title( (int) $row->ad_id ) : '';
							$delete_url = wp_nonce_url(
								admin_url( 'admin-post.php?action=wbam_delete_email_capture&id=' . (int) $row->id . '&paged=' . $page ),
								'wbam_delete_email_capture_' . (int) $row->id
							);
							?>
							<tr>
								<td><?php echo esc_html( $row->email ); ?></td>
								<td><?php echo esc_html( $row->name ); ?></td>
								<td><?php echo $ad_title ? esc_html( $ad_title ) : esc_html( '#' . (int) $row->ad_id ); ?></td>
								<td><?php echo esc_html( $row->ip_address ); ?></td>
								<td><?php echo esc_html( $row->created_at ); ?></td>
								<td>
									<a href="<?php echo esc_url( $delete_url ); ?>" class="submitdelete" data-wbam-confirm="<?php echo esc_attr__( 'Delete this capture? This cannot be undone.', 'wb-ads-rotator-with-split-test' ); ?>" data-wbam-confirm-tone="danger">
										<?php esc_html_e( 'Delete', 'wb-ads-rotator-with-split-test' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav bottom">
						<div class="tablenav-pages">
							<?php
							echo wp_kses_post(
								paginate_links(
									array(
										'base'      => add_query_arg( 'paged', '%#%' ),
										'format'    => '',
										'current'   => $page,
										'total'     => $total_pages,
										'prev_text' => __( '&laquo; Previous', 'wb-ads-rotator-with-split-test' ),
										'next_text' => __( 'Next &raquo;', 'wb-ads-rotator-with-split-test' ),
									)
								)
							);
							?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * admin-post: stream all captures as a CSV download.
	 */
	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export.', 'wb-ads-rotator-with-split-test' ) );
		}
		check_admin_referer( 'wbam_export_email_captures' );

		global $wpdb;
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; full export for the site owner.
		$rows = $wpdb->get_results( "SELECT ad_id, email, name, ip_address, created_at FROM {$table} ORDER BY created_at DESC, id DESC" );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="wbam-email-captures-' . gmdate( 'Y-m-d' ) . '.csv"' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Email', 'Name', 'Ad', 'Ad ID', 'IP', 'Date' ) );

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				fputcsv(
					$out,
					array(
						$row->email,
						$row->name,
						$row->ad_id ? get_the_title( (int) $row->ad_id ) : '',
						(int) $row->ad_id,
						$row->ip_address,
						$row->created_at,
					)
				);
			}
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://output.
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
			add_query_arg(
				array(
					'paged'   => $paged,
					'deleted' => 1,
				),
				\WBAM\Core\Admin_Links::settings( 'email-captures' )
			)
		);
		exit;
	}
}
