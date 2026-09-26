<?php
/**
 * The portal stack (chartjs, icons, portal.js/css, ~880 KB) must load only
 * on the BuddyPress profile screens that actually render it (Ads,
 * Classifieds, Wallet tabs) - not on every BuddyPress page. Owner
 * decision, card 10342761510.
 *
 * BuddyPress itself is not installed in this test environment (every real
 * call site is guarded by class_exists('BuddyPress')/function_exists()),
 * so this test defines the two BP functions the gate reads and drives
 * BuddyPress_Integration::enqueue_styles() directly via reflection
 * (its constructor bails early without a live BuddyPress).
 *
 * @package WBAM\Tests
 */

// Declared in the global namespace (bracketed syntax) so PHP's
// unqualified-function fallback resolves them from
// WBAM_Pro\Modules\BuddyPress - the same way a real BuddyPress plugin's
// global bp_is_user()/bp_current_component() would be found.
namespace {
	if ( ! function_exists( 'bp_is_user' ) ) {
		function bp_is_user() {
			return $GLOBALS['wbam_test_bp_is_user'] ?? false;
		}
	}

	if ( ! function_exists( 'bp_current_component' ) ) {
		function bp_current_component() {
			return $GLOBALS['wbam_test_bp_current_component'] ?? '';
		}
	}
}

namespace WBAM\Tests\Pro {

use WBAM_Pro\Modules\BuddyPress\BuddyPress_Integration;

class Test_BuddyPress_Portal_Gate extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		// wp_styles()/wp_scripts() are process-global. Another test in this
		// run may have already printed the portal stack (wp_style_is(...,
		// 'enqueued') is also true for a handle already 'done', not only a
		// queued one) - start from a known "not yet on" state rather than
		// depending on run order.
		foreach ( array( 'wbam-pro-portal', 'wbam-pro-buddypress' ) as $handle ) {
			wp_dequeue_style( $handle );
			wp_styles()->done = array_diff( wp_styles()->done, array( $handle ) );
		}
		wp_dequeue_script( 'wbam-pro-portal' );
		wp_scripts()->done = array_diff( wp_scripts()->done, array( 'wbam-pro-portal' ) );
	}

	public function tear_down(): void {
		unset( $GLOBALS['wbam_test_bp_is_user'], $GLOBALS['wbam_test_bp_current_component'] );
		wp_dequeue_style( 'wbam-pro-portal' );
		wp_deregister_style( 'wbam-pro-portal' );
		wp_dequeue_script( 'wbam-pro-portal' );
		wp_deregister_script( 'wbam-pro-portal' );
		wp_dequeue_style( 'wbam-pro-buddypress' );
		wp_deregister_style( 'wbam-pro-buddypress' );
		parent::tear_down();
	}

	private function integration(): BuddyPress_Integration {
		$ref = new \ReflectionClass( BuddyPress_Integration::class );
		return $ref->newInstanceWithoutConstructor();
	}

	/**
	 * wp_style_is( $handle, 'enqueued' ) is also true when $handle is a
	 * dependency of some OTHER handle that is itself queued
	 * (WP_Dependencies::recurse_deps()) - another suite in this run can
	 * leave 'wbam-pro-classified' (which depends on 'wbam-pro-portal')
	 * queued and never dequeued, which would make this true regardless of
	 * our own gate. Check direct queue membership instead - precisely what
	 * our own enqueue_style()/enqueue_script() calls did or didn't do.
	 */
	private function directly_queued( string $handle, string $type = 'style' ): bool {
		$registry = 'style' === $type ? wp_styles() : wp_scripts();
		return in_array( $handle, $registry->queue, true );
	}

	public function test_activity_page_does_not_load_the_portal_stack(): void {
		$GLOBALS['wbam_test_bp_is_user']          = false;
		$GLOBALS['wbam_test_bp_current_component'] = 'activity';

		$this->integration()->enqueue_styles();

		$this->assertFalse( $this->directly_queued( 'wbam-pro-portal', 'style' ) );
		$this->assertFalse( $this->directly_queued( 'wbam-pro-portal', 'script' ) );
	}

	public function test_a_non_portal_profile_tab_does_not_load_the_portal_stack(): void {
		$GLOBALS['wbam_test_bp_is_user']          = true;
		$GLOBALS['wbam_test_bp_current_component'] = 'notifications';

		$this->integration()->enqueue_styles();

		$this->assertFalse( $this->directly_queued( 'wbam-pro-portal', 'style' ) );
	}

	public function test_the_ads_portal_tab_loads_the_portal_stack(): void {
		$GLOBALS['wbam_test_bp_is_user']          = true;
		$GLOBALS['wbam_test_bp_current_component'] = 'ads';

		$this->integration()->enqueue_styles();

		$this->assertTrue( wp_style_is( 'wbam-pro-portal', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'wbam-pro-portal', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'wbam-pro-buddypress', 'enqueued' ) );
	}
}
}
