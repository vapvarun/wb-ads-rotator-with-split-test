<?php
/**
 * Admin Class
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Admin;

use WBAM\Core\Singleton;
use WBAM\Modules\Placements\Placement_Engine;

/**
 * Admin class.
 */
class Admin {

	use Singleton;

	/**
	 * Initialize.
	 */
	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'manage_wbam-ad_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_wbam-ad_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * Enqueue assets.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || 'wbam-ad' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		// Use file modification time for cache busting during development.
		$css_version = WBAM_VERSION . '.' . filemtime( WBAM_PATH . 'assets/css/admin.css' );
		$js_version  = WBAM_VERSION . '.' . filemtime( WBAM_PATH . 'assets/js/admin.js' );

		wp_enqueue_style(
			'wbam-admin',
			WBAM_URL . 'assets/css/admin.css',
			array(),
			$css_version
		);

		wp_enqueue_script(
			'wbam-admin',
			WBAM_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			$js_version,
			true
		);

		wp_localize_script(
			'wbam-admin',
			'wbamAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wbam-admin' ),
				'i18n'    => array(
					'selectImage' => __( 'Select Image', 'wb-ad-manager' ),
					'useImage'    => __( 'Use This Image', 'wb-ad-manager' ),
				),
			)
		);

		// Code editor.
		if ( 'post' === $hook || 'post-new' === $hook ) {
			$settings = wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
			if ( false !== $settings ) {
				wp_localize_script( 'wbam-admin', 'wbamCodeEditor', $settings );
			}
		}
	}

	/**
	 * Add metaboxes.
	 */
	public function add_metaboxes() {
		add_meta_box(
			'wbam-ad-settings',
			__( 'Ad Settings', 'wb-ad-manager' ),
			array( $this, 'render_settings_metabox' ),
			'wbam-ad',
			'normal',
			'high'
		);

		add_meta_box(
			'wbam-ad-placements',
			__( 'Placements', 'wb-ad-manager' ),
			array( $this, 'render_placements_metabox' ),
			'wbam-ad',
			'normal',
			'high'
		);

		add_meta_box(
			'wbam-ad-status',
			__( 'Ad Status', 'wb-ad-manager' ),
			array( $this, 'render_status_metabox' ),
			'wbam-ad',
			'side',
			'high'
		);
	}

	/**
	 * Render settings metabox.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_settings_metabox( $post ) {
		wp_nonce_field( 'wbam_save_ad', 'wbam_nonce' );

		$data    = get_post_meta( $post->ID, '_wbam_ad_data', true );
		$data    = is_array( $data ) ? $data : array();
		$ad_type = isset( $data['type'] ) ? $data['type'] : 'image';

		$engine   = Placement_Engine::get_instance();
		$ad_types = $engine->get_ad_types();
		?>
		<div class="wbam-metabox wbam-adtype-tabs">
			<?php foreach ( $ad_types as $type ) : ?>
				<input type="radio"
				       name="wbam_data[type]"
				       value="<?php echo esc_attr( $type->get_id() ); ?>"
				       id="wbam-adtype-<?php echo esc_attr( $type->get_id() ); ?>"
				       class="wbam-adtype-radio"
				       <?php checked( $ad_type, $type->get_id() ); ?> />
			<?php endforeach; ?>

			<div class="wbam-adtype-nav">
				<?php foreach ( $ad_types as $type ) : ?>
					<a href="#" class="wbam-adtype-tab<?php echo ( $ad_type === $type->get_id() ) ? ' wbam-adtype-tab-active' : ''; ?>" data-type="<?php echo esc_attr( $type->get_id() ); ?>">
						<span class="dashicons <?php echo esc_attr( $type->get_icon() ); ?>"></span>
						<?php echo esc_html( $type->get_name() ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<?php foreach ( $ad_types as $type ) : ?>
				<div class="wbam-adtype-content" data-type="<?php echo esc_attr( $type->get_id() ); ?>"<?php echo ( $ad_type === $type->get_id() ) ? ' style="display:block;"' : ''; ?>>
					<p class="wbam-adtype-desc"><?php echo esc_html( $type->get_description() ); ?></p>
					<?php $type->render_metabox( $post->ID, $data ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<script>
		jQuery(function($) {
			$('.wbam-adtype-tab').on('click', function(e) {
				e.preventDefault();
				var typeId = $(this).data('type');
				$('#wbam-adtype-' + typeId).prop('checked', true);
				$('.wbam-adtype-tab').removeClass('wbam-adtype-tab-active');
				$(this).addClass('wbam-adtype-tab-active');
				$('.wbam-adtype-content').hide();
				$('.wbam-adtype-content[data-type="' + typeId + '"]').show();
			});
		});
		</script>
		<?php
	}

	/**
	 * Render placements metabox.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_placements_metabox( $post ) {
		$placements = get_post_meta( $post->ID, '_wbam_placements', true );
		$placements = is_array( $placements ) ? $placements : array();

		$data             = get_post_meta( $post->ID, '_wbam_ad_data', true );
		$after_paragraph  = isset( $data['after_paragraph'] ) ? absint( $data['after_paragraph'] ) : 2;
		$paragraph_repeat = isset( $data['paragraph_repeat'] ) ? $data['paragraph_repeat'] : false;
		$after_activity   = isset( $data['after_activity'] ) ? absint( $data['after_activity'] ) : 3;
		$activity_repeat  = isset( $data['activity_repeat'] ) ? $data['activity_repeat'] : false;
		$after_posts      = isset( $data['after_posts'] ) ? absint( $data['after_posts'] ) : 3;
		$posts_repeat     = isset( $data['posts_repeat'] ) ? $data['posts_repeat'] : false;

		$engine     = Placement_Engine::get_instance();
		$all_places = $engine->get_placements_grouped();
		?>
		<div class="wbam-metabox">
			<?php foreach ( $all_places as $group => $group_placements ) : ?>
				<div class="wbam-placement-group">
					<h4><?php echo esc_html( ucfirst( $group ) ); ?> <?php esc_html_e( 'Placements', 'wb-ad-manager' ); ?></h4>
					<div class="wbam-placement-options">
						<?php foreach ( $group_placements as $placement ) : ?>
							<?php if ( ! $placement->is_available() ) continue; ?>
							<label class="wbam-placement-option">
								<input type="checkbox" name="wbam_placements[]" value="<?php echo esc_attr( $placement->get_id() ); ?>" <?php checked( in_array( $placement->get_id(), $placements, true ) ); ?> />
								<span class="wbam-option-title"><?php echo esc_html( $placement->get_name() ); ?></span>
								<span class="wbam-option-desc"><?php echo esc_html( $placement->get_description() ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>

			<div class="wbam-extra-settings wbam-paragraph-settings" <?php echo ! in_array( 'after_paragraph', $placements, true ) ? 'style="display:none;"' : ''; ?>>
				<h4><?php esc_html_e( 'Paragraph Settings', 'wb-ad-manager' ); ?></h4>
				<div class="wbam-field">
					<label for="wbam_after_paragraph"><?php esc_html_e( 'Insert after paragraph:', 'wb-ad-manager' ); ?></label>
					<input type="number" id="wbam_after_paragraph" name="wbam_data[after_paragraph]" value="<?php echo esc_attr( $after_paragraph ); ?>" min="1" max="50" />
				</div>
				<div class="wbam-field">
					<label>
						<input type="checkbox" name="wbam_data[paragraph_repeat]" value="1" <?php checked( $paragraph_repeat ); ?> />
						<?php esc_html_e( 'Repeat after every X paragraphs', 'wb-ad-manager' ); ?>
					</label>
				</div>
			</div>

			<div class="wbam-extra-settings wbam-activity-settings" <?php echo ! in_array( 'bp_activity', $placements, true ) ? 'style="display:none;"' : ''; ?>>
				<h4><?php esc_html_e( 'Activity Stream Settings', 'wb-ad-manager' ); ?></h4>
				<div class="wbam-field">
					<label for="wbam_after_activity"><?php esc_html_e( 'Insert after activity:', 'wb-ad-manager' ); ?></label>
					<input type="number" id="wbam_after_activity" name="wbam_data[after_activity]" value="<?php echo esc_attr( $after_activity ); ?>" min="1" max="50" />
				</div>
				<div class="wbam-field">
					<label>
						<input type="checkbox" name="wbam_data[activity_repeat]" value="1" <?php checked( $activity_repeat ); ?> />
						<?php esc_html_e( 'Repeat after every X activities', 'wb-ad-manager' ); ?>
					</label>
				</div>
			</div>

			<div class="wbam-extra-settings wbam-archive-settings" <?php echo ! in_array( 'archive', $placements, true ) ? 'style="display:none;"' : ''; ?>>
				<h4><?php esc_html_e( 'Archive Settings', 'wb-ad-manager' ); ?></h4>
				<div class="wbam-field">
					<label for="wbam_after_posts"><?php esc_html_e( 'Insert after post:', 'wb-ad-manager' ); ?></label>
					<input type="number" id="wbam_after_posts" name="wbam_data[after_posts]" value="<?php echo esc_attr( $after_posts ); ?>" min="1" max="50" />
				</div>
				<div class="wbam-field">
					<label>
						<input type="checkbox" name="wbam_data[posts_repeat]" value="1" <?php checked( $posts_repeat ); ?> />
						<?php esc_html_e( 'Repeat after every X posts', 'wb-ad-manager' ); ?>
					</label>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render status metabox.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_status_metabox( $post ) {
		$enabled       = get_post_meta( $post->ID, '_wbam_enabled', true );
		$enabled       = '' === $enabled ? '1' : $enabled;
		$priority      = get_post_meta( $post->ID, '_wbam_priority', true );
		$priority      = '' === $priority ? 5 : absint( $priority );
		$session_limit = get_post_meta( $post->ID, '_wbam_session_limit', true );
		$session_limit = '' === $session_limit ? '' : absint( $session_limit );
		?>
		<div class="wbam-metabox">
			<div class="wbam-status-options">
				<label class="wbam-status-option">
					<input type="radio" name="wbam_enabled" value="1" <?php checked( $enabled, '1' ); ?> />
					<span class="wbam-status-enabled"><?php esc_html_e( 'Enabled', 'wb-ad-manager' ); ?></span>
				</label>
				<label class="wbam-status-option">
					<input type="radio" name="wbam_enabled" value="0" <?php checked( $enabled, '0' ); ?> />
					<span class="wbam-status-disabled"><?php esc_html_e( 'Disabled', 'wb-ad-manager' ); ?></span>
				</label>
			</div>

			<div class="wbam-priority-field">
				<label for="wbam_priority"><?php esc_html_e( 'Priority', 'wb-ad-manager' ); ?></label>
				<input type="range" id="wbam_priority" name="wbam_priority" min="1" max="10" value="<?php echo esc_attr( $priority ); ?>" />
				<span class="wbam-priority-value"><?php echo esc_html( $priority ); ?></span>
				<p class="description"><?php esc_html_e( 'Higher priority ads display first.', 'wb-ad-manager' ); ?></p>
			</div>

			<div class="wbam-session-limit-field">
				<label for="wbam_session_limit"><?php esc_html_e( 'Session Limit', 'wb-ad-manager' ); ?></label>
				<input type="number" id="wbam_session_limit" name="wbam_session_limit" min="0" value="<?php echo esc_attr( $session_limit ); ?>" placeholder="<?php esc_attr_e( 'Unlimited', 'wb-ad-manager' ); ?>" />
				<p class="description"><?php esc_html_e( 'Max views per visitor session. Leave empty for unlimited.', 'wb-ad-manager' ); ?></p>
			</div>

			<?php
			/**
			 * Action for adding additional metabox options.
			 *
			 * @since 1.0.0
			 * @param \WP_Post $post Post object.
			 */
			do_action( 'wbam_ad_metabox_options', $post );
			?>
		</div>
		<script>
		jQuery(function($) {
			$('#wbam_priority').on('input', function() {
				$(this).next('.wbam-priority-value').text(this.value);
			});
		});
		</script>
		<?php
	}

	/**
	 * Save meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['wbam_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbam_nonce'] ) ), 'wbam_save_ad' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( 'wbam-ad' !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save enabled status.
		$enabled = isset( $_POST['wbam_enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['wbam_enabled'] ) ) : '1';
		update_post_meta( $post_id, '_wbam_enabled', $enabled );

		// Save priority.
		$priority = isset( $_POST['wbam_priority'] ) ? absint( wp_unslash( $_POST['wbam_priority'] ) ) : 5;
		$priority = max( 1, min( 10, $priority ) );
		update_post_meta( $post_id, '_wbam_priority', $priority );

		// Save session limit.
		$session_limit = isset( $_POST['wbam_session_limit'] ) && '' !== $_POST['wbam_session_limit']
			? absint( wp_unslash( $_POST['wbam_session_limit'] ) )
			: '';
		update_post_meta( $post_id, '_wbam_session_limit', $session_limit );

		// Save placements.
		$placements = isset( $_POST['wbam_placements'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wbam_placements'] ) ) : array();
		update_post_meta( $post_id, '_wbam_placements', $placements );

		// Save ad data.
		if ( isset( $_POST['wbam_data'] ) ) {
			$raw_data = wp_unslash( $_POST['wbam_data'] ); // phpcs:ignore
			$ad_type  = isset( $raw_data['type'] ) ? sanitize_text_field( $raw_data['type'] ) : 'image';

			$engine  = Placement_Engine::get_instance();
			$handler = $engine->get_ad_type( $ad_type );

			$data = array( 'type' => $ad_type );

			if ( $handler ) {
				$type_data = $handler->save( $post_id, $raw_data );
				$data      = array_merge( $data, $type_data );
			}

			// Paragraph settings.
			$data['after_paragraph']  = isset( $raw_data['after_paragraph'] ) ? absint( $raw_data['after_paragraph'] ) : 2;
			$data['paragraph_repeat'] = isset( $raw_data['paragraph_repeat'] ) ? true : false;

			// Activity settings.
			$data['after_activity']  = isset( $raw_data['after_activity'] ) ? absint( $raw_data['after_activity'] ) : 3;
			$data['activity_repeat'] = isset( $raw_data['activity_repeat'] ) ? true : false;

			// Archive settings.
			$data['after_posts']  = isset( $raw_data['after_posts'] ) ? absint( $raw_data['after_posts'] ) : 3;
			$data['posts_repeat'] = isset( $raw_data['posts_repeat'] ) ? true : false;

			update_post_meta( $post_id, '_wbam_ad_data', $data );
		}

		/**
		 * Action fired after ad meta is saved.
		 *
		 * @since 1.0.0
		 * @param int $post_id Post ID.
		 */
		do_action( 'wbam_save_ad_meta', $post_id );
	}

	/**
	 * Add columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $value ) {
			$new[ $key ] = $value;
			if ( 'title' === $key ) {
				$new['ad_type']     = __( 'Type', 'wb-ad-manager' );
				$new['placements']  = __( 'Placements', 'wb-ad-manager' );
				$new['impressions'] = __( 'Impressions', 'wb-ad-manager' );
				$new['clicks']      = __( 'Clicks', 'wb-ad-manager' );
				$new['status']      = __( 'Status', 'wb-ad-manager' );
			}
		}
		return $new;
	}

	/**
	 * Render column.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'ad_type':
				$data    = get_post_meta( $post_id, '_wbam_ad_data', true );
				$type_id = isset( $data['type'] ) ? $data['type'] : '';
				$engine  = Placement_Engine::get_instance();
				$type    = $engine->get_ad_type( $type_id );
				if ( $type ) {
					echo '<span class="dashicons ' . esc_attr( $type->get_icon() ) . '"></span> ' . esc_html( $type->get_name() );
				}
				break;

			case 'placements':
				$placements = get_post_meta( $post_id, '_wbam_placements', true );
				echo ! empty( $placements ) ? esc_html( implode( ', ', $placements ) ) : '—';
				break;

			case 'impressions':
				global $wpdb;
				$table = $wpdb->prefix . 'wbam_analytics';
				$count = $wpdb->get_var( // phpcs:ignore
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table} WHERE ad_id = %d AND event_type = 'impression'",
						$post_id
					)
				);
				echo '<strong>' . esc_html( number_format_i18n( absint( $count ) ) ) . '</strong>';
				break;

			case 'clicks':
				global $wpdb;
				$table = $wpdb->prefix . 'wbam_analytics';
				$count = $wpdb->get_var( // phpcs:ignore
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table} WHERE ad_id = %d AND event_type = 'click'",
						$post_id
					)
				);
				echo '<strong>' . esc_html( number_format_i18n( absint( $count ) ) ) . '</strong>';
				break;

			case 'status':
				$enabled = get_post_meta( $post_id, '_wbam_enabled', true );
				$class   = '1' === $enabled ? 'wbam-enabled' : 'wbam-disabled';
				$text    = '1' === $enabled ? __( 'Enabled', 'wb-ad-manager' ) : __( 'Disabled', 'wb-ad-manager' );
				echo '<span class="wbam-status-badge ' . esc_attr( $class ) . '">' . esc_html( $text ) . '</span>';
				break;
		}
	}
}
