<?php
/**
 * A disabled Links module must not boot at all - no links-frontend
 * JS/CSS, no partnership form assets, no shortcodes. Plugin::init_hooks()
 * instantiates Links_Module unconditionally, so the gate has to live at
 * the top of its own init(), the one place every consumer (frontend and
 * admin) routes through.
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

	public function test_disabled_links_module_registers_no_shortcodes_or_frontend_assets(): void {
		Settings_Helper::update( 'modules', array( 'links' => false ) );

		// Simulate a fresh request: nothing registered yet this process.
		remove_shortcode( 'wbam_link' );
		remove_shortcode( 'wbam_links' );
		remove_shortcode( 'wbam_link_url' );
		remove_shortcode( 'wbam_partnership_inquiry' );
		wp_deregister_script( 'wbam-links-frontend' );

		$module = Links_Module::get_instance();
		remove_action( 'wp_ajax_nopriv_wbam_track_link_click', array( $module, 'ajax_track_click' ) );
		$module->init();

		$this->assertFalse( shortcode_exists( 'wbam_link' ) );
		$this->assertFalse( shortcode_exists( 'wbam_partnership_inquiry' ) );
		$this->assertFalse( has_action( 'wp_ajax_nopriv_wbam_track_link_click', array( $module, 'ajax_track_click' ) ) );
	}
}
