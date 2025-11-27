<?php
/**
 * Sticky/Floating Ad Placement
 *
 * @package WB_Ad_Manager
 * @since   1.1.0
 */

namespace WBAM\Modules\Placements;

/**
 * Sticky Placement class.
 */
class Sticky_Placement implements Placement_Interface {

	/**
	 * Get placement ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'sticky';
	}

	/**
	 * Get placement name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Sticky/Floating', 'wb-ad-manager' );
	}

	/**
	 * Get placement description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Fixed position ad that stays visible while scrolling.', 'wb-ad-manager' );
	}

	/**
	 * Get placement group.
	 *
	 * @return string
	 */
	public function get_group() {
		return 'advanced';
	}

	/**
	 * Check if placement is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * Register placement hooks.
	 */
	public function register() {
		add_action( 'wp_footer', array( $this, 'render_sticky_ads' ), 50 );
	}

	/**
	 * Render sticky ads in footer.
	 */
	public function render_sticky_ads() {
		if ( is_admin() ) {
			return;
		}

		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		foreach ( $ads as $ad_id ) {
			$data     = get_post_meta( $ad_id, '_wbam_ad_data', true );
			$position = isset( $data['sticky_position'] ) ? $data['sticky_position'] : 'bottom-right';

			$output = $engine->render_ad( $ad_id );

			if ( ! empty( $output ) ) {
				printf(
					'<div class="wbam-sticky-ad wbam-sticky-%s" data-ad-id="%d">
						<button class="wbam-sticky-close" aria-label="%s">&times;</button>
						%s
					</div>',
					esc_attr( $position ),
					esc_attr( $ad_id ),
					esc_attr__( 'Close', 'wb-ad-manager' ),
					$output // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped in render_ad.
				);
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
		$position = isset( $data['sticky_position'] ) ? $data['sticky_position'] : 'bottom-right';

		$positions = array(
			'bottom-right' => __( 'Bottom Right', 'wb-ad-manager' ),
			'bottom-left'  => __( 'Bottom Left', 'wb-ad-manager' ),
			'bottom-bar'   => __( 'Bottom Bar (Full Width)', 'wb-ad-manager' ),
			'top-bar'      => __( 'Top Bar (Full Width)', 'wb-ad-manager' ),
		);
		?>
		<div class="wbam-placement-extra">
			<label><?php esc_html_e( 'Position', 'wb-ad-manager' ); ?></label>
			<select name="wbam_data[sticky_position]">
				<?php foreach ( $positions as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $position, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
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
		$valid_positions = array( 'bottom-right', 'bottom-left', 'bottom-bar', 'top-bar' );
		$position        = isset( $data['sticky_position'] ) ? sanitize_key( $data['sticky_position'] ) : 'bottom-right';

		return array(
			'sticky_position' => in_array( $position, $valid_positions, true ) ? $position : 'bottom-right',
		);
	}
}
