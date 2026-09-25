<?php
/**
 * Settings contract (BC#10342779181), FREE side: every setting a screen saves
 * is the one the runtime reads.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Core\Settings_Helper;
use WP_REST_Request;
use WP_UnitTestCase;

class Test_Settings_Contract_3_2 extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		delete_option( 'wbam_format_matching_enabled' );
		$_POST = array();
		parent::tear_down();
	}

	/** D1: Custom Container Class lands on the ad wrapper. */
	public function test_container_class_is_on_the_ad_wrapper(): void {
		update_option( 'wbam_settings', array( 'container_class' => 'my-ad-wrapper' ) );
		$ad_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta(
			$ad_id,
			'_wbam_ad_data',
			array(
				'type' => 'code',
				'code' => '<span>ok</span>',
			)
		);

		$html = \WBAM\Modules\Placements\Placement_Engine::get_instance()->render_ad( $ad_id );

		$this->assertMatchesRegularExpression( '/class="wbam-ad wbam-ad-slot[^"]* my-ad-wrapper"/', $html );
	}

	/** D2: REST writes run the settings sanitizer: unknown keys dropped, enums enforced. */
	public function test_rest_settings_write_is_sanitized(): void {
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );
		update_option( 'wbam_settings', array( 'ad_label' => 'Sponsored' ) );

		$request = new WP_REST_Request( 'PUT', '/wbam/v1/settings' );
		$request->set_param(
			'settings',
			array(
				'ad_label_position' => 'sideways',
				'bogus_key'         => 'x',
			)
		);
		rest_do_request( $request );

		$stored = get_option( 'wbam_settings' );
		$this->assertArrayNotHasKey( 'bogus_key', $stored );
		$this->assertSame( 'above', $stored['ad_label_position'] );
		$this->assertSame( 'Sponsored', $stored['ad_label'], 'Keys the write does not carry keep their value.' );
	}

	/** D9: partnership budgets use the site currency symbol, not an option nothing writes. */
	public function test_partnership_budget_uses_site_currency_symbol(): void {
		add_filter(
			'wbam_currency_symbol',
			static function () {
				return '€';
			},
			99
		);
		$partnership             = new \WBAM\Modules\Links\Partnership();
		$partnership->budget_min = 100.0;
		$partnership->budget_max = 100.0;

		$this->assertSame( '€100.00', $partnership->get_budget_range() );
	}

	/** D13: format matching has one setting, with the pre-3.2.0 option as the upgrade fallback. */
	public function test_format_matching_setting_with_legacy_fallback(): void {
		update_option( 'wbam_format_matching_enabled', 1 );
		$this->assertTrue( Settings_Helper::format_matching_enabled(), 'An install that enabled it before 3.2.0 keeps it on.' );

		update_option( 'wbam_settings', array( 'format_matching' => false ) );
		$this->assertFalse( Settings_Helper::format_matching_enabled(), 'Once saved, the Ad Display setting wins.' );

		$sanitized = \WBAM\Admin\Settings::get_instance()->sanitize_settings(
			array(
				'_fields'         => array( 'format_matching' ),
				'format_matching' => '1',
			)
		);
		$this->assertTrue( $sanitized['format_matching'] );
	}

	/** D16: the schedule box shows the date delivery uses, and a save keeps one date store. */
	public function test_schedule_dates_have_one_store(): void {
		$ad_id = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $ad_id, '_wbam_schedule', array( 'start_date' => '2026-01-01' ) );
		update_post_meta( $ad_id, '_wbam_start_date', '2026-02-01' );

		$display = \WBAM\Admin\Display_Options::get_instance();
		ob_start();
		$display->render_schedule( get_post( $ad_id ) );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'value="2026-02-01"', $html );

		$_POST = array(
			'wbam_nonce'    => wp_create_nonce( 'wbam_save_ad' ),
			'wbam_schedule' => array(
				'start_date' => '2026-03-01',
				'days'       => array( 'mon' ),
			),
		);
		$display->save_meta( $ad_id, get_post( $ad_id ) );

		$schedule = get_post_meta( $ad_id, '_wbam_schedule', true );
		$this->assertArrayNotHasKey( 'start_date', $schedule );
		$this->assertSame( array( 'mon' ), array_values( $schedule['days'] ) );
		$this->assertSame( '2026-03-01', get_post_meta( $ad_id, '_wbam_start_date', true ) );
	}
}
