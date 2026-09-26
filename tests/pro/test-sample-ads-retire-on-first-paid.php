<?php
/**
 * Owner decision 7: the setup wizard's sample ads switch off once the first
 * paid ad goes live, once per site; house ads are never touched.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Plugin;

class Test_Sample_Ads_Retire_On_First_Paid extends Pro_Test_Case {

	public function tear_down(): void {
		delete_option( Pro_Plugin::SAMPLES_RETIRED_OPTION );
		parent::tear_down();
	}

	private function ad( array $meta, string $status = 'publish' ): int {
		$meta['_wbam_enabled'] = '1';
		return self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => $status,
				'meta_input'  => $meta,
			)
		);
	}

	public function test_first_paid_ad_switches_sample_ads_off_once(): void {
		delete_option( Pro_Plugin::SAMPLES_RETIRED_OPTION );
		$sample = $this->ad( array( '_wbam_sample_ad' => '1' ) );
		$house  = $this->ad( array() );

		// A paid ad approved later: created pending with its advertiser, then published.
		$paid = $this->ad( array( '_wbam_advertiser_id' => 5 ), 'pending' );
		$this->assertSame( '1', get_post_meta( $sample, '_wbam_enabled', true ), 'A pending paid ad is not live yet.' );

		wp_publish_post( $paid );

		$this->assertSame( '0', get_post_meta( $sample, '_wbam_enabled', true ) );
		$this->assertSame( '1', get_post_meta( $house, '_wbam_enabled', true ) );
		$this->assertSame( 1, (int) get_option( Pro_Plugin::SAMPLES_RETIRED_OPTION ) );

		// The owner turns the sample back on; the next paid ad leaves it alone.
		update_post_meta( $sample, '_wbam_enabled', '1' );
		$this->ad( array( '_wbam_campaign_id' => 9 ) );
		$this->assertSame( '1', get_post_meta( $sample, '_wbam_enabled', true ) );
	}

	public function test_existing_ad_given_an_advertiser_switches_sample_ads_off(): void {
		delete_option( Pro_Plugin::SAMPLES_RETIRED_OPTION );
		$sample = $this->ad( array( '_wbam_sample_ad' => '1' ) );

		// A published demo ad with a demo advertiser is not a real paid ad.
		$this->ad( array( '_wbam_is_demo' => '1', '_wbam_advertiser_id' => 3 ) );
		$this->assertSame( '1', get_post_meta( $sample, '_wbam_enabled', true ), 'Demo ads are samples, not paid ads.' );

		// A live house ad whose advertiser field was saved as 0, then set.
		$ad = $this->ad( array( '_wbam_advertiser_id' => 0 ) );
		$this->assertSame( '1', get_post_meta( $sample, '_wbam_enabled', true ) );

		update_post_meta( $ad, '_wbam_advertiser_id', 5 );
		$this->assertSame( '0', get_post_meta( $sample, '_wbam_enabled', true ), 'Setting a real advertiser on a live ad makes it paid.' );
	}
}
