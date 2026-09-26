<?php
/**
 * Partnership Admin Class
 *
 * Admin interface for managing link partnership inquiries.
 *
 * @package WB_Ad_Manager
 * @since   2.2.0
 */

namespace WBAM\Modules\Links;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use WBAM\Core\Singleton;

/**
 * Partnership Admin class.
 */
class Partnership_Admin {

	use Singleton;

	/**
	 * Manager instance.
	 *
	 * @var Partnership_Manager
	 */
	private $manager;

	/**
	 * The screen's hook suffix, as returned by add_submenu_page() - the
	 * actual value depends on which top-level menu 'edit.php?post_type=wbam-ad'
	 * resolves to (Pro active vs Free-only change it), so a hardcoded
	 * 'links_page_wbam-partnerships' broke enqueue_scripts() with Pro active.
	 *
	 * @since 3.2.0
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->manager = Partnership_Manager::get_instance();
	}

	/**
	 * Initialize admin.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 22 ); // After Links Admin (21).
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add pending count to menu.
		add_action( 'admin_menu', array( $this, 'add_pending_count_bubble' ), 99 );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		if ( ! \WBAM\Core\Settings_Helper::is_module_enabled( 'links' ) ) {
			return;
		}

		$this->page_hook = add_submenu_page(
			'edit.php?post_type=wbam-ad',
			__( 'Partnership Inquiries', 'wb-ads-rotator-with-split-test' ),
			__( 'Partnerships', 'wb-ads-rotator-with-split-test' ),
			'manage_options',
			'wbam-partnerships',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Add pending count bubble to menu.
	 */
	public function add_pending_count_bubble() {
		global $menu, $submenu;

		// Bail before the count query when there is no menu to badge.
		if ( ! isset( $submenu['edit.php?post_type=wbam-ad'] ) ) {
			return;
		}

		$pending_count = $this->manager->count_partnerships( array( 'status' => 'pending' ) );

		if ( $pending_count > 0 && isset( $submenu['edit.php?post_type=wbam-ad'] ) ) {
			foreach ( $submenu['edit.php?post_type=wbam-ad'] as $key => $item ) {
				if ( isset( $item[2] ) && 'wbam-partnerships' === $item[2] ) {
					// Mutating $submenu is the standard WP pattern for injecting
					// a pending-count badge onto a submenu item (e.g. Posts → "3").
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional $submenu[label][0] edit to add pending-count badge.
					$submenu['edit.php?post_type=wbam-ad'][ $key ][0] .= sprintf(
						' <span class="awaiting-mod count-%d"><span class="pending-count">%d</span></span>',
						$pending_count,
						$pending_count
					);
					break;
				}
			}
		}
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		if ( ! $this->page_hook || $this->page_hook !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wbam-partnership-admin',
			WBAM_URL . 'assets/css/partnership-admin.css',
			array( 'wbam-admin-tokens' ),
			WBAM_VERSION
		);
	}

	/**
	 * Handle admin actions.
	 */
	public function handle_actions() {
		if ( ! isset( $_GET['page'] ) || 'wbam-partnerships' !== $_GET['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Bulk actions from the list: bulk_action (top) or bulk_action2
		// (bottom) + partnership_ids[].
		if ( isset( $_GET['partnership_ids'], $_GET['_wpnonce'] ) ) {
			check_admin_referer( 'bulk-partnerships' );

			$bulk = isset( $_GET['bulk_action'] ) && '-1' !== $_GET['bulk_action'] ? sanitize_key( wp_unslash( $_GET['bulk_action'] ) ) : '';
			if ( '' === $bulk && isset( $_GET['bulk_action2'] ) ) {
				$bulk = sanitize_key( wp_unslash( $_GET['bulk_action2'] ) );
			}
			$methods = array(
				'accept' => 'accept',
				'reject' => 'reject',
				'spam'   => 'mark_as_spam',
				'delete' => 'delete',
			);
			$done    = 0;
			if ( isset( $methods[ $bulk ] ) ) {
				foreach ( array_filter( array_map( 'absint', (array) wp_unslash( $_GET['partnership_ids'] ) ) ) as $id ) {
					$done += $this->manager->{$methods[ $bulk ]}( $id ) ? 1 : 0;
				}
			}

			wp_safe_redirect(
				add_query_arg(
					array(
						'message' => $done ? 'bulk_' . $bulk : 'bulk_none',
						'count'   => $done,
					),
					admin_url( 'admin.php?page=wbam-partnerships' )
				)
			);
			exit;
		}

		// Handle single actions.
		if ( isset( $_GET['action'] ) && isset( $_GET['partnership_id'] ) && isset( $_GET['_wpnonce'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
			$id     = absint( $_GET['partnership_id'] );

			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wbam_partnership_' . $action . '_' . $id ) ) {
				wp_die( esc_html__( 'Security check failed.', 'wb-ads-rotator-with-split-test' ) );
			}

			$redirect_url = admin_url( 'admin.php?page=wbam-partnerships' );
			$message      = '';

			switch ( $action ) {
				case 'accept':
					if ( $this->manager->accept( $id ) ) {
						$message = 'accepted';
					}
					break;

				case 'reject':
					if ( $this->manager->reject( $id ) ) {
						$message = 'rejected';
					}
					break;

				case 'spam':
					if ( $this->manager->mark_as_spam( $id ) ) {
						$message = 'spam';
					}
					break;

				case 'delete':
					if ( $this->manager->delete( $id ) ) {
						$message = 'deleted';
					}
					break;
			}

			if ( $message ) {
				$redirect_url = add_query_arg( 'message', $message, $redirect_url );
			}

			wp_safe_redirect( $redirect_url );
			exit;
		}

		// Handle form submission (update notes).
		if ( isset( $_POST['wbam_update_partnership'] ) && isset( $_POST['wbam_partnership_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbam_partnership_nonce'] ) ), 'wbam_update_partnership' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'wb-ads-rotator-with-split-test' ) );
			}

			$id          = isset( $_POST['partnership_id'] ) ? absint( $_POST['partnership_id'] ) : 0;
			$admin_notes = isset( $_POST['admin_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ) ) : '';
			$new_status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

			if ( $id ) {
				$update_data = array( 'admin_notes' => $admin_notes );
				if ( $new_status ) {
					$update_data['status']       = $new_status;
					$update_data['responded_at'] = current_time( 'mysql' );
				}
				$this->manager->update( $id, $update_data );
			}

			wp_safe_redirect( admin_url( 'admin.php?page=wbam-partnerships&message=updated' ) );
			exit;
		}
	}

	/**
	 * Render admin page.
	 */
	public function render_page() {
		// Read-only admin list view: filter / pagination / single-row view
		// all dispatched from GET; mutation endpoints (accept / reject /
		// spam / delete) are nonce-checked in their own handlers.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter / view state.
		// Show single view if viewing details.
		if ( isset( $_GET['view'] ) && absint( $_GET['view'] ) ) {
			$this->render_single_view( absint( $_GET['view'] ) );
			return;
		}

		// Get filters.
		$current_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$search         = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged          = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page       = 20;
		// phpcs:enable

		// Get data.
		$args = array(
			'status' => $current_status,
			'search' => $search,
			'limit'  => $per_page,
			'offset' => ( $paged - 1 ) * $per_page,
		);

		$partnerships = $this->manager->get_partnerships( $args );
		$total        = $this->manager->count_partnerships( $args );
		$total_pages  = ceil( $total / $per_page );
		$counts       = $this->manager->get_status_counts();

		// Show notices.
		$this->show_notices();
		?>
		<div class="wrap wbam-admin wbam-partnerships-wrap">
			<?php
			\WBAM\Admin\UX::page_header(
				array(
					'title' => __( 'Partnerships', 'wb-ads-rotator-with-split-test' ),
					'desc'  => __( 'Requests submitted through your partnership form.', 'wb-ads-rotator-with-split-test' ),
				)
			);
			?>

			<div class="wbam-list-toolbar">
				<!-- Status Filter Tabs -->
				<ul class="subsubsub">
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbam-partnerships' ) ); ?>" class="<?php echo '' === $current_status ? 'current' : ''; ?>">
							<?php esc_html_e( 'All', 'wb-ads-rotator-with-split-test' ); ?>
							<span class="count">(<?php echo esc_html( $counts['all'] ); ?>)</span>
						</a> |
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbam-partnerships&status=pending' ) ); ?>" class="<?php echo 'pending' === $current_status ? 'current' : ''; ?>">
							<?php esc_html_e( 'Pending', 'wb-ads-rotator-with-split-test' ); ?>
							<span class="count">(<?php echo esc_html( $counts['pending'] ); ?>)</span>
						</a> |
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbam-partnerships&status=accepted' ) ); ?>" class="<?php echo 'accepted' === $current_status ? 'current' : ''; ?>">
							<?php esc_html_e( 'Accepted', 'wb-ads-rotator-with-split-test' ); ?>
							<span class="count">(<?php echo esc_html( $counts['accepted'] ); ?>)</span>
						</a> |
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbam-partnerships&status=rejected' ) ); ?>" class="<?php echo 'rejected' === $current_status ? 'current' : ''; ?>">
							<?php esc_html_e( 'Rejected', 'wb-ads-rotator-with-split-test' ); ?>
							<span class="count">(<?php echo esc_html( $counts['rejected'] ); ?>)</span>
						</a> |
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbam-partnerships&status=spam' ) ); ?>" class="<?php echo 'spam' === $current_status ? 'current' : ''; ?>">
							<?php esc_html_e( 'Spam', 'wb-ads-rotator-with-split-test' ); ?>
							<span class="count">(<?php echo esc_html( $counts['spam'] ); ?>)</span>
						</a>
					</li>
				</ul>

				<!-- Search Box -->
				<form method="get" class="search-box">
					<input type="hidden" name="page" value="wbam-partnerships">
					<?php if ( $current_status ) : ?>
						<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>">
					<?php endif; ?>
					<label class="screen-reader-text" for="partnership-search-input"><?php esc_html_e( 'Search', 'wb-ads-rotator-with-split-test' ); ?></label>
					<input type="search" id="partnership-search-input" name="s" value="<?php echo esc_attr( $search ); ?>">
					<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Search', 'wb-ads-rotator-with-split-test' ); ?>">
				</form>
			</div>

			<!-- Partnerships Table -->
			<?php // Not .wp-list-table: core's responsive rules for real list tables mangled this hand-built one on phones. ?>
			<form method="get" class="wbam-partnerships-bulk">
			<input type="hidden" name="page" value="wbam-partnerships">
			<?php wp_nonce_field( 'bulk-partnerships' ); ?>
			<?php $this->render_bulk_select( 'bulk_action' ); ?>
			<div class="wbam-partnerships-scroll">
			<table class="widefat striped wbam-partnerships-table">
				<thead>
					<tr>
						<td class="manage-column check-column"><label class="screen-reader-text" for="wbam-partnerships-select-all"><?php esc_html_e( 'Select all', 'wb-ads-rotator-with-split-test' ); ?></label><input type="checkbox" id="wbam-partnerships-select-all" class="wbam-select-all"></td>
						<th class="wbam-col-contact"><?php esc_html_e( 'Contact', 'wb-ads-rotator-with-split-test' ); ?></th>
						<th class="wbam-col-website"><?php esc_html_e( 'Website', 'wb-ads-rotator-with-split-test' ); ?></th>
						<th class="wbam-col-type"><?php esc_html_e( 'Type', 'wb-ads-rotator-with-split-test' ); ?></th>
						<th class="wbam-col-budget"><?php esc_html_e( 'Budget', 'wb-ads-rotator-with-split-test' ); ?></th>
						<th class="wbam-col-status"><?php esc_html_e( 'Status', 'wb-ads-rotator-with-split-test' ); ?></th>
						<th class="wbam-col-date"><?php esc_html_e( 'Date', 'wb-ads-rotator-with-split-test' ); ?></th>
						<th class="wbam-col-actions"><?php esc_html_e( 'Actions', 'wb-ads-rotator-with-split-test' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $partnerships ) ) : ?>
						<tr>
							<td colspan="8" class="no-items">
								<?php
								echo wp_kses_post(
									\WBAM\Admin\UX::empty_state(
										$this->partnerships_empty_state_args( $current_status, $search, (int) $counts['all'] )
									)
								);
								?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $partnerships as $partnership ) : ?>
							<tr>
								<th scope="row" class="check-column">
									<label class="screen-reader-text" for="wbam-partnership-<?php echo esc_attr( $partnership->id ); ?>">
										<?php
										/* translators: %s: requester name */
										echo esc_html( sprintf( __( 'Select %s', 'wb-ads-rotator-with-split-test' ), $partnership->name ) );
										?>
									</label>
									<input type="checkbox" id="wbam-partnership-<?php echo esc_attr( $partnership->id ); ?>" name="partnership_ids[]" value="<?php echo esc_attr( $partnership->id ); ?>">
								</th>
								<td>
									<strong>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbam-partnerships&view=' . $partnership->id ) ); ?>">
											<?php echo esc_html( $partnership->name ); ?>
										</a>
									</strong>
									<br>
									<small><a href="mailto:<?php echo esc_attr( $partnership->email ); ?>"><?php echo esc_html( $partnership->email ); ?></a></small>
								</td>
								<td>
									<a href="<?php echo esc_url( $partnership->website_url ); ?>" target="_blank" rel="noopener">
										<?php echo esc_html( wp_parse_url( $partnership->website_url, PHP_URL_HOST ) ); ?>
										<?php echo wbam_icon( 'external-link', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
									</a>
								</td>
								<td>
									<span class="wbam-type-badge wbam-type-<?php echo esc_attr( $partnership->partnership_type ); ?>">
										<?php echo esc_html( $partnership->get_type_label() ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $partnership->get_budget_range() ); ?></td>
								<td>
									<?php echo wp_kses_post( \WBAM\Admin\UX::status_badge( $partnership->status, $partnership->get_status_label() ) ); ?>
								</td>
								<td>
									<span title="<?php echo esc_attr( $partnership->created_at ); ?>">
										<?php
										$time_ago = $partnership->get_time_ago();
										if ( '' !== $time_ago ) {
											/* translators: %s: human-readable time diff, e.g. "3 hours" */
											printf( esc_html__( '%s ago', 'wb-ads-rotator-with-split-test' ), esc_html( $time_ago ) );
										} else {
											echo esc_html( $partnership->created_at );
										}
										?>
									</span>
								</td>
								<td class="wbam-actions-cell">
									<?php $this->render_row_actions( $partnership ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			</div>
			<?php $this->render_bulk_select( 'bulk_action2' ); ?>
			</form>

			<!-- Pagination -->
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						$pagination_args = array(
							'page'   => 'wbam-partnerships',
							'status' => $current_status,
							's'      => $search,
						);
						$base_url        = add_query_arg( array_filter( $pagination_args ), admin_url( 'admin.php' ) );
						?>
						<span class="displaying-num">
							<?php
							printf(
								/* translators: %s: Number of items */
								esc_html( _n( '%s item', '%s items', $total, 'wb-ads-rotator-with-split-test' ) ),
								esc_html( number_format_i18n( $total ) )
							);
							?>
						</span>
						<span class="pagination-links">
							<?php if ( $paged > 1 ) : ?>
								<a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1, $base_url ) ); ?>">
									<span aria-hidden="true">&lsaquo;</span>
								</a>
							<?php else : ?>
								<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
							<?php endif; ?>

							<span class="paging-input">
								<?php echo esc_html( $paged ); ?> <?php esc_html_e( 'of', 'wb-ads-rotator-with-split-test' ); ?> <span class="total-pages"><?php echo esc_html( $total_pages ); ?></span>
							</span>

							<?php if ( $paged < $total_pages ) : ?>
								<a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, $base_url ) ); ?>">
									<span aria-hidden="true">&rsaquo;</span>
								</a>
							<?php else : ?>
								<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
							<?php endif; ?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Build the UX::empty_state() args for the partnerships table — wording
	 * depends on whether the table is truly empty or just filtered down to
	 * nothing, per the design-system empty-state rule: a filtered-empty
	 * result says "No results match" / "No <status> partnerships", never
	 * "No … yet" (that phrasing implies the site has none at all).
	 *
	 * @param string $status    Active status filter ('' for All).
	 * @param string $search    Active search term ('' when none).
	 * @param int    $total_all Total partnerships regardless of filter.
	 * @return array
	 */
	private function partnerships_empty_state_args( $status, $search, $total_all ) {
		if ( 0 === $total_all ) {
			return array(
				'icon'    => 'handshake',
				'title'   => __( 'No partnership inquiries yet', 'wb-ads-rotator-with-split-test' ),
				'message' => __( 'Requests submitted through your partnership form will show up here.', 'wb-ads-rotator-with-split-test' ),
			);
		}

		if ( '' !== $search ) {
			return array(
				'icon'    => 'search',
				'title'   => __( 'No results match', 'wb-ads-rotator-with-split-test' ),
				'message' => __( 'Try a different search term.', 'wb-ads-rotator-with-split-test' ),
			);
		}

		$status_titles = array(
			'pending'  => __( 'No pending partnerships', 'wb-ads-rotator-with-split-test' ),
			'accepted' => __( 'No accepted partnerships', 'wb-ads-rotator-with-split-test' ),
			'rejected' => __( 'No rejected partnerships', 'wb-ads-rotator-with-split-test' ),
			'spam'     => __( 'No spam partnerships', 'wb-ads-rotator-with-split-test' ),
		);

		return array(
			'icon'  => 'handshake',
			'title' => isset( $status_titles[ $status ] ) ? $status_titles[ $status ] : __( 'No results match', 'wb-ads-rotator-with-split-test' ),
		);
	}

	/**
	 * Bulk action picker above/below the list.
	 *
	 * @param string $name Field name (bulk_action or bulk_action2).
	 * @return void
	 */
	private function render_bulk_select( $name ) {
		?>
		<div class="tablenav wbam-partnerships-bulk-bar">
			<label for="wbam-<?php echo esc_attr( $name ); ?>" class="screen-reader-text"><?php esc_html_e( 'Bulk action', 'wb-ads-rotator-with-split-test' ); ?></label>
			<select name="<?php echo esc_attr( $name ); ?>" id="wbam-<?php echo esc_attr( $name ); ?>">
				<option value="-1"><?php esc_html_e( 'Bulk actions', 'wb-ads-rotator-with-split-test' ); ?></option>
				<option value="accept"><?php esc_html_e( 'Accept', 'wb-ads-rotator-with-split-test' ); ?></option>
				<option value="reject"><?php esc_html_e( 'Reject', 'wb-ads-rotator-with-split-test' ); ?></option>
				<option value="spam"><?php esc_html_e( 'Mark as spam', 'wb-ads-rotator-with-split-test' ); ?></option>
				<option value="delete"><?php esc_html_e( 'Delete', 'wb-ads-rotator-with-split-test' ); ?></option>
			</select>
			<?php submit_button( __( 'Apply', 'wb-ads-rotator-with-split-test' ), 'action', '', false ); ?>
		</div>
		<?php
	}

	/**
	 * Render row actions.
	 *
	 * @param Partnership $partnership Partnership object.
	 */
	private function render_row_actions( $partnership ) {
		$actions = array();

		// View.
		$actions['view'] = sprintf(
			'<a href="%s" title="%s" class="wbam-icon-link">%s</a>',
			esc_url( admin_url( 'admin.php?page=wbam-partnerships&view=' . $partnership->id ) ),
			esc_attr__( 'View Details', 'wb-ads-rotator-with-split-test' ),
			wbam_icon( 'eye', array( 'size' => 'sm' ) )
		);

		// Accept (if pending).
		if ( $partnership->is_pending() ) {
			$actions['accept'] = sprintf(
				'<a href="%s" title="%s" class="wbam-icon-link wbam-action-accept">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin.php?page=wbam-partnerships&action=accept&partnership_id=' . $partnership->id ), 'wbam_partnership_accept_' . $partnership->id ) ),
				esc_attr__( 'Accept', 'wb-ads-rotator-with-split-test' ),
				wbam_icon( 'check-circle', array( 'size' => 'sm' ) )
			);

			$actions['reject'] = sprintf(
				'<a href="%s" title="%s" class="wbam-icon-link wbam-action-reject">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin.php?page=wbam-partnerships&action=reject&partnership_id=' . $partnership->id ), 'wbam_partnership_reject_' . $partnership->id ) ),
				esc_attr__( 'Reject', 'wb-ads-rotator-with-split-test' ),
				wbam_icon( 'x', array( 'size' => 'sm' ) )
			);
		}

		// Spam (if not already spam).
		if ( 'spam' !== $partnership->status ) {
			$actions['spam'] = sprintf(
				'<a href="%s" title="%s" class="wbam-icon-link wbam-action-spam">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin.php?page=wbam-partnerships&action=spam&partnership_id=' . $partnership->id ), 'wbam_partnership_spam_' . $partnership->id ) ),
				esc_attr__( 'Mark as Spam', 'wb-ads-rotator-with-split-test' ),
				wbam_icon( 'flag', array( 'size' => 'sm' ) )
			);
		}

		// Delete.
		$actions['delete'] = sprintf(
			'<a href="%s" title="%s" class="wbam-icon-link wbam-action-delete" data-wbam-confirm="%s" data-wbam-confirm-tone="danger">%s</a>',
			esc_url( wp_nonce_url( admin_url( 'admin.php?page=wbam-partnerships&action=delete&partnership_id=' . $partnership->id ), 'wbam_partnership_delete_' . $partnership->id ) ),
			esc_attr__( 'Delete', 'wb-ads-rotator-with-split-test' ),
			esc_attr__( 'Are you sure you want to delete this inquiry?', 'wb-ads-rotator-with-split-test' ),
			wbam_icon( 'trash-2', array( 'size' => 'sm' ) )
		);

		echo implode( ' ', $actions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render single partnership view.
	 *
	 * @param int $id Partnership ID.
	 */
	private function render_single_view( $id ) {
		$partnership = $this->manager->get( $id );

		if ( ! $partnership ) {
			wp_die( esc_html__( 'Partnership not found.', 'wb-ads-rotator-with-split-test' ) );
		}
		?>
		<div class="wrap wbam-admin wbam-partnerships-wrap wbam-partnership-view">
			<?php
			\WBAM\Admin\UX::page_header(
				array(
					'title'      => __( 'Partnership inquiry details', 'wb-ads-rotator-with-split-test' ),
					'back_url'   => admin_url( 'admin.php?page=wbam-partnerships' ),
					'back_label' => __( 'Back to list', 'wb-ads-rotator-with-split-test' ),
				)
			);
			?>

			<div class="wbam-partnership-details">
				<div class="wbam-settings-card">
					<div class="wbam-settings-card__head">
						<h2 class="wbam-settings-card__title"><?php esc_html_e( 'Contact Information', 'wb-ads-rotator-with-split-test' ); ?></h2>
						<?php echo wp_kses_post( \WBAM\Admin\UX::status_badge( $partnership->status, $partnership->get_status_label() ) ); ?>
					</div>
					<div class="wbam-settings-card__body">
						<table class="wbam-details-table">
							<tr>
								<th><?php esc_html_e( 'Name', 'wb-ads-rotator-with-split-test' ); ?></th>
								<td><?php echo esc_html( $partnership->name ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Email', 'wb-ads-rotator-with-split-test' ); ?></th>
								<td><a href="mailto:<?php echo esc_attr( $partnership->email ); ?>"><?php echo esc_html( $partnership->email ); ?></a></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Website', 'wb-ads-rotator-with-split-test' ); ?></th>
								<td>
									<a href="<?php echo esc_url( $partnership->website_url ); ?>" target="_blank" rel="noopener">
										<?php echo esc_html( $partnership->website_url ); ?>
										<?php echo wbam_icon( 'external-link', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
									</a>
								</td>
							</tr>
							<?php // With IP anonymising on, the stored value is a one-way hash: meaningless to a person, so only a real address is shown. ?>
							<?php if ( filter_var( (string) $partnership->ip_address, FILTER_VALIDATE_IP ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'IP Address', 'wb-ads-rotator-with-split-test' ); ?></th>
								<td><?php echo esc_html( $partnership->ip_address ); ?></td>
							</tr>
							<?php endif; ?>
						</table>
					</div>
				</div>

				<div class="wbam-settings-card">
					<div class="wbam-settings-card__head">
						<h2 class="wbam-settings-card__title"><?php esc_html_e( 'Partnership Details', 'wb-ads-rotator-with-split-test' ); ?></h2>
					</div>
					<div class="wbam-settings-card__body">
						<table class="wbam-details-table">
							<tr>
								<th><?php esc_html_e( 'Type', 'wb-ads-rotator-with-split-test' ); ?></th>
								<td>
									<span class="wbam-type-badge wbam-type-<?php echo esc_attr( $partnership->partnership_type ); ?>">
										<?php echo esc_html( $partnership->get_type_label() ); ?>
									</span>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Target Page', 'wb-ads-rotator-with-split-test' ); ?></th>
								<td>
									<?php if ( $partnership->target_post_id ) : ?>
										<a href="<?php echo esc_url( get_permalink( $partnership->target_post_id ) ); ?>" target="_blank">
											<?php echo esc_html( $partnership->get_target_post_title() ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( $partnership->get_target_post_title() ); ?>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Anchor Text', 'wb-ads-rotator-with-split-test' ); ?></th>
								<td><?php echo esc_html( ! empty( $partnership->anchor_text ) ? $partnership->anchor_text : '-' ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Budget Range', 'wb-ads-rotator-with-split-test' ); ?></th>
								<td><?php echo esc_html( $partnership->get_budget_range() ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Submitted', 'wb-ads-rotator-with-split-test' ); ?></th>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $partnership->created_at ) ) ); ?></td>
							</tr>
							<?php if ( $partnership->responded_at ) : ?>
							<tr>
								<th><?php esc_html_e( 'Responded', 'wb-ads-rotator-with-split-test' ); ?></th>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $partnership->responded_at ) ) ); ?></td>
							</tr>
							<?php endif; ?>
						</table>
					</div>
				</div>

				<?php if ( $partnership->message ) : ?>
				<div class="wbam-settings-card">
					<div class="wbam-settings-card__head">
						<h2 class="wbam-settings-card__title"><?php esc_html_e( 'Message', 'wb-ads-rotator-with-split-test' ); ?></h2>
					</div>
					<div class="wbam-settings-card__body">
						<div class="wbam-message-content">
							<?php echo nl2br( esc_html( $partnership->message ) ); ?>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<div class="wbam-settings-card">
					<div class="wbam-settings-card__head">
						<h2 class="wbam-settings-card__title"><?php esc_html_e( 'Admin Notes & Actions', 'wb-ads-rotator-with-split-test' ); ?></h2>
					</div>
					<div class="wbam-settings-card__body">
						<form method="post">
							<?php wp_nonce_field( 'wbam_update_partnership', 'wbam_partnership_nonce' ); ?>
							<input type="hidden" name="partnership_id" value="<?php echo esc_attr( $partnership->id ); ?>">

							<div class="wbam-form-field">
								<label for="admin_notes"><?php esc_html_e( 'Admin Notes', 'wb-ads-rotator-with-split-test' ); ?></label>
								<textarea id="admin_notes" name="admin_notes" rows="4" class="large-text"><?php echo esc_textarea( $partnership->admin_notes ); ?></textarea>
								<p class="description"><?php esc_html_e( 'These notes are for internal use only and are not visible to the requester.', 'wb-ads-rotator-with-split-test' ); ?></p>
							</div>

							<div class="wbam-form-field">
								<label for="status"><?php esc_html_e( 'Update Status', 'wb-ads-rotator-with-split-test' ); ?></label>
								<select id="status" name="status">
									<option value=""><?php esc_html_e( 'No Change', 'wb-ads-rotator-with-split-test' ); ?></option>
									<?php foreach ( Partnership::get_statuses() as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $partnership->status, $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="wbam-form-actions">
								<?php // One primary action: Accept while the inquiry waits, Save otherwise. ?>
								<button type="submit" name="wbam_update_partnership" class="button<?php echo $partnership->is_pending() ? '' : ' button-primary'; ?>">
									<?php esc_html_e( 'Save Notes', 'wb-ads-rotator-with-split-test' ); ?>
								</button>

								<?php if ( $partnership->is_pending() ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wbam-partnerships&action=accept&partnership_id=' . $partnership->id ), 'wbam_partnership_accept_' . $partnership->id ) ); ?>" class="button button-primary">
										<?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
										<?php esc_html_e( 'Accept', 'wb-ads-rotator-with-split-test' ); ?>
									</a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wbam-partnerships&action=reject&partnership_id=' . $partnership->id ), 'wbam_partnership_reject_' . $partnership->id ) ); ?>" class="button wbam-button-reject">
										<?php echo wbam_icon( 'x', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
										<?php esc_html_e( 'Reject', 'wb-ads-rotator-with-split-test' ); ?>
									</a>
								<?php endif; ?>

								<a href="mailto:<?php echo esc_attr( $partnership->email ); ?>?subject=<?php echo esc_attr( rawurlencode( __( 'Re: Partnership Inquiry', 'wb-ads-rotator-with-split-test' ) ) ); ?>" class="button">
									<?php echo wbam_icon( 'mail', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
									<?php esc_html_e( 'Email Requester', 'wb-ads-rotator-with-split-test' ); ?>
								</a>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Show admin notices.
	 */
	private function show_notices() {
		// Read-only post-redirect message lookup; no state mutation.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read of message key from redirect query to display a notice.
		if ( ! isset( $_GET['message'] ) ) {
			return;
		}

		$messages = array(
			'accepted' => array( 'success', __( 'Partnership inquiry accepted.', 'wb-ads-rotator-with-split-test' ) ),
			'rejected' => array( 'success', __( 'Partnership inquiry rejected.', 'wb-ads-rotator-with-split-test' ) ),
			'spam'     => array( 'success', __( 'Partnership inquiry marked as spam.', 'wb-ads-rotator-with-split-test' ) ),
			'deleted'  => array( 'success', __( 'Partnership inquiry deleted.', 'wb-ads-rotator-with-split-test' ) ),
			'updated'  => array( 'success', __( 'Partnership inquiry updated.', 'wb-ads-rotator-with-split-test' ) ),
		);

		$count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
		/* translators: %s: number of inquiries */
		$messages['bulk_accept'] = array( 'success', sprintf( _n( '%s inquiry accepted.', '%s inquiries accepted.', $count, 'wb-ads-rotator-with-split-test' ), number_format_i18n( $count ) ) );
		/* translators: %s: number of inquiries */
		$messages['bulk_reject'] = array( 'success', sprintf( _n( '%s inquiry rejected.', '%s inquiries rejected.', $count, 'wb-ads-rotator-with-split-test' ), number_format_i18n( $count ) ) );
		/* translators: %s: number of inquiries */
		$messages['bulk_spam'] = array( 'success', sprintf( _n( '%s inquiry marked as spam.', '%s inquiries marked as spam.', $count, 'wb-ads-rotator-with-split-test' ), number_format_i18n( $count ) ) );
		/* translators: %s: number of inquiries */
		$messages['bulk_delete'] = array( 'success', sprintf( _n( '%s inquiry deleted.', '%s inquiries deleted.', $count, 'wb-ads-rotator-with-split-test' ), number_format_i18n( $count ) ) );
		$messages['bulk_none']   = array( 'warning', __( 'Pick a bulk action and at least one inquiry.', 'wb-ads-rotator-with-split-test' ) );

		$message_key = sanitize_text_field( wp_unslash( $_GET['message'] ) );
		// phpcs:enable

		if ( isset( $messages[ $message_key ] ) ) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $messages[ $message_key ][0] ),
				esc_html( $messages[ $message_key ][1] )
			);
		}
	}
}
