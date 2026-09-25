<?php
/**
 * The ad edit screen and list tell the owner the truth.
 *
 * Card 10342823390: no visible way to place an ad without a placement (the
 * shortcode was never shown), the Placements column printed raw slugs, the
 * priority hint always described a hypothetical 3-way tie, and image ads
 * printed <img> without the known width/height (layout shift).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Admin;
use WBAM\Modules\Placements\Placement_Engine;

class Test_Ad_Edit_Screen_Helpers extends \WP_UnitTestCase {

	private function ad( array $placements, int $priority = 5 ): int {
		$ad_id = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_priority', $priority );
		update_post_meta( $ad_id, '_wbam_placements', $placements );
		update_post_meta(
			$ad_id,
			'_wbam_ad_data',
			array(
				'type'      => 'image',
				'image_url' => 'https://example.com/leaderboard.png',
			)
		);

		return $ad_id;
	}

	public function test_edit_screen_and_list_helpers(): void {
		$admin = Admin::get_instance();
		$ad_id = $this->ad( array( 'popup', 'header' ) );

		ob_start();
		$admin->render_usage_metabox( get_post( $ad_id ) );
		$this->assertStringContainsString( 'data-clipboard="[wbam_ad id=&quot;' . $ad_id . '&quot;]"', ob_get_clean(), 'The shortcode is shown with a copy button.' );

		ob_start();
		$admin->render_column( 'placements', $ad_id );
		$this->assertSame( 'Popup/Modal, Header', wp_strip_all_tags( ob_get_clean() ), 'Placements read as labels, not slugs.' );

		ob_start();
		$admin->render_status_metabox( get_post( $ad_id ) );
		$alone = ob_get_clean();
		$this->assertStringNotContainsString( '3-way tie', $alone );
		$this->assertStringContainsString( 'No other enabled ad shares', $alone );

		$this->ad( array( 'header' ), 10 );
		ob_start();
		$admin->render_status_metabox( get_post( $ad_id ) );
		$this->assertStringContainsString( '"others":10', ob_get_clean(), 'The hint counts the real competitor and its priority.' );
	}

	public function test_image_ad_prints_known_dimensions(): void {
		$ad_id = $this->ad( array( 'header' ) );
		update_post_meta( $ad_id, '_wbam_ad_width', 728 );
		update_post_meta( $ad_id, '_wbam_ad_height', 90 );

		$html = Placement_Engine::get_instance()->get_ad_type( 'image' )->render( $ad_id );

		$this->assertStringContainsString( 'width="728" height="90"', $html );
	}
}
