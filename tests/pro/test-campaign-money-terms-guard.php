<?php
/**
 * An advertiser cannot change what a campaign costs, and a refund cannot
 * return more than was charged.
 *
 * PATCH /wbam-pro/v1/campaigns/{id} passed an owner's budget, pricing model,
 * rate and limits straight into Campaign_Manager::update() for any draft,
 * pending or paused campaign. Two exploits followed:
 *
 * - Raise a paused campaign's budget, then delete it: the refund was
 *   budget - spent, so the advertiser was credited money never paid.
 * - Drop a paused campaign's rate to almost nothing, then resume: delivery
 *   billed at the advertiser's own price.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;

class Test_Campaign_Money_Terms_Guard extends Pro_Test_Case {

	/**
	 * Advertiser's WordPress user.
	 *
	 * @var int
	 */
	private int $advertiser_user = 0;

	/**
	 * Advertiser row ID.
	 *
	 * @var int
	 */
	private int $advertiser_id = 0;

	/**
	 * Site admin.
	 *
	 * @var int
	 */
	private int $admin = 0;

	public function set_up(): void {
		parent::set_up();

		$this->advertiser_user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->admin           = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );

		// 100.00 credits.
		Factory::topup_user( $this->advertiser_user, 10000 );
		$this->advertiser_id = (int) Advertiser_Manager::get_instance()->get_or_create( $this->advertiser_user )->id;

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * A paid, running campaign the admin set up, then paused - the state the
	 * card's exploits start from. Returns its ID; 10.00 is reserved.
	 */
	private function paused_paid_campaign( string $model = 'cpm', float $rate = 5.0 ): int {
		wp_set_current_user( $this->admin );

		$manager  = Campaign_Manager::get_instance();
		$campaign = $manager->create(
			array(
				'advertiser_id'  => $this->advertiser_id,
				'name'           => 'Paid campaign',
				'pricing_model'  => $model,
				'price_per_unit' => $rate,
				'budget'         => 10,
				'status'         => 'draft',
			)
		);
		$this->assertNotWPError( $campaign );
		$this->assertTrue( $manager->activate( $campaign->id ) );
		$this->assertTrue( $manager->pause( $campaign->id ) );
		$this->assertSame( 90.0, $this->balance(), 'Activation reserves the 10.00 budget.' );

		return (int) $campaign->id;
	}

	private function balance(): float {
		return round( (float) Credits_Bridge::get_balance( $this->advertiser_id ), 2 );
	}

	private function rest( string $method, string $route, array $params = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, '/wbam-pro/v1' . $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_do_request( $request );
	}

	/**
	 * Exploit 1: raise the budget, then delete - the refund returned money
	 * that was never paid.
	 */
	public function test_advertiser_budget_raise_then_delete_does_not_mint_credits(): void {
		$id = $this->paused_paid_campaign();

		wp_set_current_user( $this->advertiser_user );
		$this->rest( 'PATCH', '/campaigns/' . $id, array( 'budget' => 1000 ) );
		$this->rest( 'DELETE', '/campaigns/' . $id );

		$this->assertSame( 100.0, $this->balance(), 'Only the 10.00 reservation may come back; anything more is minted credit.' );
	}

	/**
	 * Exploit 2: set your own rate while paused, then resume.
	 */
	public function test_advertiser_cannot_set_own_rate_then_resume(): void {
		$id = $this->paused_paid_campaign( 'cpc', 1.0 );

		wp_set_current_user( $this->advertiser_user );
		$patch = $this->rest(
			'PATCH',
			'/campaigns/' . $id,
			array(
				'price_per_unit' => 0.0001,
				'pricing_model'  => 'cpc',
			)
		);

		$resume = $this->rest( 'POST', '/campaigns/' . $id . '/resume' );
		$this->assertSame( 200, $resume->get_status() );

		$campaign = Campaign_Manager::get_instance()->get( $id );
		$this->assertTrue( $campaign->record_click() );

		$campaign = Campaign_Manager::get_instance()->get( $id );
		$this->assertSame( 1.0, round( (float) $campaign->spent, 4 ), 'A click must bill at the rate the site set, not the advertiser.' );
		$this->assertGreaterThanOrEqual( 400, $patch->get_status(), 'An advertiser changing the rate is refused, not ignored.' );
	}

	/**
	 * Every advertiser path funnels through Campaign_Manager::update(): the
	 * portal form and the update-campaign ability call it directly.
	 */
	public function test_manager_refuses_advertiser_money_terms_on_any_path(): void {
		$id = $this->paused_paid_campaign();

		wp_set_current_user( $this->advertiser_user );
		$manager = Campaign_Manager::get_instance();

		foreach ( array(
			'budget'            => 1000,
			'pricing_model'     => 'flat',
			'price_per_unit'    => 0.01,
			'impressions_limit' => 999999,
			'clicks_limit'      => 999999,
			'package_id'        => 12345,
		) as $field => $value ) {
			$result = $manager->update( $id, array( $field => $value ) );
			$this->assertWPError( $result, $field . ' must be refused for an advertiser.' );
			$this->assertSame( 'wbam_campaign_terms_locked', $result->get_error_code() );
		}

		$campaign = $manager->get( $id );
		$this->assertSame( 10.0, (float) $campaign->budget );
		$this->assertSame( 'cpm', $campaign->pricing_model );
		$this->assertSame( 5.0, (float) $campaign->price_per_unit );
		$this->assertNull( $campaign->impressions_limit );
		$this->assertNull( $campaign->package_id );

		// Re-posting the current value is not a change and does not block the save.
		$result = $manager->update(
			$id,
			array(
				'name'   => 'Renamed',
				'budget' => '10.00',
			)
		);
		$this->assertNotWPError( $result );
		$this->assertSame( 'Renamed', $manager->get( $id )->name );
	}

	/**
	 * An advertiser creating a campaign cannot pick its price: REST refuses
	 * the fields, and every other creator (abilities, duplicate) gets the
	 * site's default rate from Campaign_Manager::create().
	 */
	public function test_advertiser_created_campaign_uses_site_rate(): void {
		Advertiser_Manager::get_instance()->update_status( $this->advertiser_id, 'active' );
		wp_set_current_user( $this->advertiser_user );

		$refused = $this->rest(
			'POST',
			'/campaigns',
			array(
				'name'           => 'Self priced',
				'budget'         => 10,
				'pricing_model'  => 'cpc',
				'price_per_unit' => 0.0001,
			)
		);
		$this->assertGreaterThanOrEqual( 400, $refused->get_status() );

		$allowed = $this->rest(
			'POST',
			'/campaigns',
			array(
				'name'   => 'Custom',
				'budget' => 10,
			)
		);
		$this->assertSame( 200, $allowed->get_status() );
		$this->assertSame( 10.0, (float) $allowed->get_data()['budget'], 'A custom campaign keeps the budget the advertiser chose.' );

		$campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id'  => $this->advertiser_id,
				'name'           => 'Via ability',
				'pricing_model'  => 'cpc',
				'price_per_unit' => 0.0001,
				'clicks_limit'   => 1000000,
				'package_id'     => 777,
				'status'         => 'draft',
			)
		);
		$this->assertNotEquals( 0.0001, (float) $campaign->price_per_unit );
		$this->assertGreaterThan( 0.0, (float) $campaign->price_per_unit );
		$this->assertNull( $campaign->clicks_limit );
		$this->assertNull( $campaign->package_id );
	}

	public function test_admin_can_still_change_terms(): void {
		$id = $this->paused_paid_campaign();

		wp_set_current_user( $this->admin );
		$response = $this->rest(
			'PATCH',
			'/campaigns/' . $id,
			array(
				'budget'         => 20,
				'price_per_unit' => 7.5,
				'clicks_limit'   => 50,
			)
		);
		$this->assertSame( 200, $response->get_status() );

		$campaign = Campaign_Manager::get_instance()->get( $id );
		$this->assertSame( 20.0, (float) $campaign->budget );
		$this->assertSame( 7.5, (float) $campaign->price_per_unit );
		$this->assertSame( 50, (int) $campaign->clicks_limit );
	}

	public function test_advertiser_can_still_rename_and_change_dates(): void {
		$id  = $this->paused_paid_campaign();
		$end = gmdate( 'Y-m-d', strtotime( '+20 days' ) );

		wp_set_current_user( $this->advertiser_user );
		$response = $this->rest(
			'PATCH',
			'/campaigns/' . $id,
			array(
				'name'     => 'Spring sale',
				'end_date' => $end,
			)
		);
		$this->assertSame( 200, $response->get_status() );

		$campaign = Campaign_Manager::get_instance()->get( $id );
		$this->assertSame( 'Spring sale', $campaign->name );
		$this->assertSame( 0, strpos( (string) $campaign->end_date, $end ) );
	}

	/**
	 * Even when a budget is raised with nothing charged for it - here by an
	 * admin, the one caller still allowed to - cancelling returns only what
	 * the ledger shows was charged, less what was spent.
	 */
	public function test_refund_never_exceeds_net_charged(): void {
		$id      = $this->paused_paid_campaign();
		$manager = Campaign_Manager::get_instance();

		wp_set_current_user( $this->admin );
		$this->assertNotWPError( $manager->update( $id, array( 'budget' => 1000 ) ) );

		// 2.00 of the 10.00 reservation was delivered before the pause.
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'wbam_campaigns', array( 'spent' => 2 ), array( 'id' => $id ) );

		$this->assertTrue( $manager->cancel( $id ) );
		$this->assertSame( 98.0, $this->balance(), 'Refund = 10.00 charged - 2.00 spent.' );

		// A second attempt (retry, double click) has nothing left to return.
		$this->assertSame( 0.0, $manager->refundable_amount( $manager->get( $id ) ) );
	}
}
