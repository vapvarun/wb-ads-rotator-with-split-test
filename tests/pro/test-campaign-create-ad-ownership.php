<?php
/**
 * An advertiser cannot create a campaign over another advertiser's ad.
 *
 * Card 10345084789: update() refused a foreign ad, but create() never
 * checked, so POST /campaigns with someone else's ad_id returned 200 and
 * moved that ad's _wbam_campaign_id to the attacker's campaign. The victim's
 * ad left its own campaign and the attacker's budget ran on their creative.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;

class Test_Campaign_Create_Ad_Ownership extends Pro_Test_Case {

	private int $attacker_user = 0;
	private int $attacker_id   = 0;
	private int $victim_user   = 0;
	private int $victim_id     = 0;
	private int $admin         = 0;

	public function set_up(): void {
		parent::set_up();

		$this->admin         = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->attacker_user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->victim_user   = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$advertisers       = Advertiser_Manager::get_instance();
		$this->attacker_id = (int) $advertisers->get_or_create( $this->attacker_user )->id;
		$this->victim_id   = (int) $advertisers->get_or_create( $this->victim_user )->id;
		$advertisers->update_status( $this->attacker_id, 'active' );
		$advertisers->update_status( $this->victim_id, 'active' );

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

	private function ad( int $author ): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
				'post_author' => $author,
			)
		);
	}

	private function create_over_rest( int $ad_id ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/wbam-pro/v1/campaigns' );
		$request->set_param( 'name', 'Takeover' );
		$request->set_param( 'ad_id', $ad_id );
		$request->set_param( 'budget', 0 );
		return rest_do_request( $request );
	}

	public function test_advertiser_cannot_create_over_another_advertisers_ad(): void {
		$ad = $this->ad( $this->victim_user );
		wp_set_current_user( $this->admin );
		$victim_campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id' => $this->victim_id,
				'name'          => 'Victim',
				'ad_id'         => $ad,
				'status'        => 'draft',
			)
		);
		$this->assertNotWPError( $victim_campaign );

		wp_set_current_user( $this->attacker_user );
		$response = $this->create_over_rest( $ad );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'wbam_campaign_ad_forbidden', $response->get_data()['code'] );
		$this->assertSame( (int) $victim_campaign->id, (int) get_post_meta( $ad, '_wbam_campaign_id', true ), 'The ad stays in its owner\'s campaign.' );
	}

	public function test_advertiser_cannot_create_over_an_unowned_admin_ad(): void {
		$ad = $this->ad( $this->admin );

		wp_set_current_user( $this->attacker_user );
		$response = $this->create_over_rest( $ad );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( '', get_post_meta( $ad, '_wbam_campaign_id', true ) );
	}

	public function test_ability_path_is_refused_too(): void {
		$ad = $this->ad( $this->victim_user );

		wp_set_current_user( $this->attacker_user );
		$result = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id' => $this->attacker_id,
				'name'          => 'Via ability',
				'ad_id'         => $ad,
				'status'        => 'draft',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'wbam_campaign_ad_forbidden', $result->get_error_code() );
	}

	public function test_advertiser_can_create_over_own_ad(): void {
		$ad = $this->ad( $this->attacker_user );

		wp_set_current_user( $this->attacker_user );
		$response = $this->create_over_rest( $ad );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( (int) $response->get_data()['id'], (int) get_post_meta( $ad, '_wbam_campaign_id', true ) );
	}

	public function test_admin_can_link_any_ad(): void {
		$ad = $this->ad( $this->victim_user );

		wp_set_current_user( $this->admin );
		$campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id' => $this->attacker_id,
				'name'          => 'Admin set up',
				'ad_id'         => $ad,
				'status'        => 'draft',
			)
		);

		$this->assertNotWPError( $campaign );
	}
}
