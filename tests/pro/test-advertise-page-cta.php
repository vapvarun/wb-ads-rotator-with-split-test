<?php
/**
 * The public 'Advertise with us' page ([wbam_advertise]) has exactly one
 * call to action per package, and it adapts to who is looking: a guest is
 * sent to the advertiser dashboard's own register/apply form with a
 * redirect_to back to the ad form (package preselected), a signed-in user
 * with no advertiser profile yet is sent to apply, and an existing
 * advertiser is sent straight to create an ad with that package already
 * chosen.
 *
 * Card 10342786741.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertise_Page_Shortcode;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Packages\Package_Manager;

class Test_Advertise_Page_Cta extends Pro_Test_Case {

	private int $dashboard_page_id;
	private int $package_id;

	public function set_up(): void {
		parent::set_up();

		$this->dashboard_page_id = (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '[wbam_advertiser_dashboard]',
			)
		);
		update_option( 'wbam_page_advertiser_dashboard', $this->dashboard_page_id );

		// A working payment method, so the payment-state override (see
		// Test_Advertise_Page_Payment_None) never masks the per-visitor CTA
		// this test is about.
		update_option( 'wbam_credits_payment_method', 'manual' );

		$package = Package_Manager::get_instance()->create(
			array(
				'name'   => 'Starter',
				'price'  => 49.0,
				'status' => 'active',
			)
		);
		$this->package_id = (int) $package->id;

		wp_set_current_user( 0 );
	}

	public function tear_down(): void {
		delete_option( 'wbam_page_advertiser_dashboard' );
		delete_option( 'wbam_credits_payment_method' );
		parent::tear_down();
	}

	private function render(): string {
		return html_entity_decode( ( new Advertise_Page_Shortcode() )->render() );
	}

	public function test_guest_cta_redirects_through_dashboard_to_the_preselected_ad_form(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'Sign up to advertise', $html );
		$this->assertStringContainsString( (string) get_permalink( $this->dashboard_page_id ), $html, 'Guests must land on the advertiser dashboard, not core wp-login.php.' );
		$this->assertStringContainsString( 'redirect_to=', $html );
		$this->assertStringContainsString( 'tab%3Dads', $html, 'redirect_to must be URL-encoded (rawurlencode), carrying the ads tab.' );
		$this->assertStringContainsString( 'package%3D' . $this->package_id, $html, 'redirect_to must carry the chosen package id.' );
	}

	public function test_logged_in_non_advertiser_cta_is_apply_to_advertise(): void {
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user );

		$html = $this->render();

		$this->assertStringContainsString( 'Apply to advertise', $html );
		$this->assertStringContainsString( (string) get_permalink( $this->dashboard_page_id ), $html );
	}

	public function test_existing_advertiser_cta_creates_an_ad_with_the_package_preselected(): void {
		$user       = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->create( $user, array( 'status' => 'active' ) );
		$this->assertNotWPError( $advertiser );
		wp_set_current_user( $user );

		$html = $this->render();

		$this->assertStringContainsString( 'Create an ad', $html );
		$this->assertStringContainsString( 'tab=ads', $html );
		$this->assertStringContainsString( 'action=new', $html );
		$this->assertStringContainsString( 'package=' . $this->package_id, $html );
	}

	public function test_member_status_still_gets_apply_to_advertise(): void {
		// A classifieds member (no display-ad advertiser profile yet) must
		// see the same apply prompt as a plain subscriber, not "Create an ad".
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		Advertiser_Manager::get_instance()->get_or_create_member( $user );
		wp_set_current_user( $user );

		$html = $this->render();

		$this->assertStringContainsString( 'Apply to advertise', $html );
		$this->assertStringNotContainsString( 'Create an ad', $html );
	}
}
