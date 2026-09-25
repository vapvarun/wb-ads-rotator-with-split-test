<?php
/**
 * `wb-ads/ad` and `wb-ads/placement` blocks - Site Editor coverage for
 * block (FSE) themes. Card 10342783037, step 3.
 *
 * Both blocks are dynamic and route through the exact same rendering
 * methods as the [wbam_ad] shortcode / the archive placements
 * (Placement_Engine::render_ad() / render_placement()) - the block wrapper
 * div is the only difference from the shortcode's raw output.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Placements\Placement_Engine;
use WBAM\Tests\Helpers\Factory;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

class Test_Wb_Ad_Blocks_Render extends WP_UnitTestCase {

	public function tear_down(): void {
		Factory::reset_page_ads();
		parent::tear_down();
	}

	public function test_both_blocks_are_registered_with_a_render_callback(): void {
		$registry = WP_Block_Type_Registry::get_instance();

		$this->assertTrue( $registry->is_registered( 'wb-ads/ad' ), '"wb-ads/ad" block must be registered.' );
		$this->assertTrue( $registry->is_registered( 'wb-ads/placement' ), '"wb-ads/placement" block must be registered.' );

		$this->assertNotEmpty( $registry->get_registered( 'wb-ads/ad' )->render_callback, 'wb-ads/ad is dynamic.' );
		$this->assertNotEmpty( $registry->get_registered( 'wb-ads/placement' )->render_callback, 'wb-ads/placement is dynamic.' );
	}

	public function test_wb_ad_block_output_matches_the_shortcode(): void {
		add_filter( 'wbam_enforce_page_cap', '__return_false' );

		$ad_id = Factory::make_ad();
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_placements', array() );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'rich-content', 'content' => 'Block probe' ) );

		$shortcode_html = do_shortcode( '[wbam_ad id="' . $ad_id . '"]' );

		$block_html = render_block(
			array(
				'blockName'    => 'wb-ads/ad',
				'attrs'        => array( 'adId' => $ad_id ),
				'innerBlocks'  => array(),
				'innerContent' => array(),
			)
		);

		$this->assertNotEmpty( $shortcode_html );
		$this->assertStringContainsString( 'Block probe', $block_html );
		$this->assertStringContainsString( 'wbam-block-ad', $block_html, 'Block wraps the shared render_ad() output in its own container.' );

		// Strip both wrappers down to the ad markup itself - same inner HTML,
		// only the outer container differs (shortcode has none, block has
		// the block-wrapper div).
		$this->assertStringContainsString( 'wbam-ad wbam-ad-slot', $shortcode_html );
		$this->assertStringContainsString( 'wbam-ad wbam-ad-slot', $block_html );
	}

	public function test_wb_ad_block_renders_nothing_without_an_id(): void {
		$block_html = render_block(
			array(
				'blockName'    => 'wb-ads/ad',
				'attrs'        => array( 'adId' => 0 ),
				'innerBlocks'  => array(),
				'innerContent' => array(),
			)
		);

		$this->assertSame( '', $block_html );
	}

	public function test_wb_ad_placement_block_matches_the_shared_renderer(): void {
		add_filter( 'wbam_enforce_page_cap', '__return_false' );

		$ad_id = Factory::make_ad();
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_placements', array( 'footer' ) );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'rich-content', 'content' => 'Placement block probe' ) );
		Placement_Engine::get_instance()->clear_placement_cache( $ad_id );

		$block_html = render_block(
			array(
				'blockName'    => 'wb-ads/placement',
				'attrs'        => array( 'placementId' => 'footer' ),
				'innerBlocks'  => array(),
				'innerContent' => array(),
			)
		);

		$this->assertStringContainsString( 'wbam-block-placement', $block_html );
		$this->assertStringContainsString( 'wbam-placement wbam-placement-footer', $block_html );
		$this->assertStringContainsString( 'Placement block probe', $block_html );
	}

	public function test_wb_ad_placement_block_renders_nothing_without_a_placement(): void {
		$block_html = render_block(
			array(
				'blockName'    => 'wb-ads/placement',
				'attrs'        => array( 'placementId' => '' ),
				'innerBlocks'  => array(),
				'innerContent' => array(),
			)
		);

		$this->assertSame( '', $block_html );
	}
}
