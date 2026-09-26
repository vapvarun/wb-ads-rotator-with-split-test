<?php
/**
 * First-run QA reject (card 10342783654): signing up to advertise stays on
 * the site. The dashboard's own register form signs the new user in and
 * returns them to redirect_to, a sign-up from the Advertise page is an
 * advertiser application with the company the user typed, and the
 * username never becomes the company name.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Advertisers\Advertiser_Shortcodes;

class Test_Onsite_Advertiser_Signup extends Pro_Test_Case {

	private int $dashboard_page_id;

	private array $post_snapshot;

	private array $get_snapshot;

	public function set_up(): void {
		parent::set_up();

		$this->post_snapshot = $_POST;
		$this->get_snapshot  = $_GET;

		update_option( 'users_can_register', 1 );

		$enabled                = (array) Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$this->dashboard_page_id = (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '[wbam_advertiser_dashboard]',
			)
		);
		update_option( 'wbam_page_advertiser_dashboard', $this->dashboard_page_id );

		add_filter( 'send_auth_cookies', '__return_false' );
		add_filter( 'pre_wp_mail', '__return_false' );
		add_filter(
			'wp_redirect',
			static function ( $location ) {
				throw new \RuntimeException( $location );
			}
		);

		wp_set_current_user( 0 );
	}

	public function tear_down(): void {
		$_POST = $this->post_snapshot;
		$_GET  = $this->get_snapshot;
		remove_all_filters( 'wp_redirect' );
		remove_filter( 'send_auth_cookies', '__return_false' );
		remove_filter( 'pre_wp_mail', '__return_false' );
		parent::tear_down();
	}

	private function ad_form_url(): string {
		return add_query_arg(
			array(
				'tab'     => 'ads',
				'action'  => 'new',
				'package' => 7,
			),
			get_permalink( $this->dashboard_page_id )
		);
	}

	/**
	 * Submit the dashboard's register form; returns the redirect location.
	 */
	private function register( array $fields ): string {
		$_POST = array_merge(
			array(
				'wbam_register'       => '1',
				'wbam_register_nonce' => wp_create_nonce( 'wbam_register_advertiser' ),
				'reg_username'        => 'newbrand',
				'reg_email'           => 'owner@newbrand.example',
				'reg_company'         => 'New Brand Ltd',
				'reg_website'         => 'https://newbrand.example',
				'redirect_to'         => $this->ad_form_url(),
			),
			$fields
		);

		try {
			( new Advertiser_Shortcodes() )->handle_registration();
		} catch ( \RuntimeException $redirect ) {
			return $redirect->getMessage();
		}

		$this->fail( 'Registration did not redirect.' );
	}

	public function test_signup_signs_in_and_returns_to_redirect_to(): void {
		Settings_Helper::update( 'auto_approve_advertisers', true );

		$location = $this->register( array( 'reg_apply_ads' => '1' ) );

		$user = get_user_by( 'login', 'newbrand' );
		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertSame( $user->ID, get_current_user_id(), 'The new user must be signed in on-site, not sent to wp-login.php.' );
		$this->assertSame( $this->ad_form_url(), $location );
		$this->assertStringNotContainsString( 'wp-login.php', $location );
	}

	public function test_advertise_page_signup_is_an_application_with_the_typed_company(): void {
		Settings_Helper::update( 'auto_approve_advertisers', false );

		$location = $this->register( array( 'reg_apply_ads' => '1' ) );

		$advertiser = Advertiser_Manager::get_instance()->get_by_user( get_current_user_id() );
		$this->assertSame( 'pending', $advertiser->status );
		$this->assertSame( 'New Brand Ltd', $advertiser->company_name );
		$this->assertSame( 'https://newbrand.example', $advertiser->website );
		// The ad form needs approval; the dashboard shows the pending notice.
		$this->assertSame( (string) get_permalink( $this->dashboard_page_id ), $location );
	}

	public function test_signup_error_stays_on_the_form(): void {
		self::factory()->user->create( array( 'user_login' => 'newbrand' ) );

		$_POST = array(
			'wbam_register'       => '1',
			'wbam_register_nonce' => wp_create_nonce( 'wbam_register_advertiser' ),
			'reg_username'        => 'newbrand',
			'reg_email'           => 'other@newbrand.example',
		);
		( new Advertiser_Shortcodes() )->handle_registration();

		$this->assertSame( 0, get_current_user_id() );
		$this->assertStringContainsString( 'already taken', do_shortcode( '[wbam_advertiser_dashboard]' ) );
	}

	public function test_advertise_signup_link_opens_the_register_form(): void {
		$_GET['wbam_signup'] = 'advertiser';

		$html = do_shortcode( '[wbam_advertiser_dashboard]' );

		$this->assertStringContainsString( 'name="reg_apply_ads" value="1"', $html );
		$this->assertStringContainsString( 'name="reg_company"', $html );
		$this->assertDoesNotMatchRegularExpression( '/id="wbam-register-form"[^>]*hidden/', $html, 'The register form must be the open tab.' );
		$this->assertStringNotContainsString( 'jQuery(', $html, 'The tab switch must not depend on jQuery, which themes may not load.' );
	}

	public function test_member_profile_does_not_use_the_username_as_company(): void {
		$user = (int) self::factory()->user->create(
			array(
				'user_login'   => 'qa_adv',
				'display_name' => 'qa_adv',
			)
		);

		$member = Advertiser_Manager::get_instance()->get_or_create_member( $user );

		$this->assertSame( '', (string) $member->company_name );
	}

	public function test_apply_form_saves_company_and_website(): void {
		$user = (int) self::factory()->user->create( array( 'display_name' => 'qa_adv' ) );
		Advertiser_Manager::get_instance()->get_or_create_member( $user );
		wp_set_current_user( $user );

		$this->assertStringContainsString( 'name="apply_company"', do_shortcode( '[wbam_advertiser_dashboard]' ) );

		$_POST = array(
			'wbam_become_advertiser'       => '1',
			'wbam_become_advertiser_nonce' => wp_create_nonce( 'wbam_become_advertiser' ),
			'apply_company'                => 'QA Advertising Co',
			'apply_website'                => 'https://qa.example',
		);
		do_shortcode( '[wbam_advertiser_dashboard]' );

		$advertiser = Advertiser_Manager::get_instance()->get_by_user( $user );
		$this->assertContains( $advertiser->status, array( 'pending', 'active' ) );
		$this->assertSame( 'QA Advertising Co', $advertiser->company_name );
		$this->assertSame( 'https://qa.example', $advertiser->website );
	}
}
