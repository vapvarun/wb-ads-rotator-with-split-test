<?php
/**
 * Honest switches and links that never break (owner decisions 6 and 7,
 * card 10344382999).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Links\Link_Cloaker;
use WP_UnitTestCase;

class Test_Privacy_And_Links_Honest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		delete_option( 'wbam_link_prefix' );
		delete_option( 'wbam_link_prefix_history' );
		parent::tear_down();
	}

	public function test_an_old_prefix_keeps_redirecting_after_a_change(): void {
		update_option( 'wbam_link_prefix', 'go' );
		update_option( 'wbam_settings', array( 'link_cloak_prefix' => 'out' ) );

		Link_Cloaker::get_instance()->add_rewrite_rules();

		global $wp_rewrite;
		$rules = $wp_rewrite->extra_rules_top;
		$this->assertArrayHasKey( '^out/([^/]+)/?$', $rules );
		$this->assertArrayHasKey( '^go/([^/]+)/?$', $rules, 'Links under the old prefix still resolve.' );
		$this->assertSame( array( 'go' ), get_option( 'wbam_link_prefix_history' ) );
	}

	public function test_settings_links_section_follows_the_switch(): void {
		update_option( 'wbam_settings', array( 'modules' => array( 'links' => false ) ) );
		$settings = new \WBAM\Admin\Settings();

		$sections = ( new \ReflectionMethod( $settings, 'default_sections' ) )->invoke( $settings );

		$this->assertArrayNotHasKey( 'links', $sections );
	}

	public function test_delete_data_lists_what_goes(): void {
		$settings = new \WBAM\Admin\Settings();

		$text = ( new \ReflectionMethod( $settings, 'uninstall_data_description' ) )->invoke( $settings );

		$this->assertStringContainsString( 'email sign-ups', $text );
		$this->assertStringContainsString( 'cannot be undone', $text );
	}
}
