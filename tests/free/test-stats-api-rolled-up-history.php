<?php
/**
 * The stats REST endpoint and the analytics abilities count the real
 * wbam_analytics columns plus the rolled-up daily totals.
 *
 * GET /ads/{id}/stats, wbam/get-ad-stats and wbam/get-analytics-overview
 * queried `timestamp` and `type`, columns the table does not have, so they
 * errored or returned zero; and they read raw rows only, so a range older
 * than the 90-day raw retention came back empty (Basecamp card 10343188140).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Core\Abilities;
use WBAM\Tests\Helpers\Factory;
use WP_REST_Request;
use WP_UnitTestCase;

class Test_Stats_Api_Rolled_Up_History extends WP_UnitTestCase {

	private int $ad_a = 0;

	private int $ad_b = 0;

	private string $old_day = '';

	private string $recent = '';

	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->ad_a    = Factory::make_ad( array( 'post_title' => 'Stats A' ) );
		$this->ad_b    = Factory::make_ad( array( 'post_title' => 'Stats B' ) );
		$this->old_day = wp_date( 'Y-m-d', strtotime( '-200 days' ) );
		$this->recent  = wp_date( 'Y-m-d', strtotime( '-5 days' ) );

		// A: 10 impressions / 3 clicks rolled up, 2 impressions / 1 click raw.
		// B: 4 impressions rolled up, 1 impression raw.
		$this->daily( $this->ad_a, 10, 3 );
		$this->daily( $this->ad_b, 4, 0 );
		$this->raw( $this->ad_a, 'impression', 'header' );
		$this->raw( $this->ad_a, 'impression', 'header' );
		$this->raw( $this->ad_a, 'click', 'header' );
		$this->raw( $this->ad_b, 'impression', 'footer' );
	}

	private function daily( int $ad_id, int $impressions, int $clicks ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wbam_analytics_daily',
			array(
				'ad_id'       => $ad_id,
				'date'        => $this->old_day,
				'impressions' => $impressions,
				'clicks'      => $clicks,
			)
		);
	}

	private function raw( int $ad_id, string $type, string $placement ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wbam_analytics',
			array(
				'ad_id'      => $ad_id,
				'event_type' => $type,
				'placement'  => $placement,
				'created_at' => $this->recent . ' 12:00:00',
			)
		);
	}

	private function range(): array {
		return array(
			'start_date' => wp_date( 'Y-m-d', strtotime( '-250 days' ) ),
			'end_date'   => wp_date( 'Y-m-d' ),
		);
	}

	public function test_rest_ad_stats_sums_raw_and_rolled_up_days(): void {
		$request = new WP_REST_Request( 'GET', '/wbam/v1/ads/' . $this->ad_a . '/stats' );
		$request->set_query_params( $this->range() );
		$data = rest_do_request( $request )->get_data();

		$this->assertSame( $this->ad_a, $data['ad_id'] );
		$this->assertSame( 12, $data['impressions'] );
		$this->assertSame( 4, $data['clicks'] );
		$this->assertEqualsWithDelta( 33.33, $data['ctr'], 0.001 );

		// A range wholly past the raw retention returns the daily totals.
		$request->set_query_params(
			array(
				'start_date' => $this->old_day,
				'end_date'   => $this->old_day,
			)
		);
		$data = rest_do_request( $request )->get_data();
		$this->assertSame( 10, $data['impressions'] );
		$this->assertSame( 3, $data['clicks'] );
	}

	public function test_get_ad_stats_ability_sums_raw_and_rolled_up_days(): void {
		$result = ( new Abilities() )->execute_get_ad_stats( array( 'id' => $this->ad_a ) + $this->range() );

		$this->assertSame( 12, $result['impressions'] );
		$this->assertSame( 4, $result['clicks'] );
		$this->assertEqualsWithDelta( 33.33, $result['ctr'], 0.001 );
		$this->assertSame( 'header', $result['by_placement'][0]['placement'] );
		$this->assertSame( 2, $result['by_placement'][0]['impressions'] );
		$this->assertSame( 1, $result['by_placement'][0]['clicks'] );
	}

	public function test_analytics_overview_ability_sums_raw_and_rolled_up_days(): void {
		$result = ( new Abilities() )->execute_get_analytics_overview( array( 'limit' => 5 ) + $this->range() );

		$this->assertSame( 17, $result['total_impressions'] );
		$this->assertSame( 4, $result['total_clicks'] );
		$this->assertSame( array( $this->ad_a, $this->ad_b ), array_column( $result['top_ads'], 'ad_id' ) );
		$this->assertSame( array( 12, 5 ), array_column( $result['top_ads'], 'impressions' ) );
		$this->assertSame( 'Stats A', $result['top_ads'][0]['title'] );
	}
}
