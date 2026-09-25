<?php
/**
 * An advertiser cannot start a campaign that skips review or payment.
 *
 * - A pending campaign is waiting for the site owner's review. The portal,
 *   REST and abilities all let its advertiser move it to active themselves.
 * - A flat campaign charges nothing on activation (packages charge on
 *   approval; a flat campaign the owner sets up is billed outside the site).
 *   An advertiser's own custom campaign on a site whose default pricing is
 *   flat went live from the portal without being charged at all.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;

class Test_Campaign_Self_Activation_Gate extends Pro_Test_Case {

	private int $advertiser_user = 0;
	private int $advertiser_id   = 0;
	private int $admin           = 0;

	public function set_up(): void {
		parent::set_up();

		$this->advertiser_user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->admin           = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );

		Factory::topup_user( $this->advertiser_user, 10000 ); // 100.00 credits.
		$this->advertiser_id = (int) Advertiser_Manager::get_instance()->get_or_create( $this->advertiser_user )->id;
	}

	public function tear_down(): void {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function create_as( int $user, array $data ): int {
		wp_set_current_user( $user );
		$campaign = Campaign_Manager::get_instance()->create(
			$data + array(
				'advertiser_id' => $this->advertiser_id,
				'name'          => 'Probe',
				'budget'        => 10,
			)
		);
		$this->assertNotWPError( $campaign );
		return (int) $campaign->id;
	}

	private function balance(): float {
		return round( (float) Credits_Bridge::get_balance( $this->advertiser_id ), 2 );
	}

	public function test_advertiser_cannot_activate_a_pending_campaign(): void {
		$id = $this->create_as(
			$this->admin,
			array(
				'pricing_model'  => 'cpm',
				'price_per_unit' => 2,
				'status'         => 'pending',
			)
		);

		wp_set_current_user( $this->advertiser_user );
		$result = Campaign_Manager::get_instance()->activate( $id );

		$this->assertWPError( $result, 'Only the site owner approves a pending campaign.' );
		$this->assertSame( 'pending', Campaign_Manager::get_instance()->get( $id )->status );
		$this->assertSame( 100.0, $this->balance() );

		wp_set_current_user( $this->admin );
		$this->assertTrue( Campaign_Manager::get_instance()->activate( $id ), 'The site owner can still approve it.' );
	}

	public function test_advertiser_flat_custom_campaign_is_not_started_for_free(): void {
		Settings_Helper::update( 'default_pricing_model', 'flat' );

		$id = $this->create_as( $this->advertiser_user, array( 'status' => 'draft' ) );
		$this->assertSame( 'flat', Campaign_Manager::get_instance()->get( $id )->pricing_model );

		$result = Campaign_Manager::get_instance()->activate( $id );

		$this->assertWPError( $result, 'A flat campaign charges nothing, so the advertiser cannot start it.' );
		$this->assertSame( 'draft', Campaign_Manager::get_instance()->get( $id )->status );

		wp_set_current_user( $this->admin );
		$this->assertTrue( Campaign_Manager::get_instance()->activate( $id ), 'The site owner, who bills flat campaigns outside the site, can start it.' );
	}

	public function test_advertiser_can_still_start_a_metered_custom_campaign(): void {
		Settings_Helper::update( 'default_pricing_model', 'cpm' );

		$id = $this->create_as( $this->advertiser_user, array( 'status' => 'draft' ) );

		$this->assertTrue( Campaign_Manager::get_instance()->activate( $id ) );
		$this->assertSame( 90.0, $this->balance(), 'The budget is reserved on activation.' );
	}
}
