<?php
/**
 * Basecamp card 10342784279 step 6: on the Credits settings tab, the
 * built-in Stripe/PayPal gateways must render before Credit Mappings
 * (which need a separate adapter plugin active), and the "no
 * credit-source providers are active" warning must only appear when
 * NEITHER a direct gateway NOR a mapped adapter can take a payment.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Credits_Settings;

class Test_Credits_Settings_Ordering extends Pro_Test_Case {

	private int $admin_id;

	public function set_up(): void {
		parent::set_up();

		$this->admin_id = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	public function tear_down(): void {
		delete_option( 'wbcom_credits_gateway_settings_wbam-pro' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function render(): string {
		ob_start();
		( new Credits_Settings() )->render();
		return (string) ob_get_clean();
	}

	public function test_pricing_and_payments_renders_before_credit_mappings(): void {
		$html = $this->render();

		$payments_pos = strpos( $html, 'Pricing &amp; Payments' );
		$mappings_pos = strpos( $html, 'Credit Mappings' );

		$this->assertNotFalse( $payments_pos, 'Pricing & Payments section must render.' );
		$this->assertNotFalse( $mappings_pos, 'Credit Mappings section must render.' );
		$this->assertLessThan( $mappings_pos, $payments_pos, 'Stripe/PayPal (built-in) must render before the adapter-based Credit Mappings.' );
	}

	public function test_no_providers_warning_shown_with_nothing_configured(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'No credit-source providers are active', $html );
	}

	public function test_no_providers_warning_hidden_when_a_gateway_is_configured(): void {
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

		$this->assertStringNotContainsString( 'No credit-source providers are active', $html, 'A working direct gateway means providers are not "truly none".' );
	}
}
