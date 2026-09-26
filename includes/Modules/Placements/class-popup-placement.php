<?php
/**
 * Popup/Modal Ad Placement
 *
 * @package WB_Ad_Manager
 * @since   1.1.0
 */

namespace WBAM\Modules\Placements;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
		return __( 'Popup/Modal', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get placement description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Display ad in a modal popup with configurable trigger.', 'wb-ads-rotator-with-split-test' );
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
	 * Check if placement should appear in the admin selector.
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
			$data    = (array) get_post_meta( $ad_id, '_wbam_ad_data', true );
			$options = $this->save_options( $ad_id, $data );

			/**
			 * Days before a visitor sees this popup again; 0 shows it on
			 * every page until they close it. Ads saved while this was a
			 * field start from their stored value.
			 *
			 * @since 3.2.0
			 * @param int $days  Default 1 (once per visitor per day).
			 * @param int $ad_id Ad ID.
			 */
			$repeat_days = min( 365, absint( apply_filters( 'wbam_popup_repeat_days', isset( $data['popup_repeat_days'] ) ? absint( $data['popup_repeat_days'] ) : 1, $ad_id ) ) );

			/**
			 * Whether to hold this popup back on a phone visitor's first
			 * page view. Ads saved while this was a field start from their
			 * stored value.
			 *
			 * @since 3.2.0
			 * @param bool $skip  Default false.
			 * @param int  $ad_id Ad ID.
			 */
			$skip_first_view = (bool) apply_filters( 'wbam_popup_skip_mobile_first_view', isset( $data['popup_mobile_first_view'] ) ? ! $data['popup_mobile_first_view'] : false, $ad_id );

			$output = $engine->render_ad( $ad_id, array( 'placement' => $this->get_id() ) );

			if ( ! empty( $output ) ) {
				printf(
					'<div class="wbam-popup-overlay" data-ad-id="%d" data-trigger="%s" data-delay="%d" data-scroll="%d" data-repeat-days="%d" data-mobile-first-view="%d" hidden>
						<div class="wbam-popup-modal" role="dialog" aria-modal="true" aria-label="%s">
							<button type="button" class="wbam-popup-close" aria-label="%s">&times;</button>
							<div class="wbam-popup-content">
								%s
							</div>
						</div>
					</div>',
					esc_attr( $ad_id ),
					esc_attr( $options['popup_trigger'] ),
					esc_attr( $options['popup_delay'] ),
					esc_attr( $options['popup_scroll'] ),
					esc_attr( $repeat_days ),
					$skip_first_view ? 0 : 1,
					esc_attr__( 'Advertisement', 'wb-ads-rotator-with-split-test' ),
					esc_attr__( 'Close', 'wb-ads-rotator-with-split-test' ),
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
		$options = $this->save_options( $ad_id, (array) $data );

		$triggers = array(
			'delay'  => __( 'Time Delay', 'wb-ads-rotator-with-split-test' ),
			'scroll' => __( 'Scroll Percentage', 'wb-ads-rotator-with-split-test' ),
			'exit'   => __( 'Exit Intent', 'wb-ads-rotator-with-split-test' ),
		);
		?>
		<div class="wbam-placement-extra">
			<label for="wbam_popup_trigger"><?php esc_html_e( 'Trigger', 'wb-ads-rotator-with-split-test' ); ?></label>
			<select id="wbam_popup_trigger" name="wbam_data[popup_trigger]">
				<?php foreach ( $triggers as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $options['popup_trigger'], $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="wbam-placement-extra" data-wbam-show-when="wbam_popup_trigger:delay"<?php echo 'delay' !== $options['popup_trigger'] ? ' hidden' : ''; ?>>
			<label for="wbam_popup_delay"><?php esc_html_e( 'Delay (seconds)', 'wb-ads-rotator-with-split-test' ); ?></label>
			<input type="number" id="wbam_popup_delay" class="small-text" name="wbam_data[popup_delay]" value="<?php echo esc_attr( $options['popup_delay'] ); ?>" min="1" max="60" />
		</div>
		<div class="wbam-placement-extra" data-wbam-show-when="wbam_popup_trigger:scroll"<?php echo 'scroll' !== $options['popup_trigger'] ? ' hidden' : ''; ?>>
			<label for="wbam_popup_scroll"><?php esc_html_e( 'Scroll Percentage', 'wb-ads-rotator-with-split-test' ); ?></label>
			<input type="number" id="wbam_popup_scroll" class="small-text" name="wbam_data[popup_scroll]" value="<?php echo esc_attr( $options['popup_scroll'] ); ?>" min="10" max="100" />%
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

		// Restrained defaults (owner decision 9): delayed by 5 seconds. How
		// often it repeats and the phone first-view rule are filters, see
		// render_popup_ads().
		return array(
			'popup_trigger' => in_array( $trigger, $valid_triggers, true ) ? $trigger : 'delay',
			'popup_delay'   => isset( $data['popup_delay'] ) ? max( 1, min( 60, absint( $data['popup_delay'] ) ) ) : 5,
			'popup_scroll'  => isset( $data['popup_scroll'] ) ? max( 10, min( 100, absint( $data['popup_scroll'] ) ) ) : 50,
		);
	}
}
