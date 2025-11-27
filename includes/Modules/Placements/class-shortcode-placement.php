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
		return __( 'Shortcode', 'wb-ad-manager' );
	}

	public function get_description() {
		return __( 'Display ads using [wbam_ad] shortcode.', 'wb-ad-manager' );
	}

	public function get_group() {
		return 'wordpress';
	}

	public function is_available() {
		return true;
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

		$ids    = array_map( 'absint', explode( ',', $atts['ids'] ) );
		$engine = Placement_Engine::get_instance();
		$output = '<div class="wbam-placement wbam-placement-shortcode">';

		foreach ( $ids as $ad_id ) {
			if ( $ad_id > 0 ) {
				$output .= $engine->render_ad(
					$ad_id,
					array(
						'placement' => 'shortcode',
						'class'     => sanitize_html_class( $atts['class'] ),
					)
				);
			}
		}

		$output .= '</div>';
		return $output;
	}
}
