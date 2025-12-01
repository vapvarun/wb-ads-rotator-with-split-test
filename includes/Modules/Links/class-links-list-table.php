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

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Links List Table class.
 */
class Links_List_Table extends \WP_List_Table {

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
			'cb'              => '<input type="checkbox">',
			'name'            => __( 'Name', 'wb-ad-manager' ),
			'destination_url' => __( 'Destination', 'wb-ad-manager' ),
			'cloaked_url'     => __( 'Cloaked URL', 'wb-ad-manager' ),
			'link_type'       => __( 'Type', 'wb-ad-manager' ),
			'click_count'     => __( 'Clicks', 'wb-ad-manager' ),
			'status'          => __( 'Status', 'wb-ad-manager' ),
			'created_at'      => __( 'Created', 'wb-ad-manager' ),
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
			'delete'     => __( 'Delete', 'wb-ad-manager' ),
			'activate'   => __( 'Activate', 'wb-ad-manager' ),
			'deactivate' => __( 'Deactivate', 'wb-ad-manager' ),
		);
	}

	/**
	 * Get views (status filters).
	 *
	 * @return array
	 */
	protected function get_views() {
		$current = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'all';
		$views   = array();

		$all_count      = $this->link_manager->count_links();
		$active_count   = $this->link_manager->count_links( array( 'status' => 'active' ) );
		$inactive_count = $this->link_manager->count_links( array( 'status' => 'inactive' ) );
		$expired_count  = $this->link_manager->count_links( array( 'status' => 'expired' ) );

		$views['all'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-links' ) ),
			'all' === $current ? 'current' : '',
			__( 'All', 'wb-ad-manager' ),
			number_format_i18n( $all_count )
		);

		$views['active'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-links&status=active' ) ),
			'active' === $current ? 'current' : '',
			__( 'Active', 'wb-ad-manager' ),
			number_format_i18n( $active_count )
		);

		$views['inactive'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-links&status=inactive' ) ),
			'inactive' === $current ? 'current' : '',
			__( 'Inactive', 'wb-ad-manager' ),
			number_format_i18n( $inactive_count )
		);

		$views['expired'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-links&status=expired' ) ),
			'expired' === $current ? 'current' : '',
			__( 'Expired', 'wb-ad-manager' ),
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

		$link_type   = isset( $_GET['link_type'] ) ? sanitize_text_field( $_GET['link_type'] ) : '';
		$category_id = isset( $_GET['category_id'] ) ? (int) $_GET['category_id'] : 0;

		?>
		<div class="alignleft actions">
			<select name="link_type">
				<option value=""><?php esc_html_e( 'All Types', 'wb-ad-manager' ); ?></option>
				<?php foreach ( Link::get_link_types() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $link_type, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="category_id">
				<option value=""><?php esc_html_e( 'All Categories', 'wb-ad-manager' ); ?></option>
				<?php
				$categories = $this->link_manager->get_categories();
				foreach ( $categories as $cat ) :
					?>
					<option value="<?php echo esc_attr( $cat->id ); ?>" <?php selected( $category_id, $cat->id ); ?>>
						<?php echo esc_html( $cat->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php submit_button( __( 'Filter', 'wb-ad-manager' ), '', 'filter_action', false ); ?>
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

		// Build query args.
		$args = array(
			'limit'   => $per_page,
			'offset'  => ( $this->get_pagenum() - 1 ) * $per_page,
			'orderby' => isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'created_at',
			'order'   => isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC',
		);

		if ( isset( $_GET['status'] ) && ! empty( $_GET['status'] ) ) {
			$args['status'] = sanitize_text_field( $_GET['status'] );
		}

		if ( isset( $_GET['link_type'] ) && ! empty( $_GET['link_type'] ) ) {
			$args['link_type'] = sanitize_text_field( $_GET['link_type'] );
		}

		if ( isset( $_GET['category_id'] ) && ! empty( $_GET['category_id'] ) ) {
			$args['category_id'] = (int) $_GET['category_id'];
		}

		if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) ) {
			$args['search'] = sanitize_text_field( $_GET['s'] );
		}

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

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'bulk-links' ) ) {
			return;
		}

		$link_ids = isset( $_GET['link'] ) ? array_map( 'intval', $_GET['link'] ) : array();

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
			'<input type="checkbox" name="link[]" value="%s">',
			esc_attr( $item->id )
		);
	}

	/**
	 * Name column.
	 *
	 * @param Link $item Current link item.
	 * @return string
	 */
	public function column_name( $item ) {
		$edit_url   = admin_url( 'edit.php?post_type=wbam-ad&page=wbam-links&action=edit&link_id=' . $item->id );
		$delete_url = wp_nonce_url(
			admin_url( 'edit.php?post_type=wbam-ad&page=wbam-links&action=delete&link_id=' . $item->id ),
			'wbam_delete_link_' . $item->id
		);

		$actions = array(
			'edit'   => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_url ),
				__( 'Edit', 'wb-ad-manager' )
			),
			'delete' => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\');" class="delete">%s</a>',
				esc_url( $delete_url ),
				esc_attr__( 'Are you sure you want to delete this link?', 'wb-ad-manager' ),
				__( 'Delete', 'wb-ad-manager' )
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
			return '<span class="dashicons dashicons-no-alt" title="' . esc_attr__( 'Cloaking disabled', 'wb-ad-manager' ) . '"></span>';
		}

		$cloaked_url = $item->get_url();

		return sprintf(
			'<a href="%s" target="_blank">%s</a>
			<button type="button" class="button button-small wbam-copy-btn" data-clipboard="%s" title="%s">
				<span class="dashicons dashicons-admin-page"></span>
			</button>',
			esc_url( $cloaked_url ),
			esc_html( $item->slug ),
			esc_attr( $cloaked_url ),
			esc_attr__( 'Copy URL', 'wb-ad-manager' )
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
		$label    = isset( $statuses[ $item->status ] ) ? $statuses[ $item->status ] : $item->status;

		$class = 'wbam-status wbam-status-' . $item->status;

		// Check if expired.
		if ( 'active' === $item->status && $item->expires_at && strtotime( $item->expires_at ) < time() ) {
			$class = 'wbam-status wbam-status-expired';
			$label = __( 'Expired', 'wb-ad-manager' );
		}

		return sprintf( '<span class="%s">%s</span>', esc_attr( $class ), esc_html( $label ) );
	}

	/**
	 * Created at column.
	 *
	 * @param Link $item Current link item.
	 * @return string
	 */
	public function column_created_at( $item ) {
		return sprintf(
			'<span title="%s">%s</span>',
			esc_attr( $item->created_at ),
			esc_html( human_time_diff( strtotime( $item->created_at ), time() ) . ' ' . __( 'ago', 'wb-ad-manager' ) )
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
	 */
	public function no_items() {
		esc_html_e( 'No links found.', 'wb-ad-manager' );
	}
}
