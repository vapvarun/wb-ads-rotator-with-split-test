<?php
/**
 * When Credits_Bridge::payment_method_status() is 'none' (no gateway
 * configured and the owner never chose manual top-up), the public
 * 'Advertise with us' page must still list its packages - the owner still
 * wants visitors to see what advertising costs - but every "buy" CTA must
 * become "Contact us" instead of a dead-end.
 *
 * Card 10342786741.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertise_Page_Shortcode;
use WBAM_Pro\Modules\Packages\Package_Manager;

class Test_Advertise_Page_Payment_None extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();

		// Fresh test env: no gateway configured, no manual choice made -
		// Credits_Bridge::payment_method_status() resolves to 'none'.
		delete_option( 'wbam_credits_payment_method' );
		delete_option( 'wbcom_credits_gateway_settings_wbam-pro' );

		Package_Manager::get_instance()->create(
			array(
				'name'   => 'Starter',
				'price'  => 49.0,
				'status' => 'active',
			)
		);

		wp_set_current_user( 0 );
	}

	public function tear_down(): void {
		delete_option( 'wbam_credits_payment_method' );
		delete_option( 'wbam_page_contact' );
		parent::tear_down();
	}

	private function render(): string {
		return ( new Advertise_Page_Shortcode() )->render();
	}

	public function test_no_payment_method_shows_packages_with_contact_us_cta(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'Starter', $html, 'The package must still be listed.' );
		$this->assertStringContainsString( 'Contact us', $html );
		$this->assertStringNotContainsString( 'Sign up to advertise', $html, 'Nobody can pay, so the buy/sign-up CTA must not appear.' );
	}

	public function test_contact_us_links_to_the_configured_contact_page_when_set(): void {
		$contact_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		update_option( 'wbam_page_contact', $contact_page_id );

		$html = $this->render();

		$this->assertStringContainsString( (string) get_permalink( $contact_page_id ), $html );
	}

	public function test_manual_topup_choice_restores_the_normal_cta(): void {
		update_option( 'wbam_credits_payment_method', 'manual' );

		$html = $this->render();

		$this->assertStringContainsString( 'Sign up to advertise', $html );
		$this->assertStringNotContainsString( 'Contact us', $html );
	}
}
