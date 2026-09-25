<?php
/**
 * A refused Package or Campaign save shows the form again with what the owner
 * typed, instead of an empty form they have to fill in from scratch.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Admin;

class Test_Refused_Admin_Saves_Keep_Input extends Pro_Test_Case {

	private object $admin;

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		require_once WBAM_PRO_PATH . 'includes/Admin/class-field-tooltips.php'; // Admin-only load in production.
		// The handlers only need $this; skip the constructor's hook wiring.
		$this->admin = ( new \ReflectionClass( Pro_Admin::class ) )->newInstanceWithoutConstructor();
	}

	public function tear_down(): void {
		$_POST    = array();
		$_REQUEST = array();
		remove_all_filters( 'wp_redirect' );
		parent::tear_down();
	}

	private function call( string $method, ...$args ) {
		return ( new \ReflectionMethod( $this->admin, $method ) )->invoke( $this->admin, ...$args );
	}

	public function test_refused_package_save_keeps_what_was_typed(): void {
		// A per-click package with no click limit has nothing to prepay: refused.
		$_POST    = array(
			'action'         => 'save_package',
			'name'           => 'Typed package name',
			'pricing_model'  => 'cpc',
			'price_per_unit' => '0.5',
			'status'         => 'active',
			'_wpnonce'       => wp_create_nonce( 'wbam_save_package' ),
		);
		$_REQUEST = $_POST;

		$redirect = '';
		add_filter(
			'wp_redirect',
			static function ( $location ) use ( &$redirect ) {
				$redirect = $location;
				throw new \RuntimeException( 'redirected' );
			}
		);
		try {
			$this->call( 'handle_package_actions' );
		} catch ( \RuntimeException $e ) {
			unset( $e );
		}
		$this->assertStringContainsString( 'action=add', $redirect );

		$_POST    = array();
		$_REQUEST = array();
		ob_start();
		$this->call( 'render_package_form', 0 );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'value="Typed package name"', $html );
	}

	public function test_refused_campaign_save_keeps_what_was_typed(): void {
		// No advertiser: refused.
		$_POST    = array(
			'wbam_save_campaign'  => '1',
			'wbam_campaign_nonce' => wp_create_nonce( 'wbam_save_campaign' ),
			'name'                => 'Typed campaign name',
			'pricing_model'       => 'cpc',
			'price_per_unit'      => '0.25',
		);
		$_REQUEST = $_POST;

		ob_start();
		$this->call( 'handle_campaign_form_save' );
		$this->call( 'render_campaign_form', 0 );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'value="Typed campaign name"', $html );
		$this->assertStringContainsString( 'value="0.25"', $html );
	}
}
