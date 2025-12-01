<?php
/**
 * Links Admin Class
 *
 * Handles admin interface for link management.
 *
 * @package WB_Ad_Manager
 * @since   2.1.0
 */

namespace WBAM\Modules\Links;

use WBAM\Core\Singleton;

/**
 * Links Admin class.
 */
class Links_Admin {

	use Singleton;

	/**
	 * Parent menu slug.
	 *
	 * @var string
	 */
	private $parent_slug = 'edit.php?post_type=wbam-ad';

	/**
	 * Capability required.
	 *
	 * @var string
	 */
	private $capability = 'manage_options';

	/**
	 * Initialize admin.
	 */
	public function init() {
		// Priority 21: After PRO's Sections 1-3 (priority 20), before PRO's Settings (priority 22).
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 21 );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add submenu page.
	 */
	public function add_submenu() {
		// Add separator before Link Management section.
		add_submenu_page(
			$this->parent_slug,
			'',
			'<span class="wbam-menu-separator"></span>',
			$this->capability,
			'wbam-separator-links',
			'__return_false'
		);

		// Link Management section header (points to Links page).
		add_submenu_page(
			$this->parent_slug,
			__( 'Link Management', 'wb-ad-manager' ),
			'<span class="wbam-menu-section">📎 ' . __( 'Link Management', 'wb-ad-manager' ) . '</span>',
			$this->capability,
			'wbam-links',
			array( $this, 'render_page' )
		);

		// Child item: Categories (indented).
		add_submenu_page(
			$this->parent_slug,
			__( 'Link Categories', 'wb-ad-manager' ),
			'<span class="wbam-menu-child">' . __( 'Categories', 'wb-ad-manager' ) . '</span>',
			$this->capability,
			'wbam-link-categories',
			array( $this, 'render_categories_page' )
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Always add menu styling CSS on all admin pages.
		$this->add_menu_styles();

		if ( ! in_array( $hook, array( 'wbam-ad_page_wbam-links', 'wbam-ad_page_wbam-link-categories' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'wbam-links-admin',
			WBAM_URL . 'assets/css/links-admin.css',
			array(),
			WBAM_VERSION
		);

		wp_enqueue_script(
			'wbam-admin',
			WBAM_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WBAM_VERSION,
			true
		);
	}

	/**
	 * Add inline styles for admin menu grouping.
	 */
	private function add_menu_styles() {
		static $added = false;
		if ( $added ) {
			return;
		}
		$added = true;

		add_action( 'admin_head', function() {
			?>
			<style>
			/* Link Management Menu Grouping */
			#adminmenu .wbam-menu-separator {
				display: block;
				height: 1px;
				background: rgba(255,255,255,0.1);
				margin: 5px 12px;
			}
			#adminmenu li a[href*="wbam-separator-links"] {
				padding: 0 !important;
				height: auto !important;
				min-height: 0 !important;
				pointer-events: none;
				cursor: default;
			}
			#adminmenu .wbam-menu-section {
				font-weight: 600;
				opacity: 1;
			}
			#adminmenu .wbam-menu-child {
				padding-left: 8px;
				opacity: 0.85;
			}
			#adminmenu .wbam-menu-child::before {
				content: "└ ";
				opacity: 0.5;
			}
			/* Highlight active section */
			#adminmenu li.current a .wbam-menu-section,
			#adminmenu li.current a .wbam-menu-child {
				opacity: 1;
			}
			</style>
			<?php
		} );
	}

	/**
	 * Handle admin actions.
	 */
	public function handle_actions() {
		if ( ! current_user_can( $this->capability ) ) {
			return;
		}

		// Handle link actions.
		if ( isset( $_POST['wbam_save_link'] ) && check_admin_referer( 'wbam_save_link' ) ) {
			$this->save_link();
		}

		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['link_id'] ) ) {
			if ( check_admin_referer( 'wbam_delete_link_' . $_GET['link_id'] ) ) {
				$this->delete_link( (int) $_GET['link_id'] );
			}
		}

		// Handle category actions.
		if ( isset( $_POST['wbam_save_category'] ) && check_admin_referer( 'wbam_save_category' ) ) {
			$this->save_category();
		}

		if ( isset( $_GET['action'] ) && 'delete_category' === $_GET['action'] && isset( $_GET['category_id'] ) ) {
			if ( check_admin_referer( 'wbam_delete_category_' . $_GET['category_id'] ) ) {
				$this->delete_category( (int) $_GET['category_id'] );
			}
		}
	}

	/**
	 * Render main links page.
	 */
	public function render_page() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

		echo '<div class="wrap">';

		switch ( $action ) {
			case 'add':
			case 'edit':
				$this->render_edit_form();
				break;

			default:
				$this->render_links_list();
				break;
		}

		echo '</div>';
	}

	/**
	 * Render links list.
	 */
	private function render_links_list() {
		$list_table = new Links_List_Table();
		$list_table->prepare_items();

		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Links', 'wb-ad-manager' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-links&action=add' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add New Link', 'wb-ad-manager' ); ?>
		</a>
		<hr class="wp-header-end">

		<?php $this->show_notices(); ?>

		<form method="get">
			<input type="hidden" name="page" value="wbam-links">
			<?php
			$list_table->search_box( __( 'Search Links', 'wb-ad-manager' ), 'wbam-link' );
			$list_table->display();
			?>
		</form>
		<?php
	}

	/**
	 * Render edit/add form.
	 */
	private function render_edit_form() {
		$link_id = isset( $_GET['link_id'] ) ? (int) $_GET['link_id'] : 0;
		$link    = null;

		if ( $link_id ) {
			$link_manager = Link_Manager::get_instance();
			$link         = $link_manager->get( $link_id );

			if ( ! $link ) {
				wp_die( esc_html__( 'Link not found.', 'wb-ad-manager' ) );
			}
		}

		$is_edit = (bool) $link;
		$title   = $is_edit ? __( 'Edit Link', 'wb-ad-manager' ) : __( 'Add New Link', 'wb-ad-manager' );

		?>
		<h1><?php echo esc_html( $title ); ?></h1>

		<?php $this->show_notices(); ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'wbam_save_link' ); ?>
			<input type="hidden" name="link_id" value="<?php echo esc_attr( $link_id ); ?>">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="name"><?php esc_html_e( 'Name', 'wb-ad-manager' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="text" name="name" id="name" class="regular-text" required
							value="<?php echo esc_attr( $link ? $link->name : '' ); ?>">
						<p class="description"><?php esc_html_e( 'Internal name for this link.', 'wb-ad-manager' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="destination_url"><?php esc_html_e( 'Destination URL', 'wb-ad-manager' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="url" name="destination_url" id="destination_url" class="large-text" required
							value="<?php echo esc_url( $link ? $link->destination_url : '' ); ?>">
						<p class="description"><?php esc_html_e( 'The target URL where visitors will be redirected.', 'wb-ad-manager' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="slug"><?php esc_html_e( 'Slug', 'wb-ad-manager' ); ?></label>
					</th>
					<td>
						<input type="text" name="slug" id="slug" class="regular-text"
							value="<?php echo esc_attr( $link ? $link->slug : '' ); ?>">
						<p class="description">
							<?php
							$prefix = Link_Cloaker::get_instance()->get_cloak_prefix();
							printf(
								/* translators: %s: example URL */
								esc_html__( 'Leave empty to auto-generate. Cloaked URL: %s', 'wb-ad-manager' ),
								'<code>' . esc_html( home_url( '/' . $prefix . '/' ) ) . '<strong>your-slug</strong></code>'
							);
							?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="link_type"><?php esc_html_e( 'Link Type', 'wb-ad-manager' ); ?></label>
					</th>
					<td>
						<select name="link_type" id="link_type">
							<?php foreach ( Link::get_link_types() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $link ? $link->link_type : 'affiliate', $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="category_id"><?php esc_html_e( 'Category', 'wb-ad-manager' ); ?></label>
					</th>
					<td>
						<select name="category_id" id="category_id">
							<option value="0"><?php esc_html_e( '— No Category —', 'wb-ad-manager' ); ?></option>
							<?php
							$link_manager = Link_Manager::get_instance();
							$categories   = $link_manager->get_categories();
							foreach ( $categories as $cat ) :
								?>
								<option value="<?php echo esc_attr( $cat->id ); ?>" <?php selected( $link ? $link->category_id : 0, $cat->id ); ?>>
									<?php echo esc_html( $cat->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Link Options', 'wb-ad-manager' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="cloaking_enabled" value="1"
									<?php checked( $link ? $link->cloaking_enabled : true ); ?>>
								<?php esc_html_e( 'Enable cloaking', 'wb-ad-manager' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="nofollow" value="1"
									<?php checked( $link ? $link->nofollow : true ); ?>>
								<?php esc_html_e( 'Add nofollow attribute', 'wb-ad-manager' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="sponsored" value="1"
									<?php checked( $link ? $link->sponsored : false ); ?>>
								<?php esc_html_e( 'Add sponsored attribute', 'wb-ad-manager' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="new_tab" value="1"
									<?php checked( $link ? $link->new_tab : true ); ?>>
								<?php esc_html_e( 'Open in new tab', 'wb-ad-manager' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="redirect_type"><?php esc_html_e( 'Redirect Type', 'wb-ad-manager' ); ?></label>
					</th>
					<td>
						<select name="redirect_type" id="redirect_type">
							<?php foreach ( Link::get_redirect_types() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $link ? $link->redirect_type : 307, $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="status"><?php esc_html_e( 'Status', 'wb-ad-manager' ); ?></label>
					</th>
					<td>
						<select name="status" id="status">
							<?php foreach ( Link::get_statuses() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $link ? $link->status : 'active', $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="expires_at"><?php esc_html_e( 'Expiration Date', 'wb-ad-manager' ); ?></label>
					</th>
					<td>
						<input type="datetime-local" name="expires_at" id="expires_at"
							value="<?php echo esc_attr( $link && $link->expires_at ? gmdate( 'Y-m-d\TH:i', strtotime( $link->expires_at ) ) : '' ); ?>">
						<p class="description"><?php esc_html_e( 'Leave empty for no expiration.', 'wb-ad-manager' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="description"><?php esc_html_e( 'Description', 'wb-ad-manager' ); ?></label>
					</th>
					<td>
						<textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea( $link ? $link->description : '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Optional notes about this link.', 'wb-ad-manager' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="wbam_save_link" class="button button-primary"
					value="<?php echo esc_attr( $is_edit ? __( 'Update Link', 'wb-ad-manager' ) : __( 'Create Link', 'wb-ad-manager' ) ); ?>">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-links' ) ); ?>" class="button">
					<?php esc_html_e( 'Cancel', 'wb-ad-manager' ); ?>
				</a>
			</p>
		</form>

		<?php if ( $is_edit ) : ?>
			<div class="wbam-link-info">
				<h3><?php esc_html_e( 'Link Information', 'wb-ad-manager' ); ?></h3>
				<table class="widefat">
					<tr>
						<th><?php esc_html_e( 'Cloaked URL', 'wb-ad-manager' ); ?></th>
						<td>
							<code><?php echo esc_html( $link->get_url() ); ?></code>
							<button type="button" class="button button-small wbam-copy-url" data-url="<?php echo esc_attr( $link->get_url() ); ?>">
								<?php esc_html_e( 'Copy', 'wb-ad-manager' ); ?>
							</button>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Shortcode', 'wb-ad-manager' ); ?></th>
						<td>
							<code>[wbam_link id="<?php echo esc_attr( $link->id ); ?>"]</code>
							<code>[wbam_link slug="<?php echo esc_attr( $link->slug ); ?>"]</code>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Total Clicks', 'wb-ad-manager' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $link->click_count ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Created', 'wb-ad-manager' ); ?></th>
						<td><?php echo esc_html( $link->created_at ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Last Updated', 'wb-ad-manager' ); ?></th>
						<td><?php echo esc_html( $link->updated_at ); ?></td>
					</tr>
				</table>
			</div>
		<?php endif;
	}

	/**
	 * Save link from form.
	 */
	private function save_link() {
		$link_id = isset( $_POST['link_id'] ) ? (int) $_POST['link_id'] : 0;

		$data = array(
			'name'             => isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '',
			'destination_url'  => isset( $_POST['destination_url'] ) ? esc_url_raw( $_POST['destination_url'] ) : '',
			'slug'             => isset( $_POST['slug'] ) ? sanitize_title( $_POST['slug'] ) : '',
			'link_type'        => isset( $_POST['link_type'] ) ? sanitize_text_field( $_POST['link_type'] ) : 'affiliate',
			'category_id'      => isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0,
			'cloaking_enabled' => isset( $_POST['cloaking_enabled'] ) ? 1 : 0,
			'nofollow'         => isset( $_POST['nofollow'] ) ? 1 : 0,
			'sponsored'        => isset( $_POST['sponsored'] ) ? 1 : 0,
			'new_tab'          => isset( $_POST['new_tab'] ) ? 1 : 0,
			'redirect_type'    => isset( $_POST['redirect_type'] ) ? (int) $_POST['redirect_type'] : 307,
			'status'           => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'active',
			'expires_at'       => isset( $_POST['expires_at'] ) && ! empty( $_POST['expires_at'] )
				? gmdate( 'Y-m-d H:i:s', strtotime( $_POST['expires_at'] ) )
				: null,
			'description'      => isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '',
		);

		$link_manager = Link_Manager::get_instance();

		if ( $link_id ) {
			$result = $link_manager->update( $link_id, $data );
			$message = $result ? 'link_updated' : 'link_error';
		} else {
			$new_id = $link_manager->create( $data );
			if ( $new_id ) {
				$link_id = $new_id;
				$message = 'link_created';
			} else {
				$message = 'link_error';
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'wbam-links',
					'action'  => 'edit',
					'link_id' => $link_id,
					'message' => $message,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Delete a link.
	 *
	 * @param int $link_id Link ID.
	 */
	private function delete_link( $link_id ) {
		$link_manager = Link_Manager::get_instance();
		$result       = $link_manager->delete( $link_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'wbam-links',
					'message' => $result ? 'link_deleted' : 'link_error',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render categories page.
	 */
	public function render_categories_page() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

		echo '<div class="wrap">';

		if ( 'edit_category' === $action || 'add_category' === $action ) {
			$this->render_category_form();
		} else {
			$this->render_categories_list();
		}

		echo '</div>';
	}

	/**
	 * Render categories list.
	 */
	private function render_categories_list() {
		$link_manager = Link_Manager::get_instance();
		$categories   = $link_manager->get_categories();

		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Link Categories', 'wb-ad-manager' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-link-categories&action=add_category' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add New Category', 'wb-ad-manager' ); ?>
		</a>
		<hr class="wp-header-end">

		<?php $this->show_notices(); ?>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'wb-ad-manager' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'wb-ad-manager' ); ?></th>
					<th><?php esc_html_e( 'Description', 'wb-ad-manager' ); ?></th>
					<th><?php esc_html_e( 'Count', 'wb-ad-manager' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wb-ad-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $categories ) ) : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'No categories found.', 'wb-ad-manager' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $categories as $category ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $category->name ); ?></strong></td>
							<td><?php echo esc_html( $category->slug ); ?></td>
							<td><?php echo esc_html( $category->description ); ?></td>
							<td><?php echo esc_html( $category->count ); ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-link-categories&action=edit_category&category_id=' . $category->id ) ); ?>">
									<?php esc_html_e( 'Edit', 'wb-ad-manager' ); ?>
								</a> |
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-link-categories&action=delete_category&category_id=' . $category->id ), 'wbam_delete_category_' . $category->id ) ); ?>"
									onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this category?', 'wb-ad-manager' ); ?>');" class="delete">
									<?php esc_html_e( 'Delete', 'wb-ad-manager' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render category form.
	 */
	private function render_category_form() {
		$category_id = isset( $_GET['category_id'] ) ? (int) $_GET['category_id'] : 0;
		$category    = null;

		if ( $category_id ) {
			$link_manager = Link_Manager::get_instance();
			$category     = $link_manager->get_category( $category_id );

			if ( ! $category ) {
				wp_die( esc_html__( 'Category not found.', 'wb-ad-manager' ) );
			}
		}

		$is_edit = (bool) $category;
		$title   = $is_edit ? __( 'Edit Category', 'wb-ad-manager' ) : __( 'Add New Category', 'wb-ad-manager' );

		?>
		<h1><?php echo esc_html( $title ); ?></h1>

		<?php $this->show_notices(); ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'wbam_save_category' ); ?>
			<input type="hidden" name="category_id" value="<?php echo esc_attr( $category_id ); ?>">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="name"><?php esc_html_e( 'Name', 'wb-ad-manager' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="text" name="name" id="name" class="regular-text" required
							value="<?php echo esc_attr( $category ? $category->name : '' ); ?>">
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="slug"><?php esc_html_e( 'Slug', 'wb-ad-manager' ); ?></label>
					</th>
					<td>
						<input type="text" name="slug" id="slug" class="regular-text"
							value="<?php echo esc_attr( $category ? $category->slug : '' ); ?>">
						<p class="description"><?php esc_html_e( 'Leave empty to auto-generate from name.', 'wb-ad-manager' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="description"><?php esc_html_e( 'Description', 'wb-ad-manager' ); ?></label>
					</th>
					<td>
						<textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea( $category ? $category->description : '' ); ?></textarea>
					</td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="wbam_save_category" class="button button-primary"
					value="<?php echo esc_attr( $is_edit ? __( 'Update Category', 'wb-ad-manager' ) : __( 'Create Category', 'wb-ad-manager' ) ); ?>">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-link-categories' ) ); ?>" class="button">
					<?php esc_html_e( 'Cancel', 'wb-ad-manager' ); ?>
				</a>
			</p>
		</form>
		<?php
	}

	/**
	 * Save category from form.
	 */
	private function save_category() {
		$category_id = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;

		$data = array(
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '',
			'slug'        => isset( $_POST['slug'] ) ? sanitize_title( $_POST['slug'] ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '',
		);

		$link_manager = Link_Manager::get_instance();

		if ( $category_id ) {
			$result  = $link_manager->update_category( $category_id, $data );
			$message = $result ? 'category_updated' : 'category_error';
		} else {
			$new_id = $link_manager->create_category( $data );
			if ( $new_id ) {
				$category_id = $new_id;
				$message     = 'category_created';
			} else {
				$message = 'category_error';
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'wbam-link-categories',
					'message' => $message,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Delete a category.
	 *
	 * @param int $category_id Category ID.
	 */
	private function delete_category( $category_id ) {
		$link_manager = Link_Manager::get_instance();
		$result       = $link_manager->delete_category( $category_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'wbam-link-categories',
					'message' => $result ? 'category_deleted' : 'category_error',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Show admin notices.
	 */
	private function show_notices() {
		if ( ! isset( $_GET['message'] ) ) {
			return;
		}

		$messages = array(
			'link_created'     => array( 'success', __( 'Link created successfully.', 'wb-ad-manager' ) ),
			'link_updated'     => array( 'success', __( 'Link updated successfully.', 'wb-ad-manager' ) ),
			'link_deleted'     => array( 'success', __( 'Link deleted successfully.', 'wb-ad-manager' ) ),
			'link_error'       => array( 'error', __( 'An error occurred. Please try again.', 'wb-ad-manager' ) ),
			'category_created' => array( 'success', __( 'Category created successfully.', 'wb-ad-manager' ) ),
			'category_updated' => array( 'success', __( 'Category updated successfully.', 'wb-ad-manager' ) ),
			'category_deleted' => array( 'success', __( 'Category deleted successfully.', 'wb-ad-manager' ) ),
			'category_error'   => array( 'error', __( 'An error occurred. Please try again.', 'wb-ad-manager' ) ),
		);

		$message_key = sanitize_text_field( $_GET['message'] );

		if ( isset( $messages[ $message_key ] ) ) {
			$type = $messages[ $message_key ][0];
			$text = $messages[ $message_key ][1];

			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $type ),
				esc_html( $text )
			);
		}
	}
}
