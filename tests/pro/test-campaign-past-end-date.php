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

use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Advertisers\Advertiser_Shortcodes;
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

	/** An active flat campaign ending in 30 days. */
	private function active(): int {
		$id = $this->draft( wp_date( 'Y-m-d 23:59:59', strtotime( '+30 days' ) ) );
		$this->assertTrue( Campaign_Manager::get_instance()->update_status( $id, 'active' ) );

		return $id;
	}

	/** Posts the admin campaign form; returns the refusal notice, or 'saved' when it redirected. */
	private function post_admin_form( int $id, string $status, string $end_date ): string {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST    = array(
			'wbam_save_campaign'  => '1',
			'wbam_campaign_nonce' => wp_create_nonce( 'wbam_save_campaign' ),
			'campaign_id'         => (string) $id,
			'name'                => 'End date guard',
			'advertiser_id'       => (string) $this->advertiser_id,
			'pricing_model'       => 'flat',
			'price_per_unit'      => '0',
			'status'              => $status,
			'end_date'            => $end_date,
		);
		$_REQUEST = $_POST;
		$redirect = static function () {
			throw new \RuntimeException( 'saved' );
		};
		add_filter( 'wp_redirect', $redirect );

		$method = new \ReflectionMethod( Pro_Admin::class, 'handle_campaign_form_save' );
		ob_start();
		try {
			$method->invoke( new Pro_Admin() );
			$out = ob_get_clean();
		} catch ( \RuntimeException $e ) {
			ob_end_clean();
			$out = $e->getMessage();
		}
		remove_filter( 'wp_redirect', $redirect );
		$_POST    = array();
		$_REQUEST = array();

		return $out;
	}

	/**
	 * Card 10340183600 round 2: ending a campaign and dating it in the past
	 * in one admin save is exactly what a site owner closing it out does.
	 *
	 * @dataProvider leaving_active
	 */
	public function test_admin_save_leaving_active_with_a_past_end_date_succeeds( string $status ): void {
		$id = $this->active();

		$this->assertSame( 'saved', $this->post_admin_form( $id, $status, wp_date( 'Y-m-d', strtotime( '-1 day' ) ) ) );

		$campaign = Campaign_Manager::get_instance()->get( $id );
		$this->assertSame( $status, $campaign->status );
		$this->assertLessThan( current_time( 'mysql' ), $campaign->end_date );
	}

	public function leaving_active(): array {
		return array(
			'completed' => array( 'completed' ),
			'paused'    => array( 'paused' ),
		);
	}

	public function test_admin_save_staying_active_with_a_past_end_date_is_refused(): void {
		$id = $this->active();

		$this->assertStringContainsString( 'cannot be active', $this->post_admin_form( $id, 'active', wp_date( 'Y-m-d', strtotime( '-1 day' ) ) ) );
		$this->assertGreaterThan( current_time( 'mysql' ), Campaign_Manager::get_instance()->get( $id )->end_date );
	}

	public function test_admin_save_draft_to_active_with_a_past_end_date_is_refused(): void {
		$id = $this->draft( wp_date( 'Y-m-d 23:59:59', strtotime( '+30 days' ) ) );

		$this->assertStringContainsString( 'cannot be active', $this->post_admin_form( $id, 'active', wp_date( 'Y-m-d', strtotime( '-1 day' ) ) ) );
		$this->assertSame( 'draft', Campaign_Manager::get_instance()->get( $id )->status );
	}

	public function test_portal_save_completing_with_a_past_end_date_succeeds(): void {
		$id         = $this->active();
		$advertiser = Advertiser_Manager::get_instance()->get( $this->advertiser_id );
		$_POST      = array(
			'campaign_name'   => 'End date guard',
			'campaign_status' => 'completed',
			'campaign_end'    => wp_date( 'Y-m-d', strtotime( '-1 day' ) ),
		);

		$method = new \ReflectionMethod( Advertiser_Shortcodes::class, 'process_campaign_update' );
		$result = $method->invoke( new Advertiser_Shortcodes(), $advertiser, $id );
		$_POST  = array();

		$this->assertTrue( $result );
		$this->assertSame( 'completed', Campaign_Manager::get_instance()->get( $id )->status );
	}
}
