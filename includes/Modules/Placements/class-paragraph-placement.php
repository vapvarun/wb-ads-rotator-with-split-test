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
		return 'WordPress';
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
	 * Uses preg_replace_callback to properly insert ads after </p> tags
	 * without corrupting HTML structure.
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

		// Count paragraphs first.
		preg_match_all( '/<\/p>/i', $content, $matches );
		$total_paragraphs = count( $matches[0] );

		if ( 0 === $total_paragraphs ) {
			return $content;
		}

		// Collect insertion points and ads to insert.
		$insertions = array();

		foreach ( $ads as $ad_id ) {
			$data            = get_post_meta( $ad_id, '_wbam_ad_data', true );
			$after_paragraph = isset( $data['after_paragraph'] ) ? absint( $data['after_paragraph'] ) : 2;
			$repeat          = isset( $data['paragraph_repeat'] ) && $data['paragraph_repeat'];

			if ( $repeat ) {
				// Insert after every X paragraphs.
				for ( $pos = $after_paragraph; $pos <= $total_paragraphs; $pos += $after_paragraph ) {
					if ( ! isset( $insertions[ $pos ] ) ) {
						$insertions[ $pos ] = '';
					}
					$insertions[ $pos ] .= $this->wrap_ad( $engine->render_ad( $ad_id, array( 'placement' => $this->get_id() ) ) );
				}
			} elseif ( $after_paragraph <= $total_paragraphs ) {
				// Insert only once.
				if ( ! isset( $insertions[ $after_paragraph ] ) ) {
					$insertions[ $after_paragraph ] = '';
				}
				$insertions[ $after_paragraph ] .= $this->wrap_ad( $engine->render_ad( $ad_id, array( 'placement' => $this->get_id() ) ) );
			}
		}

		if ( empty( $insertions ) ) {
			return $content;
		}

		// Use preg_replace_callback to insert ads at the right positions.
		$paragraph_count = 0;

		$content = preg_replace_callback(
			'/<\/p>/i',
			function ( $matched ) use ( &$paragraph_count, $insertions ) {
				++$paragraph_count;
				$suffix = isset( $insertions[ $paragraph_count ] ) ? $insertions[ $paragraph_count ] : '';
				return $matched[0] . $suffix;
			},
			$content
		);

		return $content;
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
