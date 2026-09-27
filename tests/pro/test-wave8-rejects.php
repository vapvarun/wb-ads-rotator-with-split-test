<?php
/**
 * QA wave 7/8 rejects (cards 10344382999, 10344269919).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Core\Settings_Helper;
use Wbcom\Credits\Ledger;

class Test_Wave8_Rejects extends Pro_Test_Case {

	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( "SET time_zone = '+00:00'" ); // phpcs:ignore WordPress.DB -- restore the session clock.
		parent::tear_down();
	}

	public function test_country_note_shows_whenever_personal_details_are_stripped(): void {
		$this->snapshot_options( array( 'wbam_pro_settings' ) );
		Settings_Helper::update( 'gdpr_anonymize_ip', true );

		ob_start();
		( new Pro_Admin() )->render_location_content_card();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Country reports need', $html, 'Not tied to the classifieds maps module.' );
	}

	public function test_sdk_rows_are_stamped_in_utc_whatever_the_database_clock(): void {
		global $wpdb;
		$wpdb->query( "SET time_zone = '+05:30'" ); // phpcs:ignore WordPress.DB -- a non-UTC database session.

		$id  = Ledger::insert( 'wbam', (int) self::factory()->user->create(), 'topup', 100 );
		$row = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT created_at FROM ' . Ledger::table_name( 'wbam' ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB

		$this->assertLessThan( 120, abs( strtotime( $row . ' UTC' ) - time() ), "Stamped {$row}, not UTC." );
	}

	public function test_4_3_23_shifts_old_sdk_rows_by_the_server_offset(): void {
		global $wpdb;
		$table = Ledger::table_name( 'wbam' );
		$id    = Ledger::insert( 'wbam', (int) self::factory()->user->create(), 'topup', 100 );
		$wpdb->update( $table, array( 'created_at' => '2026-09-27 10:00:00' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		delete_option( 'wbam_pro_sdk_utc_migration' );
		$wpdb->query( "SET time_zone = '+05:30'" ); // phpcs:ignore WordPress.DB -- server clock 5.5h ahead.

		$method = new \ReflectionMethod( \WBAM_Pro\Core\Installer::class, 'upgrade_to_4_3_23' );
		$method->invoke( null );
		$method->invoke( null ); // Idempotent.

		$this->assertSame( '2026-09-27 04:30:00', (string) $wpdb->get_var( $wpdb->prepare( "SELECT created_at FROM {$table} WHERE id = %d", $id ) ) ); // phpcs:ignore WordPress.DB
	}
}
