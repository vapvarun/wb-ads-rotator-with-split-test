<?php
/**
 * One Settings page (BC#10339963947; reorganized into 10 sections by card
 * 10343706274): sidebar sections, legacy-URL redirects, each FREE-owned
 * top-level page still posting every field it owns in one form, and the
 * single Anonymize IP switch's Pro-active state (this shared test
 * environment always has Pro loaded — see individual test docblocks).
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

	/**
	 * default_sections()'s own five entries (General is Pro-owned once Pro
	 * is active, so FREE steps aside — see class docblock).
	 *
	 * WBAM_PRO_VERSION is always defined in this test environment (Pro is
	 * installed alongside Free for the suite), so this only exercises the
	 * Pro-active branch — same constraint documented on
	 * Test_Settings_Pro_Only_Copy and
	 * Test_First_Run_Wizard_Handoff::test_not_pending_when_pro_is_not_active.
	 * The FREE-only branch (all six entries, including 'general') is a
	 * plain `if ( ! defined(...) )` guard, read at a glance in
	 * default_sections().
	 */
	public function test_default_sections_omit_general_when_pro_is_active(): void {
		$settings = new Settings();
		$method   = new \ReflectionMethod( Settings::class, 'get_sections' );
		$method->setAccessible( true );
		$sections = $method->invoke( $settings );

		$this->assertSame(
			array( 'ads-display', 'links', 'location', 'privacy', 'tools' ),
			array_keys( $sections )
		);
		foreach ( $sections as $slug => $section ) {
			$this->assertTrue( is_callable( $section['render'] ), "Section '{$slug}' must have a callable render." );
		}
	}

	/**
	 * render_general_page() itself stays callable and structurally sound
	 * (one form) even though no FREE-only site reaches it in this shared
	 * test environment — see test_default_sections_omit_general_when_pro_is_active().
	 */
	public function test_general_page_renders_as_one_form(): void {
		$settings = Settings::get_instance();
		$settings->register_settings();

		ob_start();
		$settings->render_general_page();
		$html = ob_get_clean();

		$this->assertSame( 1, substr_count( $html, '<form' ) );
	}

	/** Ads & Display: who sees ads, label/wrapper, placements, AdSense — one form. */
	public function test_ads_display_page_renders_every_registered_field(): void {
		$settings = Settings::get_instance();
		$settings->register_settings();

		ob_start();
		$settings->render_ads_display_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'wbam_settings[disable_ads_logged_in]', $html, 'General (who sees ads)' );
		$this->assertStringContainsString( 'wbam_settings[ad_label]', $html, 'Display (label/wrapper)' );
		$this->assertStringContainsString( 'wbam_settings[format_matching]', $html, 'Placements' );
		$this->assertStringContainsString( 'wbam_settings[adsense_publisher_id]', $html, 'AdSense' );
		$this->assertStringContainsString( 'wbam_settings[require_consent_adsense]', $html, 'AdSense consent, grouped with AdSense not Privacy' );
		$this->assertSame( 1, substr_count( $html, '<form' ), 'Ads & Display must stay one form.' );
	}

	/** Links: FREE's cloaking fields, one form. */
	public function test_links_page_renders_cloaking_fields(): void {
		$settings = Settings::get_instance();
		$settings->register_settings();

		ob_start();
		$settings->render_links_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'wbam_settings[link_cloak_prefix]', $html );
		$this->assertSame( 1, substr_count( $html, '<form' ) );
	}

	/** Location: FREE's visitor geolocation fields, one form. */
	public function test_location_page_renders_geo_fields(): void {
		$settings = Settings::get_instance();
		$settings->register_settings();

		ob_start();
		$settings->render_location_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'wbam_settings[geo_primary_provider]', $html );
		$this->assertSame( 1, substr_count( $html, '<form' ) );
	}

	/**
	 * Privacy & Data with Pro active (the only state this shared environment
	 * can exercise — see test_default_sections_omit_general_when_pro_is_active()):
	 * FREE's own Anonymize IP switch must NOT render, leaving Pro's
	 * `wbam_pro_settings[gdpr_anonymize_ip]` (rendered by Pro's own card,
	 * appended via the `wbam_settings_privacy_content` action — see
	 * Pro_Admin::render_privacy_content_card()) as the one switch shown.
	 * Delete Data on Uninstall is FREE's regardless and must still render.
	 *
	 * The FREE-only state (this same switch shown because there is no Pro
	 * switch to prefer instead) is a plain `if ( ! defined(...) )` guard in
	 * register_general_settings(), read at a glance; the Pro suite's
	 * `test_privacy_page_shows_exactly_one_anonymize_switch()` proves the
	 * "exactly one, ever" invariant end-to-end with Pro's card attached.
	 */
	public function test_privacy_page_hides_free_anonymize_switch_when_pro_is_active(): void {
		$settings = Settings::get_instance();
		$settings->register_settings();

		ob_start();
		$settings->render_privacy_page();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'wbam_settings[anonymize_ip]', $html, 'FREE\'s own switch must be suppressed once Pro provides one.' );
		$this->assertStringContainsString( 'wbam_settings[delete_data_on_uninstall]', $html );
		$this->assertSame( 1, substr_count( $html, '<form' ) );
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

	/** The old `?section=ad-display` URL still lands on the renamed Ads & Display section. */
	public function test_ad_display_alias_resolves_to_ads_display(): void {
		$settings = Settings::get_instance();
		$settings->register_settings();

		$_GET['section'] = 'ad-display';
		ob_start();
		$settings->render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'wbam_settings[ad_label]', $html, 'ad-display alias must land on Ads & Display content.' );
	}
}
