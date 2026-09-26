<?php
/**
 * Every save path merges into _wbam_ad_data, so an advertiser's portal edit
 * or a partial REST update never clears the owner's per-placement options.
 *
 * Card 10342823390: the portal rebuilt the ad data from scratch and the REST
 * update replaced it wholesale, wiping popup and sticky settings.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;

class Test_Ad_Data_Merge extends Pro_Test_Case {

	private function owner_configured_ad(): int {
		$ad_id = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta(
			$ad_id,
			'_wbam_ad_data',
			array(
				'type'            => 'image',
				'image_url'       => 'https://example.com/old.png',
				'popup_trigger'   => 'scroll',
				'popup_scroll'    => 30,
				'sticky_position' => 'bottom-bar',
			)
		);

		return $ad_id;
	}

	private function assert_owner_keys_kept( int $ad_id, string $path ): void {
		$data = get_post_meta( $ad_id, '_wbam_ad_data', true );
		$this->assertSame( 'scroll', $data['popup_trigger'] ?? null, "{$path} keeps the popup trigger." );
		$this->assertSame( 'bottom-bar', $data['sticky_position'] ?? null, "{$path} keeps the sticky position." );
	}

	public function test_portal_and_rest_saves_keep_owner_options(): void {
		$ad_id = $this->owner_configured_ad();
		Ad_Submission_Manager::get_instance()->update_ad_meta(
			$ad_id,
			array(
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/new.png',
				'click_url' => 'https://example.com',
			)
		);
		$this->assertSame( 'https://example.com/new.png', get_post_meta( $ad_id, '_wbam_ad_data', true )['image_url'], 'The advertiser\'s creative change lands.' );
		$this->assert_owner_keys_kept( $ad_id, 'A portal edit' );

		$ad_id = $this->owner_configured_ad();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$request = new \WP_REST_Request( 'POST', '/wbam/v1/ads/' . $ad_id );
		$request->set_body_params( array( 'ad_data' => array( 'image_url' => 'https://example.com/rest.png' ) ) );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'image', get_post_meta( $ad_id, '_wbam_ad_data', true )['type'] ?? null, 'A partial REST update keeps the type.' );
		$this->assert_owner_keys_kept( $ad_id, 'A partial REST update' );
	}
}
