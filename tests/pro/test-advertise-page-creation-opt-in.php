<?php
/**
 * The public 'Advertise with us' page is never created for free: it is
 * opt-in only, either from Settings > Advertising > Pages' "Create Page"
 * button or the setup wizard's Ready screen - both write the same
 * `wbam_page_advertise` option Settings_Helper::get_page( 'advertise' )
 * reads, so mapping is a single source of truth either way.
 *
 * Card 10342786741.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Setup_Wizard;
use WBAM_Pro\Core\Installer;
use WBAM_Pro\Core\Settings_Helper;

class Test_Advertise_Page_Creation_Opt_In extends Pro_Test_Case {

	public function tear_down(): void {
		delete_option( 'wbam_page_advertise' );
		parent::tear_down();
	}

	public function test_advertise_page_is_not_in_the_auto_created_defaults(): void {
		// get_default_pages() is what Installer::create_pages() loops over
		// on activation. The advertise page must never be in it, or every
		// site would get the page without asking.
		$this->assertArrayNotHasKey( 'advertise', Installer::get_default_pages() );
	}

	public function test_no_page_is_mapped_until_something_opts_in(): void {
		delete_option( 'wbam_page_advertise' );

		$this->assertSame( 0, Settings_Helper::get_page( 'advertise' ), 'A fresh install must not have an advertise page mapped.' );
	}

	public function test_wizard_ready_screen_action_creates_and_maps_the_page(): void {
		$this->assertSame( 0, Settings_Helper::get_page( 'advertise' ) );

		$method = new \ReflectionMethod( Setup_Wizard::class, 'create_advertise_page' );
		$method->setAccessible( true );
		$method->invoke( Setup_Wizard::get_instance() );

		$page_id = Settings_Helper::get_page( 'advertise' );
		$this->assertGreaterThan( 0, $page_id, 'The opt-in action must create and map a real page.' );

		$page = get_post( $page_id );
		$this->assertSame( 'publish', $page->post_status );
		$this->assertStringContainsString( '[wbam_advertise]', $page->post_content );
	}

	public function test_opt_in_action_is_idempotent_when_already_mapped(): void {
		$existing_id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		update_option( 'wbam_page_advertise', $existing_id );

		$method = new \ReflectionMethod( Setup_Wizard::class, 'create_advertise_page' );
		$method->setAccessible( true );
		$method->invoke( Setup_Wizard::get_instance() );

		$this->assertSame( $existing_id, Settings_Helper::get_page( 'advertise' ), 'A second click must not create a duplicate page.' );
	}
}
