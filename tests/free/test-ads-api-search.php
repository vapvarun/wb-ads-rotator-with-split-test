<?php
/**
 * GET /wbam/v1/ads can search by title and fetch given ids, so the WB Ad
 * block's picker works on sites with more than 100 ads (the old picker
 * loaded one page of 100 and stopped). Card 10342783037.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Tests\Helpers\Factory;
use WP_REST_Request;
use WP_UnitTestCase;

class Test_Ads_Api_Search extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	private function enabled_ad( string $title ): int {
		$ad_id = Factory::make_ad( array( 'post_title' => $title ) );
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		return $ad_id;
	}

	private function titles( array $query ): array {
		$request = new WP_REST_Request( 'GET', '/wbam/v1/ads' );
		$request->set_query_params( $query );
		$data = rest_do_request( $request )->get_data();

		return wp_list_pluck( $data['ads'], 'title' );
	}

	public function test_search_and_include(): void {
		$this->enabled_ad( 'Summer banner' );
		$needle = $this->enabled_ad( 'Winter needle promo' );
		$hidden = Factory::make_ad( array( 'post_title' => 'Winter disabled' ) );

		$this->assertSame( array( 'Winter needle promo' ), $this->titles( array( 'search' => 'needle' ) ) );
		$this->assertSame( array( 'Winter needle promo' ), $this->titles( array( 'include' => array( $needle ) ) ) );
		$this->assertSame( array(), $this->titles( array( 'include' => array( $hidden ) ) ), 'Disabled ads stay out of the public list.' );
	}
}
