<?php
/**
 * Revenue and Ad Analytics label clarity (10339876480 step 10):
 * an advertiser's all-time "Total Spent" reads differently from the
 * Revenue report's period-scoped "Net" so the two never look like a
 * mismatch, and "Unique ad impressions" explains why it is lower than
 * Impressions without requiring a tooltip hover.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Analytics\Analytics_Dashboard;

/**
 * @group pro
 */
class Test_Revenue_Analytics_Labels extends Pro_Test_Case {

	public function test_advertiser_view_labels_total_spent_as_all_time_with_an_explainer(): void {
		$user       = self::factory()->user->create();
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		$method = new \ReflectionMethod( Pro_Admin::class, 'render_advertiser_details' );
		$method->setAccessible( true );
		ob_start();
		$method->invoke( new Pro_Admin(), $advertiser->id );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Total Spent (all time)', $html );
		$this->assertStringContainsString( 'Revenue report', $html, 'The tip should explain why this can differ from the Revenue report figure for the same advertiser.' );
	}

	public function test_unique_impressions_tile_carries_a_visible_hint_not_just_a_tooltip(): void {
		$dashboard = new Analytics_Dashboard();
		$method    = new \ReflectionMethod( Analytics_Dashboard::class, 'render_kpis' );
		$method->setAccessible( true );

		$current = array(
			'impressions'         => 2744,
			'clicks'              => 0,
			'ctr'                 => 0,
			'unique_impressions'  => 2,
			'visitors_reached'    => null,
			'visitors_period'     => null,
			'avg_ads_per_visitor' => null,
		);

		ob_start();
		$method->invoke( $dashboard, $current, null, 0, null, array( 'compare' => false ), false );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Lower than Impressions on purpose', $html );
	}
}
