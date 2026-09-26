<?php
/**
 * First-Install Pointers
 *
 * Ships contextual WP-pointer tooltips that fire once per user on
 * the first visit to specific admin screens after a fresh install.
 * Upgrades intentionally skip this (flag defaults off) so power
 * users aren't annoyed by guidance they don't need.
 *
 * @package WB_Ad_Manager
 * @since   2.8.1
 */

namespace WBAM\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * First_Install_Pointers class.
 *
 * Pattern mirrors WordPress core's built-in WP_Internal_Pointers:
 * enqueue wp-pointer, print inline JS that iterates a pointer
 * definition array, persist per-user dismissal via user meta.
 *
 * Separate class from the pro-plugin counterpart (G.3) on purpose
 * — each plugin owns its own option key + meta key so free and
 * pro never entangle.
 */
class First_Install_Pointers {

	/**
	 * Site option that gates the whole feature. Defaults on for
	 * fresh installs, off for upgrades — the Installer sets it
	 * at install time.
	 *
	 * @var string
	 */
	const OPTION_ENABLED = 'wbam_onboarding_pointers_enabled';

	/**
	 * User meta key that stores dismissed pointer slugs
	 * (array of slug strings). Same user meta shape as
	 * WordPress core's dismissed_wp_pointers, but namespaced
	 * so we don't collide with core-owned pointers.
	 *
	 * @var string
	 */
	const META_DISMISSED = 'wbam_pointers_shown';

	/**
	 * AJAX action used as a fallback to persist dismissals
	 * server-side. Also serves as the action name for the
	 * nonce attached to each pointer.
	 *
	 * @var string
	 */
	const AJAX_ACTION = 'wbam_dismiss_pointer';

	/**
	 * Register hooks.
	 *
	 * Called once from Plugin::init_components(). Safe to call
	 * only on admin requests — the registrations are all admin-
	 * side hooks so the class never fires for frontend traffic.
	 */
	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Enqueue wp-pointer + print inline JS for any pointer that
	 * hasn't been dismissed yet by the current user.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue( $hook_suffix ) {
		// Feature-flag gate. Defaults to 0 on upgrades, 1 on fresh installs.
		if ( ! (int) get_option( self::OPTION_ENABLED, 0 ) ) {
			return;
		}

		$pointers = $this->get_pointers_for_screen( $hook_suffix );

		if ( empty( $pointers ) ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$shown = get_user_meta( $user_id, self::META_DISMISSED, true );
		if ( ! is_array( $shown ) ) {
			$shown = array();
		}

		// Only show pointers the user hasn't dismissed yet.
		$remaining = array_values( array_diff( array_keys( $pointers ), $shown ) );

		if ( empty( $remaining ) ) {
			return;
		}

		// Emit only the pointers we're going to use, in stable order.
		$emit = array();
		foreach ( $remaining as $slug ) {
			$emit[ $slug ] = $pointers[ $slug ];
		}

		Pointer_Emitter::emit( $emit, self::AJAX_ACTION, 'wbam-pointer' );
	}

	/**
	 * Handle the AJAX dismissal. Mirrors core's dismiss-wp-pointer
	 * contract but writes to our own meta key so we control the
	 * list authoritatively (array_diff against the definition
	 * list — no stale slugs).
	 */
	public function ajax_dismiss() {
		check_ajax_referer( self::AJAX_ACTION );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'no_user', 403 );
		}

		// The pointers only appear on the ad edit screen, so only people who can
		// edit ads have anything to dismiss.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}

		$slug = isset( $_POST['pointer'] ) ? sanitize_key( wp_unslash( $_POST['pointer'] ) ) : '';
		if ( '' === $slug ) {
			wp_send_json_error( 'no_slug', 400 );
		}

		// Only accept slugs we actually define — keeps the meta list clean.
		$all = array_keys( $this->get_pointer_definitions() );
		if ( ! in_array( $slug, $all, true ) ) {
			wp_send_json_error( 'unknown_slug', 400 );
		}

		$shown = get_user_meta( $user_id, self::META_DISMISSED, true );
		if ( ! is_array( $shown ) ) {
			$shown = array();
		}

		if ( ! in_array( $slug, $shown, true ) ) {
			$shown[] = $slug;
			update_user_meta( $user_id, self::META_DISMISSED, $shown );
		}

		wp_send_json_success();
	}

	/**
	 * Filter the full pointer definition list down to the ones
	 * relevant for the current admin screen.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return array<string,array> Slug => definition.
	 */
	private function get_pointers_for_screen( $hook_suffix ) {
		// All three current pointers live on the ad edit screen
		// (post.php with post_type=wbam-ad, or post-new.php).
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return array();
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'wbam-ad' !== $screen->post_type ) {
			return array();
		}

		return $this->get_pointer_definitions();
	}

	/**
	 * Canonical pointer definitions. Keep slugs stable — they
	 * are persisted into user meta and renaming one would
	 * resurface the pointer for every user who already dismissed it.
	 *
	 * @return array<string,array>
	 */
	private function get_pointer_definitions() {
		return array(
			'sizing_section'  => array(
				'target'  => '.wbam-sizing-section',
				'title'   => esc_html__( 'Pick how this ad is sized', 'wb-ads-rotator-with-split-test' ),
				'content' => esc_html__( 'Responsive ads fill any slot. Fixed-size ads only render where the size matches. The system auto-filters placements below based on your choice.', 'wb-ads-rotator-with-split-test' ),
				'edge'    => 'top',
				'align'   => 'left',
			),
			'priority_slider' => array(
				'target'  => '#wbam_priority',
				'title'   => esc_html__( 'Priority controls your win share', 'wb-ads-rotator-with-split-test' ),
				'content' => esc_html__( 'When multiple ads compete for the same slot, higher priority wins a bigger share. Default is 5. Live hint below shows the exact win percentage in a three-way tie.', 'wb-ads-rotator-with-split-test' ),
				// E2 fix: this target lives in the narrow sidebar column,
				// close to the viewport's right edge — the shared 420px
				// width (needed for the wider content-area pointers below)
				// pushed its Close button past the edge. 'bottom' opens the
				// box above the slider instead of below it, so it no longer
				// covers the Max Views field underneath.
				'width'   => 260,
				'edge'    => 'bottom',
				'align'   => 'left',
			),
			'placement_cards' => array(
				'target'  => '.wbam-placement-options',
				'title'   => esc_html__( 'Where your ad can render', 'wb-ads-rotator-with-split-test' ),
				'content' => esc_html__( 'These are the placement slots this ad qualifies for. Pick one or more. The rotation engine will decide which slot fires on each page load.', 'wb-ads-rotator-with-split-test' ),
				'edge'    => 'top',
				'align'   => 'left',
			),
		);
	}
}
