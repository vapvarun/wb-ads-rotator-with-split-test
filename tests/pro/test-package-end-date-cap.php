<?php
/**
 * Package end date: an advertiser's end_date override cannot outrun the
 * package's paid-for duration.
 *
 * Campaign_Manager::create_from_package() let a portal end_date field
 * replace the package-derived end date outright, so a 30-day package could
 * be stretched into a year-long campaign at no extra charge.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;
use WBAM_Pro\Modules\Packages\Package_Manager;

class Test_Package_End_Date_Cap extends Pro_Test_Case {

	private int $advertiser_id;
	private object $package;

	public function set_up(): void {
		parent::set_up();

		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser       = Advertiser_Manager::get_instance()->get_or_create( $user );
		$this->advertiser_id = (int) $advertiser->id;

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'              => 'End Date Cap 30-day',
				'price'             => 49.00,
				'pricing_model'     => 'flat',
				'duration_days'     => 30,
				'requires_approval' => 1,
				'status'            => 'active',
				'created_at'        => current_time( 'mysql' ),
			)
		);
		$this->package = Package_Manager::get_instance()->get( (int) $wpdb->insert_id );
	}

	public function test_package_end_date_override_cannot_exceed_duration(): void {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );

		// Advertiser asked for a year, the package sells 30 days.
		$campaign = Campaign_Manager::get_instance()->create_from_package(
			$this->advertiser_id,
			$ad_id,
			$this->package,
			array( 'end_date' => gmdate( 'Y-m-d', strtotime( '+365 days' ) ) )
		);
		$this->assertNotWPError( $campaign );

		$max_allowed = strtotime( $campaign->start_date . ' +30 days' );

		$this->assertLessThanOrEqual(
			$max_allowed,
			strtotime( $campaign->end_date ),
			'End date override must not exceed start + the package duration.'
		);
	}

	public function test_override_within_duration_is_kept_as_requested(): void {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );

		$requested = gmdate( 'Y-m-d', strtotime( '+10 days' ) );
		$campaign  = Campaign_Manager::get_instance()->create_from_package(
			$this->advertiser_id,
			$ad_id,
			$this->package,
			array( 'end_date' => $requested )
		);
		$this->assertNotWPError( $campaign );

		$this->assertSame( $requested, substr( (string) $campaign->end_date, 0, 10 ) );
	}

	/**
	 * A running 30-day campaign: started 5 days ago, so the dates stay in the
	 * same relation to today whenever the suite runs.
	 */
	private function start_dt(): string {
		return gmdate( 'Y-m-d 17:21:00', strtotime( '-5 days' ) );
	}

	private function end_dt(): string {
		return gmdate( 'Y-m-d 17:21:00', strtotime( '+25 days' ) );
	}

	private function far_date(): string {
		return gmdate( 'Y-m-d', strtotime( '+400 days' ) );
	}

	/**
	 * Create an active 30-day package campaign starting 5 days ago.
	 */
	private function active_campaign(): int {
		$ad_id    = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		$campaign = Campaign_Manager::get_instance()->create_from_package( $this->advertiser_id, $ad_id, $this->package );
		$this->assertNotWPError( $campaign );

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'wbam_campaigns',
			array(
				'status'     => 'active',
				'start_date' => $this->start_dt(),
				'end_date'   => $this->end_dt(),
			),
			array( 'id' => $campaign->id )
		);
		return (int) $campaign->id;
	}

	public function test_advertiser_edit_cannot_extend_an_active_campaign_past_its_term(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$id = $this->active_campaign();

		// The portal edit form re-posts the unchanged start and a year-out end.
		$updated = Campaign_Manager::get_instance()->update(
			$id,
			array(
				'start_date' => substr( $this->start_dt(), 0, 10 ),
				'end_date'   => $this->far_date(),
			)
		);
		$this->assertNotWPError( $updated );

		$this->assertSame( $this->start_dt(), $updated->start_date, 'An unchanged start date must keep its time.' );
		$this->assertSame( $this->end_dt(), $updated->end_date, 'End date must stay capped at start + package duration.' );
	}

	public function test_moving_the_start_later_on_a_running_campaign_buys_no_extra_days(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$id = $this->active_campaign();

		$updated = Campaign_Manager::get_instance()->update(
			$id,
			array(
				'start_date' => gmdate( 'Y-m-d', strtotime( '+40 days' ) ),
				'end_date'   => gmdate( 'Y-m-d', strtotime( '+70 days' ) ),
			)
		);

		$this->assertLessThanOrEqual( strtotime( $this->end_dt() ), strtotime( $updated->end_date ) );
	}

	public function test_admin_extends_only_with_the_explicit_flag(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id      = $this->active_campaign();
		$manager = Campaign_Manager::get_instance();

		$capped = $manager->update( $id, array( 'end_date' => $this->far_date() ) );
		$this->assertSame( $this->end_dt(), $capped->end_date, 'Without the flag an admin edit is capped too.' );

		$extended = $manager->update(
			$id,
			array(
				'end_date'             => $this->far_date(),
				'allow_term_extension' => true,
			)
		);
		$this->assertSame( $this->far_date() . ' 23:59:59', $extended->end_date );
	}

	public function test_advertiser_cannot_pass_the_admin_flag(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$id = $this->active_campaign();

		$updated = Campaign_Manager::get_instance()->update(
			$id,
			array(
				'end_date'             => $this->far_date(),
				'allow_term_extension' => true,
			)
		);
		$this->assertSame( $this->end_dt(), $updated->end_date );
	}
}
