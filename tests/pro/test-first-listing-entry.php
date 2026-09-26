<?php
/**
 * P0 (Basecamp 10342786531): nobody could post a FIRST listing. The
 * My Classifieds tab (home of ?tab=classifieds&action=new) was only built
 * for members who already had a listing, so everyone got "Tab not available".
 * A member allowed to post, or an admin, must reach the new-listing form.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_First_Listing_Entry extends Pro_Test_Case {

	private $classifieds_settings;

	public function set_up(): void {
		parent::set_up();
		$this->classifieds_settings = get_option( 'wbam_pro_classifieds_settings' );
		$settings                       = (array) $this->classifieds_settings;
		$settings['allow_user_posting'] = 1;
		$settings['user_posting_roles'] = array( 'subscriber' );
		update_option( 'wbam_pro_classifieds_settings', $settings );
	}

	public function tear_down(): void {
		update_option( 'wbam_pro_classifieds_settings', $this->classifieds_settings );
		unset( $_GET['tab'], $_GET['action'] );
		parent::tear_down();
	}

	private function render_new_listing_as( int $user_id ): string {
		wp_set_current_user( $user_id );
		Advertiser_Manager::get_instance()->get_or_create_member( $user_id );
		$_GET['tab']    = 'classifieds';
		$_GET['action'] = 'new';
		return do_shortcode( '[wbam_advertiser_dashboard]' );
	}

	public function test_member_allowed_to_post_reaches_the_first_listing_form(): void {
		$html = $this->render_new_listing_as( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertStringNotContainsString( 'Tab not available', $html );
	}

	public function test_admin_reaches_the_first_listing_form(): void {
		$html = $this->render_new_listing_as( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertStringNotContainsString( 'Tab not available', $html );
	}

	public function test_member_not_allowed_to_post_does_not_get_the_tab(): void {
		$html = $this->render_new_listing_as( (int) self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertStringContainsString( 'Tab not available', $html );
	}
}
