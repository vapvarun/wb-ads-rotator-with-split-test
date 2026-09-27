<?php
/**
 * Admin screens show stored UTC moments in the site's time zone (card
 * 10344269919, QA wave 11). gmdate() printed UTC: on a +05:30 site a
 * campaign ending 23:59:59 UTC listed a day early, and hover tooltips
 * showed raw UTC.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Audit_Log_List_Table;
use WBAM_Pro\Admin\Campaigns_List_Table;

class Test_Dates_Display_Site_Time extends Pro_Test_Case {

	public function set_up(): void {
		$this->snapshot_options( array( 'timezone_string', 'gmt_offset' ) );
		parent::set_up();
		update_option( 'timezone_string', 'Asia/Kolkata' );
		set_current_screen( 'toplevel_page_wbam-campaigns' );
	}

	public function test_a_campaign_range_ends_on_the_site_day(): void {
		$item = (object) array(
			'start_date' => '2026-09-24 18:30:00', // Sep 25 00:00 site time.
			'end_date'   => '2026-10-25 23:59:59', // Oct 26 05:29 site time.
		);

		$this->assertSame( 'Sep 25 - Oct 26, 2026', ( new Campaigns_List_Table() )->column_dates( $item ) );
	}

	public function test_a_tooltip_shows_site_time(): void {
		$html = ( new Audit_Log_List_Table() )->column_created_at( (object) array( 'created_at' => '2026-09-27 20:00:00' ) );

		$this->assertStringContainsString( 'title="2026-09-28 01:30:00"', $html );
	}

	public function test_link_click_days_end_on_the_site_today(): void {
		$days = wbam_site_day_range( null, 30 );

		$this->assertCount( 31, $days, 'Thirty days back plus today, as before.' );
		$this->assertSame( wp_date( 'Y-m-d' ), end( $days ) );
		$this->assertSame( '2026-01-01', wbam_site_day_range( '2026-01-01' )[0] );
	}
}
