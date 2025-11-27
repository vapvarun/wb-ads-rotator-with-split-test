<?php
/**
 * Content Placement
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\Placements;

/**
 * Content Placement class.
 */
class Content_Placement implements Placement_Interface {

	public function get_id() {
		return 'content';
	}

	public function get_name() {
		return __( 'Before/After Content', 'wb-ad-manager' );
	}

	public function get_description() {
		return __( 'Display ads before or after post content.', 'wb-ad-manager' );
	}

	public function get_group() {
		return 'wordpress';
	}

	public function is_available() {
		return true;
	}

	public function register() {
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
	}

	public function filter_content( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$engine = Placement_Engine::get_instance();

		// Before content.
		$before_ads = $engine->get_ads_for_placement( 'before_content' );
		$before     = '';
		if ( ! empty( $before_ads ) ) {
			$before = '<div class="wbam-placement wbam-placement-before-content">';
			foreach ( $before_ads as $ad_id ) {
				$before .= $engine->render_ad( $ad_id, array( 'placement' => 'before_content' ) );
			}
			$before .= '</div>';
		}

		// After content.
		$after_ads = $engine->get_ads_for_placement( 'after_content' );
		$after     = '';
		if ( ! empty( $after_ads ) ) {
			$after = '<div class="wbam-placement wbam-placement-after-content">';
			foreach ( $after_ads as $ad_id ) {
				$after .= $engine->render_ad( $ad_id, array( 'placement' => 'after_content' ) );
			}
			$after .= '</div>';
		}

		return $before . $content . $after;
	}
}
