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

	private function ledger_row( string $stored, ?string $twin ): int {
		global $wpdb;
		$id = Ledger::insert( 'wbam', (int) self::factory()->user->create(), 'topup', 100 );
		$wpdb->update( Ledger::table_name( 'wbam' ), array( 'created_at' => $stored ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		if ( null !== $twin ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB
				$wpdb->prefix . 'wbam_revenue',
				array(
					'ledger_id'  => $id,
					'source'     => 'topup',
					'amount'     => 100,
					'created_at' => $twin,
				)
			);
		}
		return $id;
	}

	private function stored( int $id ): string {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT created_at FROM ' . Ledger::table_name( 'wbam' ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB
	}

	private function convert_at( string $zone, bool $old_run = false ): void {
		global $wpdb;
		delete_option( \WBAM_Pro\Core\Installer::SDK_UTC_OPTION );
		$old_run ? update_option( 'wbam_pro_sdk_utc_migration', array( 'done' => true ) ) : delete_option( 'wbam_pro_sdk_utc_migration' );
		$wpdb->query( $wpdb->prepare( 'SET time_zone = %s', $zone ) ); // phpcs:ignore WordPress.DB -- the server clock under test.
		\WBAM_Pro\Core\Installer::continue_sdk_utc_migration();
		\WBAM_Pro\Core\Installer::continue_sdk_utc_migration(); // Idempotent.
	}

	public function test_4_3_24_anchors_on_the_revenue_twin_and_shifts_the_rest_once(): void {
		// Winter row: the server wrote 15:30 at +05:30; its UTC twin says 10:00.
		$twin     = $this->ledger_row( '2026-01-15 15:30:00', '2026-01-15 10:00:00' );
		$twinless = $this->ledger_row( '2026-09-27 10:00:00', null );

		$this->convert_at( '+05:30' );

		$this->assertSame( '2026-01-15 10:00:00', $this->stored( $twin ), 'The twin\'s exact UTC time.' );
		$this->assertSame( '2026-09-27 04:30:00', $this->stored( $twinless ), 'Shifted by the offset, once.' );
		$this->assertFalse( \WBAM_Pro\Core\Installer::continue_sdk_utc_migration(), 'Done: nothing left.' );
	}

	public function test_4_3_24_touches_nothing_on_a_utc_server(): void {
		$row = $this->ledger_row( '2026-01-15 10:00:01', '2026-01-15 10:00:00' );

		$this->convert_at( '+00:00' );

		$this->assertSame( '2026-01-15 10:00:01', $this->stored( $row ) );
	}

	public function test_4_3_24_never_shifts_rows_the_first_cut_already_moved(): void {
		$twin     = $this->ledger_row( '2026-01-15 04:30:00', '2026-01-15 10:00:00' );
		$twinless = $this->ledger_row( '2026-09-27 04:30:00', null );

		$this->convert_at( '+05:30', true );

		$this->assertSame( '2026-01-15 10:00:00', $this->stored( $twin ), 'Corrected to the twin.' );
		$this->assertSame( '2026-09-27 04:30:00', $this->stored( $twinless ), 'Not shifted a second time.' );
	}

	public function test_ledger_times_are_read_as_utc(): void {
		$this->assertSame( strtotime( '2026-09-27 10:00:00 UTC' ), \WBAM_Pro\Core\Revenue_Ledger::ledger_timestamp( '2026-09-27 10:00:00' ) );
	}
}
