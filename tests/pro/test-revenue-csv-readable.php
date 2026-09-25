<?php
/**
 * The Revenue CSV is written for the owner's spreadsheet.
 *
 * It had snake_case headings with internal ids (ledger_id, advertiser_id,
 * item_id), whole-number amounts ("49"), a lowercase currency ("usd") and raw
 * MySQL dates.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Core\Revenue_Query;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

/**
 * @group pro
 * @group reports
 */
class Test_Revenue_Csv_Readable extends Pro_Test_Case {

	public function test_csv_has_readable_headings_amounts_and_dates(): void {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'wbam_revenue' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation.

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $user, 100000, 'seed' );
		$this->assertNotWPError( Credits_Bridge::charge( $advertiser->id, 49.00, 1, 'pkg', false, Revenue_Ledger::SOURCE_AD_PACKAGE ) );

		$handle = fopen( 'php://memory', 'w+' );
		Revenue_Query::stream_csv( gmdate( 'Y-m-d', strtotime( '-1 day' ) ), gmdate( 'Y-m-d', strtotime( '+1 day' ) ), $handle );
		rewind( $handle );
		$header = fgetcsv( $handle );
		$row    = fgetcsv( $handle );
		fclose( $handle );

		$this->assertSame( array( 'Date', 'Transaction ID', 'Advertiser', 'Type', 'Item', 'Amount', 'Currency' ), $header );
		$this->assertSame( '49.00', $row[5] );
		$this->assertSame( 'USD', $row[6] );
		$this->assertDoesNotMatchRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row[0], 'Dates use the site format, not raw MySQL.' );
	}
}
