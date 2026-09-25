<?php
/**
 * Before Archive Placement
 *
 * @package WB_Ad_Manager
 * @since   2.4.0
 */

namespace WBAM\Modules\Placements;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Before Archive Placement class.
 *
 * Displays ads before the posts loop on archive pages.
 * Uses multiple hooks for maximum theme compatibility:
 * - loop_start (standard WordPress hook)
 * - Theme-specific hooks (buddyx_before_content, genesis_before_loop, etc.)
 */
class Before_Archive_Placement implements Placement_Interface {

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
		return 'before_archive';
	}

	/**
	 * Get placement name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Before Posts Archive', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get placement description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Display ads before the posts loop on archive/blog pages.', 'wb-ads-rotator-with-split-test' );
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
		add_action( 'loop_start', array( $this, 'display_ads_loop' ), 5 );

		// Theme-specific hooks for wider compatibility.
		// BuddyX / Flavor theme.
		add_action( 'buddyx_before_content', array( $this, 'display_ads' ), 20 );
		// Genesis theme framework.
		add_action( 'genesis_before_loop', array( $this, 'display_ads' ) );
		// GeneratePress theme.
		add_action( 'generate_before_main_content', array( $this, 'display_ads' ) );
		// Astra theme.
		add_action( 'astra_primary_content_top', array( $this, 'display_ads' ) );
		// OceanWP theme.
		add_action( 'ocean_before_content', array( $this, 'display_ads' ) );
		// Theme My Login / General themes.
		add_action( 'theme_before_content', array( $this, 'display_ads' ) );

		// Block (FSE) themes: `loop_start`/the theme hooks above never fire on
		// a block-template archive/home/search page - there is no PHP loop,
		// the Query Loop block runs its own `WP_Query`. And even where
		// `loop_start` DOES fire (the Query Loop block's `the_post()` still
		// triggers it), `get_the_block_template_html()` builds the whole
		// page's HTML as a string BEFORE `<!DOCTYPE html>` is echoed (see
		// wp-includes/template-canvas.php) - so a direct echo() here would
		// print above the doctype. Use the block-safe `render_block_core/query`
		// filter instead: it receives the rendered Query Loop block markup and
		// returns a string, which composes into the template HTML in the
		// right place, after the doctype.
		if ( wp_is_block_theme() ) {
			add_filter( 'render_block_core/query', array( $this, 'inject_before_query_block' ), 10, 2 );
		}
	}

	/**
	 * Display ads before archive loop (for loop_start hook).
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
	 * Display ads before archive content.
	 */
	public function display_ads() {
		if ( ! $this->should_display() ) {
			return;
		}

		$markup = $this->get_ads_markup();

		if ( '' === $markup ) {
			return;
		}

		$this->displayed = true;

		echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by Placement_Engine::render_placement(), already escaped there.
	}

	/**
	 * Block-safe equivalent of display_ads() - prepend the placement's ads
	 * to the main Query Loop block's rendered content instead of echoing.
	 *
	 * @param string $block_content Rendered `core/query` block HTML.
	 * @param array  $parsed_block  Parsed block, including attrs.
	 * @return string
	 */
	public function inject_before_query_block( $block_content, $parsed_block ) {
		// Only the main/inherited query (the page's own archive loop) - never
		// a "Related posts" or other secondary Query Loop block on the page.
		if ( empty( $parsed_block['attrs']['query']['inherit'] ) ) {
			return $block_content;
		}

		if ( ! $this->should_display() ) {
			return $block_content;
		}

		$markup = $this->get_ads_markup();

		if ( '' === $markup ) {
			return $block_content;
		}

		$this->displayed = true;

		return $markup . $block_content;
	}

	/**
	 * Whether this placement is eligible to render on the current request.
	 *
	 * @return bool
	 */
	private function should_display() {
		// Only on archive pages (category, tag, date, author, etc.), home/blog, and search.
		if ( ! is_archive() && ! is_home() && ! is_search() ) {
			return false;
		}

		// Don't show on singular pages.
		if ( is_singular() ) {
			return false;
		}

		// Prevent duplicate output.
		return ! $this->displayed;
	}

	/**
	 * Build the placement's ad markup via the single shared renderer.
	 *
	 * @return string
	 */
	private function get_ads_markup() {
		return Placement_Engine::get_instance()->render_placement( $this->get_id() );
	}
}
