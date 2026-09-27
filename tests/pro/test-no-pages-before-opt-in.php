<?php
/**
 * Owner decision (card 10343706274, wave 6): activating Pro creates no
 * page. The Advertiser Dashboard appears when the wizard or a mode switch
 * turns on a feature with advertisers, or when the owner clicks Create Page.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Installer;
use WBAM_Pro\Core\Site_Mode;

class Test_No_Pages_Before_Opt_In extends Pro_Test_Case {

	private function page_count(): int {
		return count( get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) );
	}

	public function test_a_fresh_install_creates_no_page(): void {
		delete_option( 'wbam_pro_db_version' );
		delete_option( 'wbam_pro_settings' );
		delete_option( 'wbam_page_advertiser_dashboard' );
		delete_option( 'wbam_page_classifieds' );
		$before = $this->page_count();

		Installer::install( false );

		$this->assertSame( $before, $this->page_count() );
		$this->assertFalse( get_option( 'wbam_page_advertiser_dashboard' ) );
	}

	public function test_picking_a_mode_with_advertisers_creates_the_dashboard_once(): void {
		Site_Mode::apply( Site_Mode::PUBLISHER );
		delete_option( 'wbam_page_advertiser_dashboard' );

		Site_Mode::apply( Site_Mode::SPONSORED );
		$page_id = (int) get_option( 'wbam_page_advertiser_dashboard' );
		$this->assertGreaterThan( 0, $page_id, 'Sponsored has advertisers.' );
		$this->assertStringContainsString( '[wbam_advertiser_dashboard]', get_post( $page_id )->post_content );

		// The owner removes it; a later mode switch does not bring it back.
		wp_trash_post( $page_id );
		Site_Mode::apply( Site_Mode::PUBLISHER );
		Site_Mode::apply( Site_Mode::PAID );
		$this->assertSame( 'trash', get_post_status( $page_id ) );
		$this->assertSame( $page_id, (int) get_option( 'wbam_page_advertiser_dashboard' ) );
	}

	public function test_publisher_mode_needs_no_dashboard(): void {
		delete_option( 'wbam_page_advertiser_dashboard' );
		Site_Mode::apply( Site_Mode::PUBLISHER );

		$this->assertFalse( get_option( 'wbam_page_advertiser_dashboard' ) );
	}
}
