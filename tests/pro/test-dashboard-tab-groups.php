<?php
/**
 * Dashboard tabs are grouped by activity, not a manual role picker
 * (Basecamp #10342786531): a buyer only sees Buying, a seller only Selling,
 * a display-ad applicant only Advertising, and a brand-new member sees none
 * of the three (the getting-started state instead).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Advertisers\Dashboard_Tabs;

class Test_Dashboard_Tab_Groups extends Pro_Test_Case {

	public function test_brand_new_member_has_no_group(): void {
		$user_id    = (int) self::factory()->user->create();
		$advertiser = Advertiser_Manager::get_instance()->get_or_create_member( $user_id );

		$groups = Dashboard_Tabs::derive_groups( $advertiser, $user_id );

		$this->assertFalse( $groups['buying'] );
		$this->assertFalse( $groups['selling'] );
		$this->assertFalse( $groups['advertising'] );
		$this->assertTrue( Dashboard_Tabs::is_brand_new( $groups ) );
	}

	public function test_a_favorite_puts_a_member_in_the_buying_group_only(): void {
		$user_id    = (int) self::factory()->user->create();
		$advertiser = Advertiser_Manager::get_instance()->get_or_create_member( $user_id );
		update_user_meta( $user_id, '_wbam_favorite_classifieds', array( 123 ) );

		$groups = Dashboard_Tabs::derive_groups( $advertiser, $user_id );

		$this->assertTrue( $groups['buying'] );
		$this->assertFalse( $groups['selling'] );
		$this->assertFalse( $groups['advertising'] );
	}

	public function test_a_listing_puts_a_member_in_the_selling_group_only(): void {
		global $wpdb;

		$user_id    = (int) self::factory()->user->create();
		$advertiser = Advertiser_Manager::get_instance()->get_or_create_member( $user_id );
		$post_id    = self::factory()->post->create( array( 'post_type' => 'wbam-classified' ) );

		$wpdb->insert(
			$wpdb->prefix . 'wbam_classifieds',
			array(
				'post_id'       => $post_id,
				'advertiser_id' => $advertiser->id,
				'status'        => 'pending',
			)
		);

		$groups = Dashboard_Tabs::derive_groups( $advertiser, $user_id );

		$this->assertFalse( $groups['buying'] );
		$this->assertTrue( $groups['selling'] );
		$this->assertFalse( $groups['advertising'] );
	}

	public function test_a_pending_advertiser_application_puts_a_member_in_the_advertising_group_only(): void {
		$user_id    = (int) self::factory()->user->create();
		$advertiser = Advertiser_Manager::get_instance()->create( $user_id, array( 'status' => 'pending' ) );

		$groups = Dashboard_Tabs::derive_groups( $advertiser, $user_id );

		$this->assertFalse( $groups['buying'] );
		$this->assertFalse( $groups['selling'] );
		$this->assertTrue( $groups['advertising'] );
	}

	public function test_a_logged_out_visitor_has_no_group(): void {
		$groups = Dashboard_Tabs::derive_groups( null, 0 );

		$this->assertFalse( $groups['buying'] );
		$this->assertFalse( $groups['selling'] );
		$this->assertFalse( $groups['advertising'] );
	}
}
