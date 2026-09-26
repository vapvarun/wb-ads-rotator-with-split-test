<?php
/**
 * Card 10339876480, step 16: the Renewal Price field reloaded a stored
 * 2.5 as "2.5" instead of "2.50" - a plain float in a step="0.01" number
 * input, unlike every other money field in the same settings screen which
 * formats to 2 decimals before echoing.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Admin;

class Test_Renewal_Price_Reload_Format extends Pro_Test_Case {

	/**
	 * The site-wide test bootstrap sets $_SERVER['REQUEST_URI'] once; other
	 * tests rely on it staying set for the rest of the run, so this restores
	 * the original value instead of unsetting it.
	 *
	 * @var string|null
	 */
	private $original_request_uri;

	public function set_up(): void {
		parent::set_up();
		$this->original_request_uri = $_SERVER['REQUEST_URI'] ?? null;
	}

	public function tear_down(): void {
		delete_option( 'wbam_pro_classifieds_settings' );
		if ( null === $this->original_request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}
		parent::tear_down();
	}

	public function test_a_stored_2_5_reloads_as_2_50(): void {
		update_option( 'wbam_pro_classifieds_settings', array( 'renewal_price' => 2.5 ) );
		$_SERVER['REQUEST_URI'] = '/wp-admin/edit.php?post_type=wbam-ad&page=wbam-settings&tab=classifieds';

		$method = new \ReflectionMethod( Pro_Admin::class, 'render_classifieds_settings' );
		$method->setAccessible( true );
		ob_start();
		$method->invoke( new Pro_Admin() );
		$html = (string) ob_get_clean();

		$this->assertMatchesRegularExpression(
			'/name="wbam_renewal_price" value="2\.50"/',
			$html,
			'The field should reload as 2.50, matching the currency it charges in.'
		);
	}
}
