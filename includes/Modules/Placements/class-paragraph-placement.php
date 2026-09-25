<?php
/**
 * Paragraph Placement
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

		/** This filter is documented in includes/Modules/Placements/class-content-placement.php */
		if ( apply_filters( 'wbam_skip_content_injection', false, $content ) ) {
			return $content;
		}

		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return $content;
		}

		// Paragraph ends, as byte offsets just past each </p>. Paragraphs
		// inside an ad already in the content (a [wbam_ad] shortcode, a
		// before-content ad) are not the post's paragraphs: counting them
		// put other ads inside that ad's markup, and inside its click link.
		$paragraph_ends   = $this->get_paragraph_ends( $content );
		$total_paragraphs = count( $paragraph_ends );

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

		// Splice from the end so earlier offsets stay valid.
		for ( $i = $total_paragraphs; $i >= 1; $i-- ) {
			if ( isset( $insertions[ $i ] ) ) {
				$content = substr_replace( $content, $insertions[ $i ], $paragraph_ends[ $i - 1 ], 0 );
			}
		}

		return $content;
	}

	/**
	 * Offsets just past each </p> that is not inside an ad (.wbam-ad).
	 *
	 * @param string $content Content.
	 * @return int[]
	 */
	private function get_paragraph_ends( $content ) {
		// Byte ranges covered by ad elements: an opening tag whose class
		// list holds the wbam-ad token, through its matching closing tag.
		$ranges   = array();
		$last_end = 0;
		preg_match_all( '/<([a-z][a-z0-9]*)\b[^>]*\bclass\s*=\s*(["\'])(?:[^"\']*\s)?wbam-ad(?:\s[^"\']*)?\2[^>]*>/i', $content, $openers, PREG_OFFSET_CAPTURE | PREG_SET_ORDER );
		foreach ( $openers as $opener ) {
			$start = $opener[0][1];
			if ( $start < $last_end ) {
				continue; // Nested inside an ad already masked.
			}
			$end   = strlen( $content );
			$depth = 0;
			preg_match_all( '/<(\/?)' . $opener[1][0] . '\b[^>]*>/i', $content, $tags, PREG_OFFSET_CAPTURE | PREG_SET_ORDER, $start );
			foreach ( $tags as $tag ) {
				$depth += '' === $tag[1][0] ? 1 : -1;
				if ( 0 === $depth ) {
					$end = $tag[0][1] + strlen( $tag[0][0] );
					break;
				}
			}
			$ranges[] = array( $start, $end );
			$last_end = $end;
		}

		$ends = array();
		preg_match_all( '/<\/p>/i', $content, $matches, PREG_OFFSET_CAPTURE );
		foreach ( $matches[0] as $match ) {
			foreach ( $ranges as $range ) {
				if ( $match[1] >= $range[0] && $match[1] < $range[1] ) {
					continue 2;
				}
			}
			$ends[] = $match[1] + strlen( $match[0] );
		}

		return $ends;
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
