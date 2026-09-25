<?php
/**
 * Installer: the indexes the admin lists rely on exist after install, on
 * tables PRO does not create itself.
 *
 * FREE creates wbam_analytics before PRO activates, so PRO's CREATE TABLE IF
 * NOT EXISTS was a no-op and its declared ad_event_date key never existed;
 * the SDK ledger had no created_at index for the Transactions date range.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Installer;

class Test_Installer_List_Indexes extends Pro_Test_Case {

	private function has_index( string $table, string $index ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$wpdb->prefix}{$table} WHERE Key_name = %s", $index ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function test_install_adds_the_list_indexes(): void {
		global $wpdb;

		foreach ( array( 'wbam_analytics' => 'ad_event_date', 'wbam_credit_ledger' => 'created_at' ) as $table => $index ) {
			if ( $this->has_index( $table, $index ) ) {
				$wpdb->query( "ALTER TABLE {$wpdb->prefix}{$table} DROP INDEX {$index}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		Installer::install( false );

		$this->assertTrue( $this->has_index( 'wbam_analytics', 'ad_event_date' ) );
		$this->assertTrue( $this->has_index( 'wbam_credit_ledger', 'created_at' ) );
	}
}
