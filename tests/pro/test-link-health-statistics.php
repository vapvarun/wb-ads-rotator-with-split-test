<?php
/**
 * Card 10339876480, step 9: the Link Health screen showed "100%" next to
 * "0 Total Checked" - claiming a clean bill of health for links nobody had
 * actually run the check against yet.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Links\Link_Health_Checker;

class Test_Link_Health_Statistics extends Pro_Test_Case {

	public function tear_down(): void {
		wp_cache_delete( 'health_stats', 'wbam_pro_link_health' );
		parent::tear_down();
	}

	public function test_health_score_is_null_before_the_first_check(): void {
		wp_cache_delete( 'health_stats', 'wbam_pro_link_health' );

		$stats = Link_Health_Checker::get_instance()->get_statistics();

		$this->assertSame( 0, $stats['total_checked'] );
		$this->assertNull( $stats['health_score'], 'No checks yet means no score to report, not a false "100%".' );
	}
}
