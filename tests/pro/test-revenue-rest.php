<?php
/**
 * REST GET wbam-pro/v1/revenue — admin-only, WP_Error 403 for everyone else.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WP_REST_Request;

class Test_Revenue_Rest extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	private function request(): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/wbam-pro/v1/revenue' );
		$request->set_param( 'start_date', gmdate( 'Y-m-d', strtotime( '-30 days' ) ) );
		$request->set_param( 'end_date', gmdate( 'Y-m-d' ) );
		return $request;
	}

	public function test_subscriber_is_forbidden(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = rest_do_request( $this->request() );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'wbam_rest_forbidden', $response->as_error()->get_error_code() );
	}

	public function test_logged_out_visitor_is_forbidden(): void {
		wp_set_current_user( 0 );

		$response = rest_do_request( $this->request() );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_admin_gets_the_full_report_shape(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = rest_do_request( $this->request() );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		foreach ( array( 'start', 'end', 'totals', 'previous', 'change_pct', 'by_source', 'series', 'top_items', 'top_advertisers', 'by_placement', 'rpm', 'unearned_reservations', 'recent' ) as $key ) {
			$this->assertArrayHasKey( $key, $data, "Revenue report response is missing '{$key}'." );
		}
		foreach ( array( 'net', 'gross', 'refunds' ) as $key ) {
			$this->assertArrayHasKey( $key, $data['totals'] );
		}
	}
}
