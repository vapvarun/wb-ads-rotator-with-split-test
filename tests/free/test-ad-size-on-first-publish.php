<?php
/**
 * Image size on the first Publish (card 10344381767, owner-seat audit):
 * save_meta() resolved the size before it wrote the ad's image, so a new
 * ad saved as responsive 0x0 and fit every placement; the next Update
 * then removed placements without a word.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Admin;
use WP_UnitTestCase;

class Test_Ad_Size_On_First_Publish extends WP_UnitTestCase {

	private function banner_url(): string {
		$id = self::factory()->attachment->create_object( 'leaderboard.png', 0, array( 'post_mime_type' => 'image/png' ) );
		update_post_meta( $id, '_wp_attached_file', 'leaderboard.png' );
		wp_update_attachment_metadata(
			$id,
			array(
				'width'  => 728,
				'height' => 90,
				'file'   => 'leaderboard.png',
			)
		);
		return (string) wp_get_attachment_url( $id );
	}

	private function publish_new_ad( array $placements ): int {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );

		$original = $_POST;
		$_POST    = array(
			'wbam_nonce'       => wp_create_nonce( 'wbam_save_ad' ),
			'wbam_sizing_mode' => 'fixed',
			'wbam_ad_format'   => '',
			'wbam_placements'  => $placements,
			'wbam_data'        => array(
				'type'      => 'image',
				'image_url' => $this->banner_url(),
			),
		);
		Admin::get_instance()->save_meta( $ad_id, get_post( $ad_id ) );
		$_POST = $original;

		return $ad_id;
	}

	public function test_the_first_publish_reads_the_image_size(): void {
		$ad_id = $this->publish_new_ad( array( 'header' ) );

		$this->assertSame( 728, (int) get_post_meta( $ad_id, '_wbam_ad_width', true ) );
		$this->assertSame( 90, (int) get_post_meta( $ad_id, '_wbam_ad_height', true ) );
		$this->assertSame( '0', get_post_meta( $ad_id, '_wbam_is_responsive', true ) );
	}

	public function test_a_placement_the_size_does_not_fit_is_named_after_the_save(): void {
		add_filter( 'wbam_enforce_format_matching', '__return_true' );
		$probe  = $this->publish_new_ad( array() );
		$misfit = '';
		foreach ( array_keys( \WBAM\Modules\Placements\Placement_Engine::get_instance()->get_selectable_placements() ) as $slug ) {
			if ( ! \WBAM\Core\Ad_Formats::fits( $probe, $slug ) ) {
				$misfit = $slug;
				break;
			}
		}
		$this->assertNotSame( '', $misfit, 'Some placement rejects a 728x90.' );

		$ad_id = $this->publish_new_ad( array( 'header', $misfit ) );
		remove_filter( 'wbam_enforce_format_matching', '__return_true' );

		$placements = (array) get_post_meta( $ad_id, '_wbam_placements', true );
		$this->assertContains( 'header', $placements );
		$this->assertNotContains( $misfit, $placements );
		$notices = get_transient( 'wbam_save_notice_' . get_current_user_id() . '_' . $ad_id );
		$this->assertIsArray( $notices, 'A removed placement is reported, never silent.' );
		$this->assertStringContainsString( 'Removed from', $notices[0]['message'] );
	}
}
