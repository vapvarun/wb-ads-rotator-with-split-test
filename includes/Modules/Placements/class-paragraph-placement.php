<?php
/**
 * Paragraph Placement
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\Placements;

/**
 * Paragraph Placement class.
 */
class Paragraph_Placement implements Placement_Interface {

	public function get_id() {
		return 'after_paragraph';
	}

	public function get_name() {
		return __( 'After Paragraph', 'wb-ads-rotator-with-split-test' );
	}

	public function get_description() {
		return __( 'Insert ads after a specific paragraph.', 'wb-ads-rotator-with-split-test' );
	}

	public function get_group() {
		return 'wordpress';
	}

	public function is_available() {
		return true;
	}

	public function show_in_selector() {
		return true;
	}

	public function register() {
		add_filter( 'the_content', array( $this, 'inject_ads' ), 25 );
	}

	/**
	 * Inject ads into content.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function inject_ads( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return $content;
		}

		// Split by paragraphs.
		$paragraphs = explode( '</p>', $content );
		$count      = count( $paragraphs );

		foreach ( $ads as $ad_id ) {
			$data            = get_post_meta( $ad_id, '_wbam_ad_data', true );
			$after_paragraph = isset( $data['after_paragraph'] ) ? absint( $data['after_paragraph'] ) : 2;
			$repeat          = isset( $data['paragraph_repeat'] ) && $data['paragraph_repeat'];

			if ( $repeat ) {
				// Insert after every X paragraphs.
				$new_paragraphs = array();
				foreach ( $paragraphs as $i => $p ) {
					$new_paragraphs[] = $p;
					$pos              = $i + 1;
					if ( $pos < $count && $pos >= $after_paragraph && ( $pos % $after_paragraph ) === 0 ) {
						$new_paragraphs[] = $this->wrap_ad( $engine->render_ad( $ad_id, array( 'placement' => $this->get_id() ) ) );
					}
				}
				$paragraphs = $new_paragraphs;
			} else {
				// Insert only once.
				if ( $after_paragraph <= $count ) {
					$ad_html                         = $this->wrap_ad( $engine->render_ad( $ad_id, array( 'placement' => $this->get_id() ) ) );
					$paragraphs[ $after_paragraph - 1 ] .= $ad_html;
				}
			}
		}

		return implode( '</p>', $paragraphs );
	}

	/**
	 * Wrap ad in placement div.
	 *
	 * @param string $ad_html Ad HTML.
	 * @return string
	 */
	private function wrap_ad( $ad_html ) {
		return '<div class="wbam-placement wbam-placement-paragraph">' . $ad_html . '</div>';
	}
}
