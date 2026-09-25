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
}
