<?php
/**
 * One date-range preset component on every report screen.
 *
 * Transactions and Link Analytics used to hand-roll their quick ranges and
 * build the dates in the browser with toISOString(), which converts local
 * midnight to UTC: east of UTC 'This month' started on the last day of the
 * previous month. Their labels also differed from Revenue ('7 Days'/'Year')
 * and no preset showed which one was applied. Every screen now renders
 * Report_Shell::range_presets(): labels from preset_labels(), dates computed
 * server-side in the site timezone, and an active state.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Report_Shell;
use WBAM_Pro\Admin\Transactions_List_Table;

class Test_Report_Range_Presets extends Pro_Test_Case {

	/**
	 * Snapshot of $_GET.
	 *
	 * @var array
	 */
	private $get_snapshot;

	public function set_up(): void {
		parent::set_up();
		$this->get_snapshot = $_GET;
		update_option( 'timezone_string', 'Asia/Kolkata' );
		require_once WBAM_PRO_PATH . 'includes/Admin/class-transactions-list-table.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		$_GET = $this->get_snapshot;
		delete_option( 'timezone_string' );
		parent::tear_down();
	}

	public function test_presets_carry_site_timezone_dates_and_active_state(): void {
		ob_start();
		Report_Shell::range_presets( array( 'current' => 'month' ) );
		$html = ob_get_clean();

		foreach ( Report_Shell::preset_labels() as $slug => $label ) {
			$range = Report_Shell::range( array( 'range' => $slug ) );
			$this->assertStringContainsString( 'data-start="' . $range['start'] . '"', $html, "{$slug} start date" );
			$this->assertStringContainsString( '>' . $label . '<', $html, "{$slug} label" );
		}
		$this->assertStringContainsString( 'data-start="' . current_time( 'Y-m-01' ) . '"', $html );
		$this->assertSame( 1, substr_count( $html, 'is-active' ), 'Exactly one preset is active.' );
		$this->assertMatchesRegularExpression( '/is-active[^>]*data-range="month"/', $html );
	}

	public function test_transactions_use_the_shared_presets_without_client_date_math(): void {
		$month             = Report_Shell::range( array( 'range' => 'month' ) );
		$_GET['date_from'] = $month['start'];
		$_GET['date_to']   = $month['end'];

		$table  = new Transactions_List_Table();
		$method = new \ReflectionMethod( $table, 'extra_tablenav' );
		ob_start();
		$method->invoke( $table, 'top' );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'toISOString', $html );
		$this->assertStringContainsString( 'date_from=' . $month['start'], $html );
		$this->assertMatchesRegularExpression( '/is-active[^>]*data-range="month"/', $html, 'The applied preset is highlighted.' );
		$this->assertStringContainsString( '>' . Report_Shell::preset_labels()['ytd'] . '<', $html );
	}

	public function test_link_analytics_script_has_no_client_date_math(): void {
		$js = file_get_contents( WBAM_PRO_PATH . 'assets/js/links-pro-admin.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertStringNotContainsString( 'toISOString', $js );
	}
}
