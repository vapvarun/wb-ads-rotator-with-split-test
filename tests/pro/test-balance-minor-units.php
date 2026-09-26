<?php
/**
 * Balances are exact: get_balance() is rounded to the currency, and
 * get_balance_minor() / can_afford() compare integer minor units, so float
 * error (0.1 + 0.2 = 0.30000000000000004) never refuses a purchase the
 * advertiser can pay for.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

/**
 * @group pro
 * @group money
 */
class Test_Balance_Minor_Units extends Pro_Test_Case {

	private int $user;
	private int $advertiser_id;

	public function set_up(): void {
		parent::set_up();
		$this->user          = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser_id = (int) Advertiser_Manager::get_instance()->get_or_create( $this->user )->id;
	}

	public function test_balance_is_rounded_and_exact_in_minor_units(): void {
		// 5.50 - 0.01, then 0.10 + 0.20.
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 550, 'seed' );
		\Wbcom\Credits\Credits::adjust( 'wbam-pro', $this->user, -1, 'debit' );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 10, 'a' );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 20, 'b' );

		$this->assertSame( 579, Credits_Bridge::get_balance_minor( $this->advertiser_id ) );
		$this->assertSame( round( 5.79, wbam_get_currency_decimals() ), Credits_Bridge::get_balance( $this->advertiser_id ) );
	}

	public function test_can_afford_compares_minor_units(): void {
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 30, 'seed' );

		$this->assertTrue( Credits_Bridge::can_afford( $this->advertiser_id, 0.1 + 0.2 ), '0.30 covers a 0.1 + 0.2 fee.' );
		$this->assertFalse( Credits_Bridge::can_afford( $this->advertiser_id, 0.31 ) );
	}

	public function test_featured_affordability_uses_minor_units(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_classifieds" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation (lazy DDL commits).

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 30, 'seed' );
		$classified = Classified_Manager::get_instance()->create(
			array(
				'title'         => 'Affordability probe',
				'description'   => 'Probe.',
				'advertiser_id' => $this->advertiser_id,
			)
		);
		$this->assertNotWPError( $classified );

		$this->assertTrue( $classified->can_afford_featured( 0.1 + 0.2 ) );
	}

	public function test_advertiser_without_user_has_zero_minor_balance(): void {
		$this->assertSame( 0, Credits_Bridge::get_balance_minor( 999999 ) );
	}
}
