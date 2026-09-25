<?php
/**
 * A campaign cannot be active with an end date already behind it.
 *
 * Regression guard for Basecamp card 10340183600: the campaign form saved
 * status Active with a past end date - active on paper, expired by the
 * next cron tick. Campaign_Manager is the shared path every creator
 * (admin form, portal, REST, abilities, approval) goes through.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;

class Test_Campaign_Past_End_Date extends Pro_Test_Case {

	private int $advertiser_id;

	public function set_up(): void {
		parent::set_up();

		$enabled              = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['campaigns'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update_status( (int) $advertiser->id, 'active' );
		$this->advertiser_id = (int) $advertiser->id;
	}

	/** Flat, unfunded campaign: activation needs no rate or reservation. */
	private function draft( string $end_date ): int {
		$campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id' => $this->advertiser_id,
				'name'          => 'End date guard',
				'pricing_model' => 'flat',
				'budget'        => 0,
				'start_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '-10 days' ) ),
				'end_date'      => $end_date,
				'status'        => 'draft',
			)
		);
		$this->assertNotWPError( $campaign );

		return (int) $campaign->id;
	}

	private function yesterday(): string {
		return wp_date( 'Y-m-d 23:59:59', strtotime( '-1 day' ) );
	}

	public function test_activating_with_a_past_end_date_is_refused(): void {
		$manager = Campaign_Manager::get_instance();
		$id      = $this->draft( $this->yesterday() );

		$result = $manager->update_status( $id, 'active' );

		$this->assertWPError( $result );
		$this->assertSame( 'end_date_passed', $result->get_error_code() );
		$this->assertSame( 'draft', $manager->get( $id )->status );
	}

	public function test_moving_an_active_campaign_end_date_into_the_past_is_refused(): void {
		$manager = Campaign_Manager::get_instance();
		$id      = $this->draft( wp_date( 'Y-m-d 23:59:59', strtotime( '+30 days' ) ) );
		$this->assertTrue( $manager->update_status( $id, 'active' ) );

		$result = $manager->update( $id, array( 'end_date' => $this->yesterday() ) );

		$this->assertWPError( $result );
		$this->assertSame( 'end_date_passed', $result->get_error_code() );
		$this->assertGreaterThan( current_time( 'mysql' ), $manager->get( $id )->end_date, 'The refused end date must not be written.' );
	}

	public function test_future_and_open_ended_campaigns_still_activate(): void {
		$manager = Campaign_Manager::get_instance();

		$this->assertTrue( $manager->update_status( $this->draft( wp_date( 'Y-m-d 23:59:59', strtotime( '+30 days' ) ) ), 'active' ) );

		$open = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id' => $this->advertiser_id,
				'name'          => 'No end date',
				'pricing_model' => 'flat',
				'status'        => 'draft',
			)
		);
		$this->assertTrue( $manager->update_status( (int) $open->id, 'active' ) );
	}
}
