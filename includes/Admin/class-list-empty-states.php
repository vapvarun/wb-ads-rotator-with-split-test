<?php
/**
 * List-Table Empty States
 *
 * Replaces the generic WordPress "No items found." message on the
 * plugin's admin list-style screens with a friendly explanatory
 * callout + primary CTA. Gives first-time users a clear next
 * action instead of an empty table they have to puzzle out.
 *
 * @package WB_Ad_Manager
 * @since   2.8.0
 */

namespace WBAM\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List_Empty_States class.
 *
 * One public renderer method per list surface keeps additions cheap:
 * a future Analytics or Reports empty state is a single new method
 * and one new hook, not a refactor. All renderers share the same
 * `.wbam-empty-state` markup contract so admin.css keeps a single
 * source of truth for the visual treatment.
 */
class List_Empty_States {

	/**
	 * Post type the core Ads list lives under.
	 *
	 * @var string
	 */
	const POST_TYPE_AD = 'wbam-ad';

	/**
	 * Register hooks for every supported empty state.
	 */
	public function init() {
		// Core Ads post-type list (edit.php?post_type=wbam-ad).
		add_action( 'admin_notices', array( $this, 'maybe_render_ads_empty_state' ) );
		add_action( 'admin_head-edit.php', array( $this, 'maybe_print_ads_empty_state_style' ) );

		// Links list table shares the markup via its no_items() override
		// but needs admin.css present on its screen to pick up the styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_shared_styles' ) );
	}

	/**
	 * Render the friendly callout on the Ads edit-screen list when
	 * the site has zero ads of any status.
	 *
	 * Hooked to `admin_notices` so it sits above the list-table UI,
	 * where a first-time user's eye naturally lands. Paired with
	 * `maybe_print_ads_empty_state_style()` which hides the otherwise
	 * empty list chrome to keep the screen focused on the CTA.
	 */
	public function maybe_render_ads_empty_state() {
		if ( ! $this->is_empty_ads_list_screen() ) {
			return;
		}

		$this->render_empty_state(
			array(
				'icon'      => 'dashicons-megaphone',
				'title'     => __( 'No ads yet', 'wb-ads-rotator-with-split-test' ),
				'body'      => __( 'Ads are the creatives your visitors will see. Create your first ad to choose where and how it displays.', 'wb-ads-rotator-with-split-test' ),
				'cta_label' => __( 'Create your first ad', 'wb-ads-rotator-with-split-test' ),
				'cta_url'   => admin_url( 'post-new.php?post_type=' . self::POST_TYPE_AD ),
			)
		);
	}

	/**
	 * Hide the native WordPress list-table chrome when the Ads list
	 * is empty so the friendly callout stands alone instead of
	 * floating above a half-empty table skeleton.
	 */
	public function maybe_print_ads_empty_state_style() {
		if ( ! $this->is_empty_ads_list_screen() ) {
			return;
		}

		echo '<style>'
			. '.post-type-' . esc_attr( self::POST_TYPE_AD ) . ' .wrap .subsubsub,'
			. '.post-type-' . esc_attr( self::POST_TYPE_AD ) . ' .wrap .search-box,'
			. '.post-type-' . esc_attr( self::POST_TYPE_AD ) . ' .wrap .tablenav,'
			. '.post-type-' . esc_attr( self::POST_TYPE_AD ) . ' .wrap #posts-filter{display:none;}'
			. '</style>';
	}

	/**
	 * Render the Links list-table empty state. Called by
	 * `Links_List_Table::no_items()` so WP's default
	 * `display_rows_or_placeholder()` row wrapping still applies.
	 */
	public static function render_links_empty_state() {
		$instance = new self();
		$instance->render_empty_state(
			array(
				'icon'      => 'dashicons-admin-links',
				'title'     => __( 'No links yet', 'wb-ads-rotator-with-split-test' ),
				'body'      => __( 'Cloaked links let you track clicks, shorten destinations, and manage affiliate URLs. Create your first link to start tracking.', 'wb-ads-rotator-with-split-test' ),
				'cta_label' => __( 'Create your first link', 'wb-ads-rotator-with-split-test' ),
				'cta_url'   => admin_url( 'admin.php?page=wbam-links&action=add' ),
				'inline'    => true,
			)
		);
	}

	/**
	 * Ensure admin.css is loaded on screens that render a shared
	 * empty state but don't already depend on it (currently the
	 * Links page, which enqueues only links-admin.css).
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_shared_styles( $hook ) {
		$shared_css_hooks = array(
			'toplevel_page_wbam-links',
		);

		if ( ! in_array( $hook, $shared_css_hooks, true ) ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'wbam-admin',
			WBAM_URL . 'assets/css/admin' . $suffix . '.css',
			array( 'dashicons' ),
			WBAM_VERSION
		);
	}

	/**
	 * Determine whether the current request is the Ads edit list
	 * with zero posts of any status.
	 *
	 * @return bool
	 */
	private function is_empty_ads_list_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'edit' !== $screen->base || self::POST_TYPE_AD !== $screen->post_type ) {
			return false;
		}

		$counts = wp_count_posts( self::POST_TYPE_AD );
		if ( ! is_object( $counts ) ) {
			return false;
		}

		// Any status counts as "has an ad" — an existing draft or
		// trashed ad means the user already started the flow, so
		// don't yank the table out from under them.
		$total = 0;
		foreach ( get_object_vars( $counts ) as $count ) {
			$total += (int) $count;
		}

		return 0 === $total;
	}

	/**
	 * Render the empty-state callout markup.
	 *
	 * @param array $args {
	 *     Render arguments.
	 *
	 *     @type string $icon      Dashicon class (e.g. 'dashicons-megaphone').
	 *     @type string $title     Heading copy.
	 *     @type string $body      Explanatory sentence.
	 *     @type string $cta_label Primary button label.
	 *     @type string $cta_url   Primary button destination.
	 *     @type bool   $inline    When true, skip the outer admin-notice
	 *                             wrapper (used inside list-table rows).
	 * }
	 */
	private function render_empty_state( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'icon'      => 'dashicons-megaphone',
				'title'     => '',
				'body'      => '',
				'cta_label' => '',
				'cta_url'   => '',
				'inline'    => false,
			)
		);

		$card  = '<div class="wbam-empty-state">';
		$card .= '<span class="wbam-empty-state__icon dashicons ' . esc_attr( $args['icon'] ) . '" aria-hidden="true"></span>';
		$card .= '<h2 class="wbam-empty-state__title">' . esc_html( $args['title'] ) . '</h2>';
		$card .= '<p class="wbam-empty-state__body">' . esc_html( $args['body'] ) . '</p>';

		if ( ! empty( $args['cta_label'] ) && ! empty( $args['cta_url'] ) ) {
			$card .= '<a class="button button-primary button-hero wbam-empty-state__cta" href="'
				. esc_url( $args['cta_url'] ) . '">'
				. esc_html( $args['cta_label'] ) . '</a>';
		}

		$card .= '</div>';

		if ( $args['inline'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All fields escaped above.
			echo $card;
			return;
		}

		// .notice: core common.js moves notices below the page heading; without
		// it the card printed above the title and the admin notices.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All fields escaped above.
		echo '<div class="notice wbam-empty-state-wrap">' . $card . '</div>';
	}
}
