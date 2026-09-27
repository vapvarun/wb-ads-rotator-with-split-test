<?php
/**
 * Every REST route and ability that returns a balance also returns
 * `balance_minor`, the exact integer minor units. `balance` itself keeps
 * its existing shape (owner rule: never change an existing field's type).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Pro_Abilities;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

/**
 * @group pro
 * @group money
 */
class Test_Api_Balance_Minor extends Pro_Test_Case {

	private int $user;
	private int $advertiser_id;

	public function set_up(): void {
		parent::set_up();
		$this->user          = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser_id = (int) Advertiser_Manager::get_instance()->get_or_create( $this->user )->id;
		// 5.50 - 0.01 = 5.49: a float that JSON-encodes as 5.4900000000000002.
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 550, 'seed' );
		\Wbcom\Credits\Credits::adjust( 'wbam-pro', $this->user, -1, 'debit' );
		do_action( 'rest_api_init' );
	}

	private function get( string $route, int $as_user ): array {
		wp_set_current_user( $as_user );
		$response = rest_do_request( new \WP_REST_Request( 'GET', $route ) );
		$this->assertSame( 200, $response->get_status(), $route );
		return (array) $response->get_data();
	}

	public function test_rest_routes_return_exact_minor_units_beside_balance(): void {
		$admin = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$cases = array(
			array( '/wbam-pro/v1/advertiser/wallet', $this->user ),
			array( '/wbam-pro/v1/advertiser/stats', $this->user ),
			array( '/wbam-pro/v1/advertiser/profile', $this->user ),
			array( '/wbam-pro/v1/admin/advertisers/' . $this->advertiser_id, $admin ),
		);

		foreach ( $cases as $case ) {
			$data = $this->get( $case[0], $case[1] );
			$this->assertArrayHasKey( 'balance_minor', $data, $case[0] );
			$this->assertSame( 549, $data['balance_minor'], $case[0] );
			$this->assertSame( Credits_Bridge::get_balance( $this->advertiser_id ), $data['balance'], $case[0] . ' keeps balance as it was.' );
		}
	}

	public function test_admin_list_items_carry_minor_units(): void {
		$admin = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$data  = $this->get( '/wbam-pro/v1/admin/advertisers', $admin );
		$items = isset( $data['advertisers'] ) ? $data['advertisers'] : ( isset( $data['items'] ) ? $data['items'] : $data );
		$mine  = wp_list_filter( (array) $items, array( 'id' => $this->advertiser_id ) );

		$this->assertCount( 1, $mine );
		$this->assertSame( 549, current( $mine )['balance_minor'] );
	}

	public function test_empty_wallet_reports_zero_minor_units(): void {
		$stranger = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$data     = $this->get( '/wbam-pro/v1/advertiser/wallet', $stranger );

		$this->assertSame( 0, $data['balance_minor'] );
	}

	public function test_abilities_return_minor_units_and_keep_balance(): void {
		$abilities = new Pro_Abilities();
		wp_set_current_user( $this->user );

		$balance = $abilities->execute_get_balance( array() );
		$this->assertSame( 549, $balance['balance_minor'] );
		$this->assertSame( Credits_Bridge::get_balance( $this->advertiser_id ), $balance['balance'], 'The rounded decimal the schema declares, never truncated.' );
		$this->assertSame( wbam_get_currency_code(), $balance['currency'] );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$one = $abilities->execute_get_advertiser( array( 'id' => $this->advertiser_id ) );
		$this->assertSame( 549, $one['balance_minor'] );

		$list = $abilities->execute_list_advertisers( array( 'per_page' => 100 ) );
		$mine = wp_list_filter( $list['items'], array( 'id' => $this->advertiser_id ) );
		$this->assertSame( 549, current( $mine )['balance_minor'] );
	}
}
