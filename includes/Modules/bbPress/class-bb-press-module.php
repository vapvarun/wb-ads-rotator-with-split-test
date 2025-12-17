<?php
/**
 * bbPress Module
 *
 * @package WB_Ad_Manager
 * @since   1.1.0
 */

namespace WBAM\Modules\bbPress;

use WBAM\Modules\Placements\Placement_Engine;

/**
 * bbPress_Module class.
 */
class bbPress_Module {

	/**
	 * Initialize.
	 */
	public function init() {
		if ( ! class_exists( 'bbPress' ) ) {
			return;
		}

		$this->register_placements();
		$this->setup_hooks();
		bbPress_Widgets::init();
	}

	/**
	 * Register bbPress placements.
	 */
	private function register_placements() {
		$engine = Placement_Engine::get_instance();
		$engine->register_placement( new bbPress_Placement() );
	}

	/**
	 * Setup hooks.
	 */
	private function setup_hooks() {
		// Additional bbPress-specific hooks.
	}
}

/**
 * bbPress Placement class.
 */
class bbPress_Placement implements \WBAM\Modules\Placements\Placement_Interface {

	/**
	 * Topic counter.
	 *
	 * @var int
	 */
	private $topic_count = 0;

	/**
	 * Reply counter.
	 *
	 * @var int
	 */
	private $reply_count = 0;

	/**
	 * Get placement ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'bbpress';
	}

	/**
	 * Get placement name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'bbPress Forums', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get placement description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Display ads in bbPress forums, topics, and replies.', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get placement group.
	 *
	 * @return string
	 */
	public function get_group() {
		return 'bbpress';
	}

	/**
	 * Check if placement is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return class_exists( 'bbPress' );
	}

	/**
	 * Show in placement selector.
	 *
	 * @return bool
	 */
	public function show_in_selector() {
		return true;
	}

	/**
	 * Register placement hooks.
	 */
	public function register() {
		// Forum list.
		add_action( 'bbp_template_before_forums_loop', array( $this, 'render_before_forums' ) );
		add_action( 'bbp_template_after_forums_loop', array( $this, 'render_after_forums' ) );

		// Topic list.
		add_action( 'bbp_template_before_topics_loop', array( $this, 'render_before_topics' ) );
		add_action( 'bbp_template_after_topics_loop', array( $this, 'render_after_topics' ) );

		// Single topic.
		add_action( 'bbp_template_before_single_topic', array( $this, 'render_before_single_topic' ) );
		add_action( 'bbp_template_after_single_topic', array( $this, 'render_after_single_topic' ) );

		// Replies.
		add_action( 'bbp_template_before_replies_loop', array( $this, 'reset_reply_counter' ) );
		add_action( 'bbp_theme_after_reply_content', array( $this, 'render_between_replies' ) );
	}

	/**
	 * Render before forums.
	 */
	public function render_before_forums() {
		$this->render_bbpress_ads( 'before_forums' );
	}

	/**
	 * Render after forums.
	 */
	public function render_after_forums() {
		$this->render_bbpress_ads( 'after_forums' );
	}

	/**
	 * Render before topics.
	 */
	public function render_before_topics() {
		$this->topic_count = 0;
		$this->render_bbpress_ads( 'before_topics' );
	}

	/**
	 * Render after topics.
	 */
	public function render_after_topics() {
		$this->render_bbpress_ads( 'after_topics' );
	}

	/**
	 * Render before single topic.
	 */
	public function render_before_single_topic() {
		$this->render_bbpress_ads( 'before_single_topic' );
	}

	/**
	 * Render after single topic.
	 */
	public function render_after_single_topic() {
		$this->render_bbpress_ads( 'after_single_topic' );
	}

	/**
	 * Reset reply counter.
	 */
	public function reset_reply_counter() {
		$this->reply_count = 0;
	}

	/**
	 * Render between replies.
	 */
	public function render_between_replies() {
		$this->reply_count++;
		$this->render_between_items( 'replies', $this->reply_count );
	}

	/**
	 * Render bbPress ads.
	 *
	 * @param string $position Position key.
	 */
	private function render_bbpress_ads( $position ) {
		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		foreach ( $ads as $ad_id ) {
			$data        = get_post_meta( $ad_id, '_wbam_ad_data', true );
			$ad_position = isset( $data['bbpress_position'] ) ? $data['bbpress_position'] : 'before_forums';

			if ( $ad_position !== $position ) {
				continue;
			}

			$output = $engine->render_ad( $ad_id );

			if ( ! empty( $output ) ) {
				echo '<div class="wbam-bbpress-ad wbam-bbpress-' . esc_attr( $position ) . '">' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * Render ads between items.
	 *
	 * @param string $type  Item type (replies).
	 * @param int    $count Current item count.
	 */
	private function render_between_items( $type, $count ) {
		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		foreach ( $ads as $ad_id ) {
			$data        = get_post_meta( $ad_id, '_wbam_ad_data', true );
			$ad_position = isset( $data['bbpress_position'] ) ? $data['bbpress_position'] : '';
			$after_count = isset( $data['bbpress_after'] ) ? absint( $data['bbpress_after'] ) : 5;
			$repeat      = isset( $data['bbpress_repeat'] ) ? $data['bbpress_repeat'] : false;

			$expected_position = 'between_' . $type;
			if ( $ad_position !== $expected_position ) {
				continue;
			}

			$show = false;
			if ( $repeat ) {
				$show = ( $count % $after_count === 0 );
			} else {
				$show = ( $count === $after_count );
			}

			if ( ! $show ) {
				continue;
			}

			$output = $engine->render_ad( $ad_id );

			if ( ! empty( $output ) ) {
				echo '<div class="wbam-bbpress-ad wbam-bbpress-between-' . esc_attr( $type ) . '">' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * Render placement options.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Ad data.
	 */
	public function render_options( $ad_id, $data ) {
		$position    = isset( $data['bbpress_position'] ) ? $data['bbpress_position'] : 'before_forums';
		$after_count = isset( $data['bbpress_after'] ) ? absint( $data['bbpress_after'] ) : 5;
		$repeat      = isset( $data['bbpress_repeat'] ) ? $data['bbpress_repeat'] : false;

		$positions = array(
			'before_forums'       => __( 'Before Forum List', 'wb-ads-rotator-with-split-test' ),
			'after_forums'        => __( 'After Forum List', 'wb-ads-rotator-with-split-test' ),
			'before_topics'       => __( 'Before Topic List', 'wb-ads-rotator-with-split-test' ),
			'after_topics'        => __( 'After Topic List', 'wb-ads-rotator-with-split-test' ),
			'before_single_topic' => __( 'Before Single Topic', 'wb-ads-rotator-with-split-test' ),
			'after_single_topic'  => __( 'After Single Topic', 'wb-ads-rotator-with-split-test' ),
			'between_replies'     => __( 'Between Replies', 'wb-ads-rotator-with-split-test' ),
		);
		?>
		<div class="wbam-placement-extra">
			<label><?php esc_html_e( 'Position', 'wb-ads-rotator-with-split-test' ); ?></label>
			<select name="wbam_data[bbpress_position]" class="wbam-bbpress-position-select">
				<?php foreach ( $positions as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $position, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="wbam-placement-extra wbam-bbpress-between-options" <?php echo ( strpos( $position, 'between' ) === false ) ? 'style="display:none;"' : ''; ?>>
			<label><?php esc_html_e( 'Show after every', 'wb-ads-rotator-with-split-test' ); ?></label>
			<input type="number" name="wbam_data[bbpress_after]" value="<?php echo esc_attr( $after_count ); ?>" min="1" max="50" style="width:60px;" />
			<?php esc_html_e( 'replies', 'wb-ads-rotator-with-split-test' ); ?>
			<label style="margin-left:15px;">
				<input type="checkbox" name="wbam_data[bbpress_repeat]" value="1" <?php checked( $repeat ); ?> />
				<?php esc_html_e( 'Repeat', 'wb-ads-rotator-with-split-test' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Save placement options.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Posted data.
	 * @return array
	 */
	public function save_options( $ad_id, $data ) {
		$valid_positions = array(
			'before_forums',
			'after_forums',
			'before_topics',
			'after_topics',
			'before_single_topic',
			'after_single_topic',
			'between_replies',
		);

		$position = isset( $data['bbpress_position'] ) ? sanitize_key( $data['bbpress_position'] ) : 'before_forums';

		return array(
			'bbpress_position' => in_array( $position, $valid_positions, true ) ? $position : 'before_forums',
			'bbpress_after'    => isset( $data['bbpress_after'] ) ? absint( $data['bbpress_after'] ) : 5,
			'bbpress_repeat'   => isset( $data['bbpress_repeat'] ),
		);
	}
}

/**
 * bbPress Widgets class.
 */
class bbPress_Widgets {

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
		register_widget( __NAMESPACE__ . '\bbPress_Forum_Ad_Widget' );
		register_widget( __NAMESPACE__ . '\bbPress_Topic_Ad_Widget' );
	}
}

/**
 * bbPress Forum Ad Widget.
 */
class bbPress_Forum_Ad_Widget extends \WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'wbam_bbpress_forum_ad',
			__( 'WBAM: bbPress Forum Ad', 'wb-ads-rotator-with-split-test' ),
			array(
				'description' => __( 'Display ads on bbPress forum pages.', 'wb-ads-rotator-with-split-test' ),
				'classname'   => 'wbam-bbpress-forum-widget',
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
		// Only show on bbPress forum pages.
		if ( ! function_exists( 'is_bbpress' ) || ! is_bbpress() ) {
			return;
		}

		// Check specific page type.
		$show_on = ! empty( $instance['show_on'] ) ? $instance['show_on'] : 'all';

		if ( 'forum' === $show_on && ! bbp_is_single_forum() ) {
			return;
		}

		if ( 'topic' === $show_on && ! bbp_is_single_topic() ) {
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

		echo '<div class="wbam-bbpress-forum-ad-widget">' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Widget form.
	 *
	 * @param array $instance Widget instance.
	 */
	public function form( $instance ) {
		$title   = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$ad_id   = ! empty( $instance['ad_id'] ) ? $instance['ad_id'] : '';
		$show_on = ! empty( $instance['show_on'] ) ? $instance['show_on'] : 'all';

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
				<?php esc_html_e( 'Title (optional):', 'wb-ads-rotator-with-split-test' ); ?>
			</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'ad_id' ) ); ?>">
				<?php esc_html_e( 'Select Ad:', 'wb-ads-rotator-with-split-test' ); ?>
			</label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'ad_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'ad_id' ) ); ?>">
				<option value=""><?php esc_html_e( '— Select Ad —', 'wb-ads-rotator-with-split-test' ); ?></option>
				<?php foreach ( $ads as $ad ) : ?>
					<option value="<?php echo esc_attr( $ad->ID ); ?>" <?php selected( $ad_id, $ad->ID ); ?>>
						<?php echo esc_html( $ad->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_on' ) ); ?>">
				<?php esc_html_e( 'Show On:', 'wb-ads-rotator-with-split-test' ); ?>
			</label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'show_on' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_on' ) ); ?>">
				<option value="all" <?php selected( $show_on, 'all' ); ?>><?php esc_html_e( 'All bbPress Pages', 'wb-ads-rotator-with-split-test' ); ?></option>
				<option value="forum" <?php selected( $show_on, 'forum' ); ?>><?php esc_html_e( 'Forum Pages Only', 'wb-ads-rotator-with-split-test' ); ?></option>
				<option value="topic" <?php selected( $show_on, 'topic' ); ?>><?php esc_html_e( 'Topic Pages Only', 'wb-ads-rotator-with-split-test' ); ?></option>
			</select>
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
		$instance            = array();
		$instance['title']   = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['ad_id']   = ! empty( $new_instance['ad_id'] ) ? absint( $new_instance['ad_id'] ) : '';
		$instance['show_on'] = ! empty( $new_instance['show_on'] ) ? sanitize_key( $new_instance['show_on'] ) : 'all';

		return $instance;
	}
}

/**
 * bbPress Topic Ad Widget.
 */
class bbPress_Topic_Ad_Widget extends \WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'wbam_bbpress_topic_ad',
			__( 'WBAM: bbPress Topic Sidebar Ad', 'wb-ads-rotator-with-split-test' ),
			array(
				'description' => __( 'Display ads in sidebar on single topic pages.', 'wb-ads-rotator-with-split-test' ),
				'classname'   => 'wbam-bbpress-topic-widget',
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
		// Only show on single topic pages.
		if ( ! function_exists( 'bbp_is_single_topic' ) || ! bbp_is_single_topic() ) {
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

		echo '<div class="wbam-bbpress-topic-ad-widget">' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

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
				<?php esc_html_e( 'Title (optional):', 'wb-ads-rotator-with-split-test' ); ?>
			</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'ad_id' ) ); ?>">
				<?php esc_html_e( 'Select Ad:', 'wb-ads-rotator-with-split-test' ); ?>
			</label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'ad_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'ad_id' ) ); ?>">
				<option value=""><?php esc_html_e( '— Select Ad —', 'wb-ads-rotator-with-split-test' ); ?></option>
				<?php foreach ( $ads as $ad ) : ?>
					<option value="<?php echo esc_attr( $ad->ID ); ?>" <?php selected( $ad_id, $ad->ID ); ?>>
						<?php echo esc_html( $ad->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description">
			<?php esc_html_e( 'This widget only displays on single topic pages.', 'wb-ads-rotator-with-split-test' ); ?>
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
