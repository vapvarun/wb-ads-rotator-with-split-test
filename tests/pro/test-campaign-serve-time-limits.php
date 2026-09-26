<?php
/**
 * A paid ad serves only under a live campaign: one that exists, is active,
 * is inside its dates and under its impressions/clicks limits. Checked at
 * serve time, not when the hourly cron next marks the campaign expired.
 *
 * Card 10343031017: after a demo import, campaign 2 (end date passed,
 * 14112 of 1000 impressions) still won header slots. Card 10340186779: a
 * taken-down ad served 14 of 25 loads and a refunded campaign's ad 11 of
 * 25, both unbilled.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Modules\Placements\Placement_Engine;
use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Modules\Targeting\Campaign_Pacing;

class Test_Campaign_Serve_Time_Limits extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		Factory::reset_page_ads();
		$cache = new \ReflectionProperty( Campaign_Pacing::class, 'decision_cache' );
		$cache->setValue( null, array() );
	}

	/**
	 * A published paid ad in the footer, under a campaign with $campaign
	 * overrides, or under no campaign row when $campaign is null.
	 */
	private function paid_ad( ?array $campaign ): int {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_advertisers',
			array(
				'user_id' => (int) self::factory()->user->create(),
				'status'  => 'active',
			)
		);
		$advertiser_id = (int) $wpdb->insert_id;

		$campaign_id = 999999;
		if ( null !== $campaign ) {
			$wpdb->insert(
				$wpdb->prefix . 'wbam_campaigns',
				array_merge(
					array(
						'advertiser_id' => $advertiser_id,
						'name'          => 'Limits',
						'status'        => 'active',
						'pricing_model' => 'flat',
					),
					$campaign
				)
			);
			$campaign_id = (int) $wpdb->insert_id;
		}

		$ad_id = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'code', 'code' => '<span>Paid</span>' ) );
		update_post_meta( $ad_id, '_wbam_placements', array( 'footer' ) );
		update_post_meta( $ad_id, '_wbam_advertiser_id', $advertiser_id );
		update_post_meta( $ad_id, '_wbam_campaign_id', $campaign_id );

		return $ad_id;
	}

	private function serves( int $ad_id ): bool {
		$engine = Placement_Engine::get_instance();
		$engine->clear_placement_cache( 0 );

		return in_array( $ad_id, array_map( 'intval', $engine->get_ads_for_placement( 'footer' ) ), true );
	}

	public function test_ended_or_exhausted_campaigns_do_not_serve(): void {
		$this->assertTrue( $this->serves( $this->paid_ad( array( 'end_date' => gmdate( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS ), 'impressions_limit' => 1000, 'impressions' => 10 ) ) ) );
		$this->assertFalse( $this->serves( $this->paid_ad( array( 'end_date' => '2020-01-01 00:00:00' ) ) ), 'Past its end date.' );
		$this->assertFalse( $this->serves( $this->paid_ad( array( 'impressions_limit' => 1000, 'impressions' => 14112 ) ) ), 'At its impressions limit.' );
		$this->assertFalse( $this->serves( $this->paid_ad( array( 'clicks_limit' => 5, 'clicks' => 5 ) ) ), 'At its clicks limit.' );
	}

	public function test_paid_ad_without_a_live_campaign_never_renders(): void {
		foreach ( array( 'cancelled', 'paused', 'completed', 'pending' ) as $status ) {
			$ad_id = $this->paid_ad( array( 'status' => $status ) );
			$this->assertFalse( $this->serves( $ad_id ), "A {$status} campaign's ad is not served." );
			$this->assertSame( '', Placement_Engine::get_instance()->render_ad( $ad_id ), "A {$status} campaign's ad does not render by id either." );
		}

		$this->assertFalse( $this->serves( $this->paid_ad( null ) ), 'A paid ad whose campaign row is gone is not served.' );
		$this->assertFalse( $this->serves( $this->paid_ad( array( 'start_date' => gmdate( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS ) ) ) ), 'Not before its start date.' );
	}
}
