<?php
/**
 * Paid ads win their slot; house and sample ads only fill gaps.
 *
 * Owner decision 7 (card 10343031017): in a fresh install a paid header ad
 * won 1 of 8 rotations against the plugin's sample banner, so advertisers
 * paid for delivery they did not get.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Modules\Placements\Placement_Engine;
use WBAM\Tests\Helpers\Factory;

class Test_Paid_Ads_First extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		// Earlier tests' renders count toward one "page" in this process.
		Factory::reset_page_ads();
		// Keep sample retirement (its own test) out of the draws.
		update_option( \WBAM_Pro\Core\Pro_Plugin::SAMPLES_RETIRED_OPTION, 0 );
	}

	private function make_ad( string $title, array $meta = array() ): int {
		$ad_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'code', 'code' => '<span>' . $title . '</span>' ) );
		update_post_meta( $ad_id, '_wbam_placements', array( 'footer' ) );
		update_post_meta( $ad_id, '_wbam_priority', 10 );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $ad_id, $key, $value );
		}

		return $ad_id;
	}

	private function winners( int $draws ): array {
		$engine = Placement_Engine::get_instance();
		$engine->clear_placement_cache( 0 );

		$won = array();
		for ( $i = 0; $i < $draws; $i++ ) {
			foreach ( $engine->get_ads_for_placement( 'footer' ) as $ad_id ) {
				$won[ (int) $ad_id ] = true;
			}
		}

		return array_keys( $won );
	}

	public function test_paid_ad_beats_house_and_sample_ads(): void {
		$house  = $this->make_ad( 'House ad' );
		$sample = $this->make_ad( 'Sample ad', array( '_wbam_is_demo' => 1 ) );
		$paid   = $this->make_ad( 'Paid ad', array( '_wbam_advertiser_id' => 7, '_wbam_priority' => 1 ) );

		$this->assertSame( array( $paid ), $this->winners( 30 ), 'An eligible paid ad must win every draw, whatever the house ad priority.' );

		update_post_meta( $paid, '_wbam_enabled', '0' );
		$this->assertSame( array( $house ), $this->winners( 30 ), 'With no paid ad eligible, the house ad fills the slot and the sample never outranks it.' );

		update_post_meta( $house, '_wbam_enabled', '0' );
		$this->assertSame( array( $sample ), $this->winners( 5 ), 'A sample ad still fills a slot nothing else can serve.' );
	}

	public function test_demo_ad_with_an_advertiser_never_outranks_a_real_paid_ad(): void {
		// A demo import attaches its ads to demo advertisers and campaigns.
		$this->make_ad( 'Demo paid ad', array( '_wbam_is_demo' => 1, '_wbam_advertiser_id' => 3, '_wbam_campaign_id' => 2, '_wbam_priority' => 10 ) );
		$paid = $this->make_ad( 'Real paid ad', array( '_wbam_advertiser_id' => 7, '_wbam_priority' => 1 ) );

		$this->assertSame( array( $paid ), $this->winners( 30 ), 'Demo ads are samples, whatever advertiser or campaign they carry.' );
	}

	public function test_stack_mode_shows_only_the_top_tier(): void {
		$this->make_ad( 'House ad' );
		$this->make_ad( 'Sample ad', array( '_wbam_sample_ad' => '1' ) );
		$paid_a = $this->make_ad( 'Paid A', array( '_wbam_advertiser_id' => 7 ) );
		$paid_b = $this->make_ad( 'Paid B', array( '_wbam_campaign_id' => 8 ) );

		$stack = static function () {
			return 'stack';
		};
		add_filter( 'wbam_placement_render_mode', $stack );
		$won = $this->winners( 30 );
		remove_filter( 'wbam_placement_render_mode', $stack );

		$this->assertNotEmpty( $won );
		$this->assertSame( array(), array_diff( $won, array( $paid_a, $paid_b ) ), 'A stacked slot lists paid ads only while they can serve.' );
	}
}
