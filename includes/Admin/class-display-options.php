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
			__( 'Display Rules', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_display_rules' ),
			'wbam-ad',
			'normal',
			'default'
		);

		add_meta_box(
			'wbam-schedule',
			__( 'Schedule', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_schedule' ),
			'wbam-ad',
			'side',
			'default'
		);

		add_meta_box(
			'wbam-visitor-conditions',
			__( 'Visitor Conditions', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_visitor_conditions' ),
			'wbam-ad',
			'normal',
			'default'
		);

		add_meta_box(
			'wbam-geo-targeting',
			__( 'Geo Targeting', 'wb-ads-rotator-with-split-test' ),
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

		$post_types     = isset( $rules['post_types'] ) ? $rules['post_types'] : array();
		$categories     = isset( $rules['categories'] ) ? $rules['categories'] : array();
		$tags           = isset( $rules['tags'] ) ? $rules['tags'] : array();
		$page_types     = isset( $rules['page_types'] ) ? $rules['page_types'] : array();
		$specific_posts = isset( $rules['posts'] ) ? array_map( 'intval', $rules['posts'] ) : array();
		$exclude_posts  = isset( $rules['exclude_posts'] ) ? $rules['exclude_posts'] : array();
		$exclude_cats   = isset( $rules['exclude_categories'] ) ? $rules['exclude_categories'] : array();
		$exclude_tags   = isset( $rules['exclude_tags'] ) ? $rules['exclude_tags'] : array();
		$exclude_pages  = isset( $rules['exclude_page_types'] ) ? $rules['exclude_page_types'] : array();
		?>
		<div class="wbam-display-rules">
			<div class="wbam-rule-section">
				<label class="wbam-section-label"><?php esc_html_e( 'Show this ad on:', 'wb-ads-rotator-with-split-test' ); ?></label>
				<div class="wbam-radio-group">
					<label class="wbam-radio-option">
						<input type="radio" name="wbam_display_rules[display_on]" value="all" <?php checked( $display_on, 'all' ); ?> />
						<span><?php esc_html_e( 'All pages', 'wb-ads-rotator-with-split-test' ); ?></span>
						<span class="wbam-option-hint"><?php esc_html_e( 'Show everywhere, with optional exclusions below', 'wb-ads-rotator-with-split-test' ); ?></span>
					</label>
					<label class="wbam-radio-option">
						<input type="radio" name="wbam_display_rules[display_on]" value="specific" <?php checked( $display_on, 'specific' ); ?> />
						<span><?php esc_html_e( 'Specific pages', 'wb-ads-rotator-with-split-test' ); ?></span>
						<span class="wbam-option-hint"><?php esc_html_e( 'Only show on selected pages below', 'wb-ads-rotator-with-split-test' ); ?></span>
					</label>
				</div>
			</div>

			<div class="wbam-rule-section wbam-conditional-section" data-show-when="specific">
				<label class="wbam-section-label"><?php esc_html_e( 'Target by:', 'wb-ads-rotator-with-split-test' ); ?></label>

				<?php // Specific Pages - Most specific targeting first. ?>
				<div class="wbam-rule-row">
					<label><?php esc_html_e( 'Selected Pages', 'wb-ads-rotator-with-split-test' ); ?></label>
					<select name="wbam_display_rules[posts][]" multiple class="wbam-select2" data-placeholder="<?php esc_attr_e( 'Choose individual pages...', 'wb-ads-rotator-with-split-test' ); ?>">
						<?php
						$all_pages = get_pages(
							array(
								'sort_column' => 'post_title',
								'sort_order'  => 'ASC',
								'post_status' => 'publish',
							)
						);
						foreach ( $all_pages as $page_item ) :
							?>
							<option value="<?php echo esc_attr( $page_item->ID ); ?>" <?php selected( in_array( $page_item->ID, $specific_posts, true ) ); ?>>
								<?php echo esc_html( $page_item->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Pick individual pages where this ad should appear.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<?php // Special Page Types - Front page, blog, archives, etc. ?>
				<div class="wbam-rule-row">
					<label><?php esc_html_e( 'Special Templates', 'wb-ads-rotator-with-split-test' ); ?></label>
					<div class="wbam-checkbox-list">
						<?php
						$available_page_types = array(
							'front_page' => __( 'Front Page', 'wb-ads-rotator-with-split-test' ),
							'blog'       => __( 'Blog Page', 'wb-ads-rotator-with-split-test' ),
							'archive'    => __( 'Archives', 'wb-ads-rotator-with-split-test' ),
							'search'     => __( 'Search Results', 'wb-ads-rotator-with-split-test' ),
							'404'        => __( '404 Page', 'wb-ads-rotator-with-split-test' ),
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

				<?php // Content Types. ?>
				<div class="wbam-rule-row">
					<label><?php esc_html_e( 'Content Types', 'wb-ads-rotator-with-split-test' ); ?></label>
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
					<p class="description"><?php esc_html_e( 'Show on all content of these types.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<?php // Categories. ?>
				<div class="wbam-rule-row">
					<label><?php esc_html_e( 'Categories', 'wb-ads-rotator-with-split-test' ); ?></label>
					<select name="wbam_display_rules[categories][]" multiple class="wbam-select2" data-placeholder="<?php esc_attr_e( 'Select categories...', 'wb-ads-rotator-with-split-test' ); ?>">
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

				<?php // Tags. ?>
				<div class="wbam-rule-row">
					<label><?php esc_html_e( 'Tags', 'wb-ads-rotator-with-split-test' ); ?></label>
					<select name="wbam_display_rules[tags][]" multiple class="wbam-select2" data-placeholder="<?php esc_attr_e( 'Select tags...', 'wb-ads-rotator-with-split-test' ); ?>">
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
				<label class="wbam-section-label"><?php esc_html_e( 'Exclude from:', 'wb-ads-rotator-with-split-test' ); ?></label>

				<div class="wbam-rule-row">
					<label><?php esc_html_e( 'Special Templates', 'wb-ads-rotator-with-split-test' ); ?></label>
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
					<label><?php esc_html_e( 'Categories', 'wb-ads-rotator-with-split-test' ); ?></label>
					<select name="wbam_display_rules[exclude_categories][]" multiple class="wbam-select2" data-placeholder="<?php esc_attr_e( 'Select categories to exclude...', 'wb-ads-rotator-with-split-test' ); ?>">
						<?php foreach ( $all_categories as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( in_array( $cat->term_id, $exclude_cats, true ) ); ?>>
								<?php echo esc_html( $cat->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wbam-rule-row">
					<label><?php esc_html_e( 'Tags', 'wb-ads-rotator-with-split-test' ); ?></label>
					<select name="wbam_display_rules[exclude_tags][]" multiple class="wbam-select2" data-placeholder="<?php esc_attr_e( 'Select tags to exclude...', 'wb-ads-rotator-with-split-test' ); ?>">
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

		// Also check standalone date meta (used by PRO advertiser dashboard).
		$standalone_start = get_post_meta( $post->ID, '_wbam_start_date', true );
		$standalone_end   = get_post_meta( $post->ID, '_wbam_end_date', true );

		// Use standalone dates if schedule dates are empty.
		if ( empty( $start_date ) && ! empty( $standalone_start ) ) {
			$start_date = $standalone_start;
		}
		if ( empty( $end_date ) && ! empty( $standalone_end ) ) {
			$end_date = $standalone_end;
		}

		$days       = isset( $schedule['days'] ) ? (array) $schedule['days'] : array();
		$time_start = isset( $schedule['time_start'] ) ? $schedule['time_start'] : '';
		$time_end   = isset( $schedule['time_end'] ) ? $schedule['time_end'] : '';

		$weekdays = array(
			'mon' => __( 'Mon', 'wb-ads-rotator-with-split-test' ),
			'tue' => __( 'Tue', 'wb-ads-rotator-with-split-test' ),
			'wed' => __( 'Wed', 'wb-ads-rotator-with-split-test' ),
			'thu' => __( 'Thu', 'wb-ads-rotator-with-split-test' ),
			'fri' => __( 'Fri', 'wb-ads-rotator-with-split-test' ),
			'sat' => __( 'Sat', 'wb-ads-rotator-with-split-test' ),
			'sun' => __( 'Sun', 'wb-ads-rotator-with-split-test' ),
		);
		?>
		<div class="wbam-schedule">
			<div class="wbam-schedule-field">
				<label for="wbam_start_date"><?php esc_html_e( 'Start Date', 'wb-ads-rotator-with-split-test' ); ?></label>
				<input type="date" id="wbam_start_date" name="wbam_schedule[start_date]" value="<?php echo esc_attr( $start_date ); ?>" />
				<p class="description"><?php esc_html_e( 'Leave empty to start immediately.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>
			<div class="wbam-schedule-field">
				<label for="wbam_end_date"><?php esc_html_e( 'End Date', 'wb-ads-rotator-with-split-test' ); ?></label>
				<input type="date" id="wbam_end_date" name="wbam_schedule[end_date]" value="<?php echo esc_attr( $end_date ); ?>" />
				<p class="description"><?php esc_html_e( 'Leave empty to run indefinitely.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>
			<div class="wbam-schedule-field">
				<label><?php esc_html_e( 'Days of Week', 'wb-ads-rotator-with-split-test' ); ?></label>
				<div class="wbam-days-selector">
					<?php foreach ( $weekdays as $day_key => $day_label ) : ?>
						<label class="wbam-day-checkbox">
							<input type="checkbox" name="wbam_schedule[days][]" value="<?php echo esc_attr( $day_key ); ?>" <?php checked( in_array( $day_key, $days, true ) ); ?> />
							<span><?php echo esc_html( $day_label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<p class="description"><?php esc_html_e( 'Leave all unchecked to show every day.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>
			<div class="wbam-schedule-field">
				<label><?php esc_html_e( 'Time of Day', 'wb-ads-rotator-with-split-test' ); ?></label>
				<div class="wbam-time-range">
					<input type="time" name="wbam_schedule[time_start]" value="<?php echo esc_attr( $time_start ); ?>" />
					<span><?php esc_html_e( 'to', 'wb-ads-rotator-with-split-test' ); ?></span>
					<input type="time" name="wbam_schedule[time_end]" value="<?php echo esc_attr( $time_end ); ?>" />
				</div>
				<p class="description"><?php esc_html_e( 'Leave empty to show all day. Uses site timezone.', 'wb-ads-rotator-with-split-test' ); ?></p>
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
				<label class="wbam-section-label"><?php esc_html_e( 'Devices', 'wb-ads-rotator-with-split-test' ); ?></label>
				<p class="description"><?php esc_html_e( 'Show on selected devices. Leave all unchecked to show on all devices.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<div class="wbam-device-options">
					<label class="wbam-device-option">
						<input type="checkbox" name="wbam_visitor_conditions[devices][]" value="desktop" <?php checked( in_array( 'desktop', $devices, true ) ); ?> />
						<span class="dashicons dashicons-desktop"></span>
						<span><?php esc_html_e( 'Desktop', 'wb-ads-rotator-with-split-test' ); ?></span>
					</label>
					<label class="wbam-device-option">
						<input type="checkbox" name="wbam_visitor_conditions[devices][]" value="tablet" <?php checked( in_array( 'tablet', $devices, true ) ); ?> />
						<span class="dashicons dashicons-tablet"></span>
						<span><?php esc_html_e( 'Tablet', 'wb-ads-rotator-with-split-test' ); ?></span>
					</label>
					<label class="wbam-device-option">
						<input type="checkbox" name="wbam_visitor_conditions[devices][]" value="mobile" <?php checked( in_array( 'mobile', $devices, true ) ); ?> />
						<span class="dashicons dashicons-smartphone"></span>
						<span><?php esc_html_e( 'Mobile', 'wb-ads-rotator-with-split-test' ); ?></span>
					</label>
				</div>
			</div>

			<div class="wbam-condition-row">
				<label class="wbam-section-label"><?php esc_html_e( 'User Status', 'wb-ads-rotator-with-split-test' ); ?></label>
				<div class="wbam-radio-group wbam-inline">
					<label>
						<input type="radio" name="wbam_visitor_conditions[user_status]" value="" <?php checked( $user_status, '' ); ?> />
						<?php esc_html_e( 'All visitors', 'wb-ads-rotator-with-split-test' ); ?>
					</label>
					<label>
						<input type="radio" name="wbam_visitor_conditions[user_status]" value="logged_in" <?php checked( $user_status, 'logged_in' ); ?> />
						<?php esc_html_e( 'Logged in only', 'wb-ads-rotator-with-split-test' ); ?>
					</label>
					<label>
						<input type="radio" name="wbam_visitor_conditions[user_status]" value="logged_out" <?php checked( $user_status, 'logged_out' ); ?> />
						<?php esc_html_e( 'Logged out only', 'wb-ads-rotator-with-split-test' ); ?>
					</label>
				</div>
			</div>

			<div class="wbam-condition-row">
				<label class="wbam-section-label"><?php esc_html_e( 'User Roles', 'wb-ads-rotator-with-split-test' ); ?></label>
				<p class="description"><?php esc_html_e( 'Limit to specific roles. Leave all unchecked to show to all roles.', 'wb-ads-rotator-with-split-test' ); ?></p>
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

		// Determine the mode based on existing data.
		// If include_countries has values, mode is 'include'.
		// If exclude_countries has values, mode is 'exclude'.
		// Default to 'include' (show only in selected).
		$mode               = 'include';
		$selected_countries = array();

		if ( ! empty( $include_countries ) ) {
			$mode               = 'include';
			$selected_countries = $include_countries;
		} elseif ( ! empty( $exclude_countries ) ) {
			$mode               = 'exclude';
			$selected_countries = $exclude_countries;
		}

		// Get mode from saved data if available.
		if ( isset( $geo_rules['mode'] ) ) {
			$mode = $geo_rules['mode'];
		}

		$geo_engine = Geo_Engine::get_instance();
		$countries  = $geo_engine->get_countries_list();
		?>
		<div class="wbam-geo-targeting">
			<div class="wbam-geo-toggle">
				<label class="wbam-toggle-label">
					<input type="checkbox" name="wbam_geo_targeting[enabled]" value="1" <?php checked( $enabled ); ?> class="wbam-geo-enable" />
					<span><?php esc_html_e( 'Enable geo targeting for this ad', 'wb-ads-rotator-with-split-test' ); ?></span>
				</label>
			</div>

			<div class="wbam-geo-options" <?php echo ! $enabled ? 'style="display:none;"' : ''; ?>>
				<div class="wbam-geo-row">
					<label class="wbam-section-label"><?php esc_html_e( 'Targeting Mode', 'wb-ads-rotator-with-split-test' ); ?></label>
					<div class="wbam-geo-mode-selector" style="margin-bottom: 12px;">
						<label style="display: inline-block; margin-right: 20px; cursor: pointer;">
							<input type="radio" name="wbam_geo_targeting[mode]" value="include" <?php checked( $mode, 'include' ); ?> class="wbam-geo-mode-radio" />
							<?php esc_html_e( 'Show only in selected countries', 'wb-ads-rotator-with-split-test' ); ?>
						</label>
						<label style="display: inline-block; cursor: pointer;">
							<input type="radio" name="wbam_geo_targeting[mode]" value="exclude" <?php checked( $mode, 'exclude' ); ?> class="wbam-geo-mode-radio" />
							<?php esc_html_e( 'Show everywhere except selected countries', 'wb-ads-rotator-with-split-test' ); ?>
						</label>
					</div>
				</div>

				<div class="wbam-geo-row">
					<label class="wbam-section-label wbam-geo-countries-label">
						<?php
						if ( 'exclude' === $mode ) {
							esc_html_e( 'Countries to exclude', 'wb-ads-rotator-with-split-test' );
						} else {
							esc_html_e( 'Countries to show in', 'wb-ads-rotator-with-split-test' );
						}
						?>
					</label>
					<p class="description wbam-geo-countries-desc">
						<?php
						if ( 'exclude' === $mode ) {
							esc_html_e( 'The ad will be hidden from visitors in these countries.', 'wb-ads-rotator-with-split-test' );
						} else {
							esc_html_e( 'The ad will only be shown to visitors in these countries. Leave empty to show in all countries.', 'wb-ads-rotator-with-split-test' );
						}
						?>
					</p>
					<select name="wbam_geo_targeting[countries][]" multiple class="wbam-select2 wbam-country-select wbam-geo-countries-select" data-placeholder="<?php esc_attr_e( 'Search and select countries...', 'wb-ads-rotator-with-split-test' ); ?>" style="width: 100%;">
						<?php foreach ( $countries as $code => $name ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( in_array( $code, $selected_countries, true ) ); ?>>
								<?php echo esc_html( $name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wbam-geo-row" style="margin-top: 15px;">
					<label>
						<input type="checkbox" name="wbam_geo_targeting[show_unknown]" value="1" <?php checked( $show_unknown ); ?> />
						<?php esc_html_e( 'Show ad to visitors with unknown location', 'wb-ads-rotator-with-split-test' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'When location cannot be determined (e.g., VPN, local IP).', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Update label and description when mode changes.
			$('.wbam-geo-mode-radio').on('change', function() {
				var mode = $(this).val();
				var $label = $('.wbam-geo-countries-label');
				var $desc = $('.wbam-geo-countries-desc');

				if (mode === 'exclude') {
					$label.text('<?php echo esc_js( __( 'Countries to exclude', 'wb-ads-rotator-with-split-test' ) ); ?>');
					$desc.text('<?php echo esc_js( __( 'The ad will be hidden from visitors in these countries.', 'wb-ads-rotator-with-split-test' ) ); ?>');
				} else {
					$label.text('<?php echo esc_js( __( 'Countries to show in', 'wb-ads-rotator-with-split-test' ) ); ?>');
					$desc.text('<?php echo esc_js( __( 'The ad will only be shown to visitors in these countries. Leave empty to show in all countries.', 'wb-ads-rotator-with-split-test' ) ); ?>');
				}
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

			// Also sync to standalone date meta keys (used by PRO advertiser dashboard).
			if ( ! empty( $schedule['start_date'] ) ) {
				update_post_meta( $post_id, '_wbam_start_date', $schedule['start_date'] );
			} else {
				delete_post_meta( $post_id, '_wbam_start_date' );
			}
			if ( ! empty( $schedule['end_date'] ) ) {
				update_post_meta( $post_id, '_wbam_end_date', $schedule['end_date'] );
			} else {
				delete_post_meta( $post_id, '_wbam_end_date' );
			}
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

		// Handle new unified mode + countries structure.
		$mode      = isset( $input['mode'] ) ? sanitize_text_field( $input['mode'] ) : 'include';
		$countries = array();

		if ( ! empty( $input['countries'] ) && is_array( $input['countries'] ) ) {
			$countries = array_map( 'sanitize_text_field', $input['countries'] );
		}

		// Validate mode.
		if ( ! in_array( $mode, array( 'include', 'exclude' ), true ) ) {
			$mode = 'include';
		}

		$sanitized['mode'] = $mode;

		// Map countries to the appropriate field based on mode.
		// This maintains backward compatibility with the geo engine logic.
		if ( 'include' === $mode ) {
			$sanitized['include_countries'] = $countries;
			$sanitized['exclude_countries'] = array();
		} else {
			$sanitized['include_countries'] = array();
			$sanitized['exclude_countries'] = $countries;
		}

		// Legacy support: if old separate fields are submitted, merge them.
		if ( ! empty( $input['include_countries'] ) && is_array( $input['include_countries'] ) ) {
			$sanitized['include_countries'] = array_map( 'sanitize_text_field', $input['include_countries'] );
		}
		if ( ! empty( $input['exclude_countries'] ) && is_array( $input['exclude_countries'] ) ) {
			$sanitized['exclude_countries'] = array_map( 'sanitize_text_field', $input['exclude_countries'] );
		}

		return $sanitized;
	}
}
