<?php
/**
 * Card 10344383905: hiding ads from roles lives in Free's "Who sees ads"
 * card, and the currency setting warns once real money is on record.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Pro_Admin;

class Test_Who_Sees_Ads_And_Money_Wording extends Pro_Test_Case {

	public function test_role_hiding_is_a_row_in_who_sees_ads(): void {
		global $wp_settings_fields;

		( new Pro_Admin() )->register_settings();

		$this->assertArrayHasKey( 'wbam_pro_ad_blocked_roles', $wp_settings_fields['wbam-settings']['wbam_general'] ?? array() );
	}

	public function test_ledger_history_is_detected(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . \Wbcom\Credits\Ledger::table_name( Credits_Bridge::PREFIX ) ); // phpcs:ignore WordPress.DB -- test isolation.
		$this->assertFalse( Credits_Bridge::has_ledger_history() );

		Factory::topup_user( (int) self::factory()->user->create(), 500 );

		$this->assertTrue( Credits_Bridge::has_ledger_history() );
	}
}
