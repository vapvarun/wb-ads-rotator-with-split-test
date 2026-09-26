<?php
/**
 * One money vocabulary across admin and portal: one minus sign, one name for
 * unclassified entries, and an advertiser-facing net that does not read as a
 * loss when money came back to them.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Report_Shell;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Core\Revenue_Query;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Money_Display_Consistency extends Pro_Test_Case {

	public function test_reports_and_everything_else_share_one_minus_sign(): void {
		require_once WBAM_PRO_PATH . 'includes/Admin/class-report-shell.php';

		$this->assertSame( wbam_format_price( -5 ), Report_Shell::money( -5 ) );
		$this->assertSame( '−$5.00', wbam_format_price( -5 ) );
	}

	public function test_a_tiny_negative_does_not_print_minus_zero(): void {
		$this->assertSame( '$0.00', wbam_format_price( -0.001 ) );
	}

	public function test_unclassified_entries_have_one_name(): void {
		$this->assertSame( 'Adjustments', Revenue_Query::source_label( Revenue_Ledger::SOURCE_UNCLASSIFIED ) );
	}

	/**
	 * A credit grant is money in, not spending: "Where your credits went"
	 * does not list it (owner decision 2026-09-26, card 10340185077).
	 */
	public function test_wallet_by_type_does_not_list_a_credit_grant(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'wbam_revenue' ); // phpcs:ignore WordPress.DB -- test isolation.

		$user       = (int) self::factory()->user->create();
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Credits_Bridge::adjust( $advertiser->id, 5, 'Goodwill credit' );
		$advertiser = Advertiser_Manager::get_instance()->get( (int) $advertiser->id );

		ob_start();
		include WBAM_PRO_PATH . 'templates/portal/tabs/wallet.php';
		$html = ob_get_clean();

		$this->assertStringNotContainsString( '$5.00 back to you', $html );
		$this->assertStringNotContainsString( '<th scope="row" class="wbam-campaign-spend__name">Adjustments</th>', $html );
	}
}
