<?php
/**
 * A campaign past its end date or at its impressions/clicks limit stops
 * serving at once, not when the hourly cron next marks it expired.
 *
 * Card 10343031017: after a demo import, campaign 2 (end date passed,
 * 14112 of 1000 impressions) still won header slots.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Targeting\Campaign_Pacing;

class Test_Campaign_Serve_Time_Limits extends Pro_Test_Case {

	private function allowed( array $campaign ): bool {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_advertisers',
			array(
				'user_id' => (int) self::factory()->user->create(),
				'status'  => 'active',
			)
		);
		$campaign['advertiser_id'] = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_campaigns',
			array_merge(
				array(
					'name'          => 'Limits',
					'status'        => 'active',
					'pricing_model' => 'flat',
				),
				$campaign
			)
		);
		$campaign_id = (int) $wpdb->insert_id;
		$ad_id       = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $ad_id, '_wbam_campaign_id', $campaign_id );

		$cache = new \ReflectionProperty( Campaign_Pacing::class, 'decision_cache' );
		$cache->setValue( null, array() );

		return Campaign_Pacing::apply_pacing( true, $ad_id );
	}

	public function test_ended_or_exhausted_campaigns_do_not_serve(): void {
		$this->assertTrue( $this->allowed( array( 'end_date' => gmdate( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS ), 'impressions_limit' => 1000, 'impressions' => 10 ) ) );
		$this->assertFalse( $this->allowed( array( 'end_date' => '2020-01-01 00:00:00' ) ), 'Past its end date.' );
		$this->assertFalse( $this->allowed( array( 'impressions_limit' => 1000, 'impressions' => 14112 ) ), 'At its impressions limit.' );
		$this->assertFalse( $this->allowed( array( 'clicks_limit' => 5, 'clicks' => 5 ) ), 'At its clicks limit.' );
	}
}
