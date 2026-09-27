<?php
/**
 * Sample ads say what they are (owner decision 8, card 10344383315): no
 * made-up "Advertise here" call to action linking to the homepage. They
 * link only where Pro hands over a real Advertise page.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Setup_Wizard;
use WBAM\Core\Placement_Format_Map;

class Test_Honest_Sample_Ads extends \WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'wbam_sample_ad_link' );
		parent::tear_down();
	}

	private function create_samples(): array {
		$wizard = new \ReflectionMethod( Setup_Wizard::class, 'create_sample_ads' );
		$wizard->setAccessible( true );
		$wizard->invoke( new Setup_Wizard(), array( 'header_banner', 'sidebar_widget', 'content_promo' ) );

		$data = array();
		foreach ( get_posts( array( 'post_type' => 'wbam-ad', 'meta_key' => '_wbam_sample_ad', 'fields' => 'ids', 'numberposts' => -1 ) ) as $id ) { // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$data[] = get_post_meta( $id, '_wbam_ad_data', true );
		}
		return $data;
	}

	public function test_samples_say_they_are_samples_and_link_nowhere_by_default(): void {
		foreach ( $this->create_samples() as $ad ) {
			$text = wp_json_encode( $ad );
			$this->assertStringContainsString( 'Sample ad - replace me in WB Ad Manager', $text );
			$this->assertStringNotContainsString( 'Advertise here', $text );
			$this->assertStringNotContainsString( '<a ', (string) ( $ad['content'] ?? '' ) );
			$this->assertSame( '', (string) ( $ad['link_url'] ?? '' ) );
		}
		$svg = (string) file_get_contents( WBAM_PATH . 'assets/images/sample-leaderboard.svg' );
		$this->assertStringNotContainsString( 'Advertise here', $svg );
	}

	public function test_samples_link_to_a_real_advertise_page_when_given_one(): void {
		add_filter( 'wbam_sample_ad_link', static fn() => 'https://example.org/advertise/' );

		foreach ( $this->create_samples() as $ad ) {
			$this->assertStringContainsString( 'https://example.org/advertise/', wp_json_encode( $ad, JSON_UNESCAPED_SLASHES ) );
		}
	}

	public function test_a_placement_takes_the_shapes_its_sizes_belong_to(): void {
		$this->assertSame( array( 'banner' ), Placement_Format_Map::accepted_shapes( array( 'leaderboard', 'responsive' ) ) );
		$this->assertSame( array( 'box', 'tower' ), Placement_Format_Map::accepted_shapes( array( 'square', 'skyscraper' ) ) );
		$this->assertSame( array_keys( Placement_Format_Map::shapes() ), Placement_Format_Map::accepted_shapes( array() ), 'No size rule takes every shape.' );
	}
}
