<?php
/**
 * One Settings page (BC#10339963947), PRO side, reorganized into the 10
 * sections of card 10343706274: tabs mapped onto the new sidebar order,
 * legacy `wbam-pro-settings&tab=X` / `wbam-settings&section=X` /
 * `wbam-tools` redirects, each leaf saving without touching another leaf's
 * option, the single Anonymize IP switch, and the featured listing email
 * toggles owned by the Emails leaf.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Core\Settings_Helper;

class Test_Settings_One_Page_3_2 extends Pro_Test_Case {

	private Pro_Admin $admin;

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->admin = new Pro_Admin();
	}

	public function tear_down(): void {
		delete_option( 'wbam_pro_classifieds_settings' );
		delete_option( 'wbam_pro_email_settings' );
		delete_option( 'wbam_pro_settings' );
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/** map_settings_sections() places every section exactly where the card's 10-section order expects it. */
	public function test_map_settings_sections_order_and_free_passthrough(): void {
		$free_sections = array(
			'ads-display' => array(
				'label'  => 'Ads & Display',
				'render' => '__return_null',
			),
			'links'       => array(
				'label'  => 'Links',
				'render' => '__return_null',
			),
			'location'    => array(
				'label'  => 'Location',
				'render' => '__return_null',
			),
			'privacy'     => array(
				'label'  => 'Privacy & Data',
				'render' => '__return_null',
			),
			'tools'       => array(
				'label'  => 'Tools & License',
				'render' => '__return_null',
			),
		);

		$mapped = $this->admin->map_settings_sections( $free_sections );
		$keys   = array_keys( $mapped );

		$this->assertSame(
			array( 'general', 'ads-display', 'advertisers-billing', 'credits', 'classifieds', 'links', 'location', 'privacy', 'emails', 'tools' ),
			$keys,
			'Sidebar order matches card 10343706274 exactly.'
		);

		foreach ( array( 'ads-display', 'links', 'location', 'privacy', 'tools' ) as $slug ) {
			$this->assertSame( $free_sections[ $slug ], $mapped[ $slug ], "FREE's '{$slug}' section passes through untouched." );
		}

		// Retired sections gone; General/Advertisers & Billing are new.
		$this->assertArrayNotHasKey( 'ad-display', $mapped );
		$this->assertArrayNotHasKey( 'advertising', $mapped );
		$this->assertArrayNotHasKey( 'analytics', $mapped );
		$this->assertArrayNotHasKey( 'geolocation', $mapped );
		$this->assertArrayNotHasKey( 'license', $mapped );
		$this->assertArrayNotHasKey( 'modules', $mapped );
		$this->assertArrayNotHasKey( 'pages', $mapped );

		foreach ( $mapped as $slug => $section ) {
			$this->assertIsCallable( $section['render'], "Section '{$slug}' must have a callable render." );
		}
	}

	/** The old tab-slug -> new-section-slug remap used by the legacy-URL redirect. */
	public function test_legacy_settings_tab_map(): void {
		$map = $this->admin->legacy_settings_tab_map( array() );

		$this->assertSame( 'privacy', $map['analytics'] );
		$this->assertSame( 'general', $map['modules'] );
		$this->assertSame( 'general', $map['pages'] );
		$this->assertSame( 'ads-display', $map['rotation'] );
		$this->assertSame( 'location', $map['geolocation'] );
		$this->assertSame( 'tools', $map['license'] );
	}

	/** Every old `wbam-pro-settings&tab=X` URL redirects to its mapped `wbam-settings&section=Y`. */
	public function test_legacy_pro_settings_tab_urls_redirect_to_mapped_sections(): void {
		$free_admin = new \WBAM\Admin\Admin();

		$cases = array(
			'general'     => 'section=general',
			'classifieds' => 'section=classifieds',
			'credits'     => 'section=credits',
			'emails'      => 'section=emails',
			'geolocation' => 'section=location',
			'analytics'   => 'section=privacy',
			'modules'     => 'section=general',
			'pages'       => 'section=general',
			'rotation'    => 'section=ads-display',
			'license'     => 'section=tools',
		);

		foreach ( $cases as $old_tab => $expected_fragment ) {
			$_GET     = array(
				'page' => 'wbam-pro-settings',
				'tab'  => $old_tab,
			);
			$redirect = static function ( $location ) {
				throw new \RuntimeException( $location );
			};
			add_filter( 'wp_redirect', $redirect );
			try {
				$free_admin->redirect_legacy_settings_url();
				$this->fail( "Expected a redirect for tab={$old_tab}" );
			} catch ( \RuntimeException $e ) {
				$this->assertStringContainsString( 'page=wbam-settings', $e->getMessage(), $old_tab );
				$this->assertStringContainsString( $expected_fragment, $e->getMessage(), $old_tab );
			} finally {
				remove_filter( 'wp_redirect', $redirect );
			}
		}
	}

	/** Old `?section=X` slugs PRO used to own on its own land on their new home via the aliases filter. */
	public function test_settings_section_aliases_remap_retired_slugs(): void {
		$aliases = $this->admin->settings_section_aliases( array() );

		$this->assertSame( 'general', $aliases['advertising'] );
		$this->assertSame( 'location', $aliases['geolocation'] );
		$this->assertSame( 'tools', $aliases['license'] );
	}

	/**
	 * Owner decision, card 10343706274: with Pro active, exactly ONE
	 * Anonymize IP switch shows in Privacy & Data — Pro's own
	 * `wbam_pro_settings[gdpr_anonymize_ip]` — never FREE's
	 * `wbam_settings[anonymize_ip]`. FREE's own suite proves FREE's half
	 * (its switch absent) in isolation; this proves the end-to-end result
	 * once PRO's card is actually attached — `new Pro_Admin()` in set_up()
	 * already wires `render_privacy_content_card()` onto FREE's
	 * `wbam_settings_privacy_content` action, so a single
	 * render_privacy_page() call renders both halves, same as production.
	 */
	public function test_privacy_page_shows_exactly_one_anonymize_switch(): void {
		$settings = \WBAM\Admin\Settings::get_instance();
		$settings->register_settings();

		ob_start();
		$settings->render_privacy_page();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'wbam_settings[anonymize_ip]', $html, 'FREE\'s switch must not render once PRO provides one.' );
		$this->assertSame( 1, substr_count( $html, 'wbam_pro_settings[gdpr_anonymize_ip]' ), 'Exactly one Anonymize IP switch, ever.' );
	}

	/** General fully replaces the old Advertising tab's Modules + Currency + Pages, plus Site Mode. */
	public function test_general_section_renders_site_mode_modules_currency_and_pages(): void {
		ob_start();
		$this->admin->render_general_section();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'wbam-site-mode-card', $html, 'Site Mode card' );
		$this->assertStringContainsString( 'wbam_module_', $html, 'Modules (Features)' );
		$this->assertStringContainsString( 'wbam_pro_settings[currency]', $html, 'Currency' );

		// 4 cards (Site Mode/Modules/Currency/Pages) -> jump row required.
		$this->assertStringContainsString( 'wbam-page-jump', $html );
		$this->assertStringContainsString( 'href="#wbam-jump-site-mode"', $html );
		$this->assertStringContainsString( 'id="wbam-jump-pages"', $html );
	}

	/**
	 * Classifieds' 8 cards (posting-access + 7 always-rendered <h3> groups;
	 * Seller Profile Fields is a 9th only with BuddyPress active) get a
	 * jump row. Every link must target an anchor this page load actually
	 * renders.
	 */
	public function test_classifieds_section_renders_a_jump_row(): void {
		$reflection = new \ReflectionMethod( Pro_Admin::class, 'render_classifieds_settings' );
		$reflection->setAccessible( true );
		ob_start();
		$reflection->invoke( $this->admin );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'wbam-page-jump', $html );
		$this->assertStringContainsString( 'href="#wbam-jump-classifieds-label-url"', $html );
		$this->assertStringContainsString( 'id="wbam-jump-classifieds-promote-listing"', $html );
		$this->assertStringNotContainsString( 'wbam-jump-classifieds-seller-profile', $html, 'No BuddyPress in this suite, so that card (and its jump link) must not render.' );
	}

	/** Advertisers & Billing owns approval/trust, campaign billing defaults and the low-balance warning — one form. */
	public function test_advertisers_billing_section_renders_every_moved_field(): void {
		ob_start();
		$this->admin->render_advertisers_billing_section( Settings_Helper::get() );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'wbam_pro_settings[admin_as_advertiser]', $html );
		$this->assertStringContainsString( 'wbam_pro_settings[trust_system_enabled]', $html );
		$this->assertStringContainsString( 'wbam_pro_settings[default_pricing_model]', $html );
		// low_balance_threshold is plug-and-play (owner decision, same card) —
		// see Test_Settings_Contract_3_2::test_low_balance_threshold_is_plug_and_play().
		$this->assertStringNotContainsString( 'wbam_pro_settings[low_balance_threshold]', $html );
		$this->assertSame( 1, substr_count( $html, '<form' ) );
	}

	/**
	 * Saving Advertisers & Billing must not touch Currency (a sibling
	 * card/form on General). Also proves a programmatic write (REST,
	 * WP-CLI, a filter) can still set low_balance_threshold directly even
	 * though no UI field renders it any more — sanitize_settings()'s field
	 * type map is unchanged, only the UI is gone.
	 */
	public function test_advertisers_billing_save_does_not_touch_currency(): void {
		update_option(
			'wbam_pro_settings',
			array(
				'currency'        => 'eur',
				'currency_symbol' => '€',
			)
		);

		$_POST = array(
			'_active_tab'                => 'advertisers-billing',
			'_tab_fields'                => array( 'admin_as_advertiser', 'trust_system_enabled', 'trust_auto_approve_paid', 'trust_always_review_code', 'auto_approve_advertisers', 'advertiser_hide_share_of_voice' ),
			'admin_as_advertiser'        => '1',
			'default_pricing_model'      => 'cpc',
			'low_balance_threshold'      => '25',
		);

		$sanitized = $this->admin->sanitize_settings( $_POST );

		$this->assertTrue( $sanitized['admin_as_advertiser'] );
		$this->assertSame( 25.0, $sanitized['low_balance_threshold'] );
		$this->assertSame( 'eur', $sanitized['currency'], 'Currency must survive a save that never rendered it.' );
		$this->assertSame( '€', $sanitized['currency_symbol'] );
	}

	/** Saving Classifieds settings must not reset the featured-email toggles it no longer renders. */
	public function test_classifieds_save_preserves_featured_email_toggles(): void {
		update_option(
			'wbam_pro_classifieds_settings',
			array(
				'featured_admin_notification'     => true,
				'featured_member_notification'    => true,
				'featured_downgrade_notification' => true,
			)
		);

		$_POST = array(
			'wbam_save_classifieds_settings' => '1',
			'_wpnonce'                        => wp_create_nonce( 'wbam_classifieds_settings' ),
			'wbam_singular_label'             => 'Listing',
			'wbam_plural_label'               => 'Listings',
			'wbam_url_slug'                   => 'classifieds',
			'wbam_submission_form_type'       => 'wizard',
		);
		$_REQUEST = $_POST;

		$method = new \ReflectionMethod( Pro_Admin::class, 'render_classifieds_settings' );
		$method->setAccessible( true );
		ob_start();
		$method->invoke( $this->admin );
		ob_end_clean();

		$stored = Settings_Helper::get_classifieds();
		$this->assertTrue( $stored['featured_admin_notification'], 'Admin toggle must survive a Classifieds save.' );
		$this->assertTrue( $stored['featured_member_notification'], 'Member toggle must survive a Classifieds save.' );
		$this->assertTrue( $stored['featured_downgrade_notification'], 'Downgrade toggle must survive a Classifieds save.' );
	}

	/**
	 * Round trip (owner decision, card 10343706274): Emails is one section,
	 * one form, one Save now - the featured-listing toggles are merged into
	 * the same notification list and the same submit. One save updates BOTH
	 * `wbam_pro_email_settings` and `wbam_pro_classifieds_settings`, each
	 * through its own read-modify-write, so read = write holds per key even
	 * though the two groups live in different options: every posted key
	 * lands on the value posted, every un-posted boolean saves false, and a
	 * Classifieds-owned key this form never renders (singular_label)
	 * survives untouched.
	 */
	public function test_emails_save_updates_both_option_groups_and_leaves_other_classifieds_keys_alone(): void {
		update_option(
			'wbam_pro_classifieds_settings',
			array(
				'singular_label'              => 'Listing',
				'featured_admin_notification'  => false,
				'featured_member_notification' => false,
			)
		);
		update_option( 'wbam_pro_email_settings', array( 'from_name' => 'Old Name' ) );

		$_POST = array(
			'wbam_save_email_settings'          => '1',
			'_wpnonce'                           => wp_create_nonce( 'wbam_email_settings' ),
			'wbam_email_from_name'               => 'New Name',
			'wbam_email_from_email'              => 'new@example.org',
			'wbam_featured_admin_notification'   => '1',
			'wbam_featured_member_notification'  => '1',
			// wbam_featured_downgrade_notification intentionally absent/unchecked.
		);
		$_REQUEST = $_POST;

		$method = new \ReflectionMethod( Pro_Admin::class, 'render_emails_settings' );
		$method->setAccessible( true );
		ob_start();
		$method->invoke( $this->admin );
		$html = (string) ob_get_clean();

		$email = Settings_Helper::get_email();
		$this->assertSame( 'New Name', $email['from_name'], 'Posted key saves the posted value.' );
		$this->assertSame( 'new@example.org', $email['from_email'] );

		$classifieds = Settings_Helper::get_classifieds();
		$this->assertTrue( $classifieds['featured_admin_notification'], 'Posted featured toggle saves true.' );
		$this->assertTrue( $classifieds['featured_member_notification'] );
		$this->assertFalse( ! empty( $classifieds['featured_downgrade_notification'] ), 'Un-posted featured toggle saves false.' );
		$this->assertSame( 'Listing', $classifieds['singular_label'], 'A Classifieds-owned key this form never renders must survive untouched.' );

		// One form, one Save: no separate "Featured Listing Notifications" card/button.
		$this->assertSame( 1, substr_count( $html, '<form' ) );
		$this->assertSame( 1, substr_count( $html, 'type="submit"' ) );
		$this->assertStringNotContainsString( 'Featured Listing Notifications', $html );
	}

	/**
	 * Card 10339876480: Classified Rejected, Advertiser Approved/Rejected,
	 * New Review and New Report had no checkbox to switch them off, even
	 * though the senders already gated on those keys (or, for the
	 * advertiser ones, didn't gate at all - see class-email-notifications.php).
	 */
	public function test_email_settings_form_saves_the_new_toggles(): void {
		$_POST = array(
			'wbam_save_email_settings'         => '1',
			'_wpnonce'                          => wp_create_nonce( 'wbam_email_settings' ),
			'wbam_email_from_name'              => 'Ad Desk',
			'wbam_email_from_email'             => 'ads@example.org',
			// Every other boolean is intentionally left unchecked/absent.
			'wbam_email_classified_rejected'    => '1',
			'wbam_email_advertiser_approved'    => '1',
			'wbam_email_advertiser_rejected'    => '1',
			'wbam_email_review_submitted'       => '1',
			'wbam_email_new_report'             => '1',
		);
		$_REQUEST = $_POST;

		$method = new \ReflectionMethod( Pro_Admin::class, 'render_emails_settings' );
		$method->setAccessible( true );
		ob_start();
		$method->invoke( $this->admin );
		ob_end_clean();

		$stored = Settings_Helper::get_email();
		$this->assertTrue( $stored['classified_rejected'] );
		$this->assertTrue( $stored['advertiser_approved'] );
		$this->assertTrue( $stored['advertiser_rejected'] );
		$this->assertTrue( $stored['review_submitted'] );
		$this->assertTrue( $stored['new_report'] );
		// An unchecked box posts nothing at all, so an absent key must save false.
		$this->assertFalse( $stored['ad_approved'] );
	}

	/** Posting-without-a-plan moved to the Classifieds leaf, with its own save + redirect there. */
	public function test_posting_access_save_persists_and_redirects_to_classifieds(): void {
		$_POST = array(
			'wbam_save_posting_access'   => '1',
			'wbam_posting_access_nonce'  => wp_create_nonce( 'wbam_posting_access' ),
			'require_membership_to_post' => '1',
		);

		$redirect = static function ( $location ) {
			throw new \RuntimeException( $location );
		};
		add_filter( 'wp_redirect', $redirect );
		try {
			$this->admin->handle_posting_access_save();
			$this->fail( 'Expected handle_posting_access_save() to redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'page=wbam-settings', $e->getMessage() );
			$this->assertStringContainsString( 'section=classifieds', $e->getMessage() );
		} finally {
			remove_filter( 'wp_redirect', $redirect );
		}

		$this->assertTrue( (bool) Settings_Helper::get( 'require_membership_to_post', false ) );
	}

	/**
	 * End-to-end: every old `?section=X` slug PRO used to own on its own
	 * (before this reorg retired/renamed that section) still lands on real
	 * content for its new home when hit directly on the one wbam-settings
	 * screen — not a silent fallback to whatever the first sidebar entry
	 * happens to be.
	 */
	public function test_every_old_section_slug_resolves_to_real_content(): void {
		$settings = \WBAM\Admin\Settings::get_instance();
		$settings->register_settings();

		$cases = array(
			'advertising' => 'wbam-site-mode-card',
			'geolocation' => 'wbam_settings[geo_primary_provider]',
			'license'     => 'Import Demo Data',
		);

		foreach ( $cases as $old_slug => $needle ) {
			$_GET['section'] = $old_slug;
			ob_start();
			$settings->render_page();
			$html = ob_get_clean();

			$this->assertStringContainsString( $needle, $html, "?section={$old_slug} must land on real content." );
		}
	}
}
