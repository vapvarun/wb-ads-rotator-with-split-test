<?php
/**
 * CSV exports never hand a spreadsheet a live formula.
 *
 * A cell a visitor, seller or advertiser typed (an ad title, a listing
 * title, a captured email) that starts with = + - @, a tab or a CR runs
 * as a formula when the owner opens the export in Excel or Sheets. Every
 * export writes through wbam_fputcsv(), which neutralises those cells.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Admin\Email_Captures;
use WBAM\Tests\Helpers\Factory;

/**
 * @group pro
 * @group security
 */
class Test_CSV_Formula_Injection extends Pro_Test_Case {

	/**
	 * Write one row through the shared writer and read it back.
	 *
	 * @param array $row Cells.
	 * @return array
	 */
	private function round_trip( array $row ): array {
		$handle = fopen( 'php://memory', 'w+' );
		wbam_fputcsv( $handle, $row );
		rewind( $handle );
		$line = fgetcsv( $handle );
		fclose( $handle );
		return $line;
	}

	public function test_shared_writer_neutralises_formula_cells(): void {
		$line = $this->round_trip(
			array(
				'=HYPERLINK("http://evil.test","Click")',
				'+cmd|\' /C calc\'!A0',
				'-2+3',
				'@SUM(A1:A2)',
				"\t=1+1",
				"\r=1+1",
				'Plain title',
				'-8.00',
				42,
				'-',
			)
		);

		$this->assertSame( '\'=HYPERLINK("http://evil.test","Click")', $line[0] );
		$this->assertSame( '\'+cmd|\' /C calc\'!A0', $line[1] );
		$this->assertSame( "'-2+3", $line[2] );
		$this->assertSame( "'@SUM(A1:A2)", $line[3] );
		$this->assertSame( "'\t=1+1", $line[4] );
		$this->assertSame( "'\r=1+1", $line[5] );
		$this->assertSame( 'Plain title', $line[6], 'Ordinary text is untouched.' );
		$this->assertSame( '-8.00', $line[7], 'A negative amount stays a number.' );
		$this->assertSame( '42', $line[8] );
		$this->assertSame( '-', $line[9], 'A lone placeholder dash is not a formula.' );
	}

	public function test_email_captures_export_neutralises_visitor_input(): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_email_submissions',
			array(
				'ad_id'      => Factory::make_ad( array( 'post_title' => 'Newsletter' ) ),
				'email'      => 'eve@example.org',
				'name'       => '=HYPERLINK("http://evil.test","Win")',
				'created_at' => '2026-09-01 10:00:00',
			)
		);

		$handle = fopen( 'php://memory', 'w+' );
		( new Email_Captures() )->stream_csv( $handle, array() );
		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );

		$this->assertStringContainsString( '"\'=HYPERLINK(', $csv );
	}

	/**
	 * The guard that keeps the other exports honest: Classifieds, Ad
	 * Analytics, Impression Audit, portal analytics, Revenue, Transactions,
	 * Campaigns, Advertisers, Audit Log and Email Captures all stream rows,
	 * so none may call fputcsv() directly and skip the neutralising.
	 */
	public function test_no_export_bypasses_the_shared_writer(): void {
		$offenders = array();
		foreach ( array( WBAM_PATH . 'includes', WBAM_PRO_PATH . 'includes' ) as $dir ) {
			$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $files as $file ) {
				if ( 'php' !== $file->getExtension() || 'functions-ad-formats.php' === $file->getFilename() ) {
					continue;
				}
				foreach ( file( $file->getPathname() ) as $n => $code ) {
					if ( preg_match( '/(?<![\w>:])fputcsv\s*\(/', $code ) ) {
						$offenders[] = $file->getPathname() . ':' . ( $n + 1 );
					}
				}
			}
		}

		$this->assertSame( array(), $offenders, 'Write CSV rows with wbam_fputcsv().' );
	}
}
