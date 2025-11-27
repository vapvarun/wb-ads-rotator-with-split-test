<?php
/**
 * Content Analyzer
 *
 * @package WB_Ad_Manager
 * @since   1.1.0
 */

namespace WBAM\Modules\Targeting;

use WBAM\Core\Singleton;

/**
 * Content Analyzer class.
 */
class Content_Analyzer {

	use Singleton;

	/**
	 * Analyzed content cache.
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Analyze content.
	 *
	 * @param string $content Content to analyze.
	 * @param int    $post_id Post ID (optional).
	 * @return array
	 */
	public function analyze( $content, $post_id = 0 ) {
		$cache_key = $post_id ? $post_id : md5( $content );

		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		$analysis = array(
			'character_count' => $this->get_character_count( $content ),
			'word_count'      => $this->get_word_count( $content ),
			'paragraph_count' => $this->get_paragraph_count( $content ),
			'heading_count'   => $this->get_heading_count( $content ),
			'image_count'     => $this->get_image_count( $content ),
			'link_count'      => $this->get_link_count( $content ),
			'reading_time'    => $this->get_reading_time( $content ),
			'content_length'  => $this->get_content_length_category( $content ),
		);

		$this->cache[ $cache_key ] = $analysis;

		return $analysis;
	}

	/**
	 * Get character count.
	 *
	 * @param string $content Content.
	 * @return int
	 */
	public function get_character_count( $content ) {
		$text = wp_strip_all_tags( $content );
		return mb_strlen( $text );
	}

	/**
	 * Get word count.
	 *
	 * @param string $content Content.
	 * @return int
	 */
	public function get_word_count( $content ) {
		$text = wp_strip_all_tags( $content );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( empty( $text ) ) {
			return 0;
		}

		return count( explode( ' ', $text ) );
	}

	/**
	 * Get paragraph count.
	 *
	 * @param string $content Content.
	 * @return int
	 */
	public function get_paragraph_count( $content ) {
		preg_match_all( '/<p[^>]*>/i', $content, $matches );
		return count( $matches[0] );
	}

	/**
	 * Get heading count.
	 *
	 * @param string $content Content.
	 * @return array
	 */
	public function get_heading_count( $content ) {
		$counts = array(
			'h1' => 0,
			'h2' => 0,
			'h3' => 0,
			'h4' => 0,
			'h5' => 0,
			'h6' => 0,
		);

		foreach ( array_keys( $counts ) as $tag ) {
			preg_match_all( '/<' . $tag . '[^>]*>/i', $content, $matches );
			$counts[ $tag ] = count( $matches[0] );
		}

		$counts['total'] = array_sum( $counts );

		return $counts;
	}

	/**
	 * Get image count.
	 *
	 * @param string $content Content.
	 * @return int
	 */
	public function get_image_count( $content ) {
		preg_match_all( '/<img[^>]*>/i', $content, $matches );
		return count( $matches[0] );
	}

	/**
	 * Get link count.
	 *
	 * @param string $content Content.
	 * @return int
	 */
	public function get_link_count( $content ) {
		preg_match_all( '/<a[^>]*href/i', $content, $matches );
		return count( $matches[0] );
	}

	/**
	 * Get estimated reading time in minutes.
	 *
	 * @param string $content Content.
	 * @return int
	 */
	public function get_reading_time( $content ) {
		$words_per_minute = 200;
		$word_count       = $this->get_word_count( $content );

		return max( 1, ceil( $word_count / $words_per_minute ) );
	}

	/**
	 * Get content length category.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function get_content_length_category( $content ) {
		$word_count = $this->get_word_count( $content );

		if ( $word_count < 300 ) {
			return 'short';
		} elseif ( $word_count < 1000 ) {
			return 'medium';
		} elseif ( $word_count < 2000 ) {
			return 'long';
		}

		return 'very_long';
	}

	/**
	 * Get suggested ad positions based on content analysis.
	 *
	 * @param string $content Content.
	 * @return array
	 */
	public function get_suggested_positions( $content ) {
		$analysis  = $this->analyze( $content );
		$positions = array();

		// Short content - only after content.
		if ( 'short' === $analysis['content_length'] ) {
			$positions[] = array(
				'type'     => 'after_content',
				'reason'   => __( 'Content is short, ad after content only', 'wb-ad-manager' ),
			);
			return $positions;
		}

		// Medium content - after paragraph 2.
		if ( 'medium' === $analysis['content_length'] ) {
			if ( $analysis['paragraph_count'] >= 3 ) {
				$positions[] = array(
					'type'      => 'after_paragraph',
					'paragraph' => 2,
					'reason'    => __( 'Medium content with enough paragraphs', 'wb-ad-manager' ),
				);
			}
			$positions[] = array(
				'type'   => 'after_content',
				'reason' => __( 'End of medium content', 'wb-ad-manager' ),
			);
			return $positions;
		}

		// Long content - multiple positions.
		if ( 'long' === $analysis['content_length'] || 'very_long' === $analysis['content_length'] ) {
			$paragraphs = $analysis['paragraph_count'];

			// First ad after paragraph 2 or 3.
			if ( $paragraphs >= 4 ) {
				$positions[] = array(
					'type'      => 'after_paragraph',
					'paragraph' => 2,
					'reason'    => __( 'Early in long content', 'wb-ad-manager' ),
				);
			}

			// Middle ad.
			if ( $paragraphs >= 8 ) {
				$middle = (int) floor( $paragraphs / 2 );
				$positions[] = array(
					'type'      => 'after_paragraph',
					'paragraph' => $middle,
					'reason'    => __( 'Middle of long content', 'wb-ad-manager' ),
				);
			}

			// Before last few paragraphs.
			if ( $paragraphs >= 6 ) {
				$positions[] = array(
					'type'      => 'after_paragraph',
					'paragraph' => $paragraphs - 2,
					'reason'    => __( 'Near end of long content', 'wb-ad-manager' ),
				);
			}

			$positions[] = array(
				'type'   => 'after_content',
				'reason' => __( 'End of long content', 'wb-ad-manager' ),
			);
		}

		return $positions;
	}

	/**
	 * Check if content meets minimum requirements for ads.
	 *
	 * @param string $content   Content.
	 * @param array  $min_reqs  Minimum requirements.
	 * @return bool
	 */
	public function meets_requirements( $content, $min_reqs = array() ) {
		$defaults = array(
			'min_words'      => 100,
			'min_paragraphs' => 2,
			'min_characters' => 500,
		);

		$reqs     = wp_parse_args( $min_reqs, $defaults );
		$analysis = $this->analyze( $content );

		if ( $analysis['word_count'] < $reqs['min_words'] ) {
			return false;
		}

		if ( $analysis['paragraph_count'] < $reqs['min_paragraphs'] ) {
			return false;
		}

		if ( $analysis['character_count'] < $reqs['min_characters'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Clear cache.
	 */
	public function clear_cache() {
		$this->cache = array();
	}
}
