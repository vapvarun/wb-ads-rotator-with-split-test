<?php
/**
 * The WB Ad block's editor preview says why an ad does not deliver.
 *
 * An advertiser ad with no live campaign renders nothing, so the editor
 * showed "Block rendered as empty" with no hint why (card 10344005566).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;

class Test_Ad_Block_Not_Delivering_Notice extends Pro_Test_Case {

	public function tear_down(): void {
		Factory::reset_page_ads();
		parent::tear_down();
	}

	private function render( int $ad_id ): string {
		return render_block(
			array(
				'blockName'    => 'wb-ads/ad',
				'attrs'        => array( 'adId' => $ad_id ),
				'innerBlocks'  => array(),
				'innerContent' => array(),
			)
		);
	}

	public function test_editor_preview_names_the_missing_campaign(): void {
		$ad_id = Factory::make_ad();
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'rich-content', 'content' => 'Paid probe' ) );
		update_post_meta( $ad_id, '_wbam_advertiser_id', 7 );
		update_post_meta( $ad_id, '_wbam_placements', array( 'header' ) );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( '', $this->render( $ad_id ), 'Visitors still get nothing.' );

		$get                    = $_GET;
		$_GET['wbam_preview']   = '1';
		$html                   = $this->render( $ad_id );
		$_GET                   = $get;

		$this->assertStringContainsString( 'Not showing: No live campaign', $html );
	}
}
