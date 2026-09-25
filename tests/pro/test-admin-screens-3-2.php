<?php
/**
 * Admin screen regressions from the 3.2.0 admin audit.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Admin_Screens_3_2 extends Pro_Test_Case {

	/**
	 * A second admin_init handler in Advertiser_Manager ran first, handled
	 * only four actions and always redirected, so any other advertiser action
	 * (decline) was swallowed and approvals never showed their notice.
	 */
	public function test_advertiser_actions_have_a_single_handler(): void {
		$this->assertFalse( method_exists( Advertiser_Manager::class, 'handle_admin_actions' ) );
	}

	public function test_classified_stats_count_rejected_and_draft(): void {
		$manager = Classified_Manager::get_instance();
		$before  = $manager->get_stats();

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create_member( $user );
		$listing    = $manager->create(
			array(
				'title'         => 'Rejected stats listing',
				'advertiser_id' => $advertiser->id,
			)
		);
		$this->assertNotWPError( $listing );
		$listing->status = 'rejected';
		$listing->save();

		$after = $manager->get_stats();
		$this->assertSame( $before['rejected'] + 1, $after['rejected'], 'The admin list needs a Rejected view count.' );
		$this->assertArrayHasKey( 'draft', $after );
	}
}
