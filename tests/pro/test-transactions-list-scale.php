<?php
/**
 * Admin Transactions at scale: view counts, search, date range, CSV export,
 * and a page of rows that costs the same number of queries however many
 * advertisers it shows.
 *
 * column_user() loaded each row's user and advertiser one at a time (two
 * queries per row), views carried no counts, and there was no search, date
 * range or export, so a 2000-row ledger could only be paged through.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Transactions_List_Table;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Transactions_List_Scale extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		require_once WBAM_PRO_PATH . 'includes/Admin/class-transactions-list-table.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		unset( $_GET['entry_type'], $_GET['s'], $_GET['date_from'], $_GET['date_to'] );
		parent::tear_down();
	}

	private function advertiser( string $company ): object {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update( $advertiser->id, array( 'company_name' => $company ) );
		return $advertiser;
	}

	private function render_page(): array {
		global $wpdb;
		$table  = new Transactions_List_Table();
		$before = $wpdb->num_queries;
		$table->prepare_items();
		foreach ( $table->items as $item ) {
			$table->column_user( $item );
		}
		return array( $table, $wpdb->num_queries - $before );
	}

	public function test_page_query_count_does_not_grow_with_advertisers(): void {
		foreach ( range( 1, 3 ) as $i ) {
			Credits_Bridge::topup( $this->advertiser( "Small {$i}" )->id, 10, 'Grant' );
		}
		list( $small, $small_queries ) = $this->render_page();

		foreach ( range( 1, 12 ) as $i ) {
			Credits_Bridge::topup( $this->advertiser( "Big {$i}" )->id, 10, 'Grant' );
		}
		list( $big, $big_queries ) = $this->render_page();

		$this->assertCount( 3, $small->items );
		$this->assertCount( 15, $big->items );
		$this->assertSame( $small_queries, $big_queries, 'Rendering the user column must not query per row.' );
		$this->assertStringContainsString( 'Big 12', $big->column_user( $big->items[0] ) );
	}

	public function test_views_carry_counts_and_the_row_labels(): void {
		$advertiser = $this->advertiser( 'Acme' );
		Credits_Bridge::topup( $advertiser->id, 100, 'Grant' );
		Credits_Bridge::topup( $advertiser->id, 50, 'Grant' );
		Credits_Bridge::charge( $advertiser->id, 40, 777, 'Package', false, Revenue_Ledger::SOURCE_AD_PACKAGE );

		$this->assertSame(
			array(
				'all'       => 3,
				'topup'     => 2,
				'refund'    => 0,
				'deduction' => 1,
			),
			Credits_Bridge::count_ledger_by_kind()
		);

		$table = new Transactions_List_Table();
		$views = ( new \ReflectionMethod( $table, 'get_views' ) )->invoke( $table );
		$this->assertStringContainsString( 'Top Up <span class="count">(2)</span>', $views['topup'] );
		$this->assertStringContainsString( 'Deduction <span class="count">(1)</span>', $views['deduction'] );
	}

	public function test_search_matches_note_and_advertiser_company(): void {
		Credits_Bridge::topup( $this->advertiser( 'Acme Widgets' )->id, 10, 'Grant' );
		Credits_Bridge::topup( $this->advertiser( 'Globex' )->id, 10, 'Spring promo' );

		$_GET['s'] = 'acme';
		list( $table ) = $this->render_page();
		$this->assertCount( 1, $table->items );

		$_GET['s'] = 'spring';
		list( $table ) = $this->render_page();
		$this->assertCount( 1, $table->items );
		$this->assertSame( 'Spring promo', $table->items[0]->get_note() );
	}

	public function test_date_range_filters_rows(): void {
		Credits_Bridge::topup( $this->advertiser( 'Acme' )->id, 10, 'Today' );

		$today           = wp_date( 'Y-m-d' );
		$_GET['date_from'] = $today;
		$_GET['date_to']   = $today;
		list( $table ) = $this->render_page();
		$this->assertCount( 1, $table->items );

		$_GET['date_from'] = '2001-01-01';
		$_GET['date_to']   = '2001-01-31';
		list( $table ) = $this->render_page();
		$this->assertCount( 0, $table->items );
	}

	public function test_csv_export_streams_the_filtered_rows(): void {
		Credits_Bridge::topup( $this->advertiser( 'Acme' )->id, 10, 'Grant' );
		Credits_Bridge::topup( $this->advertiser( 'Globex' )->id, 10, 'Grant' );
		$_GET['s'] = 'Globex';

		$handle = fopen( 'php://memory', 'w+' );
		Transactions_List_Table::stream_csv( $handle );
		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );

		$this->assertStringContainsString( 'Globex', $csv );
		$this->assertStringNotContainsString( 'Acme', $csv );
		$this->assertCount( 2, array_filter( explode( "\n", $csv ) ) );
	}
}
