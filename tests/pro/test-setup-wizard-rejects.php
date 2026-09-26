<?php
/**
 * First-run QA reject (card 10342783654), Pro setup wizard: "Turn on
 * registration" stays on the Ready screen and confirms, Skip finishes the
 * first run, and the Ready screen offers "Remove sample ads".
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Setup_Wizard;

class Test_Setup_Wizard_Rejects extends Pro_Test_Case {

	private array $post_snapshot;

	private array $get_snapshot;

	public function set_up(): void {
		parent::set_up();

		$this->post_snapshot = $_POST;
		$this->get_snapshot  = $_GET;

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter(
			'wp_redirect',
			static function ( $location ) {
				throw new \RuntimeException( $location );
			}
		);
	}

	public function tear_down(): void {
		$_POST = $this->post_snapshot;
		$_GET  = $this->get_snapshot;
		remove_all_filters( 'wp_redirect' );
		parent::tear_down();
	}

	private function redirect_of( callable $action ): string {
		try {
			$action();
		} catch ( \RuntimeException $redirect ) {
			return $redirect->getMessage();
		}
		$this->fail( 'Expected a redirect.' );
	}

	private function render_ready(): string {
		ob_start();
		if ( function_exists( 'wbam_setup_wizard_render_step_3_done' ) ) {
			wbam_setup_wizard_render_step_3_done();
		} else {
			$current_step = 3;
			$settings     = array();
			include WBAM_PRO_PATH . 'templates/admin/setup-wizard.php';
		}
		return (string) ob_get_clean();
	}

	public function test_turn_on_registration_stays_on_ready_and_confirms(): void {
		update_option( 'users_can_register', 0 );
		$_POST = array(
			'wbam_wizard_nonce'               => wp_create_nonce( 'wbam_setup_wizard' ),
			'wbam_wizard_enable_registration' => '1',
		);

		$location = $this->redirect_of( array( Setup_Wizard::get_instance(), 'handle_save' ) );

		$this->assertSame( '1', (string) get_option( 'users_can_register' ) );
		$this->assertStringContainsString( 'step=3', $location );
		$this->assertStringNotContainsString( 'step=2', $location );

		wp_parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );
		$_GET = $query;
		$this->assertStringContainsString( 'Registration is on', $this->render_ready() );
	}

	public function test_skip_finishes_the_first_run(): void {
		delete_option( 'wbam_pro_setup_complete' );
		update_option( 'wbam_pro_needs_setup', true );

		wp_parse_str( (string) wp_parse_url( Setup_Wizard::get_skip_url(), PHP_URL_QUERY ), $query );
		$_GET = $query;
		$location = $this->redirect_of( array( Setup_Wizard::get_instance(), 'handle_save' ) );

		$this->assertTrue( (bool) get_option( 'wbam_pro_setup_complete' ) );
		$this->assertFalse( (bool) get_option( 'wbam_pro_needs_setup' ) );
		$this->assertStringNotContainsString( Setup_Wizard::PAGE_SLUG, $location );
	}

	public function test_ready_screen_offers_remove_sample_ads(): void {
		$ad = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_option( 'wbam_demo_data_ids', array( 'ads' => array( $ad ) ) );

		$this->assertStringContainsString( 'Remove sample ads', $this->render_ready() );
	}
}
