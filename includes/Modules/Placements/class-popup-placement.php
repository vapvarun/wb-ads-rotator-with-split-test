<?php
/**
 * Popup/Modal Ad Placement
 *
 * @package WB_Ad_Manager
 * @since   1.1.0
 */

namespace WBAM\Modules\Placements;

/**
 * Popup Placement class.
 */
class Popup_Placement implements Placement_Interface {

	/**
	 * Get placement ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'popup';
	}

	/**
	 * Get placement name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Popup/Modal', 'wb-ad-manager' );
	}

	/**
	 * Get placement description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Display ad in a modal popup with configurable trigger.', 'wb-ad-manager' );
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
		add_action( 'wp_footer', array( $this, 'render_popup_ads' ), 50 );
	}

	/**
	 * Render popup ads in footer.
	 */
	public function render_popup_ads() {
		if ( is_admin() ) {
			return;
		}

		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		foreach ( $ads as $ad_id ) {
			$data    = get_post_meta( $ad_id, '_wbam_ad_data', true );
			$trigger = isset( $data['popup_trigger'] ) ? $data['popup_trigger'] : 'delay';
			$delay   = isset( $data['popup_delay'] ) ? absint( $data['popup_delay'] ) : 5;
			$scroll  = isset( $data['popup_scroll'] ) ? absint( $data['popup_scroll'] ) : 50;

			$output = $engine->render_ad( $ad_id );

			if ( ! empty( $output ) ) {
				printf(
					'<div class="wbam-popup-overlay" data-ad-id="%d" data-trigger="%s" data-delay="%d" data-scroll="%d" style="display:none;">
						<div class="wbam-popup-modal">
							<button class="wbam-popup-close" aria-label="%s">&times;</button>
							<div class="wbam-popup-content">
								%s
							</div>
						</div>
					</div>',
					esc_attr( $ad_id ),
					esc_attr( $trigger ),
					esc_attr( $delay ),
					esc_attr( $scroll ),
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
		$trigger = isset( $data['popup_trigger'] ) ? $data['popup_trigger'] : 'delay';
		$delay   = isset( $data['popup_delay'] ) ? absint( $data['popup_delay'] ) : 5;
		$scroll  = isset( $data['popup_scroll'] ) ? absint( $data['popup_scroll'] ) : 50;

		$triggers = array(
			'delay'  => __( 'Time Delay', 'wb-ad-manager' ),
			'scroll' => __( 'Scroll Percentage', 'wb-ad-manager' ),
			'exit'   => __( 'Exit Intent', 'wb-ad-manager' ),
		);
		?>
		<div class="wbam-placement-extra">
			<label><?php esc_html_e( 'Trigger', 'wb-ad-manager' ); ?></label>
			<select name="wbam_data[popup_trigger]" class="wbam-popup-trigger-select">
				<?php foreach ( $triggers as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $trigger, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="wbam-placement-extra wbam-popup-delay-option" <?php echo 'delay' !== $trigger ? 'style="display:none;"' : ''; ?>>
			<label><?php esc_html_e( 'Delay (seconds)', 'wb-ad-manager' ); ?></label>
			<input type="number" name="wbam_data[popup_delay]" value="<?php echo esc_attr( $delay ); ?>" min="1" max="60" />
		</div>
		<div class="wbam-placement-extra wbam-popup-scroll-option" <?php echo 'scroll' !== $trigger ? 'style="display:none;"' : ''; ?>>
			<label><?php esc_html_e( 'Scroll Percentage', 'wb-ad-manager' ); ?></label>
			<input type="number" name="wbam_data[popup_scroll]" value="<?php echo esc_attr( $scroll ); ?>" min="10" max="100" />%
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
		$valid_triggers = array( 'delay', 'scroll', 'exit' );
		$trigger        = isset( $data['popup_trigger'] ) ? sanitize_key( $data['popup_trigger'] ) : 'delay';

		return array(
			'popup_trigger' => in_array( $trigger, $valid_triggers, true ) ? $trigger : 'delay',
			'popup_delay'   => isset( $data['popup_delay'] ) ? absint( $data['popup_delay'] ) : 5,
			'popup_scroll'  => isset( $data['popup_scroll'] ) ? absint( $data['popup_scroll'] ) : 50,
		);
	}
}
