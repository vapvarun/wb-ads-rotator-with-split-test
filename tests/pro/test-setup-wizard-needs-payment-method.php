<?php
/**
 * A paid mode cannot finish setup with no way to take money (card
 * 10342784279): Continue on "How will advertisers pay?" with no working
 * gateway and Manual top-up unticked stays on the step and says why,
 * instead of marking setup complete.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Setup_Wizard;

class Test_Setup_Wizard_Needs_Payment_Method extends Pro_Test_Case {

	private array $post;

	public function set_up(): void {
		parent::set_up();
		$this->post = $_POST;
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		delete_option( 'wbam_pro_setup_complete' );
		delete_option( 'wbam_credits_payment_method' );
		if ( ! function_exists( 'wbam_setup_wizard_render_step_2_payment' ) ) {
			ob_start();
			$current_step = 1;
			$settings     = array();
			include WBAM_PRO_PATH . 'templates/admin/setup-wizard.php';
			ob_end_clean();
		}
		add_filter(
			'wp_redirect',
			static function ( $location ) {
				throw new \RuntimeException( $location );
			}
		);
	}

	public function tear_down(): void {
		$_POST = $this->post;
		remove_all_filters( 'wp_redirect' );
		delete_option( 'wbam_credits_payment_method' );
		parent::tear_down();
	}

	private function continue_step_2( bool $manual ): string {
		$_POST = array(
			'wbam_wizard_nonce' => wp_create_nonce( 'wbam_setup_wizard' ),
			'wbam_wizard_step'  => '2',
		);
		if ( $manual ) {
			$_POST['wbam_credits_manual_topup'] = '1';
		}

		try {
			Setup_Wizard::get_instance()->handle_save();
		} catch ( \RuntimeException $redirect ) {
			return $redirect->getMessage();
		}
		return '';
	}

	public function test_no_method_stays_on_the_payment_step(): void {
		$location = $this->continue_step_2( false );

		$this->assertStringContainsString( 'step=2', $location );
		$this->assertStringContainsString( 'payment_needed=1', $location );
		$this->assertFalse( (bool) get_option( 'wbam_pro_setup_complete' ) );
	}

	public function test_manual_top_up_finishes_setup(): void {
		$location = $this->continue_step_2( true );

		$this->assertStringContainsString( 'step=3', $location );
		$this->assertTrue( (bool) get_option( 'wbam_pro_setup_complete' ) );
	}

	public function test_step_says_why_it_did_not_continue(): void {
		$_GET['payment_needed'] = '1';
		ob_start();
		wbam_setup_wizard_render_step_2_payment();
		$html = (string) ob_get_clean();
		unset( $_GET['payment_needed'] );

		$this->assertStringContainsString( 'Choose how advertisers will pay', $html );
	}
}
