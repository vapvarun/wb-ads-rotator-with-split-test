<?php
/**
 * Member profiles: classifieds sellers are independent of display-ad approval.
 *
 * Owner rule (3.2.0): ads and classifieds are two independent features. A
 * member profile ('member' status) can sell, message and hold a wallet, but
 * every display-ad gate still requires an approved advertiser ('active').
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Shortcodes;

class Test_Member_Profile extends Pro_Test_Case {

	private int $user;

	public function set_up(): void {
		parent::set_up();
		$this->user = (int) self::factory()->user->create(
			array(
				'role'         => 'subscriber',
				'display_name' => 'Mira Member Test',
			)
		);

		// Start from "no profile": the auto_create_advertisers setting may
		// already have made one on user_register.
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'wbam_advertisers', array( 'user_id' => $this->user ) );
	}

	public function test_member_profile_has_no_advertiser_role(): void {
		$member = Advertiser_Manager::get_instance()->get_or_create_member( $this->user );

		$this->assertNotWPError( $member );
		$this->assertSame( 'member', $member->status );
		$this->assertTrue( $member->can_sell() );
		$this->assertFalse( $member->is_active(), 'A member is not approved for display ads.' );
		$this->assertNotContains( 'wbam_advertiser', get_userdata( $this->user )->roles );
	}

	public function test_get_or_create_member_returns_existing_profile(): void {
		$manager = Advertiser_Manager::get_instance();
		$first   = $manager->get_or_create_member( $this->user );
		$second  = $manager->get_or_create_member( $this->user );

		$this->assertSame( (int) $first->id, (int) $second->id );
	}

	public function test_member_cannot_submit_display_ads(): void {
		$member = Advertiser_Manager::get_instance()->get_or_create_member( $this->user );

		$result = Ad_Submission_Manager::get_instance()->submit_ad(
			$member->id,
			array(
				'title'     => 'Member ad attempt',
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/x.png',
				'link_url'  => 'https://example.com',
			)
		);

		$this->assertWPError( $result );
	}

	public function test_member_and_pending_can_post_classifieds_suspended_cannot(): void {
		$manager = Advertiser_Manager::get_instance();
		$member  = $manager->get_or_create_member( $this->user );
		$gate    = Classified_Shortcodes::get_instance();

		$this->assertTrue( $gate->validate_advertiser_can_post( $member ) );

		$manager->update_status( $member->id, 'pending' );
		$this->assertTrue( $gate->validate_advertiser_can_post( $manager->get( $member->id ) ), 'An ad applicant still sells.' );

		$manager->update_status( $member->id, 'suspended' );
		$this->assertWPError( $gate->validate_advertiser_can_post( $manager->get( $member->id ) ) );
	}

	public function test_approving_a_member_grants_ads_and_withdrawing_removes_role(): void {
		$manager = Advertiser_Manager::get_instance();
		$member  = $manager->get_or_create_member( $this->user );

		$manager->update_status( $member->id, 'active' );
		$this->assertContains( 'wbam_advertiser', get_userdata( $this->user )->roles );

		$manager->update_status( $member->id, 'member' );
		$this->assertSame( 'member', $manager->get( $member->id )->status, 'member must survive status normalisation.' );
		$this->assertNotContains( 'wbam_advertiser', get_userdata( $this->user )->roles );
	}

	public function test_seller_slug_resolves_member(): void {
		$member = Advertiser_Manager::get_instance()->get_or_create_member( $this->user );

		$found = Advertiser_Manager::get_instance()->get_by_slug( 'mira-member-test' );
		$this->assertNotNull( $found );
		$this->assertSame( (int) $member->id, (int) $found->id );
	}
}
