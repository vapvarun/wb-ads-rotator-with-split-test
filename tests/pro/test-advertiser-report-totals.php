<?php
/**
 * Advertiser reports read one source for impressions and clicks.
 *
 * Top Performing Ads read the daily aggregate only, so it said "No ads data
 * available" until the nightly rollup ran while the chart and the CSV beside
 * it listed the same ads. The summary cards ignored the campaign filter the
 * chart honoured.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

/**
 * @group pro
 * @group reports
 */
class Test_Advertiser_Report_Totals extends Pro_Test_Case {

	private function event( int $ad_id, string $type, int $campaign_id = 0 ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_analytics',
			array(
				'ad_id'       => $ad_id,
				'campaign_id' => $campaign_id,
				'event_type'  => $type,
				'placement'   => 'header',
				'created_at'  => current_time( 'mysql' ),
			)
		);
	}

	private function get( string $route, array $params ) {
		$request = new \WP_REST_Request( 'GET', '/wbam-pro/v1/' . $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_do_request( $request )->get_data();
	}

	public function test_top_ads_and_stats_count_the_same_unaggregated_events(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		\WBAM_Pro\Modules\Advertisers\Advertiser_Manager::get_instance()->create( $user_id, array( 'status' => 'active' ) );

		$ad_in  = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_author' => $user_id, 'post_title' => 'In campaign' ) );
		$ad_out = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_author' => $user_id, 'post_title' => 'Other' ) );
		update_post_meta( $ad_in, '_wbam_campaign_id', 777 );

		$this->event( $ad_in, 'impression', 777 );
		$this->event( $ad_in, 'impression', 777 );
		$this->event( $ad_in, 'click', 777 );
		$this->event( $ad_out, 'impression' );

		wp_set_current_user( $user_id );
		$today = current_time( 'Y-m-d' );
		$range = array(
			'start_date' => gmdate( 'Y-m-d', strtotime( $today . ' -30 days' ) ),
			'end_date'   => $today,
		);

		$top = $this->get( 'analytics/top-ads', $range );
		$this->assertCount( 2, $top, 'Raw events not yet rolled up are still top-ad data.' );
		$this->assertSame( $ad_in, $top[0]['id'] );
		$this->assertSame( 2, $top[0]['impressions'] );

		$stats = $this->get( 'advertiser/stats', $range + array( 'campaign_id' => 777 ) );
		$this->assertSame( 2, $stats['total_impressions'], 'Summary cards honour the campaign filter the chart uses.' );
		$this->assertSame( 1, $stats['total_clicks'] );
	}
}
