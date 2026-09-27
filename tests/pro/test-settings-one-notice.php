<?php
/**
 * One Save, one notice (card 10343706274, wave 6): General printed
 * "Module settings saved" next to "Page settings saved", or next to the
 * duplicate-page error; Ads & Display printed "Rotation settings saved"
 * under "Settings saved".
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Admin;

class Test_Settings_One_Notice extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$GLOBALS['wp_settings_errors'] = array();
	}

	public function tear_down(): void {
		$_POST    = array();
		$_REQUEST = array();
		$GLOBALS['wp_settings_errors'] = array();
		parent::tear_down();
	}

	private function save_general( array $fields ): string {
		$_POST    = array_merge( array( 'wbam_save_general_settings' => '1' ), $fields );
		$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'wbam_general_settings' ) );

		ob_start();
		( new Pro_Admin() )->render_general_section();
		return (string) ob_get_clean();
	}

	public function test_a_clean_save_shows_one_success(): void {
		$html = $this->save_general( array() );

		$this->assertSame( 1, substr_count( $html, 'notice-success' ), $html );
		$this->assertStringNotContainsString( 'Module settings saved', $html );
	}

	public function test_a_refused_page_shows_only_the_error(): void {
		$html = $this->save_general( array( 'wbam_page_terms' => '999999' ) );

		$this->assertSame( 0, substr_count( $html, 'notice-success' ), 'No success next to the refusal.' );
		$this->assertStringContainsString( 'notice-error', $html );
	}

	public function test_the_rotation_card_adds_no_notice_of_its_own(): void {
		$_POST = array(
			'wbam_rotation_nonce' => wp_create_nonce( 'wbam_rotation_settings' ),
			'rotation_model'      => 'equal',
		);

		\WBAM_Pro\Modules\Rotation\Rotation_Admin::get_instance()->save_rotation_settings();

		$this->assertSame( array(), get_settings_errors() );
	}
}
