<?php
/**
 * Links List Table Class
 *
 * Extends WP_List_Table for displaying links in admin.
 *
 * @package WB_Ad_Manager
 * @since   2.1.0
 */

namespace WBAM\Modules\Links;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Links List Table class.
 */
class Links_List_Table extends \WP_List_Table {

	/**
	 * Table classes, with the shared admin-family class.
	 *
	 * Guarded because PRO ships this file too and can boot against an older
	 * FREE that predates the helper; falling back to WordPress's own list keeps
	 * the table looking stock rather than fataling.
	 *
	 * @since 3.0.0
	 * @return string[]
	 */
	protected function get_table_classes() {
		if ( class_exists( '\\WBAM\\Admin\\Table_Classes' ) ) {
			return \WBAM\Admin\Table_Classes::get( isset( $this->_args['plural'] ) ? $this->_args['plural'] : '' );
		}

		return parent::get_table_classes();
	}

	/**
	 * Link Manager instance.
	 *
	 * @var Link_Manager
	 */
	private $link_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'link',
				'plural'   => 'links',
				'ajax'     => false,
			)
		);

		$this->link_manager = Link_Manager::get_instance();
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'              => '<input type="checkbox" aria-label="' . esc_attr__( 'Select all links', 'wb-ads-rotator-with-split-test' ) . '">',
			'name'            => __( 'Name', 'wb-ads-rotator-with-split-test' ),
			'destination_url' => __( 'Destination', 'wb-ads-rotator-with-split-test' ),
			'cloaked_url'     => __( 'Cloaked URL', 'wb-ads-rotator-with-split-test' ),
			'link_type'       => __( 'Type', 'wb-ads-rotator-with-split-test' ),
			'click_count'     => __( 'Clicks', 'wb-ads-rotator-with-split-test' ),
			'status'          => __( 'Status', 'wb-ads-rotator-with-split-test' ),
			'created_at'      => __( 'Created', 'wb-ads-rotator-with-split-test' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'name'        => array( 'name', false ),
			'click_count' => array( 'click_count', true ),
			'status'      => array( 'status', false ),
			'created_at'  => array( 'created_at', true ),
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'delete'     => __( 'Delete', 'wb-ads-rotator-with-split-test' ),
			'activate'   => __( 'Activate', 'wb-ads-rotator-with-split-test' ),
			'deactivate' => __( 'Deactivate', 'wb-ads-rotator-with-split-test' ),
		);
	}

	/**
	 * Get views (status filters).
	 *
	 * @return array
	 */
	protected function get_views() {
		// Read-only filter state from the admin list URL. WP_List_Table filters
		// are GET-based by convention and do not carry nonces — nothing is
		// mutated here, only rendered.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter state.
		$current = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all';
		$views   = array();

		$all_count      = $this->link_manager->count_links();
		$active_count   = $this->link_manager->count_links( array( 'status' => 'active' ) );
		$inactive_count = $this->link_manager->count_links( array( 'status' => 'inactive' ) );
		$expired_count  = $this->link_manager->count_links( array( 'status' => 'expired' ) );

		$views['all'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( admin_url( 'admin.php?page=wbam-links' ) ),
			'all' === $current ? 'current' : '',
			__( 'All', 'wb-ads-rotator-with-split-test' ),
			number_format_i18n( $all_count )
		);

		$views['active'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( admin_url( 'admin.php?page=wbam-links&status=active' ) ),
			'active' === $current ? 'current' : '',
			__( 'Active', 'wb-ads-rotator-with-split-test' ),
			number_format_i18n( $active_count )
		);

		$views['inactive'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( admin_url( 'admin.php?page=wbam-links&status=inactive' ) ),
			'inactive' === $current ? 'current' : '',
			__( 'Inactive', 'wb-ads-rotator-with-split-test' ),
			number_format_i18n( $inactive_count )
		);

		$views['expired'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( admin_url( 'admin.php?page=wbam-links&status=expired' ) ),
			'expired' === $current ? 'current' : '',
			__( 'Expired', 'wb-ads-rotator-with-split-test' ),
			number_format_i18n( $expired_count )
		);

		return $views;
	}

	/**
	 * Extra table navigation (filters).
	 *
	 * @param string $which Position (top or bottom).
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		// Read-only filter state from the list-table URL; no state mutation.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter state.
		$link_type   = isset( $_GET['link_type'] ) ? sanitize_text_field( wp_unslash( $_GET['link_type'] ) ) : '';
		$category_id = isset( $_GET['category_id'] ) ? (int) $_GET['category_id'] : 0;
		// phpcs:enable

		?>
		<div class="alignleft actions">
			<select name="link_type" aria-label="<?php esc_attr_e( 'Filter by link type', 'wb-ads-rotator-with-split-test' ); ?>">
				<option value=""><?php esc_html_e( 'All Types', 'wb-ads-rotator-with-split-test' ); ?></option>
				<?php foreach ( Link::get_link_types() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $link_type, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="category_id" aria-label="<?php esc_attr_e( 'Filter by category', 'wb-ads-rotator-with-split-test' ); ?>">
				<option value=""><?php esc_html_e( 'All Categories', 'wb-ads-rotator-with-split-test' ); ?></option>
				<?php
				$categories = $this->link_manager->get_categories();
				foreach ( $categories as $cat ) :
					?>
					<option value="<?php echo esc_attr( $cat->id ); ?>" <?php selected( $category_id, $cat->id ); ?>>
						<?php echo esc_html( $cat->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php submit_button( __( 'Filter', 'wb-ads-rotator-with-split-test' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Prepare items for display.
	 */
	public function prepare_items() {
		$per_page = 20;
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Process bulk actions.
		$this->process_bulk_action();

		// Build query args from read-only filter/sort/search URL params.
		// WP_List_Table filters are GET-based by convention and carry no
		// nonce; nothing below is a state mutation.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter / sort / search state.
		$args = array(
			'limit'   => $per_page,
			'offset'  => ( $this->get_pagenum() - 1 ) * $per_page,
			'orderby' => isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'created_at',
			'order'   => isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC',
		);

		if ( isset( $_GET['status'] ) && ! empty( $_GET['status'] ) ) {
			$args['status'] = sanitize_text_field( wp_unslash( $_GET['status'] ) );
		}

		if ( isset( $_GET['link_type'] ) && ! empty( $_GET['link_type'] ) ) {
			$args['link_type'] = sanitize_text_field( wp_unslash( $_GET['link_type'] ) );
		}

		if ( isset( $_GET['category_id'] ) && ! empty( $_GET['category_id'] ) ) {
			$args['category_id'] = (int) $_GET['category_id'];
		}

		if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) ) {
			$args['search'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}
		// phpcs:enable

		$this->items = $this->link_manager->get_links( $args );

		// Count for pagination.
		$total_items = $this->link_manager->count_links( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Process bulk actions.
	 */
	public function process_bulk_action() {
		$action = $this->current_action();

		if ( ! $action ) {
			return;
		}

		// Deletes and status changes run from here. The menu capability on the
		// parent page already gates the normal path; this makes the guarantee
		// local so it survives the table being instantiated somewhere else.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'bulk-links' ) ) {
			return;
		}

		$link_ids = isset( $_GET['link'] ) ? array_map( 'absint', wp_unslash( (array) $_GET['link'] ) ) : array();

		if ( empty( $link_ids ) ) {
			return;
		}

		switch ( $action ) {
			case 'delete':
				foreach ( $link_ids as $id ) {
					$this->link_manager->delete( $id );
				}
				break;

			case 'activate':
				foreach ( $link_ids as $id ) {
					$this->link_manager->update( $id, array( 'status' => 'active' ) );
				}
				break;

			case 'deactivate':
				foreach ( $link_ids as $id ) {
					$this->link_manager->update( $id, array( 'status' => 'inactive' ) );
				}
				break;
		}
	}

	/**
	 * Checkbox column.
	 *
	 * @param Link $item Current link item.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="link[]" value="%s" aria-label="%s">',
			esc_attr( $item->id ),
			/* translators: %s: name of the item being selected */
			esc_attr( sprintf( __( 'Select %s', 'wb-ads-rotator-with-split-test' ), $item->name ) )
		);
	}

	/**
	 * Name column.
	 *
	 * @param Link $item Current link item.
	 * @return string
	 */
	public function column_name( $item ) {
		$edit_url   = admin_url( 'admin.php?page=wbam-links&action=edit&link_id=' . $item->id );
		$delete_url = wp_nonce_url(
			admin_url( 'admin.php?page=wbam-links&action=delete&link_id=' . $item->id ),
			'wbam_delete_link_' . $item->id
		);

		$cloak_prefix = \WBAM\Modules\Links\Link_Cloaker::get_instance()->get_cloak_prefix();
		$cloaked_url  = home_url( '/' . $cloak_prefix . '/' . $item->slug );
		$shortcode    = '[wbam_link id="' . (int) $item->id . '"]' . $item->name . '[/wbam_link]';

		$actions = array(
			'edit'           => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_url ),
				__( 'Edit', 'wb-ads-rotator-with-split-test' )
			),
			'copy-url'       => sprintf(
				'<a href="#" class="wbam-copy-row" data-copy="%s" data-done="%s">%s</a>',
				esc_attr( $cloaked_url ),
				esc_attr__( 'Copied!', 'wb-ads-rotator-with-split-test' ),
				esc_html__( 'Copy URL', 'wb-ads-rotator-with-split-test' )
			),
			'copy-shortcode' => sprintf(
				'<a href="#" class="wbam-copy-row" data-copy="%s" data-done="%s">%s</a>',
				esc_attr( $shortcode ),
				esc_attr__( 'Copied!', 'wb-ads-rotator-with-split-test' ),
				esc_html__( 'Copy Shortcode', 'wb-ads-rotator-with-split-test' )
			),
			'delete'         => sprintf(
				'<a href="%s" data-wbam-confirm="%s" data-wbam-confirm-tone="danger" class="delete">%s</a>',
				esc_url( $delete_url ),
				esc_attr__( 'Are you sure you want to delete this link?', 'wb-ads-rotator-with-split-test' ),
				__( 'Delete', 'wb-ads-rotator-with-split-test' )
			),
		);

		return sprintf(
			'<strong><a href="%s">%s</a></strong>%s',
			esc_url( $edit_url ),
			esc_html( $item->name ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Destination URL column.
	 *
	 * @param Link $item Current link item.
	 * @return string
	 */
	public function column_destination_url( $item ) {
		$url = $item->destination_url;

		// Truncate long URLs.
		$display_url = strlen( $url ) > 50 ? substr( $url, 0, 50 ) . '...' : $url;

		return sprintf(
			'<a href="%s" target="_blank" title="%s">%s</a>',
			esc_url( $url ),
			esc_attr( $url ),
			esc_html( $display_url )
		);
	}

	/**
	 * Cloaked URL column.
	 *
	 * @param Link $item Current link item.
	 * @return string
	 */
	public function column_cloaked_url( $item ) {
		if ( ! $item->cloaking_enabled || empty( $item->slug ) ) {
			return '<span class="wbam-icon-wrap" title="' . esc_attr__( 'Cloaking disabled', 'wb-ads-rotator-with-split-test' ) . '">' . wbam_icon( 'x', array( 'size' => 'sm' ) ) . '</span>';
		}

		$cloaked_url = $item->get_url();

		return sprintf(
			'<a href="%s" target="_blank">%s</a>
			<button type="button" class="button button-small wbam-copy-btn" data-clipboard="%s" title="%s">
				%s
			</button>',
			esc_url( $cloaked_url ),
			esc_html( $item->slug ),
			esc_attr( $cloaked_url ),
			esc_attr__( 'Copy URL', 'wb-ads-rotator-with-split-test' ),
			wbam_icon( 'file', array( 'size' => 'sm' ) )
		);
	}

	/**
	 * Link type column.
	 *
	 * @param Link $item Current link item.
	 * @return string
	 */
	public function column_link_type( $item ) {
		$types = Link::get_link_types();
		$label = isset( $types[ $item->link_type ] ) ? $types[ $item->link_type ] : $item->link_type;

		return sprintf(
			'<span class="wbam-link-type wbam-link-type-%s">%s</span>',
			esc_attr( $item->link_type ),
			esc_html( $label )
		);
	}

	/**
	 * Click count column.
	 *
	 * @param Link $item Current link item.
	 * @return string
	 */
	public function column_click_count( $item ) {
		return number_format_i18n( $item->click_count );
	}

	/**
	 * Status column.
	 *
	 * @param Link $item Current link item.
	 * @return string
	 */
	public function column_status( $item ) {
		$statuses = Link::get_statuses();
		$status   = $item->status;
		$label    = isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;

		// Check if expired. strtotime() returns false on bad input — only flag
		// as expired when we successfully parsed a past timestamp, so malformed
		// DB values don't silently mark active links "expired".
		if ( 'active' === $item->status && $item->expires_at ) {
			$expires_ts = strtotime( $item->expires_at );
			if ( false !== $expires_ts && $expires_ts < time() ) {
				$status = 'expired';
				$label  = __( 'Expired', 'wb-ads-rotator-with-split-test' );
			}
		}

		return \WBAM\Admin\UX::status_badge( $status, $label );
	}

	/**
	 * Created at column.
	 *
	 * @param Link $item Current link item.
	 * @return string
	 */
	public function column_created_at( $item ) {
		$created_ts = strtotime( (string) $item->created_at );
		if ( false === $created_ts ) {
			// Unparseable DB value — show the raw string instead of rendering
			// "55 years ago" from a strtotime(false) → 0 fallback.
			return esc_html( (string) $item->created_at );
		}

		return sprintf(
			'<span title="%s">%s</span>',
			esc_attr( $item->created_at ),
			esc_html( human_time_diff( $created_ts, time() ) . ' ' . __( 'ago', 'wb-ads-rotator-with-split-test' ) )
		);
	}

	/**
	 * Default column handler.
	 *
	 * @param Link   $item        Current link item.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}

	/**
	 * Message for no items.
	 *
	 * Delegates to the shared empty-state renderer (Phase G.5) so
	 * this list table matches the core Ads list treatment when the
	 * user has not yet created any links.
	 */
	public function no_items() {
		if ( class_exists( '\\WBAM\\Admin\\List_Empty_States' ) ) {
			\WBAM\Admin\List_Empty_States::render_links_empty_state();
			return;
		}

		esc_html_e( 'No links found.', 'wb-ads-rotator-with-split-test' );
	}
}
