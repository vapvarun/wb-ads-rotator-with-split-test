<?php
/**
 * Email Captures list table.
 *
 * @package WBAM\Admin
 * @since   3.2.0
 */

namespace WBAM\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Captured emails with search, ad filter, sort, bulk delete and export.
 */
class Email_Captures_List_Table extends \WP_List_Table {

	/**
	 * Data source.
	 *
	 * @var Email_Captures
	 */
	private $captures;

	/**
	 * Constructor.
	 *
	 * @param Email_Captures $captures Data source.
	 */
	public function __construct( Email_Captures $captures ) {
		$this->captures = $captures;
		parent::__construct(
			array(
				'singular' => 'capture',
				'plural'   => 'captures',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'email'      => __( 'Email', 'wb-ads-rotator-with-split-test' ),
			'name'       => __( 'Name', 'wb-ads-rotator-with-split-test' ),
			'ad'         => __( 'Ad', 'wb-ads-rotator-with-split-test' ),
			'created_at' => __( 'Date', 'wb-ads-rotator-with-split-test' ),
		);
	}

	/**
	 * Sortable columns. Date sorts by ID: rows are written in order.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns() {
		return array(
			'email'      => array( 'email', false ),
			'created_at' => array( 'id', true ),
		);
	}

	/**
	 * Primary column.
	 *
	 * @return string
	 */
	protected function get_primary_column_name() {
		return 'email';
	}

	/**
	 * Checkbox.
	 *
	 * @param \stdClass $item Row.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<label class="screen-reader-text" for="wbam-capture-%1$d">%2$s</label><input type="checkbox" id="wbam-capture-%1$d" name="capture_ids[]" value="%1$d" />',
			(int) $item->id,
			/* translators: %s: name of the item being selected */
			esc_html( sprintf( __( 'Select %s', 'wb-ads-rotator-with-split-test' ), $item->email ) )
		);
	}

	/**
	 * Email, with the single Delete action.
	 *
	 * @param \stdClass $item Row.
	 * @return string
	 */
	public function column_email( $item ) {
		$delete_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wbam_delete_email_capture&id=' . (int) $item->id . '&paged=' . $this->get_pagenum() ),
			'wbam_delete_email_capture_' . (int) $item->id
		);

		return esc_html( $item->email ) . $this->row_actions(
			array(
				'delete' => sprintf(
					'<a href="%s" class="submitdelete" data-wbam-confirm="%s" data-wbam-confirm-tone="danger">%s</a>',
					esc_url( $delete_url ),
					esc_attr__( 'Delete this capture? This cannot be undone.', 'wb-ads-rotator-with-split-test' ),
					esc_html__( 'Delete', 'wb-ads-rotator-with-split-test' )
				),
			)
		);
	}

	/**
	 * Default column output.
	 *
	 * @param \stdClass $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'ad':
				$title = $item->ad_id ? get_the_title( (int) $item->ad_id ) : '';
				return '' !== $title ? esc_html( $title ) : esc_html( '#' . (int) $item->ad_id );
			case 'created_at':
				return esc_html( (string) mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->created_at ) );
			default:
				return isset( $item->$column_name ) ? esc_html( (string) $item->$column_name ) : '';
		}
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array( 'delete_captures' => __( 'Delete', 'wb-ads-rotator-with-split-test' ) );
	}

	/**
	 * Ad filter and export.
	 *
	 * @param string $which top|bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$args = Email_Captures::request_args();
		$ads  = $this->captures->captured_ads();
		?>
		<div class="alignleft actions">
			<?php if ( $ads ) : ?>
				<label for="wbam-capture-ad" class="screen-reader-text"><?php esc_html_e( 'Filter by ad', 'wb-ads-rotator-with-split-test' ); ?></label>
				<select name="capture_ad" id="wbam-capture-ad">
					<option value=""><?php esc_html_e( 'All ads', 'wb-ads-rotator-with-split-test' ); ?></option>
					<?php foreach ( $ads as $id => $title ) : ?>
						<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $args['ad_id'], $id ); ?>><?php echo esc_html( '' !== $title ? $title : '#' . $id ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Filter', 'wb-ads-rotator-with-split-test' ), '', 'filter_action', false ); ?>
			<?php endif; ?>
			<?php if ( $this->has_items() ) : ?>
				<a class="button" href="
				<?php
				echo esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'action'     => 'wbam_export_email_captures',
								's'          => $args['search'],
								'capture_ad' => $args['ad_id'],
								'orderby'    => $args['orderby'],
								'order'      => $args['order'],
							),
							admin_url( 'admin-post.php' )
						),
						'wbam_export_email_captures'
					)
				);
				?>
				"><?php esc_html_e( 'Export CSV', 'wb-ads-rotator-with-split-test' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$args     = Email_Captures::request_args();
		$per_page = Email_Captures::PER_PAGE;
		$total    = $this->captures->count( $args );

		$this->items = $this->captures->get_page( $this->get_pagenum(), $per_page, $args );
		_prime_post_caches( array_filter( array_map( 'intval', wp_list_pluck( $this->items, 'ad_id' ) ) ), false, false );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Empty state.
	 *
	 * @return void
	 */
	public function no_items() {
		echo wp_kses_post(
			UX::empty_state(
				array(
					'icon'    => 'mail',
					'title'   => __( 'No email captures yet', 'wb-ads-rotator-with-split-test' ),
					'message' => __( 'Submissions from the Email Capture ad type appear here.', 'wb-ads-rotator-with-split-test' ),
				)
			)
		);
	}
}
