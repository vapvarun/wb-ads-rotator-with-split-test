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

		// Get ads with 'content' placement (shows both before and after).
		$content_ads = $engine->get_ads_for_placement( 'content' );

		// Before content - includes 'before_content' and 'content' placements.
		$before_ads = $engine->get_ads_for_placement( 'before_content' );
		$before_ads = array_unique( array_merge( $before_ads, $content_ads ) );
		$before     = '';
		if ( ! empty( $before_ads ) ) {
			$before = '<div class="wbam-placement wbam-placement-before-content">';
			foreach ( $before_ads as $ad_id ) {
				$before .= $engine->render_ad( $ad_id, array( 'placement' => 'before_content' ) );
			}
			$before .= '</div>';
		}

		// After content - includes 'after_content' and 'content' placements.
		$after_ads = $engine->get_ads_for_placement( 'after_content' );
		$after_ads = array_unique( array_merge( $after_ads, $content_ads ) );
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
