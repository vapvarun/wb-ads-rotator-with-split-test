<?php
/**
 * Report charts at 390 (card 10339876480, wave 6): the chart's data table
 * sits in the shared scroll wrapper, and the doughnut never repeats a colour.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Report_Shell;
use WBAM_Pro\Admin\Revenue_Dashboard;

class Test_Report_Chart_Mobile extends Pro_Test_Case {

	public function test_the_chart_data_table_scrolls_inside_its_card(): void {
		ob_start();
		Report_Shell::chart_card(
			array(
				'id'    => 'probe',
				'title' => 'Probe',
				'cfg'   => array( 'type' => 'bar' ),
				'table' => array(
					'headers' => array( 'Day', 'Net' ),
					'rows'    => array( array( 'Mon', '$1.00' ) ),
				),
			)
		);
		$html = (string) ob_get_clean();

		$this->assertMatchesRegularExpression( '/<details class="wbam-report-chart__data">.*<div class="wbam-admin-table-wrap wbam-report-table-wrap" role="region" tabindex="0" aria-labelledby="wbam-report-card-probe">\s*<table/s', $html );
	}

	public function test_the_doughnut_folds_the_tail_into_other(): void {
		$rows = array();
		foreach ( array( 6, 5, 4, 3, 2, 1 ) as $net ) {
			$rows[] = array( 'label' => "Type {$net}", 'net' => $net );
		}

		$slices = Revenue_Dashboard::doughnut_slices( $rows );

		$this->assertCount( 5, $slices, 'Five colours, five slices.' );
		$this->assertSame( 'Other', $slices[4]['label'] );
		$this->assertSame( 3.0, $slices[4]['net'], 'Types 2 and 1 add up.' );
		$this->assertSame( array_slice( $rows, 0, 5 ), Revenue_Dashboard::doughnut_slices( array_slice( $rows, 0, 5 ) ), 'Five or fewer stay as they are.' );
	}
}
