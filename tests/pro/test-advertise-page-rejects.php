<?php
/**
 * Advertise page QA reject (card 10342786741): in-content ads stay out of
 * the page, the theme's page title stays the only H1, the call to action
 * matches what the visitor can actually do, the audience line is a filter,
 * and the package chosen on the page is preselected on the ad form.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertise_Page_Shortcode;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Packages\Package_Manager;

class Test_Advertise_Page_Rejects extends Pro_Test_Case {

	private int $package_id;

	private $users_can_register;

	public function set_up(): void {
		parent::set_up();

		$this->users_can_register = get_option( 'users_can_register' );
		update_option( 'users_can_register', 1 );

		$dashboard = (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '[wbam_advertiser_dashboard]',
			)
		);
		update_option( 'wbam_page_advertiser_dashboard', $dashboard );
		update_option( 'wbam_credits_payment_method', 'manual' );

		$package          = Package_Manager::get_instance()->create(
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
		update_option( 'users_can_register', $this->users_can_register );
		delete_option( 'wbam_page_advertiser_dashboard' );
		delete_option( 'wbam_credits_payment_method' );
		delete_option( 'wbam_advertise_audience_note' );
		remove_all_filters( 'wbam_pro_advertise_audience_note' );
		unset( $_GET['tab'], $_GET['action'], $_GET['package'] );
		parent::tear_down();
	}

	private function render(): string {
		return html_entity_decode( ( new Advertise_Page_Shortcode() )->render() );
	}

	private function as_advertiser( string $status ): void {
		$user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->assertNotWPError( Advertiser_Manager::get_instance()->create( $user, array( 'status' => $status ) ) );
		wp_set_current_user( $user );
	}

	public function test_no_after_paragraph_ad_on_the_advertise_page(): void {
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '[wbam_advertise]',
			)
		);
		$this->go_to( get_permalink( $page ) );

		$this->assertTrue( apply_filters( 'wbam_skip_content_injection', false ) );
	}

	public function test_plugin_heading_is_not_a_second_h1(): void {
		$html = $this->render();

		$this->assertStringNotContainsString( '<h1', $html );
		$this->assertStringContainsString( '<h2 class="wbam-advertise-title"', $html );
	}

	public function test_invite_only_site_shows_contact_to_guests(): void {
		update_option( 'users_can_register', 0 );

		$html = $this->render();

		$this->assertStringNotContainsString( 'Sign up to advertise', $html );
		$this->assertStringContainsString( 'Contact us', $html );
	}

	public function test_pending_advertiser_is_not_offered_create_an_ad(): void {
		$this->as_advertiser( 'pending' );

		$html = $this->render();

		$this->assertStringNotContainsString( 'Create an ad', $html );
		$this->assertStringContainsString( 'Application pending', $html );
	}

	public function test_suspended_advertiser_is_sent_to_contact(): void {
		$this->as_advertiser( 'suspended' );

		$html = $this->render();

		$this->assertStringNotContainsString( 'Create an ad', $html );
		$this->assertStringContainsString( 'Contact us', $html );
	}

	public function test_audience_note_is_a_filter_seeded_by_the_saved_value(): void {
		$this->assertStringNotContainsString( 'wbam-advertise-audience', $this->render() );

		update_option( 'wbam_advertise_audience_note', '10,000 monthly visitors' );
		$this->assertStringContainsString( '10,000 monthly visitors', $this->render() );

		add_filter(
			'wbam_pro_advertise_audience_note',
			static function ( $note ) {
				return '' === $note ? 'unused' : '25,000 readers';
			}
		);
		$this->assertStringContainsString( '25,000 readers', $this->render() );
	}

	public function test_package_from_the_advertise_page_is_preselected_on_the_ad_form(): void {
		$modules                   = (array) Settings_Helper::get( 'enabled_modules', array() );
		$modules['packages']       = true;
		$modules['ad_submissions'] = true;
		Settings_Helper::update( 'enabled_modules', $modules );
		$this->as_advertiser( 'active' );

		$_GET['tab']     = 'ads';
		$_GET['action']  = 'new';
		$_GET['package'] = (string) $this->package_id;
		$html            = do_shortcode( '[wbam_advertiser_dashboard]' );

		$this->assertMatchesRegularExpression(
			'/name="package_id" value="' . $this->package_id . '"[^>]*checked/',
			$html
		);
	}
}
