<?php
/**
 * 'Delete Data on Uninstall' promised all data, but Pro had no uninstall
 * hook, so its tables and options survived. Owner decision 2026-09-26:
 * wire Pro's cleanup. These tests cover the switch (read without any plugin
 * class loaded) and that the drop list covers every table Pro creates.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Installer;

class Test_Pro_Uninstall extends Pro_Test_Case {

	public function tear_down(): void {
		delete_option( 'wbam_delete_data_on_uninstall' );
		parent::tear_down();
	}

	public function test_switch_off_keeps_data(): void {
		update_option( 'wbam_settings', array( 'delete_data_on_uninstall' => false ) );
		$this->assertFalse( Installer::delete_data_on_uninstall() );
	}

	public function test_switch_on_deletes_data(): void {
		update_option( 'wbam_settings', array( 'delete_data_on_uninstall' => true ) );
		$this->assertTrue( Installer::delete_data_on_uninstall() );
	}

	public function test_flag_left_by_free_uninstall_is_honoured(): void {
		delete_option( 'wbam_settings' );
		update_option( 'wbam_delete_data_on_uninstall', 1 );
		$this->assertTrue( Installer::delete_data_on_uninstall() );
	}

	public function test_drop_list_covers_every_pro_table(): void {
		global $wpdb;

		// Tables Free creates and drops in its own uninstall.
		$free = array( 'wbam_email_submissions', 'wbam_link_partnerships', 'wbam_rate_limits', 'wbam_links', 'wbam_link_clicks', 'wbam_link_categories' );

		$live = array();
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'wbam_' ) . '%' ) ) as $table ) {
			$live[] = substr( $table, strlen( $wpdb->prefix ) );
		}

		$missing = array_diff( $live, Installer::UNINSTALL_TABLES, $free );
		$this->assertSame( array(), array_values( $missing ), 'Pro tables an opted-in uninstall would leave behind.' );
	}

	public function test_uninstall_file_calls_the_cleanup(): void {
		$file = file_get_contents( dirname( WBAM_PRO_FILE ) . '/uninstall.php' );
		$this->assertStringContainsString( 'WP_UNINSTALL_PLUGIN', $file );
		$this->assertStringContainsString( 'Installer::uninstall()', $file );
	}
}
