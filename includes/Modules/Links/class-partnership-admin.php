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

		add_submenu_page(
			'wbam-links',
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
		if ( ! isset( $submenu['wbam-links'] ) ) {
			return;
		}

		$pending_count = $this->manager->count_partnerships( array( 'status' => 'pending' ) );

		if ( $pending_count > 0 && isset( $submenu['wbam-links'] ) ) {
			foreach ( $submenu['wbam-links'] as $key => $item ) {
				if ( isset( $item[2] ) && 'wbam-partnerships' === $item[2] ) {
					// Mutating $submenu is the standard WP pattern for injecting
					// a pending-count badge onto a submenu item (e.g. Posts → "3").
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional $submenu[label][0] edit to add pending-count badge.
					$submenu['wbam-links'][ $key ][0] .= sprintf(
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
		if ( 'links_page_wbam-partnerships' !== $hook ) {
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
					'title' => __( 'Partnership Inquiries', 'wb-ads-rotator-with-split-test' ),
					'desc'  => __( 'Requests submitted through your partnership form.', 'wb-ads-rotator-with-split-test' ),
				)
			);
			?>

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

			<!-- Partnerships Table -->
			<?php // Not .wp-list-table: core's responsive rules for real list tables mangled this hand-built one on phones. ?>
			<div class="wbam-partnerships-scroll">
			<table class="widefat striped wbam-partnerships-table">
				<thead>
					<tr>
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
							<td colspan="7" class="no-items"><?php esc_html_e( 'No partnership inquiries found.', 'wb-ads-rotator-with-split-test' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $partnerships as $partnership ) : ?>
							<tr>
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
			<h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbam-partnerships' ) ); ?>" class="page-title-action">
					<?php echo wbam_icon( 'arrow-left', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<?php esc_html_e( 'Back to List', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
				<?php esc_html_e( 'Partnership Inquiry Details', 'wb-ads-rotator-with-split-test' ); ?>
			</h1>

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
							<tr>
								<th><?php esc_html_e( 'IP Address', 'wb-ads-rotator-with-split-test' ); ?></th>
								<td><?php echo esc_html( ! empty( $partnership->ip_address ) ? $partnership->ip_address : '-' ); ?></td>
							</tr>
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
								<button type="submit" name="wbam_update_partnership" class="button button-primary">
									<?php esc_html_e( 'Save Notes', 'wb-ads-rotator-with-split-test' ); ?>
								</button>

								<?php if ( $partnership->is_pending() ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wbam-partnerships&action=accept&partnership_id=' . $partnership->id ), 'wbam_partnership_accept_' . $partnership->id ) ); ?>" class="button button-primary" style="background: #46b450; border-color: #46b450;">
										<?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
										<?php esc_html_e( 'Accept', 'wb-ads-rotator-with-split-test' ); ?>
									</a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wbam-partnerships&action=reject&partnership_id=' . $partnership->id ), 'wbam_partnership_reject_' . $partnership->id ) ); ?>" class="button" style="color: #a00;">
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
