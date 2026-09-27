<?php
/**
 * With the Link Manager off, links already published keep working (owner
 * decision 7a, card 10344382999): /go/ redirects, the link shortcodes and
 * click counting stay; the admin screens, new partnership inquiries and
 * Pro's link tools stop, and a published partnership form prints nothing
 * instead of its raw shortcode.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Core\Settings_Helper;
use WBAM\Modules\Links\Links_Module;
use WP_UnitTestCase;

class Test_Links_Module_Gate extends WP_UnitTestCase {

	public function tear_down(): void {
		Settings_Helper::update( 'modules', array( 'links' => true ) );
		remove_shortcode( 'wbam_link' );
		remove_shortcode( 'wbam_links' );
		remove_shortcode( 'wbam_link_url' );
		remove_shortcode( 'wbam_partnership_inquiry' );
		wp_deregister_script( 'wbam-links-frontend' );
		Links_Module::get_instance()->init();
		parent::tear_down();
	}

	public function test_a_disabled_link_manager_keeps_published_links_working(): void {
		Settings_Helper::update( 'modules', array( 'links' => false ) );

		// Simulate a fresh request: nothing registered yet this process.
		remove_shortcode( 'wbam_link' );
		remove_shortcode( 'wbam_links' );
		remove_shortcode( 'wbam_link_url' );
		remove_shortcode( 'wbam_partnership_inquiry' );

		remove_all_actions( 'wp_ajax_nopriv_wbam_submit_partnership' );

		$module = Links_Module::get_instance();
		remove_action( 'wp_ajax_nopriv_wbam_track_link_click', array( $module, 'ajax_track_click' ) );
		$module->init();

		$this->assertTrue( shortcode_exists( 'wbam_link' ), 'Published link shortcodes keep rendering.' );
		$this->assertNotFalse( has_action( 'wp_ajax_nopriv_wbam_track_link_click', array( $module, 'ajax_track_click' ) ), 'Clicks keep counting.' );
		$this->assertNotFalse( has_action( 'template_redirect', array( \WBAM\Modules\Links\Link_Cloaker::get_instance(), 'handle_redirect' ) ), '/go/ links keep redirecting.' );
		$this->assertSame( '', do_shortcode( '[wbam_partnership_inquiry]' ), 'A published form prints nothing, not its raw shortcode.' );
		$this->assertFalse( has_action( 'wp_ajax_nopriv_wbam_submit_partnership' ), 'No new partnership inquiries.' );
	}
}
