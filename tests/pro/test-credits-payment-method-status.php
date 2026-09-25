<?php
/**
 * Basecamp card 10342784279 steps 2-3 (owner decision): a single primitive
 * decides whether paid features may turn on - a working self-serve gateway
 * OR an explicit manual top-up choice both count; only "nothing configured"
 * gates paid features off.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Credits_Settings;
use WBAM_Pro\Core\Credits_Bridge;

class Test_Credits_Payment_Method_Status extends Pro_Test_Case {

	private int $admin_id;

	public function set_up(): void {
		parent::set_up();

		$this->admin_id = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	public function tear_down(): void {
		delete_option( 'wbam_credits_payment_method' );
		delete_option( 'wbcom_credits_gateway_settings_wbam-pro' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_none_by_default(): void {
		$this->assertSame( 'none', Credits_Bridge::payment_method_status() );
		$this->assertFalse( Credits_Bridge::has_working_payment_method() );
	}

	public function test_manual_when_explicitly_chosen(): void {
		update_option( 'wbam_credits_payment_method', 'manual' );

		$this->assertSame( 'manual', Credits_Bridge::payment_method_status() );
		$this->assertTrue( Credits_Bridge::has_working_payment_method() );
	}

	public function test_gateway_wins_over_manual_when_both_present(): void {
		update_option( 'wbam_credits_payment_method', 'manual' );
		update_option(
			'wbcom_credits_gateway_settings_wbam-pro',
			array(
				'stripe' => array(
					'enabled'         => '1',
					'mode'            => 'test',
					'publishable_key' => 'pk_test_x',
					'secret_key'      => 'sk_test_x',
				),
			)
		);

		$this->assertSame( 'gateway', Credits_Bridge::payment_method_status(), 'A real self-serve route beats a manual choice.' );
	}

	public function test_credits_settings_save_persists_manual_choice(): void {
		$_POST['wbam_credits_settings_nonce'] = wp_create_nonce( 'wbam_save_credits_settings' );
		$_POST['wbam_credit_price']           = '1';
		$_POST['wbam_credits_purchase_url']   = '';
		$_POST['wbam_credits_manual_topup']   = '1';

		ob_start();
		( new Credits_Settings() )->render();
		ob_get_clean();

		unset( $_POST['wbam_credits_settings_nonce'], $_POST['wbam_credit_price'], $_POST['wbam_credits_purchase_url'], $_POST['wbam_credits_manual_topup'] );

		$this->assertSame( 'manual', get_option( 'wbam_credits_payment_method' ) );
		$this->assertSame( 'manual', Credits_Bridge::payment_method_status() );
	}

	public function test_credits_settings_save_clears_manual_choice_when_unchecked(): void {
		update_option( 'wbam_credits_payment_method', 'manual' );

		$_POST['wbam_credits_settings_nonce'] = wp_create_nonce( 'wbam_save_credits_settings' );
		$_POST['wbam_credit_price']           = '1';
		$_POST['wbam_credits_purchase_url']   = '';
		// wbam_credits_manual_topup intentionally absent (checkbox unchecked).

		ob_start();
		( new Credits_Settings() )->render();
		ob_get_clean();

		unset( $_POST['wbam_credits_settings_nonce'], $_POST['wbam_credit_price'], $_POST['wbam_credits_purchase_url'] );

		$this->assertSame( '', get_option( 'wbam_credits_payment_method' ) );
	}
}
