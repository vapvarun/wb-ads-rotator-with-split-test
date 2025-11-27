<?php
/**
 * Display Options Admin
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Admin;

use WBAM\Core\Singleton;
use WBAM\Modules\GeoTargeting\Geo_Engine;

/**
 * Display Options class.
 */
class Display_Options {

	use Singleton;

	/**
	 * Initialize.
	 */
	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ), 20 );
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
	}

	/**
	 * Add metaboxes.
	 */
	public function add_metaboxes() {
		add_meta_box(
			'wbam-display-rules',
			__( 'Display Rules', 'wb-ad-manager' ),
			array( $this, 'render_display_rules' ),
			'wbam-ad',
			'normal',
			'default'
		);

		add_meta_box(
			'wbam-schedule',
			__( 'Schedule', 'wb-ad-manager' ),
			array( $this, 'render_schedule' ),
			'wbam-ad',
			'side',
			'default'
		);

		add_meta_box(
			'wbam-visitor-conditions',
			__( 'Visitor Conditions', 'wb-ad-manager' ),
			array( $this, 'render_visitor_conditions' ),
			'wbam-ad',
			'normal',
			'default'
		);

		add_meta_box(
			'wbam-geo-targeting',
			__( 'Geo Targeting', 'wb-ad-manager' ),
			array( $this, 'render_geo_targeting' ),
			'wbam-ad',
			'normal',
			'default'
		);
	}

	/**
	 * Render display rules metabox.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_display_rules( $post ) {
		$rules      = get_post_meta( $post->ID, '_wbam_display_rules', true );
		$rules      = is_array( $rules ) ? $rules : array();
		$display_on = isset( $rules['display_on'] ) ? $rules['display_on'] : 'all';

		$post_types       = isset( $rules['post_types'] ) ? $rules['post_types'] : array();
		$categories       = isset( $rules['categories'] ) ? $rules['categories'] : array();
		$tags             = isset( $rules['tags'] ) ? $rules['tags'] : array();
		$page_types       = isset( $rules['page_types'] ) ? $rules['page_types'] : array();
		$exclude_posts    = isset( $rules['exclude_posts'] ) ? $rules['exclude_posts'] : array();
		$exclude_cats     = isset( $rules['exclude_categories'] ) ? $rules['exclude_categories'] : array();
		$exclude_tags     = isset( $rules['exclude_tags'] ) ? $rules['exclude_tags'] : array();
		$exclude_pages    = isset( $rules['exclude_page_types'] ) ? $rules['exclude_page_types'] : array();
		?>
		<div class="wbam-display-rules">
			<div class="wbam-rule-section">
				<label class="wbam-section-label"><?php esc_html_e( 'Show this ad on:', 'wb-ad-manager' ); ?></label>
				<div class="wbam-radio-group">
					<label class="wbam-radio-option">
						<input type="radio" name="wbam_display_rules[display_on]" value="all" <?php checked( $display_on, 'all' ); ?> />
						<span><?php esc_html_e( 'All pages', 'wb-ad-manager' ); ?></span>
						<span class="wbam-option-hint"><?php esc_html_e( 'Show everywhere, with optional exclusions below', 'wb-ad-manager' ); ?></span>
					</label>
					<label class="wbam-radio-option">
						<input type="radio" name="wbam_display_rules[display_on]" value="specific" <?php checked( $display_on, 'specific' ); ?> />
						<span><?php esc_html_e( 'Specific pages', 'wb-ad-manager' ); ?></span>
						<span class="wbam-option-hint"><?php esc_html_e( 'Only show on selected pages below', 'wb-ad-manager' ); ?></span>
					</label>
				</div>
			</div>

			<div class="wbam-rule-section wbam-conditional-section" data-show-when="specific">
				<label class="wbam-section-label"><?php esc_html_e( 'Include on:', 'wb-ad-manager' ); ?></label>

				<div class="wbam-rule-row">
					<label><?php esc_html_e( 'Post Types', 'wb-ad-manager' ); ?></label>
					<div class="wbam-checkbox-list">
						<?php
						$available_types = get_post_types( array( 'public' => true ), 'objects' );
						foreach ( $available_types as $type ) :
							if ( 'wbam-ad' === $type->name || 'attachment' === $type->name ) {
								continue;
							}
							?>
							<label>
								<input type="checkbox" name="wbam_display_rules[post_types][]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $post_types, true ) ); ?> />
								<?php echo esc_html( $type->labels->singular_name ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="wbam-rule-row">
					<label><?php esc_html_e( 'Page Types', 'wb-ad-manager' ); ?></label>
					<div class="wbam-checkbox-list">
						<?php
						$available_page_types = array(
							'front_page' => __( 'Front Page', 'wb-ad-manager' ),
							'blog'       => __( 'Blog Page', 'wb-ad-manager' ),
							'archive'    => __( 'Archives', 'wb-ad-manager' ),
							'search'     => __( 'Search Results', 'wb-ad-manager' ),
							'404'        => __( '404 Page', 'wb-ad-manager' ),
						);
						foreach ( $available_page_types as $key => $label ) :
							?>
							<label>
								<input type="checkbox" name="wbam_display_rules[page_types][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $page_types, true ) ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="wbam-rule-row">
					<label><?php esc_html_e( 'Categories', 'wb-ad-manager' ); ?></label>
					<select name="wbam_display_rules[categories][]" multiple class="wbam-select2" data-placeholder="<?php esc_attr_e( 'Select categories...', 'wb-ad-manager' ); ?>">
						<?php
						$all_categories = get_categories( array( 'hide_empty' => false ) );
						foreach ( $all_categories as $cat ) :
							?>
							<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( in_array( $cat->term_id, $categories, true ) ); ?>>
								<?php echo esc_html( $cat->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wbam-rule-row">
					<label><?php esc_html_e( 'Tags', 'wb-ad-manager' ); ?></label>
					<select name="wbam_display_rules[tags][]" multiple class="wbam-select2" data-placeholder="<?php esc_attr_e( 'Select tags...', 'wb-ad-manager' ); ?>">
						<?php
						$all_tags = get_tags( array( 'hide_empty' => false ) );
						foreach ( $all_tags as $tag ) :
							?>
							<option value="<?php echo esc_attr( $tag->term_id ); ?>" <?php selected( in_array( $tag->term_id, $tags, true ) ); ?>>
								<?php echo esc_html( $tag->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="wbam-rule-section wbam-conditional-section" data-show-when="all">
				<label class="wbam-section-label"><?php esc_html_e( 'Exclude from:', 'wb-ad-manager' ); ?></label>

				<div class="wbam-rule-row">
					<label><?php esc_html_e( 'Page Types', 'wb-ad-manager' ); ?></label>
					<div class="wbam-checkbox-list">
						<?php foreach ( $available_page_types as $key => $label ) : ?>
							<label>
								<input type="checkbox" name="wbam_display_rules[exclude_page_types][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $exclude_pages, true ) ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="wbam-rule-row">
					<label><?php esc_html_e( 'Categories', 'wb-ad-manager' ); ?></label>
					<select name="wbam_display_rules[exclude_categories][]" multiple class="wbam-select2" data-placeholder="<?php esc_attr_e( 'Select categories to exclude...', 'wb-ad-manager' ); ?>">
						<?php foreach ( $all_categories as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( in_array( $cat->term_id, $exclude_cats, true ) ); ?>>
								<?php echo esc_html( $cat->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wbam-rule-row">
					<label><?php esc_html_e( 'Tags', 'wb-ad-manager' ); ?></label>
					<select name="wbam_display_rules[exclude_tags][]" multiple class="wbam-select2" data-placeholder="<?php esc_attr_e( 'Select tags to exclude...', 'wb-ad-manager' ); ?>">
						<?php foreach ( $all_tags as $tag ) : ?>
							<option value="<?php echo esc_attr( $tag->term_id ); ?>" <?php selected( in_array( $tag->term_id, $exclude_tags, true ) ); ?>>
								<?php echo esc_html( $tag->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render schedule metabox.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_schedule( $post ) {
		$schedule   = get_post_meta( $post->ID, '_wbam_schedule', true );
		$schedule   = is_array( $schedule ) ? $schedule : array();
		$start_date = isset( $schedule['start_date'] ) ? $schedule['start_date'] : '';
		$end_date   = isset( $schedule['end_date'] ) ? $schedule['end_date'] : '';
		$days       = isset( $schedule['days'] ) ? (array) $schedule['days'] : array();
		$time_start = isset( $schedule['time_start'] ) ? $schedule['time_start'] : '';
		$time_end   = isset( $schedule['time_end'] ) ? $schedule['time_end'] : '';

		$weekdays = array(
			'mon' => __( 'Mon', 'wb-ad-manager' ),
			'tue' => __( 'Tue', 'wb-ad-manager' ),
			'wed' => __( 'Wed', 'wb-ad-manager' ),
			'thu' => __( 'Thu', 'wb-ad-manager' ),
			'fri' => __( 'Fri', 'wb-ad-manager' ),
			'sat' => __( 'Sat', 'wb-ad-manager' ),
			'sun' => __( 'Sun', 'wb-ad-manager' ),
		);
		?>
		<div class="wbam-schedule">
			<div class="wbam-schedule-field">
				<label for="wbam_start_date"><?php esc_html_e( 'Start Date', 'wb-ad-manager' ); ?></label>
				<input type="date" id="wbam_start_date" name="wbam_schedule[start_date]" value="<?php echo esc_attr( $start_date ); ?>" />
				<p class="description"><?php esc_html_e( 'Leave empty to start immediately.', 'wb-ad-manager' ); ?></p>
			</div>
			<div class="wbam-schedule-field">
				<label for="wbam_end_date"><?php esc_html_e( 'End Date', 'wb-ad-manager' ); ?></label>
				<input type="date" id="wbam_end_date" name="wbam_schedule[end_date]" value="<?php echo esc_attr( $end_date ); ?>" />
				<p class="description"><?php esc_html_e( 'Leave empty to run indefinitely.', 'wb-ad-manager' ); ?></p>
			</div>
			<div class="wbam-schedule-field">
				<label><?php esc_html_e( 'Days of Week', 'wb-ad-manager' ); ?></label>
				<div class="wbam-days-selector">
					<?php foreach ( $weekdays as $day_key => $day_label ) : ?>
						<label class="wbam-day-checkbox">
							<input type="checkbox" name="wbam_schedule[days][]" value="<?php echo esc_attr( $day_key ); ?>" <?php checked( in_array( $day_key, $days, true ) ); ?> />
							<span><?php echo esc_html( $day_label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<p class="description"><?php esc_html_e( 'Leave all unchecked to show every day.', 'wb-ad-manager' ); ?></p>
			</div>
			<div class="wbam-schedule-field">
				<label><?php esc_html_e( 'Time of Day', 'wb-ad-manager' ); ?></label>
				<div class="wbam-time-range">
					<input type="time" name="wbam_schedule[time_start]" value="<?php echo esc_attr( $time_start ); ?>" />
					<span><?php esc_html_e( 'to', 'wb-ad-manager' ); ?></span>
					<input type="time" name="wbam_schedule[time_end]" value="<?php echo esc_attr( $time_end ); ?>" />
				</div>
				<p class="description"><?php esc_html_e( 'Leave empty to show all day. Uses site timezone.', 'wb-ad-manager' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render visitor conditions metabox.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_visitor_conditions( $post ) {
		$conditions  = get_post_meta( $post->ID, '_wbam_visitor_conditions', true );
		$conditions  = is_array( $conditions ) ? $conditions : array();
		$devices     = isset( $conditions['devices'] ) ? $conditions['devices'] : array();
		$user_status = isset( $conditions['user_status'] ) ? $conditions['user_status'] : '';
		$user_roles  = isset( $conditions['user_roles'] ) ? $conditions['user_roles'] : array();
		?>
		<div class="wbam-visitor-conditions">
			<div class="wbam-condition-row">
				<label class="wbam-section-label"><?php esc_html_e( 'Devices', 'wb-ad-manager' ); ?></label>
				<p class="description"><?php esc_html_e( 'Show on selected devices. Leave all unchecked to show on all devices.', 'wb-ad-manager' ); ?></p>
				<div class="wbam-device-options">
					<label class="wbam-device-option">
						<input type="checkbox" name="wbam_visitor_conditions[devices][]" value="desktop" <?php checked( in_array( 'desktop', $devices, true ) ); ?> />
						<span class="dashicons dashicons-desktop"></span>
						<span><?php esc_html_e( 'Desktop', 'wb-ad-manager' ); ?></span>
					</label>
					<label class="wbam-device-option">
						<input type="checkbox" name="wbam_visitor_conditions[devices][]" value="tablet" <?php checked( in_array( 'tablet', $devices, true ) ); ?> />
						<span class="dashicons dashicons-tablet"></span>
						<span><?php esc_html_e( 'Tablet', 'wb-ad-manager' ); ?></span>
					</label>
					<label class="wbam-device-option">
						<input type="checkbox" name="wbam_visitor_conditions[devices][]" value="mobile" <?php checked( in_array( 'mobile', $devices, true ) ); ?> />
						<span class="dashicons dashicons-smartphone"></span>
						<span><?php esc_html_e( 'Mobile', 'wb-ad-manager' ); ?></span>
					</label>
				</div>
			</div>

			<div class="wbam-condition-row">
				<label class="wbam-section-label"><?php esc_html_e( 'User Status', 'wb-ad-manager' ); ?></label>
				<div class="wbam-radio-group wbam-inline">
					<label>
						<input type="radio" name="wbam_visitor_conditions[user_status]" value="" <?php checked( $user_status, '' ); ?> />
						<?php esc_html_e( 'All visitors', 'wb-ad-manager' ); ?>
					</label>
					<label>
						<input type="radio" name="wbam_visitor_conditions[user_status]" value="logged_in" <?php checked( $user_status, 'logged_in' ); ?> />
						<?php esc_html_e( 'Logged in only', 'wb-ad-manager' ); ?>
					</label>
					<label>
						<input type="radio" name="wbam_visitor_conditions[user_status]" value="logged_out" <?php checked( $user_status, 'logged_out' ); ?> />
						<?php esc_html_e( 'Logged out only', 'wb-ad-manager' ); ?>
					</label>
				</div>
			</div>

			<div class="wbam-condition-row">
				<label class="wbam-section-label"><?php esc_html_e( 'User Roles', 'wb-ad-manager' ); ?></label>
				<p class="description"><?php esc_html_e( 'Limit to specific roles. Leave all unchecked to show to all roles.', 'wb-ad-manager' ); ?></p>
				<div class="wbam-checkbox-list wbam-inline">
					<?php
					$available_roles = wp_roles()->get_names();
					foreach ( $available_roles as $role_key => $role_name ) :
						?>
						<label>
							<input type="checkbox" name="wbam_visitor_conditions[user_roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $user_roles, true ) ); ?> />
							<?php echo esc_html( $role_name ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render geo targeting metabox.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_geo_targeting( $post ) {
		$geo_rules         = get_post_meta( $post->ID, '_wbam_geo_targeting', true );
		$geo_rules         = is_array( $geo_rules ) ? $geo_rules : array();
		$enabled           = isset( $geo_rules['enabled'] ) ? $geo_rules['enabled'] : false;
		$include_countries = isset( $geo_rules['include_countries'] ) ? $geo_rules['include_countries'] : array();
		$exclude_countries = isset( $geo_rules['exclude_countries'] ) ? $geo_rules['exclude_countries'] : array();
		$show_unknown      = isset( $geo_rules['show_unknown'] ) ? $geo_rules['show_unknown'] : true;

		$geo_engine = Geo_Engine::get_instance();
		$countries  = $geo_engine->get_countries_list();
		?>
		<div class="wbam-geo-targeting">
			<div class="wbam-geo-toggle">
				<label class="wbam-toggle-label">
					<input type="checkbox" name="wbam_geo_targeting[enabled]" value="1" <?php checked( $enabled ); ?> class="wbam-geo-enable" />
					<span><?php esc_html_e( 'Enable geo targeting for this ad', 'wb-ad-manager' ); ?></span>
				</label>
			</div>

			<div class="wbam-geo-options" <?php echo ! $enabled ? 'style="display:none;"' : ''; ?>>
				<div class="wbam-geo-row">
					<label class="wbam-section-label"><?php esc_html_e( 'Show only in these countries', 'wb-ad-manager' ); ?></label>
					<p class="description"><?php esc_html_e( 'Leave empty to show in all countries.', 'wb-ad-manager' ); ?></p>
					<select name="wbam_geo_targeting[include_countries][]" multiple class="wbam-select2 wbam-country-select" data-placeholder="<?php esc_attr_e( 'Select countries...', 'wb-ad-manager' ); ?>">
						<?php foreach ( $countries as $code => $name ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( in_array( $code, $include_countries, true ) ); ?>>
								<?php echo esc_html( $name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wbam-geo-row">
					<label class="wbam-section-label"><?php esc_html_e( 'Exclude these countries', 'wb-ad-manager' ); ?></label>
					<select name="wbam_geo_targeting[exclude_countries][]" multiple class="wbam-select2 wbam-country-select" data-placeholder="<?php esc_attr_e( 'Select countries to exclude...', 'wb-ad-manager' ); ?>">
						<?php foreach ( $countries as $code => $name ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( in_array( $code, $exclude_countries, true ) ); ?>>
								<?php echo esc_html( $name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wbam-geo-row">
					<label>
						<input type="checkbox" name="wbam_geo_targeting[show_unknown]" value="1" <?php checked( $show_unknown ); ?> />
						<?php esc_html_e( 'Show ad to visitors with unknown location', 'wb-ad-manager' ); ?>
					</label>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save_meta( $post_id, $post ) {
		if ( 'wbam-ad' !== $post->post_type ) {
			return;
		}

		// Verify nonce (created by Admin class).
		if ( ! isset( $_POST['wbam_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbam_nonce'] ) ), 'wbam_save_ad' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save display rules.
		if ( isset( $_POST['wbam_display_rules'] ) ) {
			$rules = $this->sanitize_display_rules( wp_unslash( $_POST['wbam_display_rules'] ) ); // phpcs:ignore
			update_post_meta( $post_id, '_wbam_display_rules', $rules );
		}

		// Save schedule.
		if ( isset( $_POST['wbam_schedule'] ) ) {
			$schedule = $this->sanitize_schedule( wp_unslash( $_POST['wbam_schedule'] ) ); // phpcs:ignore
			update_post_meta( $post_id, '_wbam_schedule', $schedule );
		}

		// Save visitor conditions.
		if ( isset( $_POST['wbam_visitor_conditions'] ) ) {
			$conditions = $this->sanitize_visitor_conditions( wp_unslash( $_POST['wbam_visitor_conditions'] ) ); // phpcs:ignore
			update_post_meta( $post_id, '_wbam_visitor_conditions', $conditions );
		}

		// Save geo targeting.
		$geo_rules = $this->sanitize_geo_targeting( isset( $_POST['wbam_geo_targeting'] ) ? wp_unslash( $_POST['wbam_geo_targeting'] ) : array() ); // phpcs:ignore
		update_post_meta( $post_id, '_wbam_geo_targeting', $geo_rules );
	}

	/**
	 * Sanitize display rules.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	private function sanitize_display_rules( $input ) {
		$sanitized = array();

		$sanitized['display_on'] = isset( $input['display_on'] ) && in_array( $input['display_on'], array( 'all', 'specific' ), true )
			? $input['display_on']
			: 'all';

		$array_fields = array(
			'post_types',
			'categories',
			'tags',
			'page_types',
			'posts',
			'exclude_posts',
			'exclude_categories',
			'exclude_tags',
			'exclude_page_types',
		);

		foreach ( $array_fields as $field ) {
			if ( ! empty( $input[ $field ] ) && is_array( $input[ $field ] ) ) {
				$sanitized[ $field ] = array_map( 'sanitize_text_field', $input[ $field ] );
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize schedule.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	private function sanitize_schedule( $input ) {
		$sanitized = array();

		if ( ! empty( $input['start_date'] ) ) {
			$sanitized['start_date'] = sanitize_text_field( $input['start_date'] );
		}

		if ( ! empty( $input['end_date'] ) ) {
			$sanitized['end_date'] = sanitize_text_field( $input['end_date'] );
		}

		// Days of week.
		$valid_days = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );
		if ( ! empty( $input['days'] ) && is_array( $input['days'] ) ) {
			$sanitized['days'] = array_intersect( $input['days'], $valid_days );
		}

		// Time range.
		if ( ! empty( $input['time_start'] ) ) {
			$sanitized['time_start'] = sanitize_text_field( $input['time_start'] );
		}

		if ( ! empty( $input['time_end'] ) ) {
			$sanitized['time_end'] = sanitize_text_field( $input['time_end'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize visitor conditions.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	private function sanitize_visitor_conditions( $input ) {
		$sanitized = array();

		if ( ! empty( $input['devices'] ) && is_array( $input['devices'] ) ) {
			$sanitized['devices'] = array_intersect( $input['devices'], array( 'desktop', 'tablet', 'mobile' ) );
		}

		if ( ! empty( $input['user_status'] ) ) {
			$sanitized['user_status'] = in_array( $input['user_status'], array( 'logged_in', 'logged_out' ), true )
				? $input['user_status']
				: '';
		}

		if ( ! empty( $input['user_roles'] ) && is_array( $input['user_roles'] ) ) {
			$sanitized['user_roles'] = array_map( 'sanitize_key', $input['user_roles'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize geo targeting.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	private function sanitize_geo_targeting( $input ) {
		$sanitized = array();

		$sanitized['enabled']      = ! empty( $input['enabled'] );
		$sanitized['show_unknown'] = ! empty( $input['show_unknown'] );

		if ( ! empty( $input['include_countries'] ) && is_array( $input['include_countries'] ) ) {
			$sanitized['include_countries'] = array_map( 'sanitize_text_field', $input['include_countries'] );
		} else {
			$sanitized['include_countries'] = array();
		}

		if ( ! empty( $input['exclude_countries'] ) && is_array( $input['exclude_countries'] ) ) {
			$sanitized['exclude_countries'] = array_map( 'sanitize_text_field', $input['exclude_countries'] );
		} else {
			$sanitized['exclude_countries'] = array();
		}

		return $sanitized;
	}
}
