<?php
/**
 * The BuddyPress profile "Classifieds > Inquiries" sub-tab redirects to the
 * portal's Inbox tab instead of rendering its own list.
 *
 * Card 10343726590 (owner decision, "same treatment as the admin Inquiries
 * screen: one source of truth"): this sub-tab read the legacy
 * `wbam_classified_inquiries` table directly, so new inquiries (which land
 * as portal message threads under one inbox) never appeared here. Rather
 * than build a second, parallel list, it now points sellers at the one
 * place their inquiries actually show up.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\BuddyPress\BuddyPress_Integration;

class Test_BP_Inquiries_Tab_Redirects_To_Inbox extends Pro_Test_Case {

	public function tear_down(): void {
		delete_option( 'wbam_page_advertiser_dashboard' );
		delete_option( 'wbam_advertiser_dashboard' );
		parent::tear_down();
	}

	public function test_inquiries_screen_redirects_to_the_portal_inbox_tab(): void {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		update_option( 'wbam_page_advertiser_dashboard', $page_id );

		$redirected_to = null;
		$catch         = static function ( $location ) use ( &$redirected_to ) {
			$redirected_to = $location;
			throw new \RuntimeException( 'redirected' );
		};
		add_filter( 'wp_redirect', $catch );

		try {
			BuddyPress_Integration::get_instance()->inquiries_screen();
			$this->fail( 'inquiries_screen() must redirect, not render its own list.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirected', $e->getMessage() );
		} finally {
			remove_filter( 'wp_redirect', $catch );
		}

		$this->assertStringContainsString( (string) get_permalink( $page_id ), $redirected_to );
		$this->assertStringContainsString( 'tab=inbox', $redirected_to );
	}

	public function test_inquiries_screen_falls_back_to_an_empty_state_with_no_dashboard_page(): void {
		delete_option( 'wbam_page_advertiser_dashboard' );
		delete_option( 'wbam_advertiser_dashboard' );

		ob_start();
		add_action( 'bp_template_content', array( BuddyPress_Integration::get_instance(), 'render_inquiries_content' ) );
		do_action( 'bp_template_content' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Inbox', $html );
	}
}
