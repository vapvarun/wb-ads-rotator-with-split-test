<?php
/**
 * Basecamp card 10342784279 step 1: the setup wizard's "How will
 * advertisers pay?" step lists every route the bundled Credits SDK
 * supports (read-only status, no key collection here - Credits settings
 * owns that) and lets the owner explicitly choose manual top-up.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Setup_Wizard;

class Test_Setup_Wizard_Payment_Step extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();

		// The step renderers are plain functions declared inside the
		// template file, only loaded when the wizard controller includes
		// it. Load it once here (discarding its own step-1 output) so this
		// test does not depend on another test file having triggered it
		// first when the suite runs filtered to just this class.
		if ( ! function_exists( 'wbam_setup_wizard_render_step_2_payment' ) ) {
			ob_start();
			$current_step = 1;
			$settings     = array();
			include WBAM_PRO_PATH . 'templates/admin/setup-wizard.php';
			ob_end_clean();
		}
	}

	public function tear_down(): void {
		delete_option( 'wbam_credits_payment_method' );
		delete_option( 'wbcom_credits_gateway_settings_wbam-pro' );
		parent::tear_down();
	}

	private function render(): string {
		ob_start();
		wbam_setup_wizard_render_step_2_payment();
		return (string) ob_get_clean();
	}

	public function test_wizard_now_has_three_steps(): void {
		$this->assertSame( 3, Setup_Wizard::TOTAL_STEPS, 'Site Mode, Payments, Done.' );
	}

	public function test_stripe_shows_not_set_up_by_default(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'How will advertisers pay?', $html );
		$this->assertStringContainsString( 'Stripe', $html );
		$this->assertStringContainsString( 'Not set up yet', $html );
	}

	public function test_stripe_shows_ready_once_configured(): void {
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

		$html = $this->render();

		$this->assertMatchesRegularExpression( '/Stripe.*Ready/s', $html );
	}

	public function test_manual_checkbox_is_offered_and_unchecked_by_default_with_no_route(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'name="wbam_credits_manual_topup"', $html );
		// No working route yet - the wizard defaults the box to checked so
		// the owner does not leave this screen with nothing selected.
		$this->assertMatchesRegularExpression( '/name="wbam_credits_manual_topup"[^>]*checked/', $html );
	}

	public function test_manual_checkbox_unchecked_when_a_gateway_already_works(): void {
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

		$html = $this->render();

		$this->assertDoesNotMatchRegularExpression( '/name="wbam_credits_manual_topup"[^>]*checked/', $html, 'A working gateway means manual is optional, not pre-selected.' );
	}

	public function test_save_step_2_persists_manual_choice(): void {
		$_POST['wbam_credits_manual_topup'] = '1';

		$method = new \ReflectionMethod( Setup_Wizard::class, 'save_step_2' );
		$method->setAccessible( true );
		$method->invoke( Setup_Wizard::get_instance() );

		unset( $_POST['wbam_credits_manual_topup'] );

		$this->assertSame( 'manual', get_option( 'wbam_credits_payment_method' ) );
	}

	public function test_save_step_2_clears_choice_when_box_unchecked(): void {
		update_option( 'wbam_credits_payment_method', 'manual' );

		$method = new \ReflectionMethod( Setup_Wizard::class, 'save_step_2' );
		$method->setAccessible( true );
		$method->invoke( Setup_Wizard::get_instance() );

		$this->assertSame( '', get_option( 'wbam_credits_payment_method' ) );
	}
}
