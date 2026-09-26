<?php
/**
 * Every admin path that sets a balance keeps cents and can say what the
 * money was (card 10342784279, sibling paths): REST PUT balance takes the
 * same offline / complimentary choice as Adjust Balance, and the user
 * profile balance no longer truncates with intval().
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Core\Revenue_Query;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Advertisers\User_Profile_Integration;

class Test_Balance_Sibling_Paths extends Pro_Test_Case {

	private int $user;
	private object $advertiser;
	private array $post;

	public function set_up(): void {
		parent::set_up();
		$this->post = $_POST;

		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'wbam_revenue' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation.

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
	}

	public function tear_down(): void {
		$_POST = $this->post;
		parent::tear_down();
	}

	public function test_rest_balance_can_record_an_offline_payment(): void {
		$request = new \WP_REST_Request( 'PUT', '/wbam-pro/v1/admin/advertisers/' . (int) $this->advertiser->id );
		$request->set_body_params(
			array(
				'balance'        => 120.5,
				'balance_source' => Revenue_Ledger::SOURCE_OFFLINE_PAYMENT,
			)
		);
		rest_get_server()->dispatch( $request );

		$totals = Revenue_Query::totals( gmdate( 'Y-m-d', strtotime( '-1 day' ) ), gmdate( 'Y-m-d', strtotime( '+1 day' ) ) );
		$this->assertSame( 120.5, $totals['net'] );
	}

	public function test_profile_balance_keeps_cents(): void {
		$_POST = array(
			'wbam_user_advertiser_nonce' => wp_create_nonce( 'wbam_save_user_advertiser' ),
			'wbam_is_advertiser'         => '1',
			'wbam_balance'               => '25.50',
		);
		User_Profile_Integration::get_instance()->save_advertiser_fields( $this->user );

		$this->assertSame( 25.5, (float) Credits_Bridge::get_balance( $this->advertiser->id ) );
	}
}
