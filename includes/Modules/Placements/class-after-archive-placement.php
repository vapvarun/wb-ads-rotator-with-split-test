<?php
/**
 * After Archive Placement
 *
 * @package WB_Ad_Manager
 * @since   2.4.0
 */

namespace WBAM\Modules\Placements;

/**
 * After Archive Placement class.
 *
 * Displays ads after the posts loop on archive pages.
 * Uses multiple hooks for maximum theme compatibility:
 * - loop_end (standard WordPress hook)
 * - Theme-specific hooks (buddyx_after_content, genesis_after_loop, etc.)
 */
class After_Archive_Placement implements Placement_Interface {

	/**
	 * Track if ads were displayed.
	 *
	 * @var bool
	 */
	private $displayed = false;

	/**
	 * Get placement ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'after_archive';
	}

	/**
	 * Get placement name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'After Posts Archive', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get placement description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Display ads after the posts loop on archive/blog pages.', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get placement group.
	 *
	 * @return string
	 */
	public function get_group() {
		return 'WordPress';
	}

	/**
	 * Check if placement is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * Check if placement should appear in the admin selector.
	 *
	 * @return bool
	 */
	public function show_in_selector() {
		return true;
	}

	/**
	 * Register placement hooks.
	 *
	 * Multiple hooks for theme compatibility.
	 */
	public function register() {
		// Standard WordPress hook.
		add_action( 'loop_end', array( $this, 'display_ads_loop' ), 15 );

		// Theme-specific hooks for wider compatibility.
		// BuddyX / Flavor theme.
		add_action( 'buddyx_after_content', array( $this, 'display_ads' ), 5 );
		// Genesis theme framework.
		add_action( 'genesis_after_loop', array( $this, 'display_ads' ) );
		// GeneratePress theme.
		add_action( 'generate_after_main_content', array( $this, 'display_ads' ) );
		// Astra theme.
		add_action( 'astra_primary_content_bottom', array( $this, 'display_ads' ) );
		// OceanWP theme.
		add_action( 'ocean_after_content', array( $this, 'display_ads' ) );
		// Theme My Login / General themes.
		add_action( 'theme_after_content', array( $this, 'display_ads' ) );
	}

	/**
	 * Display ads after archive loop (for loop_end hook).
	 *
	 * @param \WP_Query $query Query.
	 */
	public function display_ads_loop( $query ) {
		if ( ! $query->is_main_query() ) {
			return;
		}

		$this->display_ads();
	}

	/**
	 * Display ads after archive content.
	 */
	public function display_ads() {
		// Only on archive pages (category, tag, date, author, etc.), home/blog, and search.
		if ( ! is_archive() && ! is_home() && ! is_search() ) {
			return;
		}

		// Don't show on singular pages.
		if ( is_singular() ) {
			return;
		}

		// Prevent duplicate output.
		if ( $this->displayed ) {
			return;
		}

		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		$this->displayed = true;

		echo '<div class="wbam-placement wbam-placement-after-archive">';
		foreach ( $ads as $ad_id ) {
			echo $engine->render_ad( $ad_id, array( 'placement' => $this->get_id() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
	}
}
