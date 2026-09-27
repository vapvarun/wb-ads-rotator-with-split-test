<?php
/**
 * Analytics ranges (dates card 10344269919, owner decision 2026-10-03):
 * raw events are filtered by the stored UTC moment against the site days'
 * UTC bounds (index-friendly), and a day the daily table already holds is
 * excluded by its SITE day, not the UTC day.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Analytics\Analytics_Window;

class Test_Analytics_Site_Day_Ranges extends Pro_Test_Case {

	private int $ad_id = 987654;

	public function set_up(): void {
		parent::set_up();
		update_option( 'timezone_string', 'Asia/Kolkata' ); // +05:30: site midnight is 18:30 UTC.
		update_option( 'gmt_offset', '' );

		global $wpdb;
		foreach ( array( '2026-09-27 18:29:00', '2026-09-27 18:31:00' ) as $utc ) {
			$wpdb->insert(
				$wpdb->prefix . 'wbam_analytics',
				array(
					'ad_id'      => $this->ad_id,
					'event_type' => 'impression',
					'created_at' => $utc,
				)
			);
		}
	}

	public function test_each_event_counts_on_its_own_site_day(): void {
		$where = ' AND ad_id = ' . $this->ad_id;

		$this->assertSame( 1, Analytics_Window::totals( '2026-09-27', '2026-09-27', $where )['impressions'], '23:59 site time is the 27th.' );
		$this->assertSame( 1, Analytics_Window::totals( '2026-09-28', '2026-09-28', $where )['impressions'], '00:01 site time is the 28th.' );
		$this->assertSame( 2, Analytics_Window::totals( '2026-09-27', '2026-09-28', $where )['impressions'] );
	}

	public function test_a_rolled_up_day_is_excluded_by_site_day(): void {
		$this->assertStringContainsString( 'INTERVAL 19800 SECOND', Analytics_Window::exclude_dates_sql( array( '2026-09-28' ) ) );
	}

	public function test_no_where_clause_wraps_created_at_in_a_function(): void {
		$hits = array();
		foreach ( array( WBAM_PRO_PATH . 'includes', WBAM_PATH . 'includes' ) as $dir ) {
			foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir ) ) as $file ) {
				if ( 'php' !== $file->getExtension() ) {
					continue;
				}
				$src = (string) file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( preg_match_all( "/wbam_sql_site_date\\( '(created_at|clicked_at)' \\) \\. [\"'] (BETWEEN|<|>|=)/", $src, $m ) ) {
					$hits[] = $file->getFilename();
				}
			}
		}
		$this->assertSame( array(), $hits, 'Compare the raw column with wbam_site_day_utc_bounds() so the index is used.' );
	}
}
