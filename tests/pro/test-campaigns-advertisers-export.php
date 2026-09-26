<?php
/**
 * Campaigns and Advertisers "Export CSV" download a real file.
 *
 * Both buttons linked to `admin.php?page=...&action=export`, which nothing
 * handled: the click reloaded the list and no file ever arrived.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Advertisers_List_Table;
use WBAM_Pro\Admin\Campaigns_List_Table;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

/**
 * @group pro
 * @group reports
 */
class Test_Campaigns_Advertisers_Export extends Pro_Test_Case {

	public function tear_down(): void {
		unset( $_GET['status'], $_GET['s'] );
		parent::tear_down();
	}

	private function advertiser( string $company ): int {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update( $advertiser->id, array( 'company_name' => $company ) );
		return (int) $advertiser->id;
	}

	private function campaign( int $advertiser_id, string $name ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_campaigns',
			array(
				'advertiser_id' => $advertiser_id,
				'name'          => $name,
				'status'        => 'active',
				'pricing_model' => 'cpm',
				'budget'        => 100,
				'created_at'    => current_time( 'mysql' ),
			)
		);
	}

	public function test_campaigns_export_streams_headers_and_rows(): void {
		$this->campaign( $this->advertiser( 'Acme Widgets' ), 'Summer Sale' );
		$this->campaign( $this->advertiser( 'Globex' ), 'Winter Sale' );

		$handle = fopen( 'php://memory', 'w+' );
		Campaigns_List_Table::stream_csv( $handle );
		rewind( $handle );
		$lines = array();
		while ( false !== ( $line = fgetcsv( $handle ) ) ) {
			$lines[] = $line;
		}
		fclose( $handle );

		$this->assertSame(
			array( 'ID', 'Campaign', 'Advertiser', 'Status', 'Budget', 'Spent', 'Impressions', 'Clicks', 'Start', 'End' ),
			$lines[0]
		);
		$this->assertCount( 3, $lines, 'Header row plus the 2 campaigns created.' );
	}

	public function test_campaigns_export_respects_the_status_filter(): void {
		$this->campaign( $this->advertiser( 'Acme' ), 'Active One' );
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_campaigns',
			array(
				'advertiser_id' => $this->advertiser( 'Globex' ),
				'name'          => 'Draft One',
				'status'        => 'draft',
				'pricing_model' => 'cpm',
				'budget'        => 50,
				'created_at'    => current_time( 'mysql' ),
			)
		);

		$_GET['status'] = 'draft';
		$handle          = fopen( 'php://memory', 'w+' );
		Campaigns_List_Table::stream_csv( $handle );
		rewind( $handle );
		$lines = array();
		while ( false !== ( $line = fgetcsv( $handle ) ) ) {
			$lines[] = $line;
		}
		fclose( $handle );

		$this->assertCount( 2, $lines, 'Only the draft campaign matches the filter.' );
		$this->assertSame( 'Draft One', $lines[1][1] );
	}

	public function test_advertisers_export_streams_headers_and_rows(): void {
		$this->advertiser( 'Acme Widgets' );
		$this->advertiser( 'Globex' );

		$handle = fopen( 'php://memory', 'w+' );
		Advertisers_List_Table::stream_csv( $handle );
		rewind( $handle );
		$lines = array();
		while ( false !== ( $line = fgetcsv( $handle ) ) ) {
			$lines[] = $line;
		}
		fclose( $handle );

		$this->assertSame(
			array( 'ID', 'Advertiser', 'Email', 'Company', 'Status', 'Balance', 'Total Spent', 'Ads', 'Registered' ),
			$lines[0]
		);
		$this->assertCount( 3, $lines, 'Header row plus the 2 advertisers created.' );
	}
}
