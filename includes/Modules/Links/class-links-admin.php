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

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
	 * Add menu pages.
	 */
	public function add_submenu() {
		// A site that does not use links switches the module off and loses the
		// whole menu. PRO gates its four Links submenus on the same flag, so
		// nothing is left orphaned under a parent that no longer exists.
		if ( ! \WBAM\Core\Settings_Helper::is_module_enabled( 'links' ) ) {
			return;
		}

		// Links - Separate top-level menu.
		add_menu_page(
			__( 'Links', 'wb-ads-rotator-with-split-test' ),
			__( 'Links', 'wb-ads-rotator-with-split-test' ),
			$this->capability,
			'wbam-links',
			array( $this, 'render_page' ),
			'dashicons-admin-links',
			25.3 // Right after Advertisers (25.2)
		);

		// All Links (rename default submenu).
		add_submenu_page(
			'wbam-links',
			__( 'All Links', 'wb-ads-rotator-with-split-test' ),
			__( 'All Links', 'wb-ads-rotator-with-split-test' ),
			$this->capability,
			'wbam-links',
			array( $this, 'render_page' )
		);

		// Categories.
		add_submenu_page(
			'wbam-links',
			__( 'Link Categories', 'wb-ads-rotator-with-split-test' ),
			__( 'Categories', 'wb-ads-rotator-with-split-test' ),
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
		if ( ! in_array( $hook, array( 'toplevel_page_wbam-links', 'links_page_wbam-link-categories' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'wbam-links-admin',
			WBAM_URL . 'assets/css/links-admin.css',
			array( 'wbam-admin-tokens' ),
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
			$link_id = absint( wp_unslash( $_GET['link_id'] ) );
			if ( check_admin_referer( 'wbam_delete_link_' . $link_id ) ) {
				$this->delete_link( $link_id );
			}
		}

		// Handle category actions.
		if ( isset( $_POST['wbam_save_category'] ) && check_admin_referer( 'wbam_save_category' ) ) {
			$this->save_category();
		}

		if ( isset( $_GET['action'] ) && 'delete_category' === $_GET['action'] && isset( $_GET['category_id'] ) ) {
			$category_id = absint( wp_unslash( $_GET['category_id'] ) );
			if ( check_admin_referer( 'wbam_delete_category_' . $category_id ) ) {
				$this->delete_category( $category_id );
			}
		}
	}

	/**
	 * Render main links page.
	 */
	public function render_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only router between list / edit views; mutations are nonce-checked in their own handlers.
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

		echo '<div class="wrap wbam-admin">';

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
		<?php
		\WBAM\Admin\UX::page_header(
			array(
				'title'   => __( 'Links', 'wb-ads-rotator-with-split-test' ),
				'desc'    => __( 'Cloaked, trackable outbound and affiliate links.', 'wb-ads-rotator-with-split-test' ),
				'actions' => '<a href="' . esc_url( admin_url( 'admin.php?page=wbam-links&action=add' ) ) . '" class="wbam-admin-btn wbam-admin-btn--primary">' . esc_html__( 'Add New Link', 'wb-ads-rotator-with-split-test' ) . '</a>',
			)
		);
		$this->show_notices();
		?>

		<form method="get">
			<input type="hidden" name="page" value="wbam-links">
			<?php
			$list_table->search_box( __( 'Search Links', 'wb-ads-rotator-with-split-test' ), 'wbam-link' );
			$list_table->display();
			?>
		</form>
		<script>
		document.addEventListener( 'click', function ( e ) {
			var link = e.target.closest( '.wbam-copy-row' );
			if ( ! link ) {
				return;
			}
			e.preventDefault();
			var text     = link.getAttribute( 'data-copy' );
			var done     = link.getAttribute( 'data-done' ) || 'Copied';
			var original = link.textContent;
			var finish   = function () {
				link.textContent = done;
				setTimeout( function () { link.textContent = original; }, 1500 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( finish, finish );
			} else {
				finish();
			}
		} );
		</script>
		<?php
	}

	/**
	 * Render edit/add form.
	 */
	private function render_edit_form() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read of edit-target ID for form prefill; save_link() verifies the wbam_save_link nonce.
		$link_id = isset( $_GET['link_id'] ) ? (int) $_GET['link_id'] : 0;
		$link    = null;

		if ( $link_id ) {
			$link_manager = Link_Manager::get_instance();
			$link         = $link_manager->get( $link_id );

			if ( ! $link ) {
				wp_die( esc_html__( 'Link not found.', 'wb-ads-rotator-with-split-test' ) );
			}
		}

		$is_edit = (bool) $link;
		$title   = $is_edit ? __( 'Edit Link', 'wb-ads-rotator-with-split-test' ) : __( 'Add New Link', 'wb-ads-rotator-with-split-test' );

		/**
		 * Fires before the link edit form.
		 *
		 * @since 2.3.0
		 * @param Link|null $link    Link object or null for new link.
		 * @param bool      $is_edit Whether this is an edit form.
		 */
		do_action( 'wbam_link_form_before', $link, $is_edit );

		?>
		<h1><?php echo esc_html( $title ); ?></h1>

		<?php $this->show_notices(); ?>

		<?php if ( ! $is_edit ) : ?>
		<div class="notice notice-info wbam-link-intro" style="padding:14px 16px;border-left-width:4px;">
			<h3 style="margin:0 0 6px;"><?php esc_html_e( 'What a cloaked link does', 'wb-ads-rotator-with-split-test' ); ?></h3>
			<p style="margin:0 0 8px;">
				<?php esc_html_e( 'Turn a long or ugly destination URL into a clean, branded one on your domain. Visitors click the short URL on your site and are redirected to the destination. You see every click in the stats column.', 'wb-ads-rotator-with-split-test' ); ?>
			</p>
			<p style="margin:0;font-size:13px;color:#50575e;">
				<?php
				printf(
					/* translators: 1: raw affiliate URL example, 2: cloaked URL example */
					esc_html__( 'Typical use: turn %1$s into %2$s.', 'wb-ads-rotator-with-split-test' ),
					'<code>amazon.com/gp/product/B07XYZ?ref=affiliate_123</code>',
					'<code>' . esc_html( home_url( '/' . Link_Cloaker::get_instance()->get_cloak_prefix() . '/book' ) ) . '</code>'
				);
				?>
			</p>
		</div>
		<?php endif; ?>

		<?php if ( $is_edit && $link ) : ?>
			<?php
			// A cloaked URL only exists when cloaking is ON for this link —
			// with it off, the /go/ URL is not served, so advertising it
			// here (with copy + test buttons, no less) handed the admin a
			// dead URL. Cloaking off means the shortcode is the one way in.
			$is_cloaked   = $link->cloaking_enabled && ! empty( $link->slug );
			$cloak_prefix = Link_Cloaker::get_instance()->get_cloak_prefix();
			$cloaked_url  = home_url( '/' . $cloak_prefix . '/' . $link->slug );
			$shortcode    = '[wbam_link id="' . (int) $link->id . '"]' . $link->name . '[/wbam_link]';
			?>
			<div class="notice notice-success wbam-link-ready" style="padding:14px 16px;border-left-width:4px;">
				<h3 style="margin:0 0 10px;">
					<?php esc_html_e( 'Your link is ready to use', 'wb-ads-rotator-with-split-test' ); ?>
				</h3>
				<p style="margin:0 0 10px;font-size:13px;color:#50575e;">
					<?php
					if ( $is_cloaked ) {
						esc_html_e( 'Pick one of these two ways to put the link in your content.', 'wb-ads-rotator-with-split-test' );
					} else {
						esc_html_e( 'Cloaking is off for this link, so use the shortcode to place it in your content.', 'wb-ads-rotator-with-split-test' );
					}
					?>
				</p>

				<?php if ( $is_cloaked ) : ?>
				<p style="margin:0 0 6px;"><strong><?php esc_html_e( '1. Cloaked URL', 'wb-ads-rotator-with-split-test' ); ?></strong>
					. <?php esc_html_e( 'Paste this anywhere a link is accepted (posts, menus, widgets). Redirects to your destination URL.', 'wb-ads-rotator-with-split-test' ); ?>
				</p>
				<p style="margin:0 0 14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
					<input type="text" readonly value="<?php echo esc_attr( $cloaked_url ); ?>" aria-label="<?php esc_attr_e( 'Cloaked URL', 'wb-ads-rotator-with-split-test' ); ?>" class="regular-text" style="font-family:monospace;max-width:460px;" onclick="this.select();">
					<button type="button" class="button wbam-copy-btn" data-copy="<?php echo esc_attr( $cloaked_url ); ?>">
						<?php esc_html_e( 'Copy URL', 'wb-ads-rotator-with-split-test' ); ?>
					</button>
					<a href="<?php echo esc_url( $cloaked_url ); ?>" class="button" target="_blank" rel="noopener">
						<?php esc_html_e( 'Test Link', 'wb-ads-rotator-with-split-test' ); ?>
					</a>
				</p>

				<p style="margin:0 0 6px;"><strong><?php esc_html_e( '2. Shortcode with custom anchor text', 'wb-ads-rotator-with-split-test' ); ?></strong>
					. <?php esc_html_e( 'Use this in post content when you want specific anchor text and full rel-attribute control.', 'wb-ads-rotator-with-split-test' ); ?>
				</p>
				<?php else : ?>
				<p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Shortcode with custom anchor text', 'wb-ads-rotator-with-split-test' ); ?></strong>
					. <?php esc_html_e( 'Use this in post content when you want specific anchor text and full rel-attribute control.', 'wb-ads-rotator-with-split-test' ); ?>
				</p>
				<?php endif; ?>
				<p style="margin:0;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
					<input type="text" readonly value="<?php echo esc_attr( $shortcode ); ?>" aria-label="<?php esc_attr_e( 'Link shortcode', 'wb-ads-rotator-with-split-test' ); ?>" class="regular-text" style="font-family:monospace;max-width:460px;" onclick="this.select();">
					<button type="button" class="button wbam-copy-btn" data-copy="<?php echo esc_attr( $shortcode ); ?>">
						<?php esc_html_e( 'Copy Shortcode', 'wb-ads-rotator-with-split-test' ); ?>
					</button>
				</p>
			</div>
		<?php endif; ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'wbam_save_link' ); ?>
			<input type="hidden" name="link_id" value="<?php echo esc_attr( $link_id ); ?>">

			<?php
			/**
			 * Fires at the beginning of the link form fields.
			 *
			 * @since 2.3.0
			 * @param Link|null $link    Link object or null for new link.
			 * @param bool      $is_edit Whether this is an edit form.
			 */
			do_action( 'wbam_link_form_fields_before', $link, $is_edit );
			?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="name"><?php esc_html_e( 'Name', 'wb-ads-rotator-with-split-test' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="text" name="name" id="name" class="regular-text" required
							value="<?php echo esc_attr( $link ? $link->name : '' ); ?>">
						<p class="description"><?php esc_html_e( 'Private label shown only in this admin list (e.g. "Amazon Book of the Month"). Visitors never see it.', 'wb-ads-rotator-with-split-test' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="destination_url"><?php esc_html_e( 'Destination URL', 'wb-ads-rotator-with-split-test' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="url" name="destination_url" id="destination_url" class="large-text" required
							value="<?php echo esc_url( $link ? $link->destination_url : '' ); ?>">
						<p class="description"><?php esc_html_e( 'The real URL visitors end up on after clicking. Include any affiliate / tracking parameters here. They are invisible to your audience.', 'wb-ads-rotator-with-split-test' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="slug"><?php esc_html_e( 'Slug', 'wb-ads-rotator-with-split-test' ); ?></label>
					</th>
					<td>
						<input type="text" name="slug" id="slug" class="regular-text"
							value="<?php echo esc_attr( $link ? $link->slug : '' ); ?>">
						<p class="description">
							<?php
							$prefix = Link_Cloaker::get_instance()->get_cloak_prefix();
							printf(
								/* translators: %s: example URL */
								esc_html__( 'Leave empty to auto-generate. Cloaked URL: %s', 'wb-ads-rotator-with-split-test' ),
								'<code>' . esc_html( home_url( '/' . $prefix . '/' ) ) . '<strong>your-slug</strong></code>'
							);
							?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="link_type"><?php esc_html_e( 'Link Type', 'wb-ads-rotator-with-split-test' ); ?></label>
					</th>
					<td>
						<select name="link_type" id="link_type">
							<?php foreach ( Link::get_link_types() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $link ? $link->link_type : 'affiliate', $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Labels the link in the admin list so you can filter and report by type. Does not change how the redirect works.', 'wb-ads-rotator-with-split-test' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="category_id"><?php esc_html_e( 'Category', 'wb-ads-rotator-with-split-test' ); ?></label>
					</th>
					<td>
						<select name="category_id" id="category_id">
							<option value="0"><?php esc_html_e( 'No Category', 'wb-ads-rotator-with-split-test' ); ?></option>
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
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to the categories page */
								esc_html__( 'Optional. Group related links (e.g. "Amazon Books", "Software Deals") so you can filter the list and show them with %s.', 'wb-ads-rotator-with-split-test' ),
								'<code>[wbam_links category="slug"]</code>'
							);
							?>
							<br>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbam-link-categories' ) ); ?>"><?php esc_html_e( 'Manage categories →', 'wb-ads-rotator-with-split-test' ); ?></a>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Link Options', 'wb-ads-rotator-with-split-test' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="cloaking_enabled" value="1"
									<?php checked( $link ? $link->cloaking_enabled : true ); ?>>
								<?php esc_html_e( 'Enable cloaking', 'wb-ads-rotator-with-split-test' ); ?>
							</label>
							<p class="description" style="margin:4px 0 10px 24px;">
								<?php esc_html_e( 'When on, the short URL stays in the address bar while the browser is sent to the destination. Visitors see your domain, not the target. Turn off only if you want the raw destination URL in place.', 'wb-ads-rotator-with-split-test' ); ?>
							</p>

							<label>
								<input type="checkbox" name="nofollow" value="1"
									<?php checked( $link ? $link->nofollow : true ); ?>>
								<?php esc_html_e( 'Add rel="nofollow"', 'wb-ads-rotator-with-split-test' ); ?>
							</label>
							<p class="description" style="margin:4px 0 10px 24px;">
								<?php esc_html_e( 'Tells search engines not to pass link authority to the destination. Recommended for affiliate and untrusted links.', 'wb-ads-rotator-with-split-test' ); ?>
							</p>

							<label>
								<input type="checkbox" name="sponsored" value="1"
									<?php checked( $link ? $link->sponsored : false ); ?>>
								<?php esc_html_e( 'Add rel="sponsored"', 'wb-ads-rotator-with-split-test' ); ?>
							</label>
							<p class="description" style="margin:4px 0 10px 24px;">
								<?php esc_html_e( 'Google now prefers rel="sponsored" (not just nofollow) for paid or affiliate links. Enable this for any link where you receive compensation.', 'wb-ads-rotator-with-split-test' ); ?>
							</p>

							<label>
								<input type="checkbox" name="new_tab" value="1"
									<?php checked( $link ? $link->new_tab : true ); ?>>
								<?php esc_html_e( 'Open in new tab', 'wb-ads-rotator-with-split-test' ); ?>
							</label>
							<p class="description" style="margin:4px 0 0 24px;">
								<?php esc_html_e( 'Adds target="_blank" so visitors stay on your site. Only applies when you use the [wbam_link] shortcode. A raw cloaked URL opens in the same tab unless you add target yourself.', 'wb-ads-rotator-with-split-test' ); ?>
							</p>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="redirect_type"><?php esc_html_e( 'Redirect Type', 'wb-ads-rotator-with-split-test' ); ?></label>
					</th>
					<td>
						<select name="redirect_type" id="redirect_type">
							<?php foreach ( Link::get_redirect_types() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $link ? $link->redirect_type : 307, $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Which HTTP redirect the browser sees when someone clicks the cloaked URL.', 'wb-ads-rotator-with-split-test' ); ?>
							<br>
							<strong><?php esc_html_e( '307 Temporary', 'wb-ads-rotator-with-split-test' ); ?></strong>: <?php esc_html_e( 'Safest default. Browsers and bots always re-check the link, so you stay in control of where it points.', 'wb-ads-rotator-with-split-test' ); ?>
							<br>
							<strong><?php esc_html_e( '302 Found', 'wb-ads-rotator-with-split-test' ); ?></strong>: <?php esc_html_e( 'Also temporary; older equivalent of 307. Use if a specific destination rejects 307.', 'wb-ads-rotator-with-split-test' ); ?>
							<br>
							<strong><?php esc_html_e( '301 Permanent', 'wb-ads-rotator-with-split-test' ); ?></strong>: <?php esc_html_e( 'Search engines treat the destination as the canonical URL and browsers may cache the redirect aggressively. Only pick this for permanent moves, never for affiliate links.', 'wb-ads-rotator-with-split-test' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="status"><?php esc_html_e( 'Status', 'wb-ads-rotator-with-split-test' ); ?></label>
					</th>
					<td>
						<select name="status" id="status">
							<?php foreach ( Link::get_statuses() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $link ? $link->status : 'active', $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<strong><?php esc_html_e( 'Active', 'wb-ads-rotator-with-split-test' ); ?></strong>: <?php esc_html_e( 'The cloaked URL redirects to the destination and clicks are counted.', 'wb-ads-rotator-with-split-test' ); ?>
							<br>
							<strong><?php esc_html_e( 'Inactive', 'wb-ads-rotator-with-split-test' ); ?></strong>: <?php esc_html_e( 'The cloaked URL returns 404 (or redirects to a fallback if you set one in Settings). Use to temporarily disable a link without deleting its click history.', 'wb-ads-rotator-with-split-test' ); ?>
						</p>
					</td>
				</tr>

				<?php
				// strtotime() returns false on malformed input. PHP 8.1+ deprecates
				// passing false to gmdate(). Guard so the form renders blank for
				// corrupted stored values instead of throwing a deprecation notice.
				$expires_ts    = ( $link && $link->expires_at ) ? strtotime( $link->expires_at ) : false;
				$expires_value = false !== $expires_ts ? gmdate( 'Y-m-d\TH:i', $expires_ts ) : '';
				?>
				<tr>
					<th scope="row">
						<label for="expires_at"><?php esc_html_e( 'Expiration Date', 'wb-ads-rotator-with-split-test' ); ?></label>
					</th>
					<td>
						<input type="datetime-local" name="expires_at" id="expires_at"
							value="<?php echo esc_attr( $expires_value ); ?>">
						<p class="description"><?php esc_html_e( 'Leave empty for no expiration.', 'wb-ads-rotator-with-split-test' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="description"><?php esc_html_e( 'Description', 'wb-ads-rotator-with-split-test' ); ?></label>
					</th>
					<td>
						<textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea( $link ? $link->description : '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Optional notes about this link.', 'wb-ads-rotator-with-split-test' ); ?></p>
					</td>
				</tr>

				<?php
				/**
				 * Fires after all link form fields.
				 *
				 * Use this to add custom fields to the link form.
				 *
				 * @since 2.3.0
				 * @param Link|null $link    Link object or null for new link.
				 * @param bool      $is_edit Whether this is an edit form.
				 */
				do_action( 'wbam_link_form_fields_after', $link, $is_edit );
				?>
			</table>

			<?php
			/**
			 * Fires after the link form table, before submit button.
			 *
			 * @since 2.3.0
			 * @param Link|null $link    Link object or null for new link.
			 * @param bool      $is_edit Whether this is an edit form.
			 */
			do_action( 'wbam_link_form_after_fields', $link, $is_edit );
			?>

			<p class="submit">
				<input type="submit" name="wbam_save_link" class="button button-primary"
					value="<?php echo esc_attr( $is_edit ? __( 'Update Link', 'wb-ads-rotator-with-split-test' ) : __( 'Create Link', 'wb-ads-rotator-with-split-test' ) ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbam-links' ) ); ?>" class="button">
					<?php esc_html_e( 'Cancel', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
			</p>
		</form>

		<script>
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.wbam-copy-btn' );
			if ( ! btn ) {
				return;
			}
			e.preventDefault();
			var text = btn.getAttribute( 'data-copy' );
			var original = btn.textContent;
			var finish = function ( label ) {
				btn.textContent = label;
				setTimeout( function () { btn.textContent = original; }, 1500 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then(
					function () { finish( <?php echo wp_json_encode( __( 'Copied!', 'wb-ads-rotator-with-split-test' ) ); ?> ); },
					function () { finish( <?php echo wp_json_encode( __( 'Press Ctrl+C', 'wb-ads-rotator-with-split-test' ) ); ?> ); }
				);
			} else {
				finish( <?php echo wp_json_encode( __( 'Press Ctrl+C', 'wb-ads-rotator-with-split-test' ) ); ?> );
			}
		} );
		</script>

		<?php
		/**
		 * Fires after the link form.
		 *
		 * @since 2.3.0
		 * @param Link|null $link    Link object or null for new link.
		 * @param bool      $is_edit Whether this is an edit form.
		 */
		do_action( 'wbam_link_form_after', $link, $is_edit );
		?>

		<?php if ( $is_edit ) : ?>
			<div class="wbam-link-info">
				<h3><?php esc_html_e( 'Link Information', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<table class="widefat">
					<tr>
						<?php // get_url() already returns the destination when cloaking is off; the label has to say which one the admin is looking at. ?>
						<th>
							<?php
							if ( $link->cloaking_enabled && ! empty( $link->slug ) ) {
								esc_html_e( 'Cloaked URL', 'wb-ads-rotator-with-split-test' );
							} else {
								esc_html_e( 'Destination URL', 'wb-ads-rotator-with-split-test' );
							}
							?>
						</th>
						<td>
							<code><?php echo esc_html( $link->get_url() ); ?></code>
							<button type="button" class="button button-small wbam-copy-url" data-url="<?php echo esc_attr( $link->get_url() ); ?>">
								<?php esc_html_e( 'Copy', 'wb-ads-rotator-with-split-test' ); ?>
							</button>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Shortcode', 'wb-ads-rotator-with-split-test' ); ?></th>
						<td>
							<code>[wbam_link id="<?php echo esc_attr( $link->id ); ?>"]</code>
							<code>[wbam_link slug="<?php echo esc_attr( $link->slug ); ?>"]</code>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Total Clicks', 'wb-ads-rotator-with-split-test' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $link->click_count ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Created', 'wb-ads-rotator-with-split-test' ); ?></th>
						<td><?php echo esc_html( $link->created_at ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Last Updated', 'wb-ads-rotator-with-split-test' ); ?></th>
						<td><?php echo esc_html( $link->updated_at ); ?></td>
					</tr>
				</table>
			</div>
			<?php
		endif;
	}

	/**
	 * Save link from form.
	 *
	 * @return void
	 *
	 * Nonce verification: the caller `handle_admin_actions()` validates the
	 * `wbam_save_link` nonce via `check_admin_referer()` BEFORE invoking this
	 * method, so the `$_POST` reads below are guarded. phpcs:disable
	 * WordPress.Security.NonceVerification.Missing. Verified by caller.
	 */
	private function save_link() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Caller handle_admin_actions() verifies the wbam_save_link nonce.
		$link_id = isset( $_POST['link_id'] ) ? (int) $_POST['link_id'] : 0;
		$is_edit = (bool) $link_id;

		// Expiration: parse the datetime-local input. strtotime() returns false
		// on malformed input; in that case treat the field as empty rather than
		// storing a 1970-01-01 timestamp (or triggering a PHP 8.1+ deprecation
		// notice by passing false to gmdate).
		$expires_at = null;
		if ( ! empty( $_POST['expires_at'] ) ) {
			$expires_ts = strtotime( sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) );
			if ( false !== $expires_ts ) {
				$expires_at = gmdate( 'Y-m-d H:i:s', $expires_ts );
			}
		}

		$data = array(
			'name'             => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'destination_url'  => isset( $_POST['destination_url'] ) ? esc_url_raw( wp_unslash( $_POST['destination_url'] ) ) : '',
			'slug'             => isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '',
			'link_type'        => isset( $_POST['link_type'] ) ? sanitize_text_field( wp_unslash( $_POST['link_type'] ) ) : 'affiliate',
			'category_id'      => isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0,
			'cloaking_enabled' => isset( $_POST['cloaking_enabled'] ) ? 1 : 0,
			'nofollow'         => isset( $_POST['nofollow'] ) ? 1 : 0,
			'sponsored'        => isset( $_POST['sponsored'] ) ? 1 : 0,
			'new_tab'          => isset( $_POST['new_tab'] ) ? 1 : 0,
			'redirect_type'    => isset( $_POST['redirect_type'] ) ? (int) $_POST['redirect_type'] : 307,
			'status'           => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active',
			'expires_at'       => $expires_at,
			'description'      => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
		);

		/**
		 * Filter link data before saving.
		 *
		 * @since 2.3.0
		 * @param array $data    Link data to save.
		 * @param int   $link_id Link ID (0 for new links).
		 * @param array $raw_post Raw POST data (unslashed).
		 */
		$data = apply_filters( 'wbam_link_save_data', $data, $link_id, wp_unslash( $_POST ) );

		/**
		 * Fires before saving a link.
		 *
		 * @since 2.3.0
		 * @param array $data    Link data to save.
		 * @param int   $link_id Link ID (0 for new links).
		 * @param bool  $is_edit Whether this is an update.
		 */
		do_action( 'wbam_link_save_before', $data, $link_id, $is_edit );

		$link_manager = Link_Manager::get_instance();

		if ( $link_id ) {
			$result  = $link_manager->update( $link_id, $data );
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

		/**
		 * Fires after saving a link.
		 *
		 * @since 2.3.0
		 * @param int    $link_id Link ID.
		 * @param array  $data    Link data that was saved.
		 * @param bool   $is_edit Whether this was an update.
		 * @param string $message Result message key.
		 */
		do_action( 'wbam_link_save_after', $link_id, $data, $is_edit, $message );

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
		// phpcs:enable WordPress.Security.NonceVerification.Missing
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view router; mutations are nonce-checked in their own handlers.
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

		echo '<div class="wrap wbam-admin">';

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
		<?php
		\WBAM\Admin\UX::page_header(
			array(
				'title'   => __( 'Link Categories', 'wb-ads-rotator-with-split-test' ),
				'desc'    => __( 'Group your links into categories.', 'wb-ads-rotator-with-split-test' ),
				'actions' => '<a href="' . esc_url( admin_url( 'admin.php?page=wbam-link-categories&action=add_category' ) ) . '" class="wbam-admin-btn wbam-admin-btn--primary">' . esc_html__( 'Add New Category', 'wb-ads-rotator-with-split-test' ) . '</a>',
			)
		);
		?>

		<?php $this->show_notices(); ?>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th><?php esc_html_e( 'Description', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th><?php esc_html_e( 'Count', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wb-ads-rotator-with-split-test' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $categories ) ) : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'No categories found.', 'wb-ads-rotator-with-split-test' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $categories as $category ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $category->name ); ?></strong></td>
							<td><?php echo esc_html( $category->slug ); ?></td>
							<td><?php echo esc_html( $category->description ); ?></td>
							<td><?php echo esc_html( $category->count ); ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbam-link-categories&action=edit_category&category_id=' . $category->id ) ); ?>">
									<?php esc_html_e( 'Edit', 'wb-ads-rotator-with-split-test' ); ?>
								</a> |
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wbam-link-categories&action=delete_category&category_id=' . $category->id ), 'wbam_delete_category_' . $category->id ) ); ?>"
									data-wbam-confirm="<?php echo esc_attr__( 'Are you sure you want to delete this category?', 'wb-ads-rotator-with-split-test' ); ?>" data-wbam-confirm-tone="danger" class="delete">
									<?php esc_html_e( 'Delete', 'wb-ads-rotator-with-split-test' ); ?>
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read of edit-target ID for form prefill; save_category() verifies the wbam_save_category nonce.
		$category_id = isset( $_GET['category_id'] ) ? (int) $_GET['category_id'] : 0;
		$category    = null;

		if ( $category_id ) {
			$link_manager = Link_Manager::get_instance();
			$category     = $link_manager->get_category( $category_id );

			if ( ! $category ) {
				wp_die( esc_html__( 'Category not found.', 'wb-ads-rotator-with-split-test' ) );
			}
		}

		$is_edit = (bool) $category;
		$title   = $is_edit ? __( 'Edit Category', 'wb-ads-rotator-with-split-test' ) : __( 'Add New Category', 'wb-ads-rotator-with-split-test' );

		/**
		 * Fires before the link category form.
		 *
		 * @since 2.3.0
		 * @param object|null $category Category object or null for new category.
		 * @param bool        $is_edit  Whether this is an edit form.
		 */
		do_action( 'wbam_link_category_form_before', $category, $is_edit );

		?>
		<h1><?php echo esc_html( $title ); ?></h1>

		<?php $this->show_notices(); ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'wbam_save_category' ); ?>
			<input type="hidden" name="category_id" value="<?php echo esc_attr( $category_id ); ?>">

			<?php
			/**
			 * Fires at the beginning of the category form fields.
			 *
			 * @since 2.3.0
			 * @param object|null $category Category object or null for new category.
			 * @param bool        $is_edit  Whether this is an edit form.
			 */
			do_action( 'wbam_link_category_form_fields_before', $category, $is_edit );
			?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="name"><?php esc_html_e( 'Name', 'wb-ads-rotator-with-split-test' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="text" name="name" id="name" class="regular-text" required
							value="<?php echo esc_attr( $category ? $category->name : '' ); ?>">
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="slug"><?php esc_html_e( 'Slug', 'wb-ads-rotator-with-split-test' ); ?></label>
					</th>
					<td>
						<input type="text" name="slug" id="slug" class="regular-text"
							value="<?php echo esc_attr( $category ? $category->slug : '' ); ?>">
						<p class="description"><?php esc_html_e( 'Leave empty to auto-generate from name.', 'wb-ads-rotator-with-split-test' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="description"><?php esc_html_e( 'Description', 'wb-ads-rotator-with-split-test' ); ?></label>
					</th>
					<td>
						<textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea( $category ? $category->description : '' ); ?></textarea>
					</td>
				</tr>

				<?php
				/**
				 * Fires after all category form fields.
				 *
				 * Use this to add custom fields to the category form.
				 *
				 * @since 2.3.0
				 * @param object|null $category Category object or null for new category.
				 * @param bool        $is_edit  Whether this is an edit form.
				 */
				do_action( 'wbam_link_category_form_fields_after', $category, $is_edit );
				?>
			</table>

			<p class="submit">
				<input type="submit" name="wbam_save_category" class="button button-primary"
					value="<?php echo esc_attr( $is_edit ? __( 'Update Category', 'wb-ads-rotator-with-split-test' ) : __( 'Create Category', 'wb-ads-rotator-with-split-test' ) ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbam-link-categories' ) ); ?>" class="button">
					<?php esc_html_e( 'Cancel', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
			</p>
		</form>

		<?php
		/**
		 * Fires after the link category form.
		 *
		 * @since 2.3.0
		 * @param object|null $category Category object or null for new category.
		 * @param bool        $is_edit  Whether this is an edit form.
		 */
		do_action( 'wbam_link_category_form_after', $category, $is_edit );
	}

	/**
	 * Save category from form.
	 *
	 * @return void
	 *
	 * Nonce verification: the caller `handle_admin_actions()` validates the
	 * `wbam_save_category` nonce via `check_admin_referer()` BEFORE invoking
	 * this method, so the `$_POST` reads below are guarded.
	 */
	private function save_category() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Caller handle_admin_actions() verifies the wbam_save_category nonce.
		$category_id = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
		$is_edit     = (bool) $category_id;

		$data = array(
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'slug'        => isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
		);

		/**
		 * Filter category data before saving.
		 *
		 * @since 2.3.0
		 * @param array $data        Category data to save.
		 * @param int   $category_id Category ID (0 for new categories).
		 * @param array $raw_post    Raw POST data (unslashed).
		 */
		$data = apply_filters( 'wbam_link_category_save_data', $data, $category_id, wp_unslash( $_POST ) );

		/**
		 * Fires before saving a link category.
		 *
		 * @since 2.3.0
		 * @param array $data        Category data to save.
		 * @param int   $category_id Category ID (0 for new categories).
		 * @param bool  $is_edit     Whether this is an update.
		 */
		do_action( 'wbam_link_category_save_before', $data, $category_id, $is_edit );

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

		/**
		 * Fires after saving a link category.
		 *
		 * @since 2.3.0
		 * @param int    $category_id Category ID.
		 * @param array  $data        Category data that was saved.
		 * @param bool   $is_edit     Whether this was an update.
		 * @param string $message     Result message key.
		 */
		do_action( 'wbam_link_category_save_after', $category_id, $data, $is_edit, $message );

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
		// phpcs:enable WordPress.Security.NonceVerification.Missing
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
		// Read-only post-redirect message lookup; no state mutation.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read of message key from redirect query to display a notice.
		if ( ! isset( $_GET['message'] ) ) {
			return;
		}

		$messages = array(
			'link_created'     => array( 'success', __( 'Link created successfully.', 'wb-ads-rotator-with-split-test' ) ),
			'link_updated'     => array( 'success', __( 'Link updated successfully.', 'wb-ads-rotator-with-split-test' ) ),
			'link_deleted'     => array( 'success', __( 'Link deleted successfully.', 'wb-ads-rotator-with-split-test' ) ),
			'link_error'       => array( 'error', __( 'An error occurred. Please try again.', 'wb-ads-rotator-with-split-test' ) ),
			'category_created' => array( 'success', __( 'Category created successfully.', 'wb-ads-rotator-with-split-test' ) ),
			'category_updated' => array( 'success', __( 'Category updated successfully.', 'wb-ads-rotator-with-split-test' ) ),
			'category_deleted' => array( 'success', __( 'Category deleted successfully.', 'wb-ads-rotator-with-split-test' ) ),
			'category_error'   => array( 'error', __( 'An error occurred. Please try again.', 'wb-ads-rotator-with-split-test' ) ),
		);

		$message_key = sanitize_text_field( wp_unslash( $_GET['message'] ) );
		// phpcs:enable

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
