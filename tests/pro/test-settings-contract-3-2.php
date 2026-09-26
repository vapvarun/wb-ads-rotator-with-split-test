<?php
/**
 * Settings contract (BC#10342779181), PRO side: every setting a screen saves
 * is the one the runtime reads, and fields with no reader are gone.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Next_Step_Banner;
use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Core\Settings_Helper;

class Test_Settings_Contract_3_2 extends Pro_Test_Case {

	private Pro_Admin $admin;

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->admin = new Pro_Admin();
	}

	public function tear_down(): void {
		delete_option( 'wbam_pro_settings' );
		delete_option( 'wbam_pro_classifieds_settings' );
		delete_option( 'wbam_settings' );
		delete_option( 'wbam_page_my_favorites' );
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	private function render( string $method, ...$args ): string {
		$reflection = new \ReflectionMethod( Pro_Admin::class, $method );
		ob_start();
		$reflection->invoke( $this->admin, ...$args );
		return (string) ob_get_clean();
	}

	/**
	 * D5: one uninstall switch - FREE's. PRO's checkbox had no reachable
	 * reader. render_general_settings() was split (card 10343706274) into
	 * render_general_section() (Site Mode/Modules/Currency/Pages) and
	 * render_advertisers_billing_section() (approval/trust/billing) — check
	 * both, since either would be the wrong place for a second switch.
	 */
	public function test_general_has_no_second_uninstall_checkbox(): void {
		$html = $this->render( 'render_general_section' )
			. $this->render( 'render_advertisers_billing_section', Settings_Helper::get() );

		$this->assertStringNotContainsString( 'wbam_pro_settings[delete_data_on_uninstall]', $html );
		$this->assertStringNotContainsString( 'value="delete_data_on_uninstall"', $html );
	}

	/** D6: the revenue estimate option that is actually written is the one registered. */
	public function test_revenue_estimate_option_is_registered(): void {
		$this->admin->register_settings();
		$registered = get_registered_settings();

		$this->assertArrayHasKey( 'wbam_revenue_settings', $registered );
		$this->assertArrayNotHasKey( 'wbam_pro_revenue_settings', $registered );
	}

	/**
	 * D8, superseded by the plug-and-play decision on the same card
	 * (10343706274): the low-balance threshold is no longer a settings
	 * field at all — Settings_Helper::low_balance_threshold() reads the
	 * site's already-stored value as the `wbam_pro_low_balance_threshold`
	 * filter's default. Confirm the field is gone and the filter works.
	 */
	public function test_low_balance_threshold_is_plug_and_play(): void {
		$html = $this->render( 'render_advertisers_billing_section', Settings_Helper::get() );
		$this->assertStringNotContainsString( 'name="wbam_pro_settings[low_balance_threshold]"', $html );

		update_option( 'wbam_pro_settings', array( 'low_balance_threshold' => 7.5 ) );
		$this->assertSame( 7.5, Settings_Helper::low_balance_threshold(), "Site's stored value is the filter's default." );

		$forced = static function () {
			return 3.0;
		};
		add_filter( 'wbam_pro_low_balance_threshold', $forced );
		$this->assertSame( 3.0, Settings_Helper::low_balance_threshold(), 'Filter overrides the stored default.' );
		remove_filter( 'wbam_pro_low_balance_threshold', $forced );
	}

	/** D10: toggles nothing reads are not rendered. */
	public function test_dead_toggles_are_not_rendered(): void {
		$classifieds = $this->render( 'render_classifieds_settings' );
		$this->assertStringNotContainsString( 'wbam_inquiry_notification', $classifieds );

		$reflection = new \ReflectionClass( \WBAM_Pro\Modules\Rotation\Rotation_Admin::class );
		$rotation   = $reflection->newInstanceWithoutConstructor();
		ob_start();
		$rotation->render_rotation_settings( array() );
		$this->assertStringNotContainsString( 'rotation_reset_period', (string) ob_get_clean() );
	}

	/** D11: with analytics off, a Privacy save must not claim (and reset) the hidden tracking toggles. */
	public function test_privacy_contract_only_claims_rendered_booleans(): void {
		update_option(
			'wbam_pro_settings',
			array(
				'enable_analytics'     => false,
				'enable_bot_filtering' => true,
			)
		);

		$html = $this->render( 'render_analytics_settings', Settings_Helper::get() );

		$this->assertStringNotContainsString( 'value="enable_bot_filtering"', $html );
		$this->assertStringContainsString( 'value="gdpr_require_consent"', $html );
	}

	/** D12: the Modules screen shows the dependencies the runtime enforces. */
	public function test_module_screen_dependencies_match_runtime(): void {
		foreach ( Settings_Helper::get_available_modules() as $slug => $module ) {
			$this->assertSame( Settings_Helper::get_module_dependencies( $slug ), $module['depends_on'], $slug );
		}
		$this->assertContains( 'wallet', Settings_Helper::get_available_modules()['classifieds']['depends_on'] );
	}

	/** D13: the banner's "Enable now" writes FREE's setting through FREE's API. */
	public function test_enable_format_matching_writes_free_setting(): void {
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'wbam_enable_format_matching' );

		$redirect = static function ( $location ) {
			throw new \RuntimeException( (string) $location );
		};
		add_filter( 'wp_redirect', $redirect );
		try {
			Next_Step_Banner::handle_enable_format_matching();
		} catch ( \RuntimeException $e ) {
			unset( $e );
		} finally {
			remove_filter( 'wp_redirect', $redirect );
		}

		$this->assertTrue( \WBAM\Core\Settings_Helper::get( 'format_matching' ) );
		$this->assertTrue( Settings_Helper::format_matching_enabled() );
	}

	/** D14: a programmatic wbam_pro_settings write is not an error and logs nothing. */
	public function test_programmatic_settings_write_does_not_log(): void {
		$this->admin->register_settings();
		$log = wp_tempnam( 'wbam-log' );
		$old = ini_set( 'error_log', $log ); // phpcs:ignore WordPress.PHP.IniSet.Risky

		update_option( 'wbam_pro_settings', array( 'rotation_model' => 'equal' ) );

		ini_set( 'error_log', (string) $old ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		$contents = (string) file_get_contents( $log ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		unlink( $log ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

		$this->assertStringNotContainsString( 'sanitize_settings', $contents );
		$this->assertSame( 'equal', Settings_Helper::get( 'rotation_model' ) );
	}

	/** D15: page options the plugin reads can be mapped from the Pages screen. */
	public function test_optional_pages_are_mappable(): void {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$_POST = array(
			'wbam_page_my_favorites' => (string) $page_id,
		);
		$_REQUEST = $_POST;

		// $saving=true: the shared General-section nonce (card 10343706274)
		// is now verified once by render_general_section(), not by this
		// card's own removed nonce/isset() check.
		$html = $this->render( 'render_pages_settings', true );

		$this->assertStringContainsString( 'name="wbam_page_my_favorites"', $html );
		$this->assertStringContainsString( 'name="wbam_page_contact"', $html );
		$this->assertSame( $page_id, (int) get_option( 'wbam_page_my_favorites' ) );
		$this->assertSame( get_permalink( $page_id ), wbam_get_my_favorites_url() );
	}

	/** D17: "Accept new listings" off pauses posting only; the module stays the one master switch. */
	public function test_accept_new_listings_off_does_not_disable_classifieds(): void {
		Settings_Helper::set_module_enabled( 'wallet', true );
		Settings_Helper::set_module_enabled( 'classifieds', true );
		update_option( 'wbam_pro_classifieds_settings', array( 'enabled' => false ) );

		$this->assertTrue( wbam_is_classifieds_enabled() );

		$manager = \WBAM_Pro\Modules\Classifieds\Classified_Manager::get_instance();
		$result  = $manager->submit( (object) array( 'id' => 1 ), array() );
		$this->assertWPError( $result );
		$this->assertSame( 'classifieds_disabled', $result->get_error_code() );
	}
}
