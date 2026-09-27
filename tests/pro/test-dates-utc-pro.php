<?php
/**
 * Pro dates: campaign dates are picked in the site's zone and stored in
 * UTC, and an active campaign that has not started shows as Scheduled
 * (docs/standards/dates.md, owner decisions 2026-09-27, card 10344269919).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Core\Status_Labels;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;

/**
 * @group pro
 * @group dates
 */
class Test_Dates_Utc_Pro extends Pro_Test_Case {

	private int $advertiser_id;

	public function set_up(): void {
		parent::set_up();
		update_option( 'timezone_string', 'Asia/Kolkata' );
		update_option( 'gmt_offset', '' );

		$enabled              = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['campaigns'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$user                = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser_id = (int) Advertiser_Manager::get_instance()->get_or_create( $user )->id;
	}

	private function campaign( array $dates ) {
		$campaign = Campaign_Manager::get_instance()->create(
			array_merge(
				array(
					'advertiser_id' => $this->advertiser_id,
					'name'          => 'Dates probe',
					'pricing_model' => 'flat',
					'budget'        => 0,
					'status'        => 'draft',
				),
				$dates
			)
		);
		$this->assertNotWPError( $campaign );

		return $campaign;
	}

	public function test_a_picked_day_is_that_site_day_in_utc(): void {
		$campaign = $this->campaign(
			array(
				'start_date' => '2026-10-01',
				'end_date'   => '2026-10-31',
			)
		);

		// Kolkata midnight is 18:30 UTC the day before.
		$this->assertSame( '2026-09-30 18:30:00', $campaign->start_date );
		$this->assertSame( '2026-10-31 18:29:59', $campaign->end_date );
		$this->assertSame( '2026-10-01', wbam_format_datetime( $campaign->start_date, 'Y-m-d' ), 'Shown as the day the advertiser picked.' );
	}

	public function test_resaving_the_same_day_keeps_the_stored_time(): void {
		$campaign = $this->campaign( array( 'start_date' => '2026-10-01 11:51:00' ) );

		Campaign_Manager::get_instance()->update( $campaign->id, array( 'start_date' => '2026-10-01' ) );

		$this->assertSame( '2026-10-01 11:51:00', Campaign_Manager::get_instance()->get( $campaign->id )->start_date );
	}

	public function test_rest_and_abilities_send_site_time(): void {
		$this->assertSame( '2026-10-01 04:30:00', Campaign_Manager::from_site_input( '2026-10-01 10:00:00' ) );
		$this->assertSame( '2026-10-01', Campaign_Manager::from_site_input( '2026-10-01' ), 'A bare day is left for create()/update().' );
		$this->assertNull( Campaign_Manager::from_site_input( '' ) );
	}

	public function test_an_active_campaign_before_its_start_is_scheduled(): void {
		$this->assertSame( 'scheduled', Status_Labels::display_status( 'active', gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) ) );
		$this->assertSame( 'active', Status_Labels::display_status( 'active', gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) );
		$this->assertSame( 'paused', Status_Labels::display_status( 'paused', gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) ) );
		$this->assertSame( 'active', Status_Labels::display_status( 'active', '' ) );

		// An ad's schedule start is a site-calendar day.
		$this->assertSame( 'scheduled', Status_Labels::display_status( 'active', wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) ) );
		$this->assertSame( 'active', Status_Labels::display_status( 'active', wp_date( 'Y-m-d' ) ) );

		$this->assertSame( 'Scheduled', Status_Labels::get_label( 'campaign', 'scheduled' ) );
		$this->assertSame( 'Scheduled', Status_Labels::get_label( 'ad', 'scheduled' ) );
	}

	public function test_a_campaign_label_reads_scheduled_until_it_starts(): void {
		$campaign             = $this->campaign( array( 'start_date' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) ) );
		$campaign->status     = 'active';
		$this->assertSame( 'Scheduled', $campaign->get_status_label() );

		$campaign->start_date = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$this->assertSame( 'Live', $campaign->get_status_label() );
	}

	public function test_the_pro_conversion_plan_leaves_utc_columns_alone(): void {
		$plan = \WBAM_Pro\Core\Installer::utc_plan();

		$this->assertArrayNotHasKey( 'expires_at', $plan['wbam_classifieds'], 'Written in UTC since 3.1.1.' );
		$this->assertArrayNotHasKey( 'wbam_revenue', $plan, 'The revenue ledger is UTC.' );
		$this->assertSame( 'server', $plan['wbam_campaigns']['updated_at'] );
		$this->assertSame( 'local', $plan['wbam_campaigns']['start_date'] );
	}
}
