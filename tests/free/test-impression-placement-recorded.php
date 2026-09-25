<?php
/**
 * Every surface reports its placement with the impression.
 *
 * Sticky, popup and comment ads rendered without a placement, so their
 * analytics rows were stored with an empty placement column and the
 * per-placement report could not attribute them (Basecamp card 10342824516).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Placements\Placement_Engine;
use WBAM\Tests\Helpers\Factory;
use WP_UnitTestCase;

class Test_Impression_Placement_Recorded extends WP_UnitTestCase {

	public function test_footer_and_comment_surfaces_report_their_placement(): void {
		$ad_id = Factory::make_ad();
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_placements', array( 'sticky', 'popup', 'comments' ) );
		update_post_meta(
			$ad_id,
			'_wbam_ad_data',
			array(
				'type'             => 'rich-content',
				'content'          => 'Placement probe',
				'comment_position' => 'before_form',
			)
		);

		$seen = array();
		add_filter(
			'wbam_ad_output',
			static function ( $output, $id, $placement ) use ( &$seen ) {
				$seen[] = $placement;
				return $output;
			},
			1,
			3
		);
		add_filter( 'wbam_enforce_page_cap', '__return_false' );

		$engine = Placement_Engine::get_instance();
		ob_start();
		$engine->get_placement( 'sticky' )->render_sticky_ads();
		$engine->get_placement( 'popup' )->render_popup_ads();
		$engine->get_placement( 'comments' )->render_before_form();
		ob_end_clean();

		$this->assertSame( array( 'sticky', 'popup', 'comments' ), $seen );
	}
}
