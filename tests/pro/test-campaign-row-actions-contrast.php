<?php
/**
 * Campaign row actions use the admin link colour, not inline colours.
 *
 * "Pause" was painted inline `color:orange` (#ffa500, 1.84:1 on white), and
 * Approve/Resume inline green; inline styles also break the admin colour
 * scheme. WordPress's own row-action link colour passes AA.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Campaigns_List_Table;

/**
 * @group pro
 * @group reports
 */
class Test_Campaign_Row_Actions_Contrast extends Pro_Test_Case {

	/**
	 * @dataProvider statuses
	 */
	public function test_row_actions_carry_no_inline_colour( string $status ): void {
		set_current_screen( 'toplevel_page_wbam-campaigns' );
		$table = new Campaigns_List_Table();
		$item  = (object) array(
			'id'     => 7,
			'name'   => 'Row actions',
			'status' => $status,
			'ad_id'  => 0,
		);

		$this->assertStringNotContainsString( 'style=', $table->column_name( $item ) );
	}

	public function statuses(): array {
		return array( array( 'active' ), array( 'paused' ), array( 'pending' ) );
	}
}
