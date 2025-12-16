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
		add_action( 'admin_init', array( $this, 'handle_disable_ad' ) );
	}

	/**
	 * Handle disable ad from comparison view.
	 */
	public function handle_disable_ad() {
		if ( ! isset( $_GET['wbam_disable'] ) || '1' !== $_GET['wbam_disable'] ) {
			return;
		}

		if ( ! isset( $_GET['post'] ) ) {
			return;
		}

		$post_id = absint( $_GET['post'] );

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wbam_disable_ad_' . $post_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wb-ads-rotator-with-split-test' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return;
		}

		// Disable the ad.
		update_post_meta( $post_id, '_wbam_enabled', '0' );

		// Add admin notice.
		add_action(
			'admin_notices',
			function () use ( $post ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					sprintf(
						/* translators: %s: Ad title */
						esc_html__( 'Ad "%s" has been disabled.', 'wb-ads-rotator-with-split-test' ),
						esc_html( $post->post_title )
					)
				);
			}
		);
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

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'wbam-admin',
			WBAM_URL . 'assets/css/admin' . $suffix . '.css',
			array(),
			WBAM_VERSION
		);

		wp_enqueue_script(
			'wbam-admin',
			WBAM_URL . 'assets/js/admin' . $suffix . '.js',
			array( 'jquery' ),
			WBAM_VERSION,
			true
		);

		wp_localize_script(
			'wbam-admin',
			'wbamAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wbam-admin' ),
				'i18n'    => array(
					'selectImage' => __( 'Select Image', 'wb-ads-rotator-with-split-test' ),
					'useImage'    => __( 'Use This Image', 'wb-ads-rotator-with-split-test' ),
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
			__( 'Ad Settings', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_settings_metabox' ),
			'wbam-ad',
			'normal',
			'high'
		);

		add_meta_box(
			'wbam-ad-placements',
			__( 'Placements', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_placements_metabox' ),
			'wbam-ad',
			'normal',
			'high'
		);

		add_meta_box(
			'wbam-ad-status',
			__( 'Ad Status', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_status_metabox' ),
			'wbam-ad',
			'side',
			'high'
		);

		// Only show comparison metabox for existing ads with placements.
		global $post;
		if ( $post && $post->ID ) {
			$placements = get_post_meta( $post->ID, '_wbam_placements', true );
			if ( ! empty( $placements ) ) {
				add_meta_box(
					'wbam-ad-comparison',
					__( 'Ad Performance Comparison', 'wb-ads-rotator-with-split-test' ),
					array( $this, 'render_comparison_metabox' ),
					'wbam-ad',
					'normal',
					'default'
				);
			}
		}
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
					<h4><?php echo esc_html( ucfirst( $group ) ); ?> <?php esc_html_e( 'Placements', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<div class="wbam-placement-options">
						<?php foreach ( $group_placements as $placement ) : ?>
							<?php if ( ! $placement->is_available() || ! $placement->show_in_selector() ) continue; ?>
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
				<h4><?php esc_html_e( 'Paragraph Settings', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<div class="wbam-field">
					<label for="wbam_after_paragraph"><?php esc_html_e( 'Insert after paragraph:', 'wb-ads-rotator-with-split-test' ); ?></label>
					<input type="number" id="wbam_after_paragraph" name="wbam_data[after_paragraph]" value="<?php echo esc_attr( $after_paragraph ); ?>" min="1" max="50" />
				</div>
				<div class="wbam-field">
					<label>
						<input type="checkbox" name="wbam_data[paragraph_repeat]" value="1" <?php checked( $paragraph_repeat ); ?> />
						<?php esc_html_e( 'Repeat after every X paragraphs', 'wb-ads-rotator-with-split-test' ); ?>
					</label>
				</div>
			</div>

			<div class="wbam-extra-settings wbam-activity-settings" <?php echo ! in_array( 'bp_activity', $placements, true ) ? 'style="display:none;"' : ''; ?>>
				<h4><?php esc_html_e( 'Activity Stream Settings', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<div class="wbam-field">
					<label for="wbam_after_activity"><?php esc_html_e( 'Insert after activity:', 'wb-ads-rotator-with-split-test' ); ?></label>
					<input type="number" id="wbam_after_activity" name="wbam_data[after_activity]" value="<?php echo esc_attr( $after_activity ); ?>" min="1" max="50" />
				</div>
				<div class="wbam-field">
					<label>
						<input type="checkbox" name="wbam_data[activity_repeat]" value="1" <?php checked( $activity_repeat ); ?> />
						<?php esc_html_e( 'Repeat after every X activities', 'wb-ads-rotator-with-split-test' ); ?>
					</label>
				</div>
			</div>

			<div class="wbam-extra-settings wbam-archive-settings" <?php echo ! in_array( 'archive', $placements, true ) ? 'style="display:none;"' : ''; ?>>
				<h4><?php esc_html_e( 'Archive Settings', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<div class="wbam-field">
					<label for="wbam_after_posts"><?php esc_html_e( 'Insert after post:', 'wb-ads-rotator-with-split-test' ); ?></label>
					<input type="number" id="wbam_after_posts" name="wbam_data[after_posts]" value="<?php echo esc_attr( $after_posts ); ?>" min="1" max="50" />
				</div>
				<div class="wbam-field">
					<label>
						<input type="checkbox" name="wbam_data[posts_repeat]" value="1" <?php checked( $posts_repeat ); ?> />
						<?php esc_html_e( 'Repeat after every X posts', 'wb-ads-rotator-with-split-test' ); ?>
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
					<span class="wbam-status-enabled"><?php esc_html_e( 'Enabled', 'wb-ads-rotator-with-split-test' ); ?></span>
				</label>
				<label class="wbam-status-option">
					<input type="radio" name="wbam_enabled" value="0" <?php checked( $enabled, '0' ); ?> />
					<span class="wbam-status-disabled"><?php esc_html_e( 'Disabled', 'wb-ads-rotator-with-split-test' ); ?></span>
				</label>
			</div>

			<div class="wbam-priority-field">
				<label for="wbam_priority"><?php esc_html_e( 'Priority', 'wb-ads-rotator-with-split-test' ); ?></label>
				<input type="range" id="wbam_priority" name="wbam_priority" min="1" max="10" value="<?php echo esc_attr( $priority ); ?>" />
				<span class="wbam-priority-value"><?php echo esc_html( $priority ); ?></span>
				<p class="description"><?php esc_html_e( 'Higher priority ads display first.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-session-limit-field">
				<label for="wbam_session_limit"><?php esc_html_e( 'Session Limit', 'wb-ads-rotator-with-split-test' ); ?></label>
				<input type="number" id="wbam_session_limit" name="wbam_session_limit" min="0" value="<?php echo esc_attr( $session_limit ); ?>" placeholder="<?php esc_attr_e( 'Unlimited', 'wb-ads-rotator-with-split-test' ); ?>" />
				<p class="description"><?php esc_html_e( 'Max views per visitor session. Leave empty for unlimited.', 'wb-ads-rotator-with-split-test' ); ?></p>
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
	 * Render comparison metabox.
	 *
	 * Shows performance comparison of all ads sharing the same placements.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_comparison_metabox( $post ) {
		global $wpdb;

		$current_placements = get_post_meta( $post->ID, '_wbam_placements', true );
		if ( empty( $current_placements ) || ! is_array( $current_placements ) ) {
			echo '<p>' . esc_html__( 'No placements assigned to this ad.', 'wb-ads-rotator-with-split-test' ) . '</p>';
			return;
		}

		// Find all ads that share at least one placement with current ad.
		$all_ads = get_posts(
			array(
				'post_type'      => 'wbam-ad',
				'posts_per_page' => 50,
				'post_status'    => 'publish',
				'post__not_in'   => array( $post->ID ),
				'meta_query'     => array(
					array(
						'key'     => '_wbam_enabled',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);

		// Filter to only ads sharing placements.
		$competing_ads = array();
		foreach ( $all_ads as $ad ) {
			$ad_placements = get_post_meta( $ad->ID, '_wbam_placements', true );
			if ( ! empty( $ad_placements ) && is_array( $ad_placements ) ) {
				$shared = array_intersect( $current_placements, $ad_placements );
				if ( ! empty( $shared ) ) {
					$competing_ads[] = $ad;
				}
			}
		}

		if ( empty( $competing_ads ) ) {
			echo '<p>' . esc_html__( 'No other enabled ads are using the same placements. Enable more ads to compare performance.', 'wb-ads-rotator-with-split-test' ) . '</p>';
			return;
		}

		// Add current ad to comparison.
		array_unshift( $competing_ads, $post );

		// Get stats for all ads.
		$table_name   = $wpdb->prefix . 'wbam_analytics';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		$stats = array();
		foreach ( $competing_ads as $ad ) {
			$impressions = 0;
			$clicks      = 0;

			if ( $table_exists ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$impressions = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wbam_analytics WHERE ad_id = %d AND event_type = %s',
						$ad->ID,
						'impression'
					)
				);
				$clicks = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wbam_analytics WHERE ad_id = %d AND event_type = %s',
						$ad->ID,
						'click'
					)
				);
				// phpcs:enable
			}

			$ctr = $impressions > 0 ? ( $clicks / $impressions ) * 100 : 0;

			$stats[ $ad->ID ] = array(
				'id'          => $ad->ID,
				'title'       => $ad->post_title,
				'impressions' => $impressions,
				'clicks'      => $clicks,
				'ctr'         => $ctr,
				'is_current'  => $ad->ID === $post->ID,
			);
		}

		// Sort by CTR descending.
		usort(
			$stats,
			function ( $a, $b ) {
				return $b['ctr'] <=> $a['ctr'];
			}
		);

		// Find winner (highest CTR with at least 100 impressions).
		$winner_id = 0;
		foreach ( $stats as $stat ) {
			if ( $stat['impressions'] >= 100 ) {
				$winner_id = $stat['id'];
				break;
			}
		}

		// Find max CTR for bar scaling.
		$max_ctr = max( array_column( $stats, 'ctr' ) );
		$max_ctr = $max_ctr > 0 ? $max_ctr : 1;
		?>
		<style>
			.wbam-comparison-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
			.wbam-comparison-table th { text-align: left; padding: 10px; border-bottom: 2px solid #ddd; }
			.wbam-comparison-table td { padding: 10px; border-bottom: 1px solid #eee; }
			.wbam-comparison-table tr.wbam-current-ad { background: #f0f7ff; }
			.wbam-ctr-bar { background: #ddd; height: 20px; border-radius: 3px; overflow: hidden; min-width: 100px; }
			.wbam-ctr-fill { background: #2271b1; height: 100%; transition: width 0.3s; }
			.wbam-ctr-fill.winner { background: #00a32a; }
			.wbam-winner-badge { background: #00a32a; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-left: 5px; }
			.wbam-current-badge { background: #2271b1; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-left: 5px; }
			.wbam-disable-btn { color: #b32d2e !important; }
			.wbam-comparison-note { color: #666; font-style: italic; margin-top: 10px; }
		</style>

		<table class="wbam-comparison-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Ad', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th><?php esc_html_e( 'Impressions', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th><?php esc_html_e( 'Clicks', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th><?php esc_html_e( 'CTR', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th><?php esc_html_e( 'Performance', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $stats as $stat ) : ?>
					<tr class="<?php echo $stat['is_current'] ? 'wbam-current-ad' : ''; ?>">
						<td>
							<?php if ( $stat['is_current'] ) : ?>
								<strong><?php echo esc_html( $stat['title'] ); ?></strong>
								<span class="wbam-current-badge"><?php esc_html_e( 'This Ad', 'wb-ads-rotator-with-split-test' ); ?></span>
							<?php else : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $stat['id'] ) ); ?>">
									<?php echo esc_html( $stat['title'] ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $winner_id === $stat['id'] ) : ?>
								<span class="wbam-winner-badge"><?php esc_html_e( 'Winner', 'wb-ads-rotator-with-split-test' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( $stat['impressions'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $stat['clicks'] ) ); ?></td>
						<td><?php echo esc_html( number_format( $stat['ctr'], 2 ) ); ?>%</td>
						<td>
							<div class="wbam-ctr-bar">
								<div class="wbam-ctr-fill <?php echo $winner_id === $stat['id'] ? 'winner' : ''; ?>"
								     style="width: <?php echo esc_attr( ( $stat['ctr'] / $max_ctr ) * 100 ); ?>%"></div>
							</div>
						</td>
						<td>
							<?php if ( ! $stat['is_current'] && $winner_id !== $stat['id'] ) : ?>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'post.php?post=' . $stat['id'] . '&action=edit&wbam_disable=1' ), 'wbam_disable_ad_' . $stat['id'] ) ); ?>"
								   class="wbam-disable-btn"
								   onclick="return confirm('<?php esc_attr_e( 'Disable this underperforming ad?', 'wb-ads-rotator-with-split-test' ); ?>');">
									<?php esc_html_e( 'Disable', 'wb-ads-rotator-with-split-test' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="wbam-comparison-note">
			<?php
			if ( 0 === $winner_id ) {
				esc_html_e( 'No winner yet — ads need at least 100 impressions each for a meaningful comparison.', 'wb-ads-rotator-with-split-test' );
			} else {
				esc_html_e( 'Winner is the ad with highest CTR among those with 100+ impressions.', 'wb-ads-rotator-with-split-test' );
			}
			?>
		</p>
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

			/**
			 * Filter ad data before saving.
			 *
			 * @since 2.3.0
			 * @param array $data     Ad data to save.
			 * @param int   $post_id  Ad post ID.
			 * @param array $raw_data Raw POST data.
			 */
			$data = apply_filters( 'wbam_ad_data_before_save', $data, $post_id, $raw_data );

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
				$new['ad_type']     = __( 'Type', 'wb-ads-rotator-with-split-test' );
				$new['placements']  = __( 'Placements', 'wb-ads-rotator-with-split-test' );
				$new['impressions'] = __( 'Impressions', 'wb-ads-rotator-with-split-test' );
				$new['clicks']      = __( 'Clicks', 'wb-ads-rotator-with-split-test' );
				$new['status']      = __( 'Status', 'wb-ads-rotator-with-split-test' );
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
				$cache_key = 'wbam_impressions_' . $post_id;
				$count     = wp_cache_get( $cache_key, 'wbam' );
				if ( false === $count ) {
					global $wpdb;
					$table_name = $wpdb->prefix . 'wbam_analytics';
					// Check if table exists before querying.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
					if ( $table_exists ) {
						// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$count = $wpdb->get_var(
							$wpdb->prepare(
								'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wbam_analytics WHERE ad_id = %d AND event_type = %s',
								$post_id,
								'impression'
							)
						);
						// phpcs:enable
					} else {
						$count = 0;
					}
					wp_cache_set( $cache_key, $count, 'wbam', HOUR_IN_SECONDS );
				}
				echo '<strong>' . esc_html( number_format_i18n( absint( $count ) ) ) . '</strong>';
				break;

			case 'clicks':
				$cache_key = 'wbam_clicks_' . $post_id;
				$count     = wp_cache_get( $cache_key, 'wbam' );
				if ( false === $count ) {
					global $wpdb;
					$table_name = $wpdb->prefix . 'wbam_analytics';
					// Check if table exists before querying.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
					if ( $table_exists ) {
						// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$count = $wpdb->get_var(
							$wpdb->prepare(
								'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wbam_analytics WHERE ad_id = %d AND event_type = %s',
								$post_id,
								'click'
							)
						);
						// phpcs:enable
					} else {
						$count = 0;
					}
					wp_cache_set( $cache_key, $count, 'wbam', HOUR_IN_SECONDS );
				}
				echo '<strong>' . esc_html( number_format_i18n( absint( $count ) ) ) . '</strong>';
				break;

			case 'status':
				$enabled = get_post_meta( $post_id, '_wbam_enabled', true );
				$class   = '1' === $enabled ? 'wbam-enabled' : 'wbam-disabled';
				$text    = '1' === $enabled ? __( 'Enabled', 'wb-ads-rotator-with-split-test' ) : __( 'Disabled', 'wb-ads-rotator-with-split-test' );
				echo '<span class="wbam-status-badge ' . esc_attr( $class ) . '">' . esc_html( $text ) . '</span>';
				break;
		}
	}
}
