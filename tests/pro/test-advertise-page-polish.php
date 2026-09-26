<?php
/**
 * Advertise page wave-5 polish (card 10342783037): the theme's page title
 * is the only title, a guest on a site with registration closed still gets
 * a way in (sign in; the dashboard offers "Contact us" beside it), and the
 * package slot label does not talk to a guest as if they were an advertiser.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertise_Page_Shortcode;
use WBAM_Pro\Modules\Packages\Package_Manager;

class Test_Advertise_Page_Polish extends Pro_Test_Case {

	private int $dashboard_id;

	private $users_can_register;

	public function set_up(): void {
		parent::set_up();

		$this->users_can_register = get_option( 'users_can_register' );

		$this->dashboard_id = (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '[wbam_advertiser_dashboard]',
			)
		);
		update_option( 'wbam_page_advertiser_dashboard', $this->dashboard_id );
		update_option( 'wbam_credits_payment_method', 'manual' );

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
		update_option( 'users_can_register', $this->users_can_register );
		delete_option( 'wbam_page_advertiser_dashboard' );
		delete_option( 'wbam_credits_payment_method' );
		remove_all_filters( 'wbam_pro_selectable_placements' );
		parent::tear_down();
	}

	private function render(): string {
		return html_entity_decode( ( new Advertise_Page_Shortcode() )->render() );
	}

	public function test_page_title_is_not_repeated_under_the_theme_title(): void {
		update_option( 'users_can_register', 1 );

		$html = $this->render();

		$this->assertStringNotContainsString( 'wbam-advertise-title', $html );
		$this->assertStringNotContainsString( '>Advertise with us<', $html );
	}

	public function test_guest_on_closed_registration_is_sent_to_sign_in_not_a_bare_contact(): void {
		update_option( 'users_can_register', 0 );

		$html = $this->render();

		$this->assertStringContainsString( 'Sign in to advertise', $html );
		$this->assertStringNotContainsString( 'Sign up to advertise', $html );
		$this->assertStringContainsString( (string) get_permalink( $this->dashboard_id ), $html );
		$this->assertStringContainsString( 'redirect_to=', $html );
	}

	public function test_guest_on_closed_registration_can_ask_for_an_account(): void {
		update_option( 'users_can_register', 0 );

		$html = $this->render();

		$this->assertStringContainsString( 'Contact us to get an account', $html );
		$this->assertStringContainsString( 'href="' . wbam_pro_get_invitation_contact_url() . '"', $html, 'Same contact target the old Contact us button used.' );
	}

	public function test_open_registration_has_no_contact_line(): void {
		update_option( 'users_can_register', 1 );

		$this->assertStringNotContainsString( 'Contact us to get an account', $this->render() );
	}

	public function test_all_slots_label_does_not_address_the_visitor(): void {
		add_filter(
			'wbam_pro_selectable_placements',
			static function () {
				$slots = array();
				for ( $i = 1; $i <= 6; $i++ ) {
					$slots[ 'slot_' . $i ] = array( 'name' => 'Slot ' . $i );
				}
				return $slots;
			}
		);

		$package = Package_Manager::get_instance()->create(
			array(
				'name'   => 'Anywhere',
				'price'  => 10.0,
				'status' => 'active',
			)
		);

		$label = implode( ' ', $package->get_placement_names() );

		$this->assertStringNotContainsString( 'available to you', $label );
		$this->assertStringContainsString( '6', $label );
	}
}
