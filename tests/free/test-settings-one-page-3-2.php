<?php
/**
 * One Settings page (BC#10339963947): sidebar sections, legacy-URL
 * redirects, and the Ad Display leaf's client-side sub-nav still posting
 * every registered field.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Settings;
use WBAM\Core\Admin_Links;
use WP_UnitTestCase;

class Test_Settings_One_Page_3_2 extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		$_GET = array();
		parent::tear_down();
	}

	/** Admin_Links::settings() builds `?section=` URLs, not the old `?tab=`. */
	public function test_admin_links_settings_uses_section_query_arg(): void {
		$url = Admin_Links::settings( 'classifieds' );
		$this->assertStringContainsString( 'page=wbam-settings', $url );
		$this->assertStringContainsString( 'section=classifieds', $url );
		$this->assertStringNotContainsString( 'tab=', $url );
	}

	public function test_admin_links_settings_with_no_section_omits_query_arg(): void {
		$url = Admin_Links::settings();
		$this->assertStringNotContainsString( 'section=', $url );
	}

	/** Solo Free install (no Pro): the sidebar is Ad Display + Tools. */
	public function test_default_sections_are_ad_display_and_tools(): void {
		$settings = new Settings();
		$method   = new \ReflectionMethod( Settings::class, 'get_sections' );
		$method->setAccessible( true );
		$sections = $method->invoke( $settings );

		$this->assertSame( array( 'ad-display', 'tools' ), array_keys( $sections ) );
		$this->assertTrue( is_callable( $sections['ad-display']['render'] ) );
		$this->assertTrue( is_callable( $sections['tools']['render'] ) );
	}

	/**
	 * The Ad Display leaf's sub-nav (General/Display/Placements/...) is pure
	 * front-end toggling — every subsection must still be present in the
	 * rendered HTML (and therefore in the POST) regardless of which pill is
	 * visually active, or a save with JS enabled would silently drop fields
	 * belonging to a hidden subsection.
	 */
	public function test_ad_display_section_renders_every_registered_subsection(): void {
		$settings = Settings::get_instance();
		$settings->register_settings();

		ob_start();
		$settings->render_ad_display_section();
		$html = ob_get_clean();

		// One field from each of the free settings sections must be present.
		$this->assertStringContainsString( 'wbam_settings[disable_ads_logged_in]', $html, 'General section' );
		$this->assertStringContainsString( 'wbam_settings[ad_label]', $html, 'Display section' );
		$this->assertStringContainsString( 'wbam_settings[geo_primary_provider]', $html, 'Geo section' );
		$this->assertStringContainsString( 'wbam_settings[adsense_publisher_id]', $html, 'AdSense section' );
		$this->assertStringContainsString( 'wbam_settings[require_consent_adsense]', $html, 'Privacy section' );
		$this->assertStringContainsString( 'wbam_settings[delete_data_on_uninstall]', $html, 'Advanced section' );
		$this->assertStringContainsString( 'wbam_settings[link_cloak_prefix]', $html, 'Link cloaking section' );

		// The whole thing is still one form with one Save button.
		$this->assertSame( 1, substr_count( $html, '<form' ), 'Ad Display must stay one form.' );
	}

	/** Old wbam-tools / wbam-email-captures URLs redirect to the new sections. */
	public function test_legacy_tools_and_email_captures_urls_redirect(): void {
		$admin = new \WBAM\Admin\Admin();

		$cases = array(
			'wbam-tools'          => 'section=tools',
			'wbam-email-captures' => 'section=email-captures',
		);

		foreach ( $cases as $old_page => $expected_fragment ) {
			$_GET     = array( 'page' => $old_page );
			$redirect = static function ( $location ) {
				throw new \RuntimeException( $location );
			};
			add_filter( 'wp_redirect', $redirect );
			try {
				$admin->redirect_legacy_settings_url();
				$this->fail( "Expected a redirect for page={$old_page}" );
			} catch ( \RuntimeException $e ) {
				$this->assertStringContainsString( 'page=wbam-settings', $e->getMessage(), $old_page );
				$this->assertStringContainsString( $expected_fragment, $e->getMessage(), $old_page );
			} finally {
				remove_filter( 'wp_redirect', $redirect );
			}
		}
	}

	/** A page with no legacy slug must not redirect at all. */
	public function test_unrelated_page_does_not_redirect(): void {
		$admin    = new \WBAM\Admin\Admin();
		$_GET     = array( 'page' => 'wbam-links' );
		$redirect = static function ( $location ) {
			throw new \RuntimeException( $location );
		};
		add_filter( 'wp_redirect', $redirect );
		try {
			$admin->redirect_legacy_settings_url();
			$this->assertTrue( true );
		} catch ( \RuntimeException $e ) {
			$this->fail( 'Unrelated page must not trigger the legacy-settings redirect.' );
		} finally {
			remove_filter( 'wp_redirect', $redirect );
		}
	}
}
