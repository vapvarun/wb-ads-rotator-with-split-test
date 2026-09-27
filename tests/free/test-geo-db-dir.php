<?php
/**
 * An uploaded MaxMind database is kept where the web server cannot serve
 * it: .htaccess only guards on Apache, and on nginx a file under uploads is
 * downloadable by URL (card 10344383905, owner follow-up step).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Settings;

class Test_Geo_Db_Dir extends \WP_UnitTestCase {

	private function dir(): string {
		$method = new \ReflectionMethod( Settings::class, 'geo_db_dir' );
		$method->setAccessible( true );
		return (string) $method->invoke( null );
	}

	public function tear_down(): void {
		$dir = (string) get_option( 'wbam_geo_db_dir', '' );
		if ( $dir && is_dir( $dir ) ) {
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test cleanup.
		}
		delete_option( 'wbam_geo_db_dir' );
		remove_all_filters( 'wbam_geo_db_dir' );
		parent::tear_down();
	}

	public function test_the_folder_is_outside_the_web_root_when_writable(): void {
		$dir = $this->dir();

		$this->assertSame( dirname( untrailingslashit( ABSPATH ) ) . '/wbam-geo', $dir );
		$this->assertStringStartsNotWith( untrailingslashit( ABSPATH ), $dir );
		$this->assertDirectoryExists( $dir );
		$this->assertSame( $dir, get_option( 'wbam_geo_db_dir' ), 'Remembered, so a later upload lands in the same place.' );
	}

	public function test_a_filter_chooses_the_folder(): void {
		$chosen = get_temp_dir() . 'wbam-geo-custom-' . wp_generate_password( 6, false );
		add_filter( 'wbam_geo_db_dir', static fn () => $chosen );

		$this->assertSame( $chosen, $this->dir() );
		@rmdir( $chosen ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test cleanup.
	}

	public function test_an_uploads_fallback_has_an_unguessable_name(): void {
		// A remembered folder that can no longer be created is skipped.
		update_option( 'wbam_geo_db_dir', '/proc/not-writable/wbam-geo' );
		add_filter( 'wbam_geo_db_dir', static fn () => '/proc/not-writable/wbam-geo' );

		$dir = $this->dir();

		$this->assertNotSame( '/proc/not-writable/wbam-geo', $dir );
		$this->assertDirectoryExists( $dir );
	}
}
