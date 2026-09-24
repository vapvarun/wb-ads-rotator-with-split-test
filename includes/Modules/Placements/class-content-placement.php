<?php
/**
 * Content Placement
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
 * Content Placement class.
 */
class Content_Placement implements Placement_Interface {

	public function get_id() {
		return 'content';
	}

	public function get_name() {
		return __( 'Before/After Content', 'wb-ads-rotator-with-split-test' );
	}

	public function get_description() {
		return __( 'Display ads before or after post content.', 'wb-ads-rotator-with-split-test' );
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
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
	}

	public function filter_content( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		/**
		 * Filter whether to skip in-content ad injection (before/after content
		 * and after-paragraph) on the current page. Application pages - an
		 * account dashboard, a posting form, a message thread - render their
		 * UI through the_content, and ads injected there land inside forms.
		 *
		 * @since 3.2.0
		 *
		 * @param bool   $skip    Whether to skip. Default false.
		 * @param string $content The content being filtered.
		 */
		if ( apply_filters( 'wbam_skip_content_injection', false, $content ) ) {
			return $content;
		}

		$engine = Placement_Engine::get_instance();

		// Get ads with 'content' placement (shows both before and after).
		$content_ads = $engine->get_ads_for_placement( 'content' );

		// Before content.
		$before = '';
		if ( ! empty( $content_ads ) ) {
			$before = '<div class="wbam-placement wbam-placement-before-content">';
			foreach ( $content_ads as $ad_id ) {
				$before .= $engine->render_ad( $ad_id, array( 'placement' => 'content' ) );
			}
			$before .= '</div>';
		}

		// After content.
		$after = '';
		if ( ! empty( $content_ads ) ) {
			$after = '<div class="wbam-placement wbam-placement-after-content">';
			foreach ( $content_ads as $ad_id ) {
				$after .= $engine->render_ad( $ad_id, array( 'placement' => 'content' ) );
			}
			$after .= '</div>';
		}

		return $before . $content . $after;
	}
}
