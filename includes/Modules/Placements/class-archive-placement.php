<?php
/**
 * Archive Placement
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\Placements;

/**
 * Archive Placement class.
 *
 * Displays ads between posts on archive pages.
 */
class Archive_Placement implements Placement_Interface {

	/**
	 * Post counter.
	 *
	 * @var int
	 */
	private $post_count = 0;

	/**
	 * Ads displayed.
	 *
	 * @var array
	 */
	private $displayed = array();

	public function get_id() {
		return 'archive';
	}

	public function get_name() {
		return __( 'Between Posts (Archive)', 'wb-ads-rotator-with-split-test' );
	}

	public function get_description() {
		return __( 'Display ads between posts on archive pages.', 'wb-ads-rotator-with-split-test' );
	}

	public function get_group() {
		return 'wordpress';
	}

	public function is_available() {
		return true;
	}

	public function register() {
		add_action( 'loop_start', array( $this, 'reset_counter' ) );
		add_action( 'the_post', array( $this, 'maybe_inject_ad' ) );
	}

	/**
	 * Reset counter at loop start.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function reset_counter( $query ) {
		if ( ! $query->is_main_query() ) {
			return;
		}

		$this->post_count = 0;
		$this->displayed  = array();
	}

	/**
	 * Maybe inject ad after post.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function maybe_inject_ad( $post ) {
		global $wp_query;

		// Only on archive pages.
		if ( ! is_archive() && ! is_home() && ! is_search() ) {
			return;
		}

		// Only main query.
		if ( ! $wp_query->is_main_query() ) {
			return;
		}

		$this->post_count++;

		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		foreach ( $ads as $ad_id ) {
			if ( in_array( $ad_id, $this->displayed, true ) ) {
				continue;
			}

			$data        = get_post_meta( $ad_id, '_wbam_ad_data', true );
			$after_posts = isset( $data['after_posts'] ) ? absint( $data['after_posts'] ) : 3;
			$repeat      = isset( $data['posts_repeat'] ) && $data['posts_repeat'];

			if ( $repeat ) {
				if ( $this->post_count >= $after_posts && ( $this->post_count % $after_posts ) === 0 ) {
					$this->output_ad( $ad_id, $engine );
				}
			} else {
				if ( $this->post_count === $after_posts ) {
					$this->output_ad( $ad_id, $engine );
					$this->displayed[] = $ad_id;
				}
			}
		}
	}

	/**
	 * Output ad.
	 *
	 * @param int              $ad_id  Ad ID.
	 * @param Placement_Engine $engine Engine.
	 */
	private function output_ad( $ad_id, $engine ) {
		$ad_html = '<div class="wbam-placement wbam-placement-archive">';
		$ad_html .= $engine->render_ad( $ad_id, array( 'placement' => 'archive' ) );
		$ad_html .= '</div>';

		// Add to both content and excerpt to support different themes.
		$output_filter = function ( $content ) use ( $ad_html, &$output_filter ) {
			// Remove filter to prevent multiple outputs.
			remove_filter( 'the_content', $output_filter, 999 );
			remove_filter( 'the_excerpt', $output_filter, 999 );
			return $content . $ad_html;
		};

		add_filter( 'the_content', $output_filter, 999 );
		add_filter( 'the_excerpt', $output_filter, 999 );
	}
}
