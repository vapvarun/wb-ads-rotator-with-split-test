<?php
/**
 * Paid links carry rel="sponsored"; the owner's house ads do not.
 *
 * Card 10342823390: an approved advertiser ad printed rel="noopener
 * noreferrer" and affiliate links printed only nofollow. Google requires
 * paid and affiliate links to be qualified with rel="sponsored".
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Links\Link;
use WBAM\Modules\Placements\Placement_Engine;

class Test_Paid_Link_Rel extends \WP_UnitTestCase {

	private function image_ad(): int {
		$ad_id = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta(
			$ad_id,
			'_wbam_ad_data',
			array(
				'type'      => 'image',
				'image_url' => 'https://example.com/banner.png',
				'link_url'  => 'https://advertiser.example/',
			)
		);

		return $ad_id;
	}

	private function rel_of( string $html ): string {
		return preg_match( '/<a [^>]*rel="([^"]*)"/', $html, $m ) ? $m[1] : '';
	}

	public function test_house_and_paid_ad_links(): void {
		$engine = Placement_Engine::get_instance();
		$ad_id  = $this->image_ad();

		$this->assertSame( 'noopener', $this->rel_of( $engine->render_ad( $ad_id, array( 'skip_targeting' => true ) ) ), 'A house ad is the owner\'s own link and must not be marked sponsored.' );

		$paid = static function () {
			return Placement_Engine::TIER_PAID;
		};
		add_filter( 'wbam_ad_delivery_tier', $paid );
		$paid_ad = $this->image_ad();
		$rel     = $this->rel_of( $engine->render_ad( $paid_ad, array( 'skip_targeting' => true ) ) );
		remove_filter( 'wbam_ad_delivery_tier', $paid );

		$this->assertSame( 'sponsored noopener', $rel, 'A paid ad link must be qualified as sponsored.' );
	}

	public function test_affiliate_link_is_sponsored_by_default(): void {
		$link = new Link(
			array(
				'link_type' => 'affiliate',
				'sponsored' => 0,
				'nofollow'  => 1,
				'new_tab'   => 1,
			)
		);

		$this->assertSame( 'nofollow sponsored noopener', $link->get_attributes()['rel'] );
	}
}
