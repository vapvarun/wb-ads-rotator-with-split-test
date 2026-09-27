<?php
/**
 * Widget Placement
 *
 * Placement class + the sidebar widget class live together because they
 * deploy as a single unit and share the same module gate.
 *
 * phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Placement + widget cohesive pair.
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\Placements;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Widget Placement class.
 */
class Widget_Placement implements Placement_Interface {

	public function get_id() {
		return 'widget';
	}

	public function get_name() {
		return __( 'Widget', 'wb-ads-rotator-with-split-test' );
	}

	public function get_description() {
		return __( 'Display ads in sidebar using widget.', 'wb-ads-rotator-with-split-test' );
	}

	public function get_group() {
		return 'WordPress';
	}

	public function is_available() {
		return true;
	}

	public function show_in_selector() {
		return true;
	}

	public function register() {
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
	}

	/**
	 * Register widget.
	 */
	public function register_widget() {
		register_widget( 'WBAM\\Modules\\Placements\\WBAM_Ad_Widget' );
	}
}

/**
 * Ad Widget class.
 */
class WBAM_Ad_Widget extends \WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'wbam_ad_widget',
			__( 'WB Ad Manager', 'wb-ads-rotator-with-split-test' ),
			array(
				'description' => __( 'Display an ad from WB Ad Manager.', 'wb-ads-rotator-with-split-test' ),
				'classname'   => 'wbam-widget',
			)
		);
	}

	/**
	 * Widget output.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ) {
		$ad_id  = isset( $instance['ad_id'] ) ? absint( $instance['ad_id'] ) : 0;
		$engine = Placement_Engine::get_instance();

		// No ad chosen (the default) rotates every ad assigned to the Widget
		// placement, advertisers' paid ones included, like the placement
		// block (card 10344381767). A chosen ad is shown on its own.
		if ( $ad_id ) {
			$ad     = $engine->render_ad( $ad_id, array( 'placement' => 'widget' ) );
			$output = '' === $ad ? '' : '<div class="wbam-placement wbam-placement-widget">' . $ad . '</div>';
		} else {
			$output = $engine->render_placement( 'widget' );
		}

		if ( '' === $output ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget wrapper HTML from theme is trusted.
		echo $args['before_widget'];

		if ( ! empty( $instance['title'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget wrapper HTML from theme is trusted.
			echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title'];
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ad types escape their own output. Code ads require unfiltered_html capability.
		echo $output;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget wrapper HTML from theme is trusted.
		echo $args['after_widget'];
	}

	/**
	 * Widget form.
	 *
	 * @param array $instance Widget instance.
	 * @return void
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$ad_id = isset( $instance['ad_id'] ) ? absint( $instance['ad_id'] ) : 0;

		$ads = get_posts(
			array(
				'post_type'      => 'wbam-ad',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only query; cost acceptable.
				'meta_query'     => array(
					array(
						'key'     => '_wbam_enabled',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title (optional):', 'wb-ads-rotator-with-split-test' ); ?>
			</label>
			<input
				type="text"
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $title ); ?>"
			/>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'ad_id' ) ); ?>">
				<?php esc_html_e( 'Ad:', 'wb-ads-rotator-with-split-test' ); ?>
			</label>
			<select
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'ad_id' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'ad_id' ) ); ?>"
			>
				<option value=""><?php esc_html_e( 'Rotate the ads assigned to the Widget placement', 'wb-ads-rotator-with-split-test' ); ?></option>
				<?php foreach ( $ads as $ad ) : ?>
					<option value="<?php echo esc_attr( $ad->ID ); ?>" <?php selected( $ad_id, $ad->ID ); ?>>
						<?php echo esc_html( $ad->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description"><?php esc_html_e( 'Rotating includes ads advertisers buy for the Widget placement. Pick one ad to show only that ad here.', 'wb-ads-rotator-with-split-test' ); ?></p>
		<?php if ( empty( $ads ) ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: URL to create new ad */
					esc_html__( 'No ads found. %s', 'wb-ads-rotator-with-split-test' ),
					'<a href="' . esc_url( admin_url( 'post-new.php?post_type=wbam-ad' ) ) . '">' . esc_html__( 'Create one', 'wb-ads-rotator-with-split-test' ) . '</a>'
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Widget update.
	 *
	 * @param array $new_instance New instance.
	 * @param array $old_instance Old instance.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = sanitize_text_field( $new_instance['title'] ?? '' );
		$instance['ad_id'] = absint( $new_instance['ad_id'] ?? 0 );

		return $instance;
	}
}
