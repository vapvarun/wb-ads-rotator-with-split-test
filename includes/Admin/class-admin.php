<?php
/**
 * Admin Class
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use WBAM\Core\Singleton;
use WBAM\Modules\Placements\Placement_Engine;

/**
 * Admin class.
 */
class Admin {

	use Singleton;

	/**
	 * Cache for table existence checks.
	 *
	 * @var array
	 */
	private static $table_cache = array();

	/**
	 * Initialize.
	 */
	public function init() {
		// Register + load the shared admin token palette before anything else,
		// on every WB Ad Manager admin screen (priority 5). Pro depends on the
		// `wbam-admin-tokens` handle too, so it must exist early.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_tokens' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_assets' ) );

		// Group the WB Ad Manager submenu into labelled sections. Priority 99
		// so it runs after both Free (default 10) and Pro (20/22) have
		// registered every submenu item. The section-header CSS must load on
		// every admin page, because the sidebar renders everywhere.
		add_action( 'admin_menu', array( $this, 'reorder_submenu_into_sections' ), 99 );
		add_action( 'admin_head', array( $this, 'print_menu_section_css' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
		// Publishing an ad should make it live. The sidebar "Ad Status" radio
		// is separate from WP's Publish box, so an admin who reviews a pending
		// submission and clicks the big Publish button got a published-but-
		// disabled ad that never rendered. Enable on the transition INTO
		// publish, via wp_after_insert_post so it runs AFTER save_meta().
		add_action( 'wp_after_insert_post', array( $this, 'enable_on_publish' ), 10, 4 );
		add_filter( 'manage_wbam-ad_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_wbam-ad_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'handle_disable_ad' ) );
		add_action( 'admin_init', array( $this, 'handle_row_toggle' ) );

		// Bulk-action pipeline on the Ads list table — enable/disable
		// selected ads in one click instead of opening each edit screen.
		add_filter( 'bulk_actions-edit-wbam-ad', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-wbam-ad', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'render_bulk_action_notice' ) );

		// Inline row-action link so a single "Disable" or "Enable"
		// click on a row does not require opening the edit screen.
		add_filter( 'post_row_actions', array( $this, 'add_row_action_toggle' ), 10, 2 );

		// Status filter dropdown (All / Enabled / Disabled) on the
		// Ads list table top toolbar, wired to a meta_query on the
		// main query so the filter persists through pagination.
		add_action( 'restrict_manage_posts', array( $this, 'render_status_filter' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_status_filter' ) );

		// Legacy settings URLs (wbam-pro-settings&tab=X, wbam-tools,
		// wbam-email-captures) no longer resolve to a registered page now
		// that everything lives on the one wbam-settings screen. WordPress
		// fires this action right before the "Sorry, you are not allowed to
		// access this page" wp_die() for any $_GET['page'] with no matching
		// menu entry — redirect there instead of dying.
		add_action( 'admin_page_access_denied', array( $this, 'redirect_legacy_settings_url' ) );
	}

	/**
	 * Redirect a pre-3.2.0 settings URL to its new home on `wbam-settings`.
	 *
	 * @since 3.2.0
	 */
	public function redirect_legacy_settings_url() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect routing, no state change.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'wbam-pro-settings' === $page ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect routing.
			$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
			/**
			 * Filter the old Pro Settings tab slug -> new section slug map.
			 *
			 * Most tabs keep their slug as the section slug; PRO adds the
			 * few that were renamed or merged (analytics -> privacy;
			 * modules/pages/rotation -> advertising).
			 *
			 * @since 3.2.0
			 * @param array<string,string> $map Old tab slug => new section slug.
			 */
			$map     = (array) apply_filters( 'wbam_legacy_settings_tab_map', array() );
			$section = isset( $map[ $tab ] ) ? $map[ $tab ] : $tab;
			wp_safe_redirect( \WBAM\Core\Admin_Links::settings( $section ) );
			exit;
		}

		if ( 'wbam-tools' === $page ) {
			wp_safe_redirect( \WBAM\Core\Admin_Links::settings( 'tools' ) );
			exit;
		}

		if ( 'wbam-email-captures' === $page ) {
			wp_safe_redirect( \WBAM\Core\Admin_Links::settings( 'email-captures' ) );
			exit;
		}
	}

	/**
	 * Register bulk-action options on the Ads list table.
	 *
	 * @param array<string, string> $actions Existing bulk actions keyed by slug.
	 * @return array<string, string>
	 */
	public function register_bulk_actions( $actions ) {
		$actions['wbam_enable']  = __( 'Enable ads', 'wb-ads-rotator-with-split-test' );
		$actions['wbam_disable'] = __( 'Disable ads', 'wb-ads-rotator-with-split-test' );
		return $actions;
	}

	/**
	 * Execute one of our bulk actions. WP handles the nonce and capability
	 * check before this runs, so we only need to apply the meta change.
	 *
	 * @param string $redirect   URL to redirect to after handling.
	 * @param string $action     Action slug chosen from the dropdown.
	 * @param int[]  $post_ids   Selected post IDs.
	 * @return string Redirect URL with a counter query arg appended.
	 */
	public function handle_bulk_actions( $redirect, $action, $post_ids ) {
		if ( 'wbam_enable' !== $action && 'wbam_disable' !== $action ) {
			return $redirect;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $redirect;
		}

		$value = 'wbam_enable' === $action ? '1' : '0';
		$count = 0;
		foreach ( (array) $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			$post = get_post( $post_id );
			if ( ! $post || 'wbam-ad' !== $post->post_type ) {
				continue;
			}
			update_post_meta( $post_id, '_wbam_enabled', $value );

			// Placement_Engine caches per-placement ad lists for 5 minutes;
			// fire the same hook the save_post path fires so the cache
			// clears and disabled ads stop serving on the next request.
			do_action( 'wbam_save_ad_meta', $post_id );

			++$count;
		}

		return add_query_arg(
			array(
				'wbam_bulk_action' => $action,
				'wbam_bulk_count'  => $count,
			),
			$redirect
		);
	}

	/**
	 * Render the result notice after a bulk action. Hooks admin_notices
	 * instead of admin_init because the notice needs the query args the
	 * bulk handler put on the redirect URL.
	 *
	 * @return void
	 */
	public function render_bulk_action_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display of bulk-action result; nonce is already verified in handle_bulk_actions() before the redirect.
		if ( empty( $_GET['wbam_bulk_action'] ) || empty( $_GET['wbam_bulk_count'] ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'edit-wbam-ad' !== $screen->id ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET['wbam_bulk_action'] ) );
		$count  = absint( wp_unslash( $_GET['wbam_bulk_count'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( 0 === $count ) {
			return;
		}

		$message = 'wbam_enable' === $action
			/* translators: %d: number of ads */
			? sprintf( _n( '%d ad enabled.', '%d ads enabled.', $count, 'wb-ads-rotator-with-split-test' ), $count )
			/* translators: %d: number of ads */
			: sprintf( _n( '%d ad disabled.', '%d ads disabled.', $count, 'wb-ads-rotator-with-split-test' ), $count );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Add an inline "Enable"/"Disable" toggle to each ad row so admins
	 * can flip a single ad without opening the edit screen.
	 *
	 * @param array<string, string> $actions Existing row actions.
	 * @param \WP_Post              $post    Current row post.
	 * @return array<string, string>
	 */
	public function add_row_action_toggle( $actions, $post ) {
		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return $actions;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$enabled = (string) get_post_meta( $post->ID, '_wbam_enabled', true );
		$is_on   = '1' === $enabled;
		$next    = $is_on ? '0' : '1';
		$url     = wp_nonce_url(
			add_query_arg(
				array(
					'wbam_toggle_enabled' => $next,
					'post'                => $post->ID,
				),
				admin_url( 'edit.php?post_type=wbam-ad' )
			),
			'wbam_toggle_enabled_' . $post->ID
		);
		$label   = $is_on
			? __( 'Disable', 'wb-ads-rotator-with-split-test' )
			: __( 'Enable', 'wb-ads-rotator-with-split-test' );
		$class   = $is_on ? 'wbam-row-action-disable' : 'wbam-row-action-enable';

		$actions['wbam_toggle'] = sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( $url ),
			esc_attr( $class ),
			esc_html( $label )
		);
		return $actions;
	}

	/**
	 * Render the Status filter dropdown in the list table toolbar.
	 *
	 * @param string $post_type Current admin screen post type.
	 * @return void
	 */
	public function render_status_filter( $post_type ) {
		if ( 'wbam-ad' !== $post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter. Nonce-less GET is the standard WP pattern for admin list filters (see core's manage_edit-{post_type}_columns handlers).
		$current = isset( $_GET['wbam_enabled_filter'] ) ? sanitize_key( wp_unslash( $_GET['wbam_enabled_filter'] ) ) : '';
		?>
		<label for="wbam_enabled_filter" class="screen-reader-text">
			<?php esc_html_e( 'Filter by enabled status', 'wb-ads-rotator-with-split-test' ); ?>
		</label>
		<select name="wbam_enabled_filter" id="wbam_enabled_filter">
			<option value=""><?php esc_html_e( 'All statuses', 'wb-ads-rotator-with-split-test' ); ?></option>
			<option value="enabled" <?php selected( $current, 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'wb-ads-rotator-with-split-test' ); ?></option>
			<option value="disabled" <?php selected( $current, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'wb-ads-rotator-with-split-test' ); ?></option>
		</select>
		<?php

		$this->render_ad_tag_filter();
	}

	/**
	 * Tag filter on the ads list.
	 *
	 * This is what makes bulk actions work on a group. "Disable everything for
	 * this sponsor" needs no new bulk action - the existing Enable/Disable act
	 * on whatever is selected, and narrowing the list is what makes selecting a
	 * group possible at all.
	 *
	 * Note the limit: select-all covers the current page, so an inventory larger
	 * than the page size needs either several rounds or a higher per-page value
	 * from Screen Options. That is WordPress's behaviour on every post list, not
	 * something introduced here.
	 *
	 * Submitting under the taxonomy's own name with the term slug is the pattern
	 * WP_Query understands natively, so no pre_get_posts handling is needed -
	 * unlike the status filter above, which is meta-based and has to be applied
	 * by hand.
	 *
	 * @since 3.1.0
	 * @return void
	 */
	private function render_ad_tag_filter() {
		if ( ! taxonomy_exists( 'wbam_ad_tag' ) ) {
			return;
		}

		// Nothing to filter by yet - an empty dropdown is worse than none.
		$has_terms = get_terms(
			array(
				'taxonomy'   => 'wbam_ad_tag',
				'hide_empty' => false,
				'number'     => 1,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $has_terms ) || empty( $has_terms ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter; nonce-less GET is the standard WP admin pattern.
		$selected = isset( $_GET['wbam_ad_tag'] ) ? sanitize_text_field( wp_unslash( $_GET['wbam_ad_tag'] ) ) : '';

		wp_dropdown_categories(
			array(
				'taxonomy'        => 'wbam_ad_tag',
				'name'            => 'wbam_ad_tag',
				'id'              => 'wbam_ad_tag',
				'value_field'     => 'slug',
				'selected'        => $selected,
				'show_option_all' => __( 'All ad tags', 'wb-ads-rotator-with-split-test' ),
				// A tag created a moment ago carries nothing yet; hiding it
				// would read as the tag having failed to save.
				'hide_empty'      => false,
				'hierarchical'    => false,
				'show_count'      => true,
				'orderby'         => 'name',
				// Core's Walker_CategoryDropdown hardcodes two &nbsp;
				// before the count; this walker renders a single space.
				'walker'          => new Single_Space_Count_Dropdown_Walker(),
			)
		);
	}

	/**
	 * Apply the Status filter to the list table's main query.
	 *
	 * @param \WP_Query $query Current query object.
	 * @return void
	 */
	public function apply_status_filter( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( 'wbam-ad' !== $query->get( 'post_type' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter. Standard WP admin GET pattern (no nonce on pre_get_posts filters).
		if ( empty( $_GET['wbam_enabled_filter'] ) ) {
			return;
		}

		$mode = sanitize_key( wp_unslash( $_GET['wbam_enabled_filter'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( 'enabled' === $mode ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'     => '_wbam_enabled',
						'value'   => '1',
						'compare' => '=',
					),
				)
			);
			return;
		}
		if ( 'disabled' === $mode ) {
			// "Disabled" = meta exists and != '1', OR meta is missing entirely.
			$query->set(
				'meta_query',
				array(
					'relation' => 'OR',
					array(
						'key'     => '_wbam_enabled',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_wbam_enabled',
						'value'   => '1',
						'compare' => '!=',
					),
				)
			);
		}
	}

	/**
	 * Handle disable ad from comparison view.
	 */
	public function handle_disable_ad() {
		if ( ! isset( $_GET['wbam_disable'] ) || '1' !== $_GET['wbam_disable'] ) {
			return;
		}

		if ( ! isset( $_GET['post'] ) ) {
			return;
		}

		$post_id = absint( $_GET['post'] );

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wbam_disable_ad_' . $post_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wb-ads-rotator-with-split-test' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return;
		}

		// Disable the ad.
		update_post_meta( $post_id, '_wbam_enabled', '0' );

		// Invalidate the placement cache so the ad stops serving now,
		// not five minutes from now.
		do_action( 'wbam_save_ad_meta', $post_id );

		// Add admin notice.
		add_action(
			'admin_notices',
			function () use ( $post ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					sprintf(
						/* translators: %s: Ad title */
						esc_html__( 'Ad "%s" has been disabled.', 'wb-ads-rotator-with-split-test' ),
						esc_html( $post->post_title )
					)
				);
			}
		);
	}

	/**
	 * Flip the enabled/disabled flag from the inline row action link.
	 * Back-end for the "Enable"/"Disable" entry added to each ad row.
	 *
	 * @return void
	 */
	public function handle_row_toggle() {
		if ( ! isset( $_GET['wbam_toggle_enabled'], $_GET['post'] ) ) {
			return;
		}

		$post_id = absint( $_GET['post'] );
		if ( ! $post_id ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wbam_toggle_enabled_' . $post_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wb-ads-rotator-with-split-test' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return;
		}

		$next = '1' === sanitize_text_field( wp_unslash( $_GET['wbam_toggle_enabled'] ) ) ? '1' : '0';
		update_post_meta( $post_id, '_wbam_enabled', $next );

		// Invalidate the placement cache; otherwise the frontend keeps
		// serving the ad for up to 5 minutes after the toggle.
		do_action( 'wbam_save_ad_meta', $post_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'        => 'wbam-ad',
					'wbam_bulk_action' => '1' === $next ? 'wbam_enable' : 'wbam_disable',
					'wbam_bulk_count'  => 1,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Whether the current admin screen belongs to WB Ad Manager.
	 *
	 * True for the ad and classified CPT screens, and for any page whose hook
	 * or slug carries the `wbam` prefix (settings, links, advertisers, Pro
	 * pages, etc.). This is the single gate that decides where the shared
	 * token palette loads.
	 *
	 * @since 2.9.2
	 * @param string $hook Current admin page hook suffix.
	 * @return bool
	 */
	public function is_wbam_admin_screen( $hook ) {
		if ( false !== strpos( (string) $hook, 'wbam' ) ) {
			return true;
		}

		$screen = get_current_screen();
		if ( $screen && in_array( $screen->post_type, array( 'wbam-ad', 'wbam-classified' ), true ) ) {
			return true;
		}

		/**
		 * Filter whether the shared admin token palette should load here.
		 *
		 * Lets an extension opt a custom screen into the WB Ad Manager admin
		 * styling foundation.
		 *
		 * @since 2.9.2
		 * @param bool   $is_ours Whether this is a WB Ad Manager admin screen.
		 * @param string $hook    Current admin page hook suffix.
		 */
		return (bool) apply_filters( 'wbam_is_admin_screen', false, $hook );
	}

	/**
	 * Register and enqueue the shared admin token palette.
	 *
	 * The `wbam-admin-tokens` handle is the single source of the --wbam-*
	 * admin palette. Every admin stylesheet in both plugins depends on it and
	 * inherits the tokens rather than redeclaring them. Registered even when
	 * not enqueued here, so a dependent can pull it in on a screen this gate
	 * does not match.
	 *
	 * @since 2.9.2
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_tokens( $hook ) {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		if ( ! wp_style_is( 'wbam-admin-tokens', 'registered' ) ) {
			wp_register_style(
				'wbam-admin-tokens',
				WBAM_URL . 'assets/css/admin-tokens' . $suffix . '.css',
				array(),
				WBAM_VERSION
			);
		}

		// The shared admin family (page header, cards, tables, buttons,
		// badges). Depends on the token palette; both plugins' screens use it.
		if ( ! wp_style_is( 'wbam-admin-family', 'registered' ) ) {
			wp_register_style(
				'wbam-admin-family',
				WBAM_URL . 'assets/css/admin-family' . $suffix . '.css',
				array( 'wbam-admin-tokens' ),
				WBAM_VERSION
			);
		}

		if ( $this->is_wbam_admin_screen( $hook ) ) {
			wp_enqueue_style( 'wbam-admin-family' );

			// Shared toast/confirm toolkit (handle registered by
			// `Plugin::register_shared_assets()` on init@1, before this
			// runs). Loaded on every WB Ad Manager admin screen because the
			// delegated `[data-wbam-confirm]` listener lives inside it —
			// any admin screen that renders a confirm link/button needs it.
			wp_enqueue_style( 'wbam-toast' );
			wp_enqueue_script( 'wbam-toast' );
		}
	}

	/**
	 * Group the WB Ad Manager submenu into labelled sections.
	 *
	 * A purely presentational reorder of items already registered under the
	 * ad CPT menu — no URL changes, no capability changes, nothing added or
	 * removed. Free owns the default section map and order; Pro (and any
	 * extension) slots its own pages into a section, or defines new sections,
	 * through the two filters `regroup_submenu()` applies. An item whose slug
	 * is not mapped lands in the header-less `other` bucket and is never
	 * dropped.
	 *
	 * @since 2.9.2
	 */
	public function reorder_submenu_into_sections() {
		$this->regroup_submenu(
			'edit.php?post_type=wbam-ad',
			array(
				// Ads.
				'edit.php?post_type=wbam-ad'     => 'ads',
				'post-new.php?post_type=wbam-ad' => 'ads',
				// Ad Tags organises ads, so it sits with them. Unmapped it falls
				// into the trailing bucket and renders under Settings, which
				// reads as a configuration screen rather than an inventory one.
				'edit-tags.php?taxonomy=wbam_ad_tag&amp;post_type=wbam-ad' => 'ads',
				'edit-tags.php?taxonomy=wbam_ad_tag&post_type=wbam-ad' => 'ads',
				// Pro's folder browser organises ads too, so it sits with them.
				'wbam-folders'                   => 'ads',
				// Delivery.
				'wbam-inventory'                 => 'delivery',
				'wbam-ab-testing'                => 'delivery',
				// Campaigns (advertiser intake + scheduling).
				'wbam-campaigns'                 => 'campaigns',
				'wbam-submissions'               => 'campaigns',
				// Reports.
				'wbam-analytics'                 => 'reports',
				'wbam-revenue'                   => 'reports',
				'wbam-audit-log'                 => 'reports',
				// Settings. wbam-pro-settings and wbam-tools no longer exist
				// as separate submenu items as of 3.2.0 - both live inside
				// the one wbam-settings screen (see WBAM\Admin\Settings) -
				// so they are gone from this map rather than left as dead
				// entries that never match a registered page.
				'wbam-settings'                  => 'settings',
				'wbam-help'                      => 'settings',
			),
			array(
				// First group is header-less: its items sit directly under the
				// menu title and are self-evidently the ads, so a header there
				// only adds height. The rest are labelled.
				'ads'       => '',
				'delivery'  => __( 'Delivery', 'wb-ads-rotator-with-split-test' ),
				'campaigns' => __( 'Campaigns', 'wb-ads-rotator-with-split-test' ),
				'reports'   => __( 'Reports', 'wb-ads-rotator-with-split-test' ),
				'settings'  => __( 'Settings', 'wb-ads-rotator-with-split-test' ),
				'other'     => '',
			)
		);
	}

	/**
	 * Rewrite a top-level menu's submenu into labelled sections.
	 *
	 * Ported from Learnomy's proven implementation so both products share one
	 * approach. Fully filterable: an extension can slot any page into any
	 * section (`wbam_admin_menu_section_map`) or define new sections and their
	 * order (`wbam_admin_menu_sections`), for any of our menus, keyed by
	 * `$parent`. Unmapped slugs fall into the header-less `other` bucket, so
	 * nothing is ever dropped.
	 *
	 * @since 2.9.2
	 * @param string               $parent_slug Top-level menu slug.
	 * @param array<string,string> $groups      Default slug -> section-key map.
	 * @param array<string,string> $group_order Default ordered section-key -> label.
	 */
	private function regroup_submenu( $parent_slug, $groups, $group_order ) {
		global $submenu;

		if ( empty( $submenu[ $parent_slug ] ) ) {
			return;
		}

		/**
		 * Filter the slug -> section-key map for a menu. $parent_slug says which menu.
		 *
		 * @since 2.9.2
		 * @param array<string,string> $groups Slug -> section-key.
		 * @param string               $parent_slug Top-level menu slug.
		 */
		$groups = (array) apply_filters( 'wbam_admin_menu_section_map', $groups, $parent_slug );

		/**
		 * Filter the ordered section-key -> label list for a menu. Controls both
		 * section order and labels (an empty label renders no header row).
		 *
		 * @since 2.9.2
		 * @param array<string,string> $group_order Section-key -> label, in order.
		 * @param string               $parent_slug      Top-level menu slug.
		 */
		$group_order = (array) apply_filters( 'wbam_admin_menu_sections', $group_order, $parent_slug );

		// Always keep a header-less catch-all so an unmapped slug is never
		// dropped even if a filter removed 'other'.
		if ( ! array_key_exists( 'other', $group_order ) ) {
			$group_order['other'] = '';
		}

		$buckets = array_fill_keys( array_keys( $group_order ), array() );

		foreach ( $submenu[ $parent_slug ] as $item ) {
			// $item is [ menu_title, capability, menu_slug, page_title (optional) ].
			$slug  = $item[2] ?? '';
			$group = $groups[ $slug ] ?? 'other';
			if ( ! isset( $buckets[ $group ] ) ) {
				$group = 'other';
			}
			$buckets[ $group ][] = $item;
		}

		// Within a section, order items by their position in the map rather
		// than by whatever order the plugins happened to register them in —
		// otherwise a late-registered page lands between two related ones
		// (Tools appearing between Settings and Ad Display, say). Slugs not in
		// the map keep their relative order at the end of their bucket.
		$order_index = array_flip( array_keys( $groups ) );
		foreach ( $buckets as $bucket_key => $bucket_items ) {
			usort(
				$bucket_items,
				static function ( $a, $b ) use ( $order_index ) {
					$pos_a = isset( $order_index[ $a[2] ?? '' ] ) ? $order_index[ $a[2] ?? '' ] : PHP_INT_MAX;
					$pos_b = isset( $order_index[ $b[2] ?? '' ] ) ? $order_index[ $b[2] ?? '' ] : PHP_INT_MAX;
					return $pos_a <=> $pos_b;
				}
			);
			$buckets[ $bucket_key ] = $bucket_items;
		}

		// Rebuild in section order, emitting a header before each labelled,
		// non-empty section. Empty-label sections render flush.
		$rebuilt = array();
		foreach ( $group_order as $group => $label ) {
			if ( empty( $buckets[ $group ] ) ) {
				continue;
			}
			if ( '' !== (string) $label ) {
				// Anchor namespaced by parent so headers in different menus
				// never collide.
				$rebuilt[] = $this->build_section_header( sanitize_key( $parent_slug ) . '-' . $group, (string) $label );
			}
			$rebuilt = array_merge( $rebuilt, $buckets[ $group ] );
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional presentational reorder of our own menu's submenu; items are unchanged, only regrouped.
		$submenu[ $parent_slug ] = $rebuilt;
	}

	/**
	 * Build a non-clickable section header row for a submenu.
	 *
	 * The href is a no-op anchor (`#wbam-section-{slug}`); CSS from
	 * `print_menu_section_css()` styles it as a small-caps label and disables
	 * hover. Capability matches the pages it groups (`manage_options`) so the
	 * header shows exactly when its items do.
	 *
	 * @since 2.9.2
	 * @param string $slug  Section identifier.
	 * @param string $label Translated section label.
	 * @return array<int,string> WordPress submenu row tuple.
	 */
	private function build_section_header( $slug, $label ) {
		return array(
			'<span class="wbam-menu-section">' . esc_html( $label ) . '</span>',
			'manage_options',
			'#wbam-section-' . $slug,
			esc_html( $label ),
		);
	}

	/**
	 * Print the CSS that styles the submenu section-header rows.
	 *
	 * Emitted on every admin page because the sidebar renders everywhere. The
	 * rows are marked up as links to a `#wbam-section-*` anchor, so they are
	 * neutralised here into non-interactive small-caps labels.
	 *
	 * @since 2.9.2
	 */
	public function print_menu_section_css() {
		?>
		<style id="wbam-menu-sections">
			/* Header rows are tight labels, not full item-height rows. The
				WordPress submenu link supplies its own vertical padding; zero it
				for headers and let the span provide a small top gap so five
				sections do not inflate the menu height. */
			#adminmenu .wp-submenu li a[href^="#wbam-section-"] {
				padding-top: 0;
				padding-bottom: 0;
				min-height: 0;
			}
			#adminmenu .wp-submenu li a .wbam-menu-section {
				display: block;
				padding: 8px 0 1px;
				color: rgba(240, 246, 252, 0.45);
				font-size: 10px;
				font-weight: 600;
				letter-spacing: 0.06em;
				line-height: 1.2;
				text-transform: uppercase;
				pointer-events: none;
			}
			/* First labelled section needs no big top gap - it follows the
				header-less Ads group directly. */
			#adminmenu .wp-submenu li a[href^="#wbam-section-"]:hover,
			#adminmenu .wp-submenu li a[href^="#wbam-section-"]:focus,
			#adminmenu .wp-submenu li.current a[href^="#wbam-section-"],
			#adminmenu .wp-submenu li a[href^="#wbam-section-"].current {
				cursor: default;
				background: transparent !important;
				color: rgba(240, 246, 252, 0.45) !important;
			}
		</style>
		<?php
	}

	/**
	 * Enqueue assets.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || 'wbam-ad' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'wbam-admin',
			WBAM_URL . 'assets/css/admin' . $suffix . '.css',
			array( 'wbam-admin-tokens' ),
			WBAM_VERSION
		);

		wp_enqueue_script(
			'wbam-admin',
			WBAM_URL . 'assets/js/admin' . $suffix . '.js',
			array( 'jquery', 'media-editor', 'wbam-toast' ),
			WBAM_VERSION,
			true
		);

		wp_localize_script(
			'wbam-admin',
			'wbamAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'wbam-admin' ),
				'restUrl'   => esc_url_raw( rest_url() ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'      => array(
					'selectImage' => __( 'Select Image', 'wb-ads-rotator-with-split-test' ),
					'useImage'    => __( 'Use This Image', 'wb-ads-rotator-with-split-test' ),
					'noMatches'   => __( 'No matches.', 'wb-ads-rotator-with-split-test' ),
					'selected'    => __( 'selected', 'wb-ads-rotator-with-split-test' ),
				),
			)
		);

		// Expose the placement registry + format dimensions to the
		// ad-edit sizing section so the "Will render in:" live summary
		// can resolve matches client-side without an AJAX round-trip.
		$format_data = self::collect_format_js_data();
		if ( ! empty( $format_data ) ) {
			wp_localize_script( 'wbam-admin', 'wbamFormatData', $format_data );
		}

		// Code editor.
		if ( 'post' === $hook || 'post-new' === $hook ) {
			$settings = wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
			if ( false !== $settings ) {
				wp_localize_script( 'wbam-admin', 'wbamCodeEditor', $settings );
			}
		}

		wp_enqueue_script(
			'wbam-placement-settings',
			WBAM_URL . 'assets/js/admin-placement-settings.js',
			array( 'wbam-toast' ),
			WBAM_VERSION,
			true
		);
		wp_localize_script(
			'wbam-placement-settings',
			'wbamPlacementSettings',
			array(
				/* translators: %d is replaced client-side with the active ad count for the slot being closed. */
				'confirmDisable' => __(
					'%d active ad(s) will stop rendering in this slot. Continue?',
					'wb-ads-rotator-with-split-test'
				),
			)
		);
	}

	/**
	 * Enqueue the Settings-screen-only sub-nav script (Ad Display pills).
	 *
	 * Separate from enqueue_assets() because that method gates on
	 * `$screen->post_type === 'wbam-ad'`, which the settings screen may or
	 * may not carry depending on how WP resolved the hybrid `edit.php?post_type=`
	 * submenu hook — checking `$_GET['page']` directly here is unambiguous.
	 *
	 * @since 3.2.0
	 */
	public function enqueue_settings_assets() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen gate, no state change.
		if ( ! isset( $_GET['page'] ) || 'wbam-settings' !== $_GET['page'] ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script(
			'wbam-admin-settings-nav',
			WBAM_URL . 'assets/js/admin-settings-nav' . $suffix . '.js',
			array(),
			WBAM_VERSION,
			true
		);
	}

	/**
	 * Enable an ad when it is published.
	 *
	 * The plugin's "Ad Status" (`_wbam_enabled`) is a control separate from
	 * WordPress's post status. A submitted ad sits at `_wbam_enabled = 0`, so
	 * an admin who reviews it and clicks Publish ends up with a published ad
	 * that never renders — every visual cue says live, the ad is off. Clicking
	 * Publish is an unambiguous "make this live" action, so the transition into
	 * publish enables the ad. Runs on `wp_after_insert_post` (after
	 * `save_meta()`), using `$post_before` to fire only on the transition — a
	 * later save that sets the radio to Disabled on an already-published ad is
	 * left alone.
	 *
	 * @since 2.9.2
	 * @param int           $post_id     Post ID.
	 * @param \WP_Post      $post        The saved post.
	 * @param bool          $update      Whether this is an update.
	 * @param \WP_Post|null $post_before The post before the save (null on create).
	 */
	public function enable_on_publish( $post_id, $post, $update, $post_before ) {
		if ( ! $post instanceof \WP_Post || 'wbam-ad' !== $post->post_type ) {
			return;
		}

		$was_published = $post_before instanceof \WP_Post && 'publish' === $post_before->post_status;
		if ( 'publish' !== $post->post_status || $was_published ) {
			return;
		}

		if ( '1' === (string) get_post_meta( $post_id, '_wbam_enabled', true ) ) {
			return;
		}

		update_post_meta( $post_id, '_wbam_enabled', '1' );

		// Clear the placement cache so the newly-enabled ad serves immediately.
		do_action( 'wbam_save_ad_meta', $post_id );
	}

	/**
	 * Add metaboxes.
	 */
	public function add_metaboxes() {
		add_meta_box(
			'wbam-ad-settings',
			__( 'Ad Settings', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_settings_metabox' ),
			'wbam-ad',
			'normal',
			'high'
		);

		// Preview metabox is only useful once the ad has been saved at
		// least once — before that there is no post meta to render from.
		global $post;
		if ( $post && $post->ID && 'auto-draft' !== $post->post_status ) {
			add_meta_box(
				'wbam-ad-preview',
				__( 'Preview', 'wb-ads-rotator-with-split-test' ),
				array( $this, 'render_preview_metabox' ),
				'wbam-ad',
				'normal',
				'high'
			);
		}

		add_meta_box(
			'wbam-ad-placements',
			__( 'Placements', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_placements_metabox' ),
			'wbam-ad',
			'normal',
			'high'
		);

		add_meta_box(
			'wbam-ad-status',
			__( 'Ad Status', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_status_metabox' ),
			'wbam-ad',
			'side',
			'high'
		);

		// Only show comparison metabox for existing ads with placements.
		global $post;
		if ( $post && $post->ID ) {
			$placements = get_post_meta( $post->ID, '_wbam_placements', true );
			if ( ! empty( $placements ) ) {
				add_meta_box(
					'wbam-ad-comparison',
					__( 'Ad Performance Comparison', 'wb-ads-rotator-with-split-test' ),
					array( $this, 'render_comparison_metabox' ),
					'wbam-ad',
					'normal',
					'default'
				);
			}
		}
	}

	/**
	 * Render settings metabox.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_settings_metabox( $post ) {
		wp_nonce_field( 'wbam_save_ad', 'wbam_nonce' );

		$data    = get_post_meta( $post->ID, '_wbam_ad_data', true );
		$data    = is_array( $data ) ? $data : array();
		$ad_type = isset( $data['type'] ) ? $data['type'] : 'image';

		$engine   = Placement_Engine::get_instance();
		$ad_types = $engine->get_ad_types();
		?>
		<div class="wbam-metabox wbam-adtype-tabs">
			<?php foreach ( $ad_types as $type ) : ?>
				<input type="radio"
						name="wbam_data[type]"
						value="<?php echo esc_attr( $type->get_id() ); ?>"
						id="wbam-adtype-<?php echo esc_attr( $type->get_id() ); ?>"
						class="wbam-adtype-radio"
						aria-label="<?php echo esc_attr( $type->get_name() ); ?>"
						<?php checked( $ad_type, $type->get_id() ); ?> />
			<?php endforeach; ?>

			<div class="wbam-adtype-nav">
				<?php foreach ( $ad_types as $type ) : ?>
					<a href="#" class="wbam-adtype-tab<?php echo ( $ad_type === $type->get_id() ) ? ' wbam-adtype-tab-active' : ''; ?>" data-type="<?php echo esc_attr( $type->get_id() ); ?>">
						<span class="dashicons <?php echo esc_attr( $type->get_icon() ); ?>"></span>
						<?php echo esc_html( $type->get_name() ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<?php foreach ( $ad_types as $type ) : ?>
				<div class="wbam-adtype-content" data-type="<?php echo esc_attr( $type->get_id() ); ?>"<?php echo ( $ad_type === $type->get_id() ) ? ' style="display:block;"' : ''; ?>>
					<p class="wbam-adtype-desc"><?php echo esc_html( $type->get_description() ); ?></p>
					<?php $type->render_metabox( $post->ID, $data ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<script>
		jQuery(function($) {
			function readTypeList(rawAttr) {
				try {
					var parsed = JSON.parse(rawAttr || '[]');
					return $.isArray(parsed) ? parsed : [];
				} catch (e) {
					return [];
				}
			}

			// A3 fix: some ad types (e.g. Pro's video type) don't use a
			// placement or a fixed size at all — Sizing (Ad Status metabox)
			// and Placements (its own metabox) both hide/disable themselves
			// for those types, driven by the same `data-no-*-types` lists
			// the two panels were rendered with, so switching tabs without
			// a page reload keeps both consistent.
			function syncTypeDependentPanels(typeId) {
				var $sizing         = $('.wbam-sizing-section'),
					$sizingNote     = $('.wbam-sizing-unavailable-notice'),
					$placementsWrap = $('.wbam-placements-metabox'),
					$placements     = $placementsWrap.find('.wbam-placements-fields'),
					$placementsNote = $('.wbam-placements-unavailable-notice');

				if ( ! $sizing.length && ! $placementsWrap.length ) {
					return;
				}

				var hideSizing     = readTypeList( $sizing.attr('data-no-sizing-types') ).indexOf(typeId) !== -1,
					hidePlacements = readTypeList( $placementsWrap.attr('data-no-placement-types') ).indexOf(typeId) !== -1;

				$sizing.prop('hidden', hideSizing);
				$sizingNote.prop('hidden', ! hideSizing);

				// `hidden` only (never `disabled`): a disabled checkbox is
				// dropped from the POST entirely, and the save handler's
				// "union posted with whatever the form didn't offer" logic
				// keys off get_selectable_placements() - a site-wide list
				// that has no notion of "not offered for this ad's type".
				// Disabling would make a video-ad save silently wipe
				// _wbam_placements instead of leaving it untouched.
				$placements.prop('hidden', hidePlacements);
				$placementsNote.prop('hidden', ! hidePlacements);
			}

			$('.wbam-adtype-tab').on('click', function(e) {
				e.preventDefault();
				var typeId = $(this).data('type');
				$('#wbam-adtype-' + typeId).prop('checked', true);
				$('.wbam-adtype-tab').removeClass('wbam-adtype-tab-active');
				$(this).addClass('wbam-adtype-tab-active');
				$('.wbam-adtype-content').hide();
				$('.wbam-adtype-content[data-type="' + typeId + '"]').show();
				syncTypeDependentPanels( String( typeId ) );
			});

			// Re-assert on load: matches the PHP-rendered initial hidden
			// state (belt-and-braces — the two must never disagree).
			syncTypeDependentPanels( String( $('.wbam-adtype-radio:checked').val() || '' ) );
		});
		</script>
		<?php
	}

	/**
	 * Render placements metabox.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_placements_metabox( $post ) {
		$placements = get_post_meta( $post->ID, '_wbam_placements', true );
		$placements = is_array( $placements ) ? $placements : array();

		$data             = get_post_meta( $post->ID, '_wbam_ad_data', true );
		$after_paragraph  = isset( $data['after_paragraph'] ) ? absint( $data['after_paragraph'] ) : 2;
		$paragraph_repeat = isset( $data['paragraph_repeat'] ) ? $data['paragraph_repeat'] : false;
		$after_activity   = isset( $data['after_activity'] ) ? absint( $data['after_activity'] ) : 3;
		$activity_repeat  = isset( $data['activity_repeat'] ) ? $data['activity_repeat'] : false;
		$after_posts      = isset( $data['after_posts'] ) ? absint( $data['after_posts'] ) : 3;
		$posts_repeat     = isset( $data['posts_repeat'] ) ? $data['posts_repeat'] : false;

		// A3 fix: an ad type that bypasses placements entirely (video ads
		// are selected by `_wbam_ad_type` and delivered in-stream, not
		// through this metabox) must not leave a fully-interactive but
		// functionally inert Placements panel. See self::ad_types_without_placements().
		//
		// Hidden only - deliberately NOT `disabled`. A disabled checkbox is
		// dropped from the POST entirely, and save_meta()'s "union posted
		// placements with whatever the form didn't offer" logic keys off
		// Placement_Engine::get_selectable_placements() - a site-wide list
		// with no notion of "not offered for this ad's type". Disabling
		// would make saving a video ad silently wipe its stored
		// `_wbam_placements`, instead of leaving it untouched for if the
		// admin ever switches the ad back to a placement-based type.
		$ad_type            = is_array( $data ) && ! empty( $data['type'] ) ? (string) $data['type'] : 'image';
		$no_placement_types = self::ad_types_without_placements();
		$placements_hidden  = in_array( $ad_type, $no_placement_types, true );

		$engine     = Placement_Engine::get_instance();
		$all_places = $engine->get_selectable_placements_grouped();
		?>
		<div class="wbam-metabox wbam-placements-metabox" data-no-placement-types="<?php echo esc_attr( (string) wp_json_encode( array_values( $no_placement_types ) ) ); ?>">
			<p class="wbam-placements-unavailable-notice"<?php echo $placements_hidden ? '' : ' hidden'; ?>>
				<?php esc_html_e( 'This ad type is not assigned to a placement. It plays inside protected lesson videos (pre-roll, mid-roll, post-roll) or as a standalone player, delivered by the video engine — ticking boxes below has no effect.', 'wb-ads-rotator-with-split-test' ); ?>
			</p>

			<div class="wbam-placements-fields"<?php echo $placements_hidden ? ' hidden' : ''; ?>>
			<?php foreach ( $all_places as $group => $group_placements ) : ?>
				<div class="wbam-placement-group">
					<h4><?php echo esc_html( ucfirst( $group ) ); ?> <?php esc_html_e( 'Placements', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<div class="wbam-placement-options">
						<?php foreach ( $group_placements as $placement ) : ?>
							<label class="wbam-placement-option">
								<input type="checkbox" name="wbam_placements[]" value="<?php echo esc_attr( $placement->get_id() ); ?>" <?php checked( in_array( $placement->get_id(), $placements, true ) ); ?> />
								<span class="wbam-option-body">
									<span class="wbam-option-title"><?php echo esc_html( $placement->get_name() ); ?></span>
									<span class="wbam-option-desc"><?php echo esc_html( $placement->get_description() ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>

			<div class="wbam-extra-settings wbam-paragraph-settings" <?php echo ! in_array( 'after_paragraph', $placements, true ) ? 'style="display:none;"' : ''; ?>>
				<h4><?php esc_html_e( 'Paragraph Settings', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<div class="wbam-field">
					<label for="wbam_after_paragraph"><?php esc_html_e( 'Insert after paragraph:', 'wb-ads-rotator-with-split-test' ); ?></label>
					<input type="number" id="wbam_after_paragraph" name="wbam_data[after_paragraph]" value="<?php echo esc_attr( $after_paragraph ); ?>" min="1" max="50" />
				</div>
				<div class="wbam-field">
					<label>
						<input type="checkbox" name="wbam_data[paragraph_repeat]" value="1" <?php checked( $paragraph_repeat ); ?> />
						<?php esc_html_e( 'Repeat after every X paragraphs', 'wb-ads-rotator-with-split-test' ); ?>
					</label>
				</div>
			</div>

			<div class="wbam-extra-settings wbam-activity-settings" <?php echo ! in_array( 'bp_activity', $placements, true ) ? 'style="display:none;"' : ''; ?>>
				<h4><?php esc_html_e( 'Activity Stream Settings', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<div class="wbam-field">
					<label for="wbam_after_activity"><?php esc_html_e( 'Insert after activity:', 'wb-ads-rotator-with-split-test' ); ?></label>
					<input type="number" id="wbam_after_activity" name="wbam_data[after_activity]" value="<?php echo esc_attr( $after_activity ); ?>" min="1" max="50" />
				</div>
				<div class="wbam-field">
					<label>
						<input type="checkbox" name="wbam_data[activity_repeat]" value="1" <?php checked( $activity_repeat ); ?> />
						<?php esc_html_e( 'Repeat after every X activities', 'wb-ads-rotator-with-split-test' ); ?>
					</label>
				</div>
			</div>

			<div class="wbam-extra-settings wbam-archive-settings" <?php echo ! in_array( 'archive', $placements, true ) ? 'style="display:none;"' : ''; ?>>
				<h4><?php esc_html_e( 'Archive Settings', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<div class="wbam-field">
					<label for="wbam_after_posts"><?php esc_html_e( 'Insert after post:', 'wb-ads-rotator-with-split-test' ); ?></label>
					<input type="number" id="wbam_after_posts" name="wbam_data[after_posts]" value="<?php echo esc_attr( $after_posts ); ?>" min="1" max="50" />
				</div>
				<div class="wbam-field">
					<label>
						<input type="checkbox" name="wbam_data[posts_repeat]" value="1" <?php checked( $posts_repeat ); ?> />
						<?php esc_html_e( 'Repeat after every X posts', 'wb-ads-rotator-with-split-test' ); ?>
					</label>
				</div>
			</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Ad type IDs whose ads are not assigned to a placement.
	 *
	 * Video ads (Pro's `video` type, id registered via the free
	 * `wbam_register_ad_types` action) are selected by `_wbam_ad_type` and
	 * delivered in-stream by MediaShield/the standalone player — they
	 * bypass Placement_Engine::get_ads_for_placement() entirely, so the
	 * Placements metabox and the Sizing section are both inert for them.
	 * Listing 'video' here is safe even when Pro is not installed: free
	 * never registers a type with that id, so the check is a no-op until
	 * Pro's Video_Ad type is present.
	 *
	 * Filterable so a future ad type (free or Pro) that doesn't use
	 * placements can opt in without another core change.
	 *
	 * @since 2.11.1
	 * @return string[]
	 */
	private static function ad_types_without_placements() {
		// Delegates to the shared helper so the admin notice and
		// Placement_Engine read one list. They disagreed until 3.1.1, and the
		// engine's copy of the truth was "no list at all".
		return wbam_ad_types_without_placements();
	}

	/**
	 * Ad type IDs whose ads have no fixed width/height to configure.
	 *
	 * Kept as a separate filter from ad_types_without_placements() because
	 * the two concerns are independent in principle (a future type could
	 * skip one but not the other) even though 'video' opts out of both
	 * today. See ad_types_without_placements() for why 'video' is a safe
	 * default with or without Pro installed.
	 *
	 * @since 2.11.1
	 * @return string[]
	 */
	private static function ad_types_without_sizing() {
		return (array) apply_filters( 'wbam_ad_types_without_sizing', array( 'video' ) );
	}

	/**
	 * Render status metabox.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_status_metabox( $post ) {
		$enabled       = get_post_meta( $post->ID, '_wbam_enabled', true );
		$enabled       = '' === $enabled ? '1' : $enabled;
		$priority      = get_post_meta( $post->ID, '_wbam_priority', true );
		$priority      = '' === $priority ? 5 : absint( $priority );
		$session_limit = get_post_meta( $post->ID, '_wbam_session_limit', true );
		$session_limit = '' === $session_limit ? '' : absint( $session_limit );
		$is_responsive = get_post_meta( $post->ID, '_wbam_is_responsive', true );
		$ad_format     = get_post_meta( $post->ID, '_wbam_ad_format', true );
		$ad_width      = (int) get_post_meta( $post->ID, '_wbam_ad_width', true );
		$ad_height     = (int) get_post_meta( $post->ID, '_wbam_ad_height', true );
		$format_labels = \WBAM\Core\Ad_Formats::all();

		// A1 fix: the sizing summary must answer "will THIS ad render in
		// the placements the admin actually ticked", not "which placements
		// accept this format" — see self::render_compat_summary().
		$assigned_placements = get_post_meta( $post->ID, '_wbam_placements', true );
		$assigned_placements = is_array( $assigned_placements ) ? array_map( 'strval', $assigned_placements ) : array();

		$placement_registry = apply_filters( 'wbam_get_placements', array() );
		$placement_registry = is_array( $placement_registry ) ? $placement_registry : array();

		$sizing_compat = self::initial_compat_summary(
			$placement_registry,
			$assigned_placements,
			(string) $is_responsive,
			(string) $ad_format,
			$ad_width,
			$ad_height
		);

		// A3 fix: an ad type that doesn't use fixed dimensions (video ads —
		// see self::ad_types_without_placements()) hides Sizing the same
		// way it hides Placements, driven by the same live ad-type switch
		// in render_settings_metabox()'s inline script.
		$ad_data_for_type = get_post_meta( $post->ID, '_wbam_ad_data', true );
		$current_ad_type  = is_array( $ad_data_for_type ) && ! empty( $ad_data_for_type['type'] ) ? (string) $ad_data_for_type['type'] : 'image';
		$sizing_hidden    = in_array( $current_ad_type, self::ad_types_without_sizing(), true );
		?>
		<div class="wbam-metabox">
			<div class="wbam-status-options">
				<label class="wbam-status-option">
					<input type="radio" name="wbam_enabled" value="1" <?php checked( $enabled, '1' ); ?> />
					<span class="wbam-status-enabled"><?php esc_html_e( 'Enabled', 'wb-ads-rotator-with-split-test' ); ?></span>
				</label>
				<label class="wbam-status-option">
					<input type="radio" name="wbam_enabled" value="0" <?php checked( $enabled, '0' ); ?> />
					<span class="wbam-status-disabled"><?php esc_html_e( 'Disabled', 'wb-ads-rotator-with-split-test' ); ?></span>
				</label>
			</div>

			<div class="wbam-priority-field">
				<label for="wbam_priority"><?php esc_html_e( 'Priority', 'wb-ads-rotator-with-split-test' ); ?></label><?php echo Field_Tooltips::tip_for( 'priority' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped HTML. ?>
				<input type="range" id="wbam_priority" name="wbam_priority" min="1" max="10" value="<?php echo esc_attr( $priority ); ?>" />
				<span class="wbam-priority-value"><?php echo esc_html( $priority ); ?></span>
				<p class="description"><?php esc_html_e( 'Higher priority = bigger share when multiple ads compete for the same slot. Default is 5.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<p class="wbam-priority-share-hint" aria-live="polite">
					<?php /* Filled in by JS: 'In a 3-way tie with two default-priority ads, this ad wins ~X% of impressions.' */ ?>
				</p>
			</div>

			<div class="wbam-session-limit-field">
				<label for="wbam_session_limit"><?php esc_html_e( 'Session Limit', 'wb-ads-rotator-with-split-test' ); ?></label><?php echo Field_Tooltips::tip_for( 'session_limit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped HTML. ?>
				<input type="number" id="wbam_session_limit" name="wbam_session_limit" min="0" value="<?php echo esc_attr( $session_limit ); ?>" placeholder="<?php esc_attr_e( 'Unlimited', 'wb-ads-rotator-with-split-test' ); ?>" />
				<p class="description"><?php esc_html_e( 'Max views per visitor session. Leave empty for unlimited.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<p class="wbam-sizing-unavailable-notice"<?php echo $sizing_hidden ? '' : ' hidden'; ?>>
				<?php esc_html_e( 'This ad type has no fixed size — it plays inside protected lesson videos or as a standalone player, not in a sized slot.', 'wb-ads-rotator-with-split-test' ); ?>
			</p>

			<div class="wbam-sizing-section" data-no-sizing-types="<?php echo esc_attr( (string) wp_json_encode( array_values( self::ad_types_without_sizing() ) ) ); ?>"<?php echo $sizing_hidden ? ' hidden' : ''; ?>>
				<div class="wbam-sizing-section__head">
					<h3 class="wbam-sizing-section__title"><?php esc_html_e( 'Sizing', 'wb-ads-rotator-with-split-test' ); ?><?php echo Field_Tooltips::tip_for( 'sizing_mode' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped HTML. ?></h3>
					<span class="wbam-sizing-section__hint"><?php esc_html_e( 'Controls where this ad can render.', 'wb-ads-rotator-with-split-test' ); ?></span>
				</div>

				<div class="wbam-sizing-choice" role="radiogroup" aria-label="<?php esc_attr_e( 'Ad sizing mode', 'wb-ads-rotator-with-split-test' ); ?>">
					<label class="wbam-sizing-option <?php echo '1' === (string) $is_responsive ? 'is-active' : ''; ?>">
						<input type="radio" name="wbam_sizing_mode" value="responsive" <?php checked( '1', (string) $is_responsive ); ?> />
						<span class="wbam-sizing-option__title"><?php esc_html_e( 'Responsive', 'wb-ads-rotator-with-split-test' ); ?></span>
						<span class="wbam-sizing-option__desc"><?php esc_html_e( 'Fills any slot. Best for AdSense auto and fluid HTML.', 'wb-ads-rotator-with-split-test' ); ?></span>
					</label>
					<label class="wbam-sizing-option <?php echo '1' !== (string) $is_responsive ? 'is-active' : ''; ?>">
						<input type="radio" name="wbam_sizing_mode" value="fixed" <?php checked( '1', (string) $is_responsive, false ) ? '' : checked( true, true ); ?> <?php echo '1' !== (string) $is_responsive ? 'checked' : ''; ?> />
						<span class="wbam-sizing-option__title"><?php esc_html_e( 'Fixed size', 'wb-ads-rotator-with-split-test' ); ?></span>
						<span class="wbam-sizing-option__desc"><?php esc_html_e( 'Known width and height. Matches only compatible slots.', 'wb-ads-rotator-with-split-test' ); ?></span>
					</label>
				</div>

				<!-- Hidden carrier so existing save pipeline (_wbam_is_responsive) keeps working. -->
				<input type="hidden" id="wbam_is_responsive" name="wbam_is_responsive" value="<?php echo '1' === (string) $is_responsive ? '1' : ''; ?>" />

				<div class="wbam-sizing-fixed-fields" <?php echo '1' === (string) $is_responsive ? 'hidden' : ''; ?>>
					<label for="wbam_ad_format" class="wbam-inline-label"><?php esc_html_e( 'Format', 'wb-ads-rotator-with-split-test' ); ?></label>
					<select id="wbam_ad_format" name="wbam_ad_format" class="wbam-sizing-fixed-fields__select">
						<option value=""><?php esc_html_e( 'Auto-detect from image', 'wb-ads-rotator-with-split-test' ); ?></option>
						<?php foreach ( $format_labels as $slug => $meta ) : ?>
							<?php
							if ( 'responsive' === $slug ) {
								continue; } // Responsive lives in the choice above.
							?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $ad_format, $slug ); ?>>
								<?php echo esc_html( $meta['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<div class="wbam-sizing-custom-dims" <?php echo 'custom' === $ad_format ? '' : 'hidden'; ?>>
						<span class="wbam-inline-label"><?php esc_html_e( 'Dimensions', 'wb-ads-rotator-with-split-test' ); ?></span><?php echo Field_Tooltips::tip_for( 'custom_dims' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped HTML. ?>
						<input type="number" name="wbam_ad_width" min="0" step="1" value="<?php echo esc_attr( $ad_width ? $ad_width : '' ); ?>" placeholder="W" class="small-text" aria-label="<?php esc_attr_e( 'Width in pixels', 'wb-ads-rotator-with-split-test' ); ?>" />
						<span class="wbam-sizing-x">&times;</span>
						<input type="number" name="wbam_ad_height" min="0" step="1" value="<?php echo esc_attr( $ad_height ? $ad_height : '' ); ?>" placeholder="H" class="small-text" aria-label="<?php esc_attr_e( 'Height in pixels', 'wb-ads-rotator-with-split-test' ); ?>" />
						<span class="wbam-sizing-units"><?php esc_html_e( 'px', 'wb-ads-rotator-with-split-test' ); ?></span>
					</div>
				</div>

				<div class="wbam-sizing-compat" aria-live="polite">
					<span class="wbam-sizing-compat__label"><?php echo esc_html( $sizing_compat['label'] ); ?></span>
					<span class="wbam-sizing-compat__value"><?php echo esc_html( $sizing_compat['value'] ); ?></span>
				</div>
			</div>

			<?php
			/**
			 * Action for adding additional metabox options.
			 *
			 * @since 1.0.0
			 * @param \WP_Post $post Post object.
			 */
			do_action( 'wbam_ad_metabox_options', $post );
			?>
		</div>
		<script>
		jQuery(function($) {
			// Priority slider live value + win-share transparency hint.
			//
			// Phase H.3: site owners and advertisers should see exactly
			// what raising the priority slider does. Frequency_Manager
			// builds a weighted pool where each ad contributes `priority`
			// copies, so the win share in a tie of N ads at priorities
			// p1..pN is p_i / sum(p). We illustrate the most common tie
			// scenario (3 ads, two at the default priority of 5).
			var priorityHintTpl = 
			<?php
				/* translators: %d: this ad's share of impressions (percent) in a 3-way priority tie */
				echo wp_json_encode( __( 'In a 3-way tie with two default-priority (5) ads, this ad would win about %d%% of impressions.', 'wb-ads-rotator-with-split-test' ) );
			?>
			;

			function updatePriorityHint( value ) {
				var p     = parseInt( value, 10 ) || 5;
				var share = Math.round( ( p / ( p + 5 + 5 ) ) * 100 );
				// Translation string uses printf-style %d / %% — un-escape
				// the literal percent so JS shows '33%' not '33%%'.
				$( '.wbam-priority-share-hint' ).text(
					priorityHintTpl.replace( '%d', share ).replace( /%%/g, '%' )
				);
			}

			$('#wbam_priority').on('input', function() {
				$(this).next('.wbam-priority-value').text(this.value);
				updatePriorityHint( this.value );
			});

			// Initial paint so the hint is visible on page load.
			updatePriorityHint( $( '#wbam_priority' ).val() );

			// Sizing section wiring. We use a two-choice radio group for the
			// sizing mode instead of a standalone checkbox so the mental
			// model is explicit: 'responsive' or 'fixed', with the fixed
			// controls only visible when fixed is selected.
			var $modeRadios = $('input[name="wbam_sizing_mode"]'),
				$hidden     = $('#wbam_is_responsive'),
				$fixed      = $('.wbam-sizing-fixed-fields'),
				$format     = $('#wbam_ad_format'),
				$customDims = $('.wbam-sizing-custom-dims'),
				$compat     = $('.wbam-sizing-compat__value'),
				$options    = $('.wbam-sizing-option');

			function currentMode() {
				return $modeRadios.filter(':checked').val() || 'responsive';
			}

			function syncMode() {
				var mode = currentMode();
				$hidden.val( mode === 'responsive' ? '1' : '' );
				$fixed.prop('hidden', mode === 'responsive');
				$options.each(function() {
					$(this).toggleClass('is-active', $(this).find('input[type="radio"]').is(':checked'));
				});
				updateCompat();
			}

			function syncCustomDims() {
				$customDims.prop('hidden', $format.val() !== 'custom');
				updateCompat();
			}

			var $compatLabel = $('.wbam-sizing-compat__label');

			// Placements ticked in the (separate) Placements metabox on this
			// same screen. Read live via selector rather than cached at load
			// time so ticking/unticking a box updates the summary below
			// without a page reload.
			function selectedPlacementSlugs() {
				var ids = [];
				$('input[name="wbam_placements[]"]:checked').each(function() {
					ids.push( String( this.value ) );
				});
				return ids;
			}

			function placementName( slug ) {
				var entry = wbamFormatData.placements[ slug ];
				return entry && entry.name ? entry.name : slug;
			}

			function namesFor( slugs ) {
				return $.map( slugs, placementName );
			}

			// A1 fix: this summary must answer "will THIS ad render in the
			// placements the admin ticked", not "which placements accept
			// this ad's format" — and it must say so honestly when nothing
			// is ticked yet, or when a ticked placement's format doesn't
			// match. Mirrors Ad_Formats::summarize_placement_compat() (PHP)
			// used for the initial server-rendered value; kept in sync by
			// hand since the live recompute here has no AJAX round-trip.
			//
			// No AJAX round-trip — the match logic is pure and fast, driven
			// entirely by data emitted in wbamFormatData (populated via
			// wp_localize).
			function updateCompat() {
				if ( typeof window.wbamFormatData === 'undefined' ) {
					$compat.text('');
					return;
				}

				var i18n   = wbamFormatData.i18n,
					mode   = currentMode(),
					format = mode === 'responsive' ? 'responsive' : ($format.val() || 'auto');

				if ( mode === 'fixed' && format === 'auto' ) {
					$compatLabel.text( i18n.labelPending );
					$compat.text( i18n.autoDetect );
					return;
				}

				if ( mode === 'fixed' && format === 'custom' ) {
					var w = parseInt($customDims.find('input[name="wbam_ad_width"]').val(), 10) || 0,
						h = parseInt($customDims.find('input[name="wbam_ad_height"]').val(), 10) || 0;
					if ( w <= 0 || h <= 0 ) {
						$compatLabel.text( i18n.labelPending );
						$compat.text( i18n.enterDims );
						return;
					}
					format = detectFormat(w, h);
					if ( format === 'custom' ) {
						$compatLabel.text( i18n.labelPending );
						$compat.text( i18n.noMatch );
						return;
					}
				}

				var compatible = [];
				$.each( wbamFormatData.placements, function( slug, entry ) {
					if ( format === 'responsive' || (entry.accepted || []).indexOf( format ) !== -1 ) {
						compatible.push( slug );
					}
				} );

				var selected = selectedPlacementSlugs();

				// Nothing ticked yet in the Placements metabox: this ad
				// will not render anywhere regardless of format, so we
				// describe capability ("could render in"), never a promise
				// ("will render in").
				if ( selected.length === 0 ) {
					$compatLabel.text( i18n.labelPotential );

					if ( compatible.length === 0 ) {
						$compat.text( i18n.noMatch );
					} else if ( compatible.length === Object.keys(wbamFormatData.placements).length ) {
						$compat.text( i18n.every + ' ' + i18n.untickedHint );
					} else {
						$compat.text( namesFor(compatible).join(', ') + ' ' + i18n.untickedHint );
					}
					return;
				}

				// Intersect the ticked placements with the format-compatible
				// ones so a mismatch (ticked, but wrong size for this
				// placement) is surfaced explicitly instead of silently
				// listed alongside placements that actually match.
				var willRender = [],
					mismatched = [];
				$.each( selected, function( i, slug ) {
					if ( ! wbamFormatData.placements.hasOwnProperty( slug ) ) {
						return; // No checkbox exists for an unregistered slug.
					}
					if ( compatible.indexOf( slug ) !== -1 ) {
						willRender.push( slug );
					} else {
						mismatched.push( slug );
					}
				} );

				$compatLabel.text( i18n.labelWillRender );

				if ( willRender.length > 0 ) {
					var text = namesFor(willRender).join(', ');
					if ( mismatched.length > 0 ) {
						text += ' ' + i18n.mismatchPrefix + ' ' + namesFor(mismatched).join(', ') + '.';
					}
					$compat.text( text );
				} else {
					$compat.text( i18n.noneOfSelected + ' ' + namesFor(mismatched).join(', ') + '.' );
				}
			}

			function detectFormat(w, h) {
				var found = 'custom';
				$.each( wbamFormatData.formats, function( slug, dims ) {
					if ( dims.w === w && dims.h === h ) {
						found = slug;
						return false;
					}
				} );
				return found;
			}

			$modeRadios.on('change', syncMode);
			$format.on('change', syncCustomDims);
			$customDims.on('input', 'input[type="number"]', updateCompat);
			// Placements metabox lives outside this metabox's DOM subtree,
			// so delegate on document — works regardless of metabox render
			// order or drag-reordering.
			$(document).on('change', 'input[name="wbam_placements[]"]', updateCompat);

			syncMode();
			syncCustomDims();
		});
		</script>
		<?php
	}

	/**
	 * Render the Preview metabox.
	 *
	 * Shows an approximate render of the saved ad so the admin can verify
	 * their content without clicking through to a frontend page. Code ads
	 * are isolated in a sandboxed iframe so pasted scripts cannot touch the
	 * admin. AdSense shows a placeholder because the real AdSense script
	 * only runs on public pages.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function render_preview_metabox( $post ) {
		$ad_data = get_post_meta( $post->ID, '_wbam_ad_data', true );
		$type    = is_array( $ad_data ) && ! empty( $ad_data['type'] ) ? (string) $ad_data['type'] : '';

		if ( '' === $type ) {
			echo '<p class="description">'
				. esc_html__( 'Save the ad first to see a preview.', 'wb-ads-rotator-with-split-test' )
				. '</p>';
			return;
		}

		echo '<p class="description" style="margin:0 0 10px;">'
			. esc_html__( 'Approximate render. Final appearance depends on the theme and placement wrapper. Save the ad to refresh this preview.', 'wb-ads-rotator-with-split-test' )
			. '</p>';

		echo '<div class="wbam-preview-stage" style="padding:20px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;min-height:60px;">';

		switch ( $type ) {
			case 'image':
				$this->render_preview_image( $ad_data );
				break;
			case 'rich-content':
			case 'rich_content': // Legacy stored spelling.
				$this->render_preview_rich_content( $ad_data );
				break;
			case 'code':
				$this->render_preview_code( $ad_data );
				break;
			case 'adsense':
				$this->render_preview_adsense( $ad_data );
				break;
			case 'email_capture':
				$this->render_preview_email_capture( $ad_data, (int) $post->ID );
				break;
			default:
				echo '<p>' . esc_html(
					sprintf(
						/* translators: %s: ad type slug */
						__( 'Preview not available for ad type: %s', 'wb-ads-rotator-with-split-test' ),
						$type
					)
				) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Image ad preview.
	 *
	 * @param array<string,mixed> $data Ad data.
	 * @return void
	 */
	private function render_preview_image( $data ) {
		$url = isset( $data['image_url'] ) ? esc_url( $data['image_url'] ) : '';
		if ( '' === $url ) {
			echo '<p>' . esc_html__( 'No image selected.', 'wb-ads-rotator-with-split-test' ) . '</p>';
			return;
		}
		$alt  = isset( $data['alt_text'] ) ? $data['alt_text'] : '';
		$link = isset( $data['link_url'] ) ? $data['link_url'] : '';

		echo '<div style="text-align:center;">';
		if ( '' !== $link ) {
			printf(
				'<a href="%s" target="_blank" rel="noopener nofollow"><img src="%s" alt="%s" style="max-width:100%%;height:auto;border:0;"></a>',
				esc_url( $link ),
				esc_attr( $url ),
				esc_attr( $alt )
			);
		} else {
			printf(
				'<img src="%s" alt="%s" style="max-width:100%%;height:auto;border:0;">',
				esc_attr( $url ),
				esc_attr( $alt )
			);
		}
		echo '</div>';
	}

	/**
	 * Rich-content ad preview.
	 *
	 * @param array<string,mixed> $data Ad data.
	 * @return void
	 */
	private function render_preview_rich_content( $data ) {
		$content = isset( $data['content'] ) ? (string) $data['content'] : '';
		if ( '' === trim( $content ) ) {
			echo '<p>' . esc_html__( 'No content yet.', 'wb-ads-rotator-with-split-test' ) . '</p>';
			return;
		}
		echo '<div class="wbam-preview-rich">' . wp_kses_post( $content ) . '</div>';
	}

	/**
	 * Code ad preview rendered in a sandboxed iframe so pasted scripts
	 * cannot read admin cookies, modify the edit screen, or phone home
	 * on behalf of the logged-in admin.
	 *
	 * @param array<string,mixed> $data Ad data.
	 * @return void
	 */
	private function render_preview_code( $data ) {
		$code = isset( $data['code'] ) ? (string) $data['code'] : '';
		if ( '' === trim( $code ) ) {
			echo '<p>' . esc_html__( 'No code pasted yet.', 'wb-ads-rotator-with-split-test' ) . '</p>';
			return;
		}
		// Build a minimal HTML document. Keep bg/color sensible so an
		// unstyled ad snippet doesn't render as white-on-white.
		$doc  = '<!DOCTYPE html><html><head><meta charset="utf-8">';
		$doc .= '<style>body{margin:0;padding:12px;font:14px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1d2327;background:#fff;}</style>';
		$doc .= '</head><body>' . $code . '</body></html>';
		printf(
			'<iframe sandbox="allow-scripts allow-same-origin" style="width:100%%;min-height:200px;border:0;background:#fff;" srcdoc="%s"></iframe>',
			esc_attr( $doc )
		);
	}

	/**
	 * AdSense preview. Real AdSense only loads on approved public pages,
	 * so show a labeled placeholder with the configured unit IDs.
	 *
	 * @param array<string,mixed> $data Ad data.
	 * @return void
	 */
	private function render_preview_adsense( $data ) {
		$pub = isset( $data['publisher_id'] ) ? (string) $data['publisher_id'] : '';
		if ( '' === $pub ) {
			$pub = (string) \WBAM\Core\Settings_Helper::get( 'adsense_publisher_id', '' );
		}
		$unit   = isset( $data['ad_unit_id'] ) ? (string) $data['ad_unit_id'] : '';
		$format = isset( $data['format'] ) ? (string) $data['format'] : 'auto';

		echo '<div style="padding:28px 20px;background:#fff;border:1px dashed #c3c4c7;border-radius:4px;text-align:center;">';
		echo '<div style="font-weight:600;color:#1d2327;margin-bottom:6px;">' . esc_html__( 'Google AdSense', 'wb-ads-rotator-with-split-test' ) . '</div>';
		echo '<div style="font-family:monospace;color:#50575e;font-size:13px;">';
		if ( '' !== $pub ) {
			echo esc_html( $pub );
		}
		if ( '' !== $unit ) {
			echo ' / ' . esc_html( $unit );
		}
		echo '</div>';
		echo '<div style="color:#8c8f94;font-size:12px;margin-top:10px;">' . esc_html(
			sprintf(
			/* translators: %s: AdSense format (auto, horizontal, etc.) */
				__( 'Format: %s. Real AdSense renders only on approved public pages.', 'wb-ads-rotator-with-split-test' ),
				$format
			)
		) . '</div>';
		echo '</div>';
	}

	/**
	 * Email Capture ad preview.
	 *
	 * @param array<string,mixed> $data    Ad data.
	 * @param int                 $post_id Ad post ID (for CSS isolation hints).
	 * @return void
	 */
	private function render_preview_email_capture( $data, $post_id ) {
		$headline    = isset( $data['headline'] ) ? (string) $data['headline'] : __( 'Subscribe to our Newsletter', 'wb-ads-rotator-with-split-test' );
		$description = isset( $data['description'] ) ? (string) $data['description'] : '';
		$button      = isset( $data['button_text'] ) ? (string) $data['button_text'] : __( 'Subscribe', 'wb-ads-rotator-with-split-test' );
		$bg          = isset( $data['bg_color'] ) ? (string) $data['bg_color'] : '#ffffff';
		$text_color  = isset( $data['text_color'] ) ? (string) $data['text_color'] : '#1d2327';
		$btn_color   = isset( $data['button_color'] ) ? (string) $data['button_color'] : '#2271b1';
		$show_name   = ! empty( $data['show_name_field'] );
		$privacy     = isset( $data['privacy_text'] ) ? (string) $data['privacy_text'] : '';

		printf(
			'<div style="background:%s;color:%s;padding:22px;border-radius:6px;max-width:420px;margin:0 auto;">',
			esc_attr( $bg ),
			esc_attr( $text_color )
		);
		echo '<div style="font-size:18px;font-weight:700;margin-bottom:6px;">' . esc_html( $headline ) . '</div>';
		if ( '' !== $description ) {
			echo '<div style="font-size:13px;margin-bottom:14px;">' . esc_html( $description ) . '</div>';
		}
		if ( $show_name ) {
			echo '<input type="text" placeholder="' . esc_attr__( 'Your name', 'wb-ads-rotator-with-split-test' ) . '" aria-label="' . esc_attr__( 'Name field preview', 'wb-ads-rotator-with-split-test' ) . '" disabled style="display:block;width:100%;padding:8px 10px;margin-bottom:8px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;color:#1d2327;">';
		}
		echo '<input type="email" placeholder="' . esc_attr__( 'you@example.com', 'wb-ads-rotator-with-split-test' ) . '" aria-label="' . esc_attr__( 'Email field preview', 'wb-ads-rotator-with-split-test' ) . '" disabled style="display:block;width:100%;padding:8px 10px;margin-bottom:8px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;color:#1d2327;">';
		printf(
			'<button type="button" disabled style="background:%s;color:#fff;border:0;padding:9px 16px;border-radius:4px;font-weight:600;cursor:not-allowed;">%s</button>',
			esc_attr( $btn_color ),
			esc_html( $button )
		);
		if ( '' !== $privacy ) {
			echo '<div style="font-size:11px;margin-top:10px;opacity:.75;">' . esc_html( $privacy ) . '</div>';
		}
		echo '</div>';
		unset( $post_id );
	}

	/**
	 * Render comparison metabox.
	 *
	 * Shows performance comparison of all ads sharing the same placements.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_comparison_metabox( $post ) {
		global $wpdb;

		$current_placements = get_post_meta( $post->ID, '_wbam_placements', true );
		if ( empty( $current_placements ) || ! is_array( $current_placements ) ) {
			echo '<p>' . esc_html__( 'No placements assigned to this ad.', 'wb-ads-rotator-with-split-test' ) . '</p>';
			return;
		}

		// Find all ads that share at least one placement with current ad.
		$all_ads = get_posts(
			array(
				'post_type'      => 'wbam-ad',
				'posts_per_page' => 50,
				'post_status'    => 'publish',
				// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Bounded exclusion set on an admin screen.
				'post__not_in'   => array( $post->ID ),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only query; cost acceptable.
				'meta_query'     => array(
					array(
						'key'     => '_wbam_enabled',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);

		// Filter to only ads sharing placements.
		$competing_ads = array();
		foreach ( $all_ads as $ad ) {
			$ad_placements = get_post_meta( $ad->ID, '_wbam_placements', true );
			if ( ! empty( $ad_placements ) && is_array( $ad_placements ) ) {
				$shared = array_intersect( $current_placements, $ad_placements );
				if ( ! empty( $shared ) ) {
					$competing_ads[] = $ad;
				}
			}
		}

		if ( empty( $competing_ads ) ) {
			echo '<p>' . esc_html__( 'No other enabled ads are using the same placements. Enable more ads to compare performance.', 'wb-ads-rotator-with-split-test' ) . '</p>';
			return;
		}

		// Add current ad to comparison.
		array_unshift( $competing_ads, $post );

		// Get stats for all ads.
		$table_name   = $wpdb->prefix . 'wbam_analytics';
		$table_exists = $this->table_exists( $table_name );

		$stats = array();
		foreach ( $competing_ads as $ad ) {
			$impressions = 0;
			$clicks      = 0;

			if ( $table_exists ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$impressions = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wbam_analytics WHERE ad_id = %d AND event_type = %s',
						$ad->ID,
						'impression'
					)
				);
				$clicks      = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wbam_analytics WHERE ad_id = %d AND event_type = %s',
						$ad->ID,
						'click'
					)
				);
				// phpcs:enable
			}

			$ctr = $impressions > 0 ? ( $clicks / $impressions ) * 100 : 0;

			$stats[ $ad->ID ] = array(
				'id'          => $ad->ID,
				'title'       => $ad->post_title,
				'impressions' => $impressions,
				'clicks'      => $clicks,
				'ctr'         => $ctr,
				'is_current'  => $ad->ID === $post->ID,
			);
		}

		// Sort by CTR descending.
		usort(
			$stats,
			function ( $a, $b ) {
				return $b['ctr'] <=> $a['ctr'];
			}
		);

		// Find a credible winner. "Credible" = top ad by CTR among those
		// with enough impressions, AND a meaningful lead over the runner-up
		// so we don't declare a winner when two variants are within noise
		// of each other. The old rule ("top CTR with ≥100 impressions")
		// flagged a winner even on a 0.05% CTR gap, which misled customers
		// into disabling variants that were statistically identical.
		$min_samples   = 100; // Impressions required on every contender.
		$min_lead      = 0.20; // Winner's CTR must be 20% higher than runner-up.
		$min_abs_lead  = 1.0;  // ...or at least 1 percentage point above, for low-CTR tests.
		$eligible      = array_values(
			array_filter(
				$stats,
				static function ( $s ) use ( $min_samples ) {
					return $s['impressions'] >= $min_samples;
				}
			)
		);
		$winner_id     = 0;
		$winner_reason = '';

		if ( count( $eligible ) === 1 ) {
			$winner_id     = $eligible[0]['id'];
			$winner_reason = 'only_eligible';
		} elseif ( count( $eligible ) >= 2 ) {
			$leader     = $eligible[0];
			$runner_up  = $eligible[1];
			$lead_ratio = $runner_up['ctr'] > 0
				? ( $leader['ctr'] - $runner_up['ctr'] ) / $runner_up['ctr']
				: 1.0;
			$lead_abs   = $leader['ctr'] - $runner_up['ctr'];

			if ( $lead_ratio >= $min_lead || $lead_abs >= $min_abs_lead ) {
				$winner_id     = $leader['id'];
				$winner_reason = 'clear_lead';
			} else {
				$winner_reason = 'too_close';
			}
		}

		// Find max CTR for bar scaling.
		$max_ctr = max( array_column( $stats, 'ctr' ) );
		$max_ctr = $max_ctr > 0 ? $max_ctr : 1;
		?>
		<div class="wbam-comparison-scroll">
		<table class="wbam-comparison-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Ad', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th><?php esc_html_e( 'Impressions', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th><?php esc_html_e( 'Clicks', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th><?php esc_html_e( 'CTR', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th><?php esc_html_e( 'Performance', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $stats as $stat ) : ?>
					<tr class="<?php echo $stat['is_current'] ? 'wbam-current-ad' : ''; ?>">
						<td>
							<?php if ( $stat['is_current'] ) : ?>
								<strong><?php echo esc_html( $stat['title'] ); ?></strong>
								<span class="wbam-current-badge"><?php esc_html_e( 'This Ad', 'wb-ads-rotator-with-split-test' ); ?></span>
							<?php else : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $stat['id'] ) ); ?>">
									<?php echo esc_html( $stat['title'] ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $winner_id === $stat['id'] ) : ?>
								<span class="wbam-winner-badge"><?php esc_html_e( 'Winner', 'wb-ads-rotator-with-split-test' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( $stat['impressions'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $stat['clicks'] ) ); ?></td>
						<td><?php echo esc_html( number_format( $stat['ctr'], 2 ) ); ?>%</td>
						<td>
							<div class="wbam-ctr-bar">
								<div class="wbam-ctr-fill <?php echo $winner_id === $stat['id'] ? 'winner' : ''; ?>"
									style="width: <?php echo esc_attr( ( $stat['ctr'] / $max_ctr ) * 100 ); ?>%"></div>
							</div>
						</td>
						<td>
							<?php if ( ! $stat['is_current'] && $winner_id !== $stat['id'] ) : ?>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'post.php?post=' . $stat['id'] . '&action=edit&wbam_disable=1' ), 'wbam_disable_ad_' . $stat['id'] ) ); ?>"
									class="wbam-disable-btn"
									data-wbam-confirm="<?php echo esc_attr__( 'Disable this underperforming ad?', 'wb-ads-rotator-with-split-test' ); ?>"
									data-wbam-confirm-tone="warning">
									<?php esc_html_e( 'Disable', 'wb-ads-rotator-with-split-test' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>

		<p class="wbam-comparison-note">
			<?php
			if ( 0 === $winner_id ) {
				if ( 'too_close' === $winner_reason ) {
					esc_html_e( 'The top two ads are still within noise of each other. Keep the test running until one pulls clearly ahead.', 'wb-ads-rotator-with-split-test' );
				} else {
					esc_html_e( 'No winner yet. Each ad needs at least 100 impressions before a fair comparison.', 'wb-ads-rotator-with-split-test' );
				}
			} elseif ( 'only_eligible' === $winner_reason ) {
				esc_html_e( 'Only one ad has enough data so far. Keep the rotation running to compare against the others.', 'wb-ads-rotator-with-split-test' );
			} else {
				esc_html_e( 'Winner has a clear lead in CTR over the runner-up with enough data to trust the result.', 'wb-ads-rotator-with-split-test' );
			}
			?>
		</p>
		<?php
	}

	/**
	 * Save meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['wbam_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbam_nonce'] ) ), 'wbam_save_ad' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( 'wbam-ad' !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save enabled status.
		$enabled = isset( $_POST['wbam_enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['wbam_enabled'] ) ) : '1';
		update_post_meta( $post_id, '_wbam_enabled', $enabled );

		// Save priority.
		$priority = isset( $_POST['wbam_priority'] ) ? absint( wp_unslash( $_POST['wbam_priority'] ) ) : 5;
		$priority = max( 1, min( 10, $priority ) );
		update_post_meta( $post_id, '_wbam_priority', $priority );

		// Save session limit.
		$session_limit = isset( $_POST['wbam_session_limit'] ) && '' !== $_POST['wbam_session_limit']
			? absint( wp_unslash( $_POST['wbam_session_limit'] ) )
			: '';
		update_post_meta( $post_id, '_wbam_session_limit', $session_limit );

		// Save responsive flag. The sizing section emits a hidden
		// wbam_is_responsive input whose value is '1' when the
		// Responsive mode is selected and '' otherwise, so we check
		// the value rather than isset() (the field is always present).
		// A sibling wbam_sizing_mode radio is the authoritative source
		// of truth for UI rendering, but the hidden carrier keeps the
		// existing _wbam_is_responsive meta key stable for downstream
		// consumers (wrapper CSS, REST exposure, etc.).
		$mode_input    = isset( $_POST['wbam_sizing_mode'] ) ? sanitize_key( wp_unslash( $_POST['wbam_sizing_mode'] ) ) : '';
		$is_responsive = 'responsive' === $mode_input || ! empty( $_POST['wbam_is_responsive'] ) ? '1' : '0';
		update_post_meta( $post_id, '_wbam_is_responsive', $is_responsive );

		// Save ad format + dimensions. Resolution order:
		// 1. If Responsive ticked: format is 'responsive', dims cleared.
		// 2. Else if admin picked a named format (non-custom): store slug,
		// copy W/H from the taxonomy so downstream consumers have
		// dimensions without another lookup.
		// 3. Else if admin picked 'custom' with W/H: store as-is, detect
		// if dims match a named format and upgrade the slug for free.
		// 4. Else (Auto-detect option): call the detector against the
		// ad-type data; fall back to 'responsive' when indeterminate.
		$format_input = isset( $_POST['wbam_ad_format'] ) ? sanitize_text_field( wp_unslash( $_POST['wbam_ad_format'] ) ) : '';
		$width_input  = isset( $_POST['wbam_ad_width'] ) ? absint( wp_unslash( $_POST['wbam_ad_width'] ) ) : 0;
		$height_input = isset( $_POST['wbam_ad_height'] ) ? absint( wp_unslash( $_POST['wbam_ad_height'] ) ) : 0;

		$resolved = self::resolve_ad_format( $post_id, $is_responsive, $format_input, $width_input, $height_input );

		update_post_meta( $post_id, '_wbam_ad_format', $resolved['format'] );
		update_post_meta( $post_id, '_wbam_ad_width', $resolved['width'] );
		update_post_meta( $post_id, '_wbam_ad_height', $resolved['height'] );

		// Save placements.
		//
		// A3 fix: an ad type with no Placements UI at all (see
		// self::ad_types_without_placements(), e.g. Pro's video type) must
		// not have this section touch `_wbam_placements`. The metabox's
		// checkboxes are hidden-not-disabled specifically so a normal save
		// still posts whatever was already checked (see the render-side
		// comment in render_placements_metabox()) - but ANY other write
		// path that omits the field entirely (Quick Edit, a bulk action, a
		// REST/WP-CLI update that doesn't touch placements) would otherwise
		// fall through to the "form didn't offer it" fallback below, which
		// has no notion of "not offered because of this ad's type" - only
		// "not offered because the site gate/an integration closed it".
		// Skipping the whole write for these types is simpler and safer
		// than teaching that fallback a second kind of exclusion.
		$submitted_ad_type = isset( $_POST['wbam_data']['type'] )
			? sanitize_text_field( wp_unslash( $_POST['wbam_data']['type'] ) )
			: '';

		if ( ! in_array( $submitted_ad_type, self::ad_types_without_placements(), true ) ) {
			// The metabox only draws checkboxes for get_selectable_placements(),
			// so a slug the site gate has closed - or one whose integration is
			// switched off right now, e.g. BuddyPress deactivated - is simply
			// absent from the form. Replacing the meta wholesale would then
			// DESTROY that assignment the next time anyone edits the ad for an
			// unrelated reason, and re-opening the slot would not bring it back.
			// So: union the posted list with the stored slugs the form never
			// offered. The admin can only ever change what they were shown.
			$posted_placements = isset( $_POST['wbam_placements'] ) && is_array( $_POST['wbam_placements'] )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST['wbam_placements'] ) )
				: array();

			$stored_placements = get_post_meta( $post_id, '_wbam_placements', true );
			$stored_placements = is_array( $stored_placements ) ? $stored_placements : array();

			$offered_placements = array_keys( Placement_Engine::get_instance()->get_selectable_placements() );
			$unoffered          = array_values( array_diff( $stored_placements, $offered_placements ) );

			$placements = array_values( array_unique( array_merge( $posted_placements, $unoffered ) ) );
			update_post_meta( $post_id, '_wbam_placements', $placements );
		}

		// Save ad data.
		if ( isset( $_POST['wbam_data'] ) ) {
			$raw_data = wp_unslash( $_POST['wbam_data'] ); // phpcs:ignore
			$ad_type  = isset( $raw_data['type'] ) ? sanitize_text_field( $raw_data['type'] ) : 'image';

			$engine  = Placement_Engine::get_instance();
			$handler = $engine->get_ad_type( $ad_type );

			$data = array( 'type' => $ad_type );

			if ( $handler ) {
				$type_data = $handler->save( $post_id, $raw_data );
				$data      = array_merge( $data, $type_data );
			}

			// Paragraph settings.
			$data['after_paragraph']  = isset( $raw_data['after_paragraph'] ) ? absint( $raw_data['after_paragraph'] ) : 2;
			$data['paragraph_repeat'] = isset( $raw_data['paragraph_repeat'] ) ? true : false;

			// Activity settings.
			$data['after_activity']  = isset( $raw_data['after_activity'] ) ? absint( $raw_data['after_activity'] ) : 3;
			$data['activity_repeat'] = isset( $raw_data['activity_repeat'] ) ? true : false;

			// Archive settings.
			$data['after_posts']  = isset( $raw_data['after_posts'] ) ? absint( $raw_data['after_posts'] ) : 3;
			$data['posts_repeat'] = isset( $raw_data['posts_repeat'] ) ? true : false;

			/**
			 * Filter ad data before saving.
			 *
			 * @since 2.3.0
			 * @param array $data     Ad data to save.
			 * @param int   $post_id  Ad post ID.
			 * @param array $raw_data Raw POST data.
			 */
			$data = apply_filters( 'wbam_ad_data_before_save', $data, $post_id, $raw_data );

			update_post_meta( $post_id, '_wbam_ad_data', $data );
		}

		/**
		 * Action fired after ad meta is saved.
		 *
		 * @since 1.0.0
		 * @param int $post_id Post ID.
		 */
		do_action( 'wbam_save_ad_meta', $post_id );
	}

	/**
	 * Build the JS-side payload consumed by the sizing section's live
	 * placement-compatibility summary.
	 *
	 * @since 2.8.1
	 * @return array
	 */
	private static function collect_format_js_data() {
		if ( ! class_exists( '\\WBAM\\Core\\Ad_Formats' ) ) {
			return array();
		}

		$formats_out = array();
		foreach ( \WBAM\Core\Ad_Formats::all() as $slug => $meta ) {
			$formats_out[ $slug ] = array(
				'w' => (int) $meta['width'],
				'h' => (int) $meta['height'],
			);
		}

		$placements_out = array();
		$registry       = apply_filters( 'wbam_get_placements', array() );
		if ( is_array( $registry ) ) {
			foreach ( $registry as $slug => $entry ) {
				if ( ! is_array( $entry ) || empty( $entry['name'] ) ) {
					continue;
				}
				$placements_out[ $slug ] = array(
					'name'     => (string) $entry['name'],
					'accepted' => isset( $entry['accepted_formats'] ) ? (array) $entry['accepted_formats'] : array(),
				);
			}
		}

		return array(
			'formats'    => $formats_out,
			'placements' => $placements_out,
			'i18n'       => self::compat_i18n(),
		);
	}

	/**
	 * Shared i18n strings for the sizing "compatibility" summary.
	 *
	 * Used both by the initial server-rendered value
	 * (self::initial_compat_summary(), no-JS paint) and by the live
	 * client-side recompute (collect_format_js_data() -> wbamFormatData.i18n
	 * consumed by the inline script in render_status_metabox()).
	 * Centralized so wording never drifts between the two.
	 *
	 * @since 2.11.1
	 * @return array<string,string>
	 */
	private static function compat_i18n() {
		return array(
			'autoDetect'      => __( 'Auto-detected from your image on save.', 'wb-ads-rotator-with-split-test' ),
			'enterDims'       => __( 'Enter width x height to see matches.', 'wb-ads-rotator-with-split-test' ),
			'noMatch'         => __( 'No placements match this size yet.', 'wb-ads-rotator-with-split-test' ),
			'every'           => __( 'Every placement.', 'wb-ads-rotator-with-split-test' ),
			// Ad has at least one placement ticked: the summary now names
			// an outcome, so it may say "Will render in:".
			'labelWillRender' => __( 'Will render in:', 'wb-ads-rotator-with-split-test' ),
			// Ad has NO placement ticked yet: it will not render anywhere
			// regardless of format, so the label must not claim it will.
			'labelPotential'  => __( 'Could render in:', 'wb-ads-rotator-with-split-test' ),
			// Format itself isn't resolved yet (auto-detect pending, custom
			// dims not entered, or dims don't map to any known format).
			'labelPending'    => __( 'Placement fit:', 'wb-ads-rotator-with-split-test' ),
			'untickedHint'    => __( 'Tick a placement below to enable rendering.', 'wb-ads-rotator-with-split-test' ),
			'mismatchPrefix'  => __( 'Wrong size for:', 'wb-ads-rotator-with-split-test' ),
			'noneOfSelected'  => __( 'None of the selected placements accept this size:', 'wb-ads-rotator-with-split-test' ),
		);
	}

	/**
	 * Build the { label, value } pair for the sizing section's
	 * placement-compatibility summary, given an ad's currently persisted
	 * sizing fields and its ticked placements.
	 *
	 * Mirrors the client-side recompute in render_status_metabox()'s
	 * inline script so the initial (pre-JS) paint and the first live
	 * update never disagree. Delegates the actual set logic to
	 * Ad_Formats::summarize_placement_compat() (pure, unit-tested).
	 *
	 * @since 2.11.1
	 * @param array<string,array{name?:string,accepted_formats?:array<int,string>}> $registry   Placement registry (wbam_get_placements shape).
	 * @param string[]                                                              $selected   Placement slugs ticked in the Placements metabox.
	 * @param string                                                                $responsive '1' when the Responsive sizing mode is selected.
	 * @param string                                                                $ad_format  Raw `_wbam_ad_format` meta value ('' = auto-detect, 'custom', or a named slug).
	 * @param int                                                                   $ad_width   Persisted custom width, if any.
	 * @param int                                                                   $ad_height  Persisted custom height, if any.
	 * @return array{label:string, value:string}
	 */
	private static function initial_compat_summary( array $registry, array $selected, $responsive, $ad_format, $ad_width, $ad_height ) {
		$i18n = self::compat_i18n();

		if ( '1' === (string) $responsive ) {
			$format = \WBAM\Core\Ad_Formats::RESPONSIVE;
		} elseif ( '' === (string) $ad_format ) {
			return array(
				'label' => $i18n['labelPending'],
				'value' => $i18n['autoDetect'],
			);
		} elseif ( \WBAM\Core\Ad_Formats::CUSTOM === $ad_format ) {
			if ( $ad_width <= 0 || $ad_height <= 0 ) {
				return array(
					'label' => $i18n['labelPending'],
					'value' => $i18n['enterDims'],
				);
			}

			$detected = \WBAM\Core\Ad_Formats::detect_by_dimensions( $ad_width, $ad_height );
			if ( \WBAM\Core\Ad_Formats::CUSTOM === $detected ) {
				return array(
					'label' => $i18n['labelPending'],
					'value' => $i18n['noMatch'],
				);
			}

			$format = $detected;
		} else {
			$format = $ad_format;
		}

		$compat  = \WBAM\Core\Ad_Formats::summarize_placement_compat( $registry, $format, $selected );
		$name_of = function ( $slug ) use ( $registry ) {
			return isset( $registry[ $slug ]['name'] ) ? (string) $registry[ $slug ]['name'] : $slug;
		};

		if ( empty( $selected ) ) {
			$total = count( $registry );

			if ( empty( $compat['compatible'] ) ) {
				$value = $i18n['noMatch'];
			} elseif ( $total > 0 && count( $compat['compatible'] ) === $total ) {
				$value = $i18n['every'] . ' ' . $i18n['untickedHint'];
			} else {
				$value = implode( ', ', array_map( $name_of, $compat['compatible'] ) ) . ' ' . $i18n['untickedHint'];
			}

			return array(
				'label' => $i18n['labelPotential'],
				'value' => $value,
			);
		}

		if ( ! empty( $compat['match'] ) ) {
			$value = implode( ', ', array_map( $name_of, $compat['match'] ) );
			if ( ! empty( $compat['mismatch'] ) ) {
				$value .= ' ' . $i18n['mismatchPrefix'] . ' ' . implode( ', ', array_map( $name_of, $compat['mismatch'] ) ) . '.';
			}
		} else {
			$value = $i18n['noneOfSelected'] . ' ' . implode( ', ', array_map( $name_of, $compat['mismatch'] ) ) . '.';
		}

		return array(
			'label' => $i18n['labelWillRender'],
			'value' => $value,
		);
	}

	/**
	 * Resolve the final format slug + dimensions for an ad.
	 *
	 * Pure function; safe to unit-test in isolation. See the comment
	 * in save_metaboxes() for the resolution order.
	 *
	 * @since 2.8.1
	 * @param int    $post_id       Ad post ID (read ad-type data for auto-detect).
	 * @param string $is_responsive '1' if the Responsive flag is ticked.
	 * @param string $format_input  Admin-selected format slug ('' = auto-detect).
	 * @param int    $width_input   Width from the Custom W x H inputs.
	 * @param int    $height_input  Height from the Custom W x H inputs.
	 * @return array{format:string, width:int, height:int}
	 */
	private static function resolve_ad_format( $post_id, $is_responsive, $format_input, $width_input, $height_input ) {
		// Rule 1: Responsive flag wins.
		if ( '1' === (string) $is_responsive ) {
			return array(
				'format' => \WBAM\Core\Ad_Formats::RESPONSIVE,
				'width'  => 0,
				'height' => 0,
			);
		}

		$all = \WBAM\Core\Ad_Formats::all();

		// Rule 2: Named format picked.
		if ( '' !== $format_input && isset( $all[ $format_input ] ) && 'custom' !== $format_input ) {
			$meta = $all[ $format_input ];
			return array(
				'format' => $format_input,
				'width'  => (int) $meta['width'],
				'height' => (int) $meta['height'],
			);
		}

		// Rule 3: Custom W x H.
		if ( 'custom' === $format_input && $width_input > 0 && $height_input > 0 ) {
			$detected = \WBAM\Core\Ad_Formats::detect_by_dimensions( $width_input, $height_input );
			return array(
				'format' => $detected, // may auto-upgrade to a named slug when dims match.
				'width'  => $width_input,
				'height' => $height_input,
			);
		}

		// Rule 4: Auto-detect from ad-type data.
		$dims = self::detect_ad_dimensions( $post_id );
		if ( $dims['width'] > 0 && $dims['height'] > 0 ) {
			$detected = \WBAM\Core\Ad_Formats::detect_by_dimensions( $dims['width'], $dims['height'] );
			return array(
				'format' => $detected,
				'width'  => $dims['width'],
				'height' => $dims['height'],
			);
		}

		// Fallback: responsive. Safe permissive default — the ad will
		// render in every placement until someone corrects the format.
		return array(
			'format' => \WBAM\Core\Ad_Formats::RESPONSIVE,
			'width'  => 0,
			'height' => 0,
		);
	}

	/**
	 * Best-effort dimension detection for an ad, read from its type data.
	 *
	 * Image ads: if the image_url points to a local attachment, read the
	 * attachment metadata. External URLs return zeros (we don't fetch
	 * remote images during a save — that's a blocking network call and
	 * a privacy surface).
	 *
	 * Code / AdSense / Rich / Email Capture: no deterministic size, so
	 * we return zeros and let the caller fall back to responsive.
	 *
	 * @since 2.8.1
	 * @param int $post_id Ad post ID.
	 * @return array{width:int, height:int}
	 */
	private static function detect_ad_dimensions( $post_id ) {
		$data = get_post_meta( $post_id, '_wbam_ad_data', true );
		$type = is_array( $data ) && ! empty( $data['type'] ) ? (string) $data['type'] : '';

		if ( 'image' !== $type ) {
			return array(
				'width'  => 0,
				'height' => 0,
			);
		}

		$image_url = isset( $data['image_url'] ) ? (string) $data['image_url'] : '';
		if ( '' === $image_url ) {
			return array(
				'width'  => 0,
				'height' => 0,
			);
		}

		$attachment_id = attachment_url_to_postid( $image_url );
		if ( $attachment_id <= 0 ) {
			return array(
				'width'  => 0,
				'height' => 0,
			);
		}

		$src = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! is_array( $src ) ) {
			return array(
				'width'  => 0,
				'height' => 0,
			);
		}

		return array(
			'width'  => isset( $src[1] ) ? (int) $src[1] : 0,
			'height' => isset( $src[2] ) ? (int) $src[2] : 0,
		);
	}

	/**
	 * Add columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $value ) {
			$new[ $key ] = $value;
			if ( 'title' === $key ) {
				$new['ad_type']     = __( 'Type', 'wb-ads-rotator-with-split-test' );
				$new['placements']  = __( 'Placements', 'wb-ads-rotator-with-split-test' );
				$new['impressions'] = __( 'Impressions', 'wb-ads-rotator-with-split-test' );
				$new['clicks']      = __( 'Clicks', 'wb-ads-rotator-with-split-test' );
				$new['status']      = __( 'Status', 'wb-ads-rotator-with-split-test' );
			}
		}
		return $new;
	}

	/**
	 * Render column.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'ad_type':
				$data    = get_post_meta( $post_id, '_wbam_ad_data', true );
				$type_id = isset( $data['type'] ) ? $data['type'] : '';
				$engine  = Placement_Engine::get_instance();
				$type    = $engine->get_ad_type( $type_id );
				if ( $type ) {
					echo '<span class="dashicons ' . esc_attr( $type->get_icon() ) . '"></span> ' . esc_html( $type->get_name() );
				}
				break;

			case 'placements':
				$placements = get_post_meta( $post_id, '_wbam_placements', true );
				echo ! empty( $placements ) ? esc_html( implode( ', ', $placements ) ) : '—';
				break;

			case 'impressions':
				echo '<strong>' . esc_html( number_format_i18n( $this->get_event_total( $post_id, 'impression' ) ) ) . '</strong>';
				break;

			case 'clicks':
				echo '<strong>' . esc_html( number_format_i18n( $this->get_event_total( $post_id, 'click' ) ) ) . '</strong>';
				break;

			case 'status':
				$enabled = get_post_meta( $post_id, '_wbam_enabled', true );
				$status  = '1' === $enabled ? 'enabled' : 'disabled';
				$text    = '1' === $enabled ? __( 'Enabled', 'wb-ads-rotator-with-split-test' ) : __( 'Disabled', 'wb-ads-rotator-with-split-test' );
				echo wp_kses_post( \WBAM\Admin\UX::status_badge( $status, $text ) );

				// Creative-health marker: an enabled ad whose creative cannot
				// render (image deleted from the media library) is skipped by
				// delivery - without this badge the list said "Enabled" while
				// the slot served nothing and revenue stopped silently.
				if ( '1' === $enabled ) {
					$ad_data      = get_post_meta( $post_id, '_wbam_ad_data', true );
					$type_handler = Placement_Engine::get_instance()->get_ad_type( isset( $ad_data['type'] ) ? $ad_data['type'] : '' );
					if ( $type_handler && method_exists( $type_handler, 'has_creative' ) && ! $type_handler->has_creative( $post_id ) ) {
						echo ' ' . wp_kses_post( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UX::status_badge() output plus a translated title attribute, both escaped inline.
							sprintf(
								'<span class="wbam-status-badge wbam-status-badge--danger" title="%s">%s</span>',
								esc_attr__( 'This ad is skipped by delivery until its creative is restored.', 'wb-ads-rotator-with-split-test' ),
								esc_html__( 'Creative missing', 'wb-ads-rotator-with-split-test' )
							)
						);
					}
				}
				break;
		}
	}

	/**
	 * Lifetime total of an event type for one ad, as shown in the list table.
	 *
	 * Counts rows in the raw events table. That table is not the whole story
	 * once PRO is active: PRO rolls events older than its aggregation window
	 * into `wbam_analytics_daily` and DELETES the raw rows, so a raw-only count
	 * silently decays to zero while Ad Analytics still reports lifetime totals.
	 * The `wbam_ad_event_total` filter is the seam PRO uses to add the
	 * aggregated remainder back, keeping both screens in agreement.
	 *
	 * @since 3.1.1
	 *
	 * @param int    $post_id    Ad ID.
	 * @param string $event_type Event type ('impression' or 'click').
	 * @return int
	 */
	private function get_event_total( $post_id, $event_type ) {
		$cache_key = 'wbam_total_' . $event_type . '_' . $post_id;
		$count     = wp_cache_get( $cache_key, 'wbam' );

		if ( false === $count ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'wbam_analytics';

			if ( $this->table_exists( $table_name ) ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$count = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wbam_analytics WHERE ad_id = %d AND event_type = %s',
						$post_id,
						$event_type
					)
				);
				// phpcs:enable
			} else {
				$count = 0;
			}

			/**
			 * Filters the lifetime event total shown in the ads list table.
			 *
			 * PRO adds the aggregated daily rows, which the raw table no
			 * longer holds after aggregation has run.
			 *
			 * @since 3.1.1
			 *
			 * @param int    $count      Count from the raw events table.
			 * @param int    $post_id    Ad ID.
			 * @param string $event_type Event type ('impression' or 'click').
			 */
			$count = (int) apply_filters( 'wbam_ad_event_total', $count, $post_id, $event_type );

			wp_cache_set( $cache_key, $count, 'wbam', HOUR_IN_SECONDS );
		}

		return absint( $count );
	}

	/**
	 * Invalidate the cached list-table totals for one ad.
	 *
	 * The totals are cached for an hour with no invalidation path before
	 * 3.1.1, so on a site with a persistent object cache the column could sit
	 * stale after every recorded event.
	 *
	 * @since 3.1.1
	 *
	 * @param int $post_id Ad ID.
	 * @return void
	 */
	public static function flush_event_totals( $post_id ) {
		wp_cache_delete( 'wbam_total_impression_' . (int) $post_id, 'wbam' );
		wp_cache_delete( 'wbam_total_click_' . (int) $post_id, 'wbam' );
	}

	/**
	 * Check if a database table exists (with static caching).
	 *
	 * Caches the result to avoid repeated SHOW TABLES queries during
	 * the same request. Tables are created during plugin activation,
	 * so they should always exist at runtime.
	 *
	 * @since 2.3.1
	 *
	 * @param string $table_name Full table name including prefix.
	 * @return bool True if table exists, false otherwise.
	 */
	private function table_exists( $table_name ) {
		// Return cached result if available.
		if ( isset( self::$table_cache[ $table_name ] ) ) {
			return self::$table_cache[ $table_name ];
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		// Cache the result for subsequent calls.
		self::$table_cache[ $table_name ] = ! empty( $exists );

		return self::$table_cache[ $table_name ];
	}
}
