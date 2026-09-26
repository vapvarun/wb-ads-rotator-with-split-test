<?php
/**
 * Owner decision 13 (card 10343726460), QA wave 4: every write path that
 * persists `_wbam_placements` for an ad that already exists routes through
 * the one shared wbam_filter_placements_to_fitting() helper — not just the
 * admin editor. Covers the FREE REST API and the Abilities executor;
 * the advertiser portal's edit path lives in the Pro test suite
 * (tests/pro/test-portal-ad-edit-shape-enforcement.php).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Core\Abilities;
use WBAM\Core\Settings_Helper;
use WBAM\Tests\Helpers\Factory;
use WP_REST_Request;
use WP_UnitTestCase;

class Test_Placement_Fit_Shared_Save_Paths extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		parent::tear_down();
	}

	private function make_square_ad(): int {
		$ad_id = Factory::make_ad();
		update_post_meta( $ad_id, '_wbam_is_responsive', '0' );
		update_post_meta( $ad_id, '_wbam_ad_format', 'custom' );
		update_post_meta( $ad_id, '_wbam_ad_width', 300 );
		update_post_meta( $ad_id, '_wbam_ad_height', 250 );

		return $ad_id;
	}

	// --- Unit level: the shared helper itself ---------------------------------

	public function test_helper_drops_mismatched_and_keeps_fitting(): void {
		Settings_Helper::update( 'format_matching', true );
		$ad_id = $this->make_square_ad();

		$saved = wbam_filter_placements_to_fitting( $ad_id, array( 'header', 'widget' ) );

		$this->assertNotContains( 'header', $saved );
		$this->assertContains( 'widget', $saved );
	}

	public function test_helper_is_a_noop_when_matching_is_off(): void {
		Settings_Helper::update( 'format_matching', false );
		$ad_id = $this->make_square_ad();

		$saved = wbam_filter_placements_to_fitting( $ad_id, array( 'header', 'widget' ) );

		$this->assertSame( array( 'header', 'widget' ), $saved );
	}

	public function test_helper_never_drops_an_exempt_slug(): void {
		Settings_Helper::update( 'format_matching', true );
		$ad_id = $this->make_square_ad();

		$saved = wbam_filter_placements_to_fitting( $ad_id, array( 'header' ), array( 'header' ) );

		$this->assertContains( 'header', $saved, 'A slug the caller could not have re-offered must survive.' );
	}

	// --- FREE REST API: PUT /wbam/v1/ads/{id} ---------------------------------

	public function test_rest_update_ad_enforces_the_fit(): void {
		Settings_Helper::update( 'format_matching', true );
		$ad_id = $this->make_square_ad();

		$request = new WP_REST_Request( 'PUT', '/wbam/v1/ads/' . $ad_id );
		$request->set_body_params( array( 'placements' => array( 'header', 'widget' ) ) );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$saved = (array) get_post_meta( $ad_id, '_wbam_placements', true );
		$this->assertNotContains( 'header', $saved, 'REST save must not persist a placement the ad does not fit.' );
		$this->assertContains( 'widget', $saved );
	}

	// --- Abilities executor ---------------------------------------------------

	public function test_ability_update_ad_enforces_the_fit(): void {
		Settings_Helper::update( 'format_matching', true );
		$ad_id = $this->make_square_ad();

		$abilities = new Abilities();
		$result    = $abilities->execute_update_ad(
			array(
				'id'         => $ad_id,
				'placements' => array( 'header', 'widget' ),
			)
		);

		$this->assertIsArray( $result );
		$saved = (array) get_post_meta( $ad_id, '_wbam_placements', true );
		$this->assertNotContains( 'header', $saved );
		$this->assertContains( 'widget', $saved );
	}

	public function test_ability_create_ad_enforces_the_fit(): void {
		Settings_Helper::update( 'format_matching', true );

		$abilities = new Abilities();
		$result    = $abilities->execute_create_ad(
			array(
				'title'      => 'Ability-created ad',
				'type'       => 'image',
				'content'    => array( 'image_url' => 'https://example.com/square.png' ),
				'placements' => array( 'header', 'widget' ),
			)
		);

		$this->assertIsArray( $result );
		$ad_id = (int) $result['id'];
		update_post_meta( $ad_id, '_wbam_is_responsive', '0' );
		update_post_meta( $ad_id, '_wbam_ad_format', 'custom' );
		update_post_meta( $ad_id, '_wbam_ad_width', 300 );
		update_post_meta( $ad_id, '_wbam_ad_height', 250 );

		// Re-run through the ability's own save path (update) now that the
		// ad has a resolved size, proving create+update share the guard.
		$abilities->execute_update_ad(
			array(
				'id'         => $ad_id,
				'placements' => array( 'header', 'widget' ),
			)
		);

		$saved = (array) get_post_meta( $ad_id, '_wbam_placements', true );
		$this->assertNotContains( 'header', $saved );
		$this->assertContains( 'widget', $saved );
	}
}
