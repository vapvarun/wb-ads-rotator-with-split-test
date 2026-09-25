<?php
/**
 * Listing expiry runs through the real cron hooks: the hourly job unpublishes
 * and emails, and the daily job runs its listeners instead of recursing.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Cron_Classified_Expiry extends Pro_Test_Case {

	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create_member( $user );
	}

	private function active_listing( string $expires_at ) {
		$manager = Classified_Manager::get_instance();
		$listing = $manager->create(
			array(
				'title'         => 'Cron expiry ' . wp_generate_password( 4, false ),
				'advertiser_id' => $this->advertiser->id,
			)
		);
		$this->assertNotWPError( $listing );
		$listing->status = 'active';
		$listing->save();
		// save() refuses past dates, so set the end date the way time would.
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'wbam_classifieds', array( 'expires_at' => $expires_at ), array( 'id' => $listing->id ) );
		wp_update_post(
			array(
				'ID'          => $listing->post_id,
				'post_status' => 'publish',
			)
		);
		return $listing;
	}

	public function test_hourly_cron_expires_through_expire_and_notifies(): void {
		$listing = $this->active_listing( wp_date( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) );
		$fired   = 0;
		add_action(
			'wbam_classified_expired',
			function () use ( &$fired ) {
				++$fired;
			}
		);

		do_action( 'wbam_expire_classifieds' );

		$this->assertSame( 'expired', Classified_Manager::get_instance()->get( $listing->id )->status );
		$this->assertSame( 'draft', get_post_status( $listing->post_id ), 'An expired listing is no longer public.' );
		$this->assertSame( 1, $fired, 'wbam_classified_expired drives the owner email.' );
	}

	public function test_daily_cleanup_runs_listeners_without_recursing(): void {
		$listing = $this->active_listing( gmdate( 'Y-m-d H:i:s', time() + 3 * DAY_IN_SECONDS ) );

		do_action( 'wbam_pro_daily_cleanup' );

		$this->assertSame( '1', get_post_meta( $listing->post_id, '_wbam_expiry_warned_3', true ), 'The expiry warning listener ran.' );
	}
}
