<?php
/**
 * Shared factory helpers for test data creation.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Helpers;

class Factory {

	/**
	 * Forget the ads "rendered on this page". Frequency_Manager is a
	 * per-request singleton, so without this every render in the suite
	 * counts toward one page's max_ads_per_page and later tests see ads
	 * withheld for a limit their own page never reached.
	 */
	public static function reset_page_ads(): void {
		$page_ads = new \ReflectionProperty( \WBAM\Modules\Targeting\Frequency_Manager::class, 'page_ads' );
		$page_ads->setAccessible( true );
		$page_ads->setValue( \WBAM\Modules\Targeting\Frequency_Manager::get_instance(), array() );
	}

	/**
	 * Create a wbam-ad post with sensible defaults.
	 */
	public static function make_ad( array $overrides = array() ): int {
		$args = array_merge(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
				'post_title'  => 'Test Ad',
			),
			$overrides
		);

		return (int) wp_insert_post( $args );
	}

	/**
	 * Create a wbam-classified post (pro).
	 */
	public static function make_classified( array $overrides = array() ): int {
		if ( ! post_type_exists( 'wbam-classified' ) ) {
			return 0;
		}

		$args = array_merge(
			array(
				'post_type'   => 'wbam-classified',
				'post_status' => 'publish',
				'post_title'  => 'Test Classified',
			),
			$overrides
		);

		return (int) wp_insert_post( $args );
	}

	/**
	 * Create a subscriber user.
	 */
	public static function make_user( string $role = 'subscriber' ): int {
		return (int) self::factory()->user->create( array( 'role' => $role ) );
	}

	/**
	 * Top-up a user's credit balance via the SDK. Returns the ledger id.
	 */
	public static function topup_user( int $user_id, int $amount, string $slug = 'wbam-pro' ) {
		if ( ! class_exists( '\\Wbcom\\Credits\\Credits' ) ) {
			return false;
		}

		return \Wbcom\Credits\Credits::topup( $slug, $user_id, $amount, 'test topup' );
	}

	/**
	 * Shortcut to the global WP test factory (installed by the test harness).
	 */
	private static function factory() {
		return \WP_UnitTestCase_Base::factory();
	}
}
