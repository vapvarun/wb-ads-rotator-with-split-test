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
		if ( $this->is_empty_ads_list_screen() ) {
			$this->render_empty_state(
				array(
					'icon'      => 'megaphone',
					'title'     => __( 'No ads yet', 'wb-ads-rotator-with-split-test' ),
					'body'      => __( 'Ads are the creatives your visitors will see. Create your first ad to choose where and how it displays.', 'wb-ads-rotator-with-split-test' ),
					'cta_label' => __( 'Create your first ad', 'wb-ads-rotator-with-split-test' ),
					'cta_url'   => admin_url( 'post-new.php?post_type=' . self::POST_TYPE_AD ),
				)
			);
			return;
		}

		// A search that matches nothing is a different situation from having
		// no ads at all — the site has ads, this search just didn't find
		// any, so the copy and CTA (adjust the search) must say so instead
		// of "No ads yet".
		if ( $this->is_filtered_empty_ads_list_screen() ) {
			$this->render_empty_state(
				array(
					'icon'  => 'search',
					'title' => __( 'No results match', 'wb-ads-rotator-with-split-test' ),
					'body'  => __( 'Try a different search term, or clear the search to see every ad.', 'wb-ads-rotator-with-split-test' ),
				)
			);
		}
	}

	/**
	 * Determine whether the current request is the Ads edit list with an
	 * active search term ('s') that matches zero ads. Runs a bare, capped
	 * query mirroring the list table's search so the check works whether
	 * or not the site has ads overall — is_empty_ads_list_screen() only
	 * catches the zero-ads-total case.
	 *
	 * @return bool
	 */
	private function is_filtered_empty_ads_list_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'edit' !== $screen->base || self::POST_TYPE_AD !== $screen->post_type ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display routing, no state change.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		if ( '' === $search ) {
			return false;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => self::POST_TYPE_AD,
				's'                      => $search,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return 0 === (int) $query->found_posts;
	}

	/**
	 * Hide the native WordPress list-table chrome when the Ads list
	 * is empty so the friendly callout stands alone instead of
	 * floating above a half-empty table skeleton.
	 */
	public function maybe_print_ads_empty_state_style() {
		if ( $this->is_empty_ads_list_screen() ) {
			echo '<style>'
				. '.post-type-' . esc_attr( self::POST_TYPE_AD ) . ' .wrap .subsubsub,'
				. '.post-type-' . esc_attr( self::POST_TYPE_AD ) . ' .wrap .search-box,'
				. '.post-type-' . esc_attr( self::POST_TYPE_AD ) . ' .wrap .tablenav,'
				. '.post-type-' . esc_attr( self::POST_TYPE_AD ) . ' .wrap #posts-filter{display:none;}'
				. '</style>';
			return;
		}

		// Filtered-empty: keep the search box, status tabs and tablenav so
		// the owner can see/adjust what they searched for — only the
		// native "Not found" row (WP_List_Table's own `.no-items` row)
		// is replaced by the callout above it.
		if ( $this->is_filtered_empty_ads_list_screen() ) {
			echo '<style>.post-type-' . esc_attr( self::POST_TYPE_AD ) . ' .wrap .wp-list-table .no-items{display:none;}</style>';
		}
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
				'icon'      => 'link',
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
			'wbam-ad_page_wbam-links',
		);

		if ( ! in_array( $hook, $shared_css_hooks, true ) ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'wbam-admin',
			wbam_asset_url( 'css/admin.css' ),
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
	 * Thin wrapper around the shared UX::empty_state() component (one
	 * empty-state look for the whole admin, per the design-system plan)
	 * that adds the list-screen-specific chrome: the CTA button and the
	 * `.notice` wrapper core needs to reposition this below the page title.
	 *
	 * @param array $args {
	 *     Render arguments.
	 *
	 *     @type string $icon      Lucide icon name (e.g. 'megaphone').
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
				'icon'      => 'megaphone',
				'title'     => '',
				'body'      => '',
				'cta_label' => '',
				'cta_url'   => '',
				'inline'    => false,
			)
		);

		$actions = '';
		if ( ! empty( $args['cta_label'] ) && ! empty( $args['cta_url'] ) ) {
			$actions = '<a class="wbam-admin-btn wbam-admin-btn--primary" href="'
				. esc_url( $args['cta_url'] ) . '">'
				. esc_html( $args['cta_label'] ) . '</a>';
		}

		$card = UX::empty_state(
			array(
				'icon'    => $args['icon'],
				'title'   => $args['title'],
				'message' => $args['body'],
				'actions' => $actions,
			)
		);

		if ( $args['inline'] ) {
			echo wp_kses_post( $card );
			return;
		}

		// .notice: core common.js moves notices below the page heading; without
		// it the card printed above the title and the admin notices.
		echo '<div class="notice wbam-empty-state-wrap">' . wp_kses_post( $card ) . '</div>';
	}
}
