<?php
/**
 * One Settings page QA rejects (BC#10339963947): the Rotation and License
 * saves show a notice, and the advertiser registration form reads the terms
 * page the Pages setting writes.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Rotation\Rotation_Admin;

class Test_Settings_One_Page_Rejects extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		global $wp_settings_errors;
		$wp_settings_errors = array();
		delete_option( 'wbam_page_terms' );
		delete_option( 'wbam_pro_license_key' );
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/** Rotation saves on admin_init of the same request; its notice must print in the leaf. */
	public function test_rotation_save_prints_notice(): void {
		$_POST = array(
			'wbam_rotation_nonce' => wp_create_nonce( 'wbam_rotation_settings' ),
			'enable_rotation'     => '1',
			'rotation_model'      => 'equal',
		);

		$reflection = new \ReflectionClass( Rotation_Admin::class );
		$admin      = $reflection->newInstanceWithoutConstructor();
		$admin->save_rotation_settings();

		ob_start();
		$admin->render_rotation_settings( get_option( 'wbam_pro_settings', array() ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Rotation settings saved.', $html );
	}

	/** The License save lands on the one Settings page with the flag its notice prints for. */
	public function test_license_save_redirects_with_saved_flag(): void {
		$_POST = array(
			'wbam_pro_save_license'  => '1',
			'wbam_pro_license_nonce' => wp_create_nonce( 'wbam_pro_license_nonce' ),
			'wbam_pro_license_key'   => 'abc123',
		);

		$redirect = static function ( $location ) {
			throw new \RuntimeException( $location );
		};
		add_filter( 'wp_redirect', $redirect );
		try {
			\WBAM_Pro_License_Manager::get_instance()->handle_license_actions();
			$this->fail( 'Expected a redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'page=wbam-settings', $e->getMessage() );
			$this->assertStringContainsString( 'section=license', $e->getMessage() );
			$this->assertStringContainsString( 'settings-updated=true', $e->getMessage() );
		} finally {
			remove_filter( 'wp_redirect', $redirect );
		}
	}

	/** The terms page chosen under Settings > General > Pages drives the registration checkbox. */
	public function test_registration_form_reads_the_saved_terms_page(): void {
		wp_set_current_user( 0 );
		update_option( 'users_can_register', 1 );
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		update_option( 'wbam_page_terms', $page_id );

		$shortcodes = new \WBAM_Pro\Modules\Advertisers\Advertiser_Shortcodes();
		$method     = new \ReflectionMethod( $shortcodes, 'render_login_form' );
		$html       = $method->invoke( $shortcodes );

		$this->assertStringContainsString( 'name="reg_agree_terms"', $html );
		$this->assertStringContainsString( get_permalink( $page_id ), $html );
	}
}
