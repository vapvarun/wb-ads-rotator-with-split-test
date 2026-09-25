<?php
/**
 * Audit Log "Export CSV" downloads the filtered log.
 *
 * The button linked to `action=export`, which nothing handled: the click
 * reloaded the list and no file ever arrived.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Audit_Log_List_Table;
use WBAM_Pro\Core\Audit_Logger;

/**
 * @group pro
 * @group reports
 */
class Test_Audit_Log_Export extends Pro_Test_Case {

	public function tear_down(): void {
		unset( $_GET['object_type'] );
		parent::tear_down();
	}

	public function test_export_streams_the_filtered_log(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_audit_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation.

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator', 'display_name' => 'Owner Olga' ) ) );
		Audit_Logger::get_instance()->log( 'update', 'campaign', 12 );
		Audit_Logger::get_instance()->log( 'update', 'package', 3 );

		$_GET['object_type'] = 'campaign';

		$handle = fopen( 'php://memory', 'w+' );
		Audit_Log_List_Table::stream_csv( $handle );
		rewind( $handle );
		$lines = array();
		while ( false !== ( $line = fgetcsv( $handle ) ) ) {
			$lines[] = $line;
		}
		fclose( $handle );

		$this->assertSame( array( 'Date', 'User', 'Action', 'Object Type', 'Object ID', 'IP Address' ), $lines[0] );
		$this->assertCount( 2, $lines, 'Only the campaign entry matches the filter.' );
		$this->assertSame( 'Owner Olga', $lines[1][1] );
		$this->assertSame( 'Campaign', $lines[1][3] );
		$this->assertSame( '12', $lines[1][4] );
	}
}
