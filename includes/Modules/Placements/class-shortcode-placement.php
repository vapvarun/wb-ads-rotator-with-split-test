<?php
/**
 * Shortcode Placement
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\Placements;

/**
 * Shortcode Placement class.
 */
class Shortcode_Placement implements Placement_Interface {

	public function get_id() {
		return 'shortcode';
	}

	public function get_name() {
		return __( 'Shortcode', 'wb-ads-rotator-with-split-test' );
	}

	public function get_description() {
		return __( 'Display ads using [wbam_ad] shortcode.', 'wb-ads-rotator-with-split-test' );
	}

	public function get_group() {
		return 'WordPress';
	}

	public function is_available() {
		return true;
	}

	/**
	 * Shortcode placement should not appear in the placement selector.
	 * It's used manually via [wbam_ad] shortcode.
	 *
	 * @return bool
	 */
	public function show_in_selector() {
		return false;
	}

	public function register() {
		add_shortcode( 'wbam_ad', array( $this, 'shortcode_single' ) );
		add_shortcode( 'wbam_ads', array( $this, 'shortcode_multiple' ) );
	}

	/**
	 * Single ad shortcode.
	 * Usage: [wbam_ad id="123"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode_single( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'class' => '',
			),
			$atts,
			'wbam_ad'
		);

		$ad_id = absint( $atts['id'] );
		if ( $ad_id <= 0 ) {
			return '';
		}

		$engine = Placement_Engine::get_instance();
		return $engine->render_ad(
			$ad_id,
			array(
				'placement' => 'shortcode',
				'class'     => sanitize_html_class( $atts['class'] ),
			)
		);
	}

	/**
	 * Multiple ads shortcode.
	 * Usage: [wbam_ads ids="123,456,789"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode_multiple( $atts ) {
		$atts = shortcode_atts(
			array(
				'ids'   => '',
				'class' => '',
			),
			$atts,
			'wbam_ads'
		);

		if ( empty( $atts['ids'] ) ) {
			return '';
		}

		$ids        = array_map( 'absint', explode( ',', $atts['ids'] ) );
		$engine     = Placement_Engine::get_instance();
		$ads_output = '';

		foreach ( $ids as $ad_id ) {
			if ( $ad_id > 0 ) {
				$ads_output .= $engine->render_ad(
					$ad_id,
					array(
						'placement' => 'shortcode',
						'class'     => sanitize_html_class( $atts['class'] ),
					)
				);
			}
		}

		// Only output wrapper if we have actual ads to show.
		if ( empty( $ads_output ) ) {
			return '';
		}

		return '<div class="wbam-placement wbam-placement-shortcode">' . $ads_output . '</div>';
	}
}
