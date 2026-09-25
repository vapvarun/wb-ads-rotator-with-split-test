<?php
/**
 * Basecamp card 10342783654 step 12: a logged-out guest clicking "Post a
 * Listing" must land on the advertiser dashboard's own login/register form
 * (account + advertiser application in one step, with company/website
 * fields) - never on WordPress core's bare wp-login.php register screen -
 * and signing in from there must return them to the submit form they came
 * from, not just the dashboard overview tab.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Classifieds\Shortcodes\Browse_Shortcode;
use WBAM_Pro\Modules\Advertisers\Advertiser_Shortcodes;

class Test_Advertiser_Registration_No_Wplogin extends Pro_Test_Case {

	private int $dashboard_page_id;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$classifieds                        = get_option( 'wbam_pro_classifieds_settings', array() );
		$classifieds['allow_user_posting']  = true;
		update_option( 'wbam_pro_classifieds_settings', $classifieds );

		$this->dashboard_page_id = (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '[wbam_advertiser_dashboard]',
			)
		);
		update_option( 'wbam_page_advertiser_dashboard', $this->dashboard_page_id );

		wp_set_current_user( 0 );
	}

	public function tear_down(): void {
		delete_option( 'wbam_page_advertiser_dashboard' );
		$_GET = array();
		parent::tear_down();
	}

	public function test_guest_post_listing_cta_skips_wplogin(): void {
		$html = ( new Browse_Shortcode() )->render( array() );

		$this->assertStringNotContainsString( 'wp-login.php', $html, 'Guests must never be sent to core wp-login.php to become a seller.' );
		$this->assertStringContainsString( (string) get_permalink( $this->dashboard_page_id ), $html, 'The Post a Listing CTA must point at the advertiser dashboard.' );
		$this->assertStringContainsString( 'redirect_to=', $html, 'The dashboard link must carry a redirect_to back to the submit form.' );
	}

	public function test_dashboard_login_form_honors_redirect_to(): void {
		$_GET['redirect_to'] = home_url( '/my-classifieds/?action=new' );

		$html = do_shortcode( '[wbam_advertiser_dashboard]' );

		$this->assertStringContainsString(
			'value="' . esc_attr( home_url( '/my-classifieds/?action=new' ) ) . '"',
			$html,
			'The login form must redirect back to the page the guest actually wanted, not just the dashboard overview.'
		);
	}

	public function test_dashboard_login_form_ignores_external_redirect(): void {
		$_GET['redirect_to'] = 'https://evil.example/steal';

		$html = do_shortcode( '[wbam_advertiser_dashboard]' );

		$this->assertStringNotContainsString( 'evil.example', $html, 'wp_validate_redirect() must reject an off-site redirect target.' );
	}
}
