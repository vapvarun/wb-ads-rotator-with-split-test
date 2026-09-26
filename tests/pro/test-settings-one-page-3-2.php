<?php
/**
 * One Settings page (BC#10339963947), PRO side: tabs mapped onto sidebar
 * sections, legacy `wbam-pro-settings&tab=X` / `wbam-tools` redirects, each
 * leaf saving without touching another leaf's option, and the featured
 * listing email toggles now owned by the Emails leaf.
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

	/** map_settings_sections() places FREE's own sections where the card's sidebar order expects them. */
	public function test_map_settings_sections_order_and_ad_display_passthrough(): void {
		$free_sections = array(
			'ad-display' => array(
				'label'  => 'Ad Display',
				'render' => '__return_null',
			),
			'tools'      => array(
				'label'  => 'Tools',
				'render' => '__return_null',
			),
		);

		$mapped = $this->admin->map_settings_sections( $free_sections );
		$keys   = array_keys( $mapped );

		$this->assertSame( 'general', $keys[0], 'General is the first section.' );
		$this->assertContains( 'ad-display', $keys );
		$this->assertContains( 'advertising', $keys );
		$this->assertContains( 'credits', $keys );
		$this->assertContains( 'emails', $keys );
		$this->assertContains( 'tools', $keys );
		$this->assertSame( $free_sections['ad-display'], $mapped['ad-display'], 'FREE\'s Ad Display section passes through untouched.' );
		$this->assertSame( $free_sections['tools'], $mapped['tools'], 'FREE\'s Tools section passes through untouched.' );

		// Analytics renamed to Privacy; Modules/Pages/Rotation folded into Advertising.
		$this->assertArrayHasKey( 'privacy', $mapped );
		$this->assertArrayNotHasKey( 'analytics', $mapped );
		$this->assertArrayNotHasKey( 'modules', $mapped );
		$this->assertArrayNotHasKey( 'pages', $mapped );

		// General comes before ad-display, which comes before advertising.
		$general_pos     = array_search( 'general', $keys, true );
		$ad_display_pos  = array_search( 'ad-display', $keys, true );
		$advertising_pos = array_search( 'advertising', $keys, true );
		$this->assertLessThan( $ad_display_pos, $general_pos );
		$this->assertLessThan( $advertising_pos, $ad_display_pos );

		foreach ( $mapped as $slug => $section ) {
			$this->assertIsCallable( $section['render'], "Section '{$slug}' must have a callable render." );
		}
	}

	/** The old tab-slug -> new-section-slug remap used by the legacy-URL redirect. */
	public function test_legacy_settings_tab_map(): void {
		$map = $this->admin->legacy_settings_tab_map( array() );

		$this->assertSame( 'privacy', $map['analytics'] );
		$this->assertSame( 'advertising', $map['modules'] );
		$this->assertSame( 'advertising', $map['pages'] );
		$this->assertSame( 'advertising', $map['rotation'] );
	}

	/** Every old `wbam-pro-settings&tab=X` URL redirects to its mapped `wbam-settings&section=Y`. */
	public function test_legacy_pro_settings_tab_urls_redirect_to_mapped_sections(): void {
		$free_admin = new \WBAM\Admin\Admin();

		$cases = array(
			'general'     => 'section=general',
			'classifieds' => 'section=classifieds',
			'credits'     => 'section=credits',
			'emails'      => 'section=emails',
			'geolocation' => 'section=geolocation',
			'analytics'   => 'section=privacy',
			'modules'     => 'section=advertising',
			'pages'       => 'section=advertising',
			'rotation'    => 'section=advertising',
			'license'     => 'section=license',
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

	/** The Emails leaf's own form now owns the featured-email toggles' save. */
	public function test_emails_leaf_saves_featured_toggles_without_touching_email_settings(): void {
		update_option( 'wbam_pro_classifieds_settings', array( 'featured_admin_notification' => false ) );
		update_option( 'wbam_pro_email_settings', array( 'from_name' => 'Keep Me' ) );

		$_POST = array(
			'wbam_save_featured_email_settings' => '1',
			'_wpnonce'                            => wp_create_nonce( 'wbam_featured_email_settings' ),
			'wbam_featured_admin_notification'    => '1',
			'wbam_featured_member_notification'   => '1',
		);
		$_REQUEST = $_POST;

		$method = new \ReflectionMethod( Pro_Admin::class, 'render_emails_settings' );
		$method->setAccessible( true );
		ob_start();
		$method->invoke( $this->admin );
		ob_end_clean();

		$classifieds = Settings_Helper::get_classifieds();
		$this->assertTrue( $classifieds['featured_admin_notification'] );
		$this->assertTrue( $classifieds['featured_member_notification'] );
		$this->assertFalse( ! empty( $classifieds['featured_downgrade_notification'] ), 'Untouched toggle stays false.' );

		// The separate Email Settings form was not submitted, so its option
		// must be exactly what it was before.
		$email_settings = Settings_Helper::get_email();
		$this->assertSame( 'Keep Me', $email_settings['from_name'] );
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
}
