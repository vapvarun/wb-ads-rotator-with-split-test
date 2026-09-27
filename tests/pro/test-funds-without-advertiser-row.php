<?php
/**
 * A credit pack bought before applying (card 10344497657): on an ads-only
 * site the buyer sees their money and the next step, not the bare
 * "Advertiser Account Required" wall.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Funds_Without_Advertiser_Row extends Pro_Test_Case {

	public function set_up(): void {
		$this->snapshot_options( array( 'wbam_pro_settings' ) );
		parent::set_up();

		$enabled                   = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds']    = false;
		$enabled['wallet']         = true;
		$enabled['ad_submissions'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
	}

	public function tear_down(): void {
		unset( $_GET['tab'] );
		parent::tear_down();
	}

	private function portal( int $user_id, string $tab = '' ): string {
		wp_set_current_user( $user_id );
		if ( $tab ) {
			$_GET['tab'] = $tab;
		}
		return do_shortcode( '[wbam_advertiser_dashboard]' );
	}

	public function test_a_buyer_with_funds_sees_them_on_an_ads_only_site(): void {
		$user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		Factory::topup_user( $user, 1000 );
		$this->assertNull( Advertiser_Manager::get_instance()->get_by_user( $user ) );

		$html = $this->portal( $user );

		$this->assertStringNotContainsString( 'Advertiser Account Required', $html );
		$this->assertStringContainsString( 'tab=wallet', $html, 'The Balance tab is reachable.' );
		$this->assertTrue( Credits_Bridge::user_has_money_history( $user ) );
	}

	public function test_a_user_with_no_money_still_gets_the_registration_prompt(): void {
		$html = $this->portal( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertStringContainsString( 'Advertiser Account Required', $html );
	}

	public function test_the_balance_tab_opens_for_money_on_an_ads_only_site(): void {
		$user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		Factory::topup_user( $user, 1000 );

		$html = $this->portal( $user, 'wallet' );

		// QA wave 11: the sidebar linked here but the tab said "not available".
		$this->assertStringNotContainsString( 'not available', $html );
		$this->assertStringNotContainsString( 'Advertiser Account Required', $html );
	}

	public function test_revenue_booked_before_the_member_row_gets_its_owner(): void {
		global $wpdb;
		$user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		Factory::topup_user( $user, 1000 );
		$table = \WBAM_Pro\Core\Revenue_Ledger::table_name();
		$ledger = \Wbcom\Credits\Ledger::table_name( Credits_Bridge::PREFIX );
		$owner = static fn () => $wpdb->get_col( $wpdb->prepare( "SELECT r.advertiser_id FROM {$table} r INNER JOIN {$ledger} l ON l.id = r.ledger_id WHERE l.user_id = %d", $user ) ); // phpcs:ignore WordPress.DB
		$this->assertSame( array( '0' ), $owner(), 'Booked before any advertiser row.' );

		$this->portal( $user ); // Creates the member row.
		$member = Advertiser_Manager::get_instance()->get_by_user( $user );

		$this->assertSame( array( (string) $member->id ), $owner() );
	}
}
