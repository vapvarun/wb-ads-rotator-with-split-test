<?php
/**
 * BuddyPress Widgets
 *
 * @package WB_Ad_Manager
 * @since   1.1.0
 */

namespace WBAM\Modules\BuddyPress;

use WBAM\Modules\Placements\Placement_Engine;

/**
 * BP Widgets class.
 */
class BP_Widgets {

	/**
	 * Initialize widgets.
	 */
	public static function init() {
		add_action( 'widgets_init', array( __CLASS__, 'register_widgets' ) );
	}

	/**
	 * Register widgets.
	 */
	public static function register_widgets() {
		register_widget( __NAMESPACE__ . '\BP_Profile_Ad_Widget' );
		register_widget( __NAMESPACE__ . '\BP_Group_Ad_Widget' );
		register_widget( __NAMESPACE__ . '\BP_Activity_Ad_Widget' );
	}
}

/**
 * BuddyPress Profile Ad Widget.
 */
class BP_Profile_Ad_Widget extends \WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'wbam_bp_profile_ad',
			__( 'WBAM: BuddyPress Profile Ad', 'wb-ad-manager' ),
			array(
				'description' => __( 'Display ads on BuddyPress member profiles.', 'wb-ad-manager' ),
				'classname'   => 'wbam-bp-profile-widget',
			)
		);
	}

	/**
	 * Output widget.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ) {
		// Only show on BuddyPress member pages.
		if ( ! function_exists( 'bp_is_user' ) || ! bp_is_user() ) {
			return;
		}

		$ad_id = ! empty( $instance['ad_id'] ) ? absint( $instance['ad_id'] ) : 0;

		if ( ! $ad_id ) {
			return;
		}

		$engine = Placement_Engine::get_instance();
		$output = $engine->render_ad( $ad_id );

		if ( empty( $output ) ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<div class="wbam-bp-profile-ad">' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Widget form.
	 *
	 * @param array $instance Widget instance.
	 */
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$ad_id = ! empty( $instance['ad_id'] ) ? $instance['ad_id'] : '';

		$ads = get_posts(
			array(
				'post_type'      => 'wbam-ad',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title (optional):', 'wb-ad-manager' ); ?>
			</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'ad_id' ) ); ?>">
				<?php esc_html_e( 'Select Ad:', 'wb-ad-manager' ); ?>
			</label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'ad_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'ad_id' ) ); ?>">
				<option value=""><?php esc_html_e( '— Select Ad —', 'wb-ad-manager' ); ?></option>
				<?php foreach ( $ads as $ad ) : ?>
					<option value="<?php echo esc_attr( $ad->ID ); ?>" <?php selected( $ad_id, $ad->ID ); ?>>
						<?php echo esc_html( $ad->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description">
			<?php esc_html_e( 'This widget only displays on BuddyPress member profile pages.', 'wb-ad-manager' ); ?>
		</p>
		<?php
	}

	/**
	 * Update widget.
	 *
	 * @param array $new_instance New instance.
	 * @param array $old_instance Old instance.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['ad_id'] = ! empty( $new_instance['ad_id'] ) ? absint( $new_instance['ad_id'] ) : '';

		return $instance;
	}
}

/**
 * BuddyPress Group Ad Widget.
 */
class BP_Group_Ad_Widget extends \WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'wbam_bp_group_ad',
			__( 'WBAM: BuddyPress Group Ad', 'wb-ad-manager' ),
			array(
				'description' => __( 'Display ads on BuddyPress group pages.', 'wb-ad-manager' ),
				'classname'   => 'wbam-bp-group-widget',
			)
		);
	}

	/**
	 * Output widget.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ) {
		// Only show on BuddyPress group pages.
		if ( ! function_exists( 'bp_is_group' ) || ! bp_is_group() ) {
			return;
		}

		$ad_id = ! empty( $instance['ad_id'] ) ? absint( $instance['ad_id'] ) : 0;

		if ( ! $ad_id ) {
			return;
		}

		$engine = Placement_Engine::get_instance();
		$output = $engine->render_ad( $ad_id );

		if ( empty( $output ) ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<div class="wbam-bp-group-ad">' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Widget form.
	 *
	 * @param array $instance Widget instance.
	 */
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$ad_id = ! empty( $instance['ad_id'] ) ? $instance['ad_id'] : '';

		$ads = get_posts(
			array(
				'post_type'      => 'wbam-ad',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title (optional):', 'wb-ad-manager' ); ?>
			</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'ad_id' ) ); ?>">
				<?php esc_html_e( 'Select Ad:', 'wb-ad-manager' ); ?>
			</label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'ad_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'ad_id' ) ); ?>">
				<option value=""><?php esc_html_e( '— Select Ad —', 'wb-ad-manager' ); ?></option>
				<?php foreach ( $ads as $ad ) : ?>
					<option value="<?php echo esc_attr( $ad->ID ); ?>" <?php selected( $ad_id, $ad->ID ); ?>>
						<?php echo esc_html( $ad->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description">
			<?php esc_html_e( 'This widget only displays on BuddyPress group pages.', 'wb-ad-manager' ); ?>
		</p>
		<?php
	}

	/**
	 * Update widget.
	 *
	 * @param array $new_instance New instance.
	 * @param array $old_instance Old instance.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['ad_id'] = ! empty( $new_instance['ad_id'] ) ? absint( $new_instance['ad_id'] ) : '';

		return $instance;
	}
}

/**
 * BuddyPress Activity Ad Widget.
 */
class BP_Activity_Ad_Widget extends \WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'wbam_bp_activity_ad',
			__( 'WBAM: BuddyPress Activity Ad', 'wb-ad-manager' ),
			array(
				'description' => __( 'Display ads on BuddyPress activity pages.', 'wb-ad-manager' ),
				'classname'   => 'wbam-bp-activity-widget',
			)
		);
	}

	/**
	 * Output widget.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ) {
		// Only show on BuddyPress activity pages.
		if ( ! function_exists( 'bp_is_activity_component' ) || ! bp_is_activity_component() ) {
			return;
		}

		$ad_id = ! empty( $instance['ad_id'] ) ? absint( $instance['ad_id'] ) : 0;

		if ( ! $ad_id ) {
			return;
		}

		$engine = Placement_Engine::get_instance();
		$output = $engine->render_ad( $ad_id );

		if ( empty( $output ) ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<div class="wbam-bp-activity-ad-widget">' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Widget form.
	 *
	 * @param array $instance Widget instance.
	 */
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$ad_id = ! empty( $instance['ad_id'] ) ? $instance['ad_id'] : '';

		$ads = get_posts(
			array(
				'post_type'      => 'wbam-ad',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title (optional):', 'wb-ad-manager' ); ?>
			</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'ad_id' ) ); ?>">
				<?php esc_html_e( 'Select Ad:', 'wb-ad-manager' ); ?>
			</label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'ad_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'ad_id' ) ); ?>">
				<option value=""><?php esc_html_e( '— Select Ad —', 'wb-ad-manager' ); ?></option>
				<?php foreach ( $ads as $ad ) : ?>
					<option value="<?php echo esc_attr( $ad->ID ); ?>" <?php selected( $ad_id, $ad->ID ); ?>>
						<?php echo esc_html( $ad->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description">
			<?php esc_html_e( 'This widget only displays on BuddyPress activity pages.', 'wb-ad-manager' ); ?>
		</p>
		<?php
	}

	/**
	 * Update widget.
	 *
	 * @param array $new_instance New instance.
	 * @param array $old_instance Old instance.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['ad_id'] = ! empty( $new_instance['ad_id'] ) ? absint( $new_instance['ad_id'] ) : '';

		return $instance;
	}
}
