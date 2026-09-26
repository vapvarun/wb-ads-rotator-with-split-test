<?php
/**
 * Owner decision (card 10343726460, QA wave 5): when a save drops
 * placements the ad's size does not fit, the person saving is told which
 * ones. REST and Abilities return `dropped_placements`; the admin editor
 * names the placements it unticked. All paths share
 * wbam_split_placements_by_fit().
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Admin;
use WBAM\Core\Abilities;
use WBAM\Core\Settings_Helper;
use WBAM\Tests\Helpers\Factory;
use WP_REST_Request;
use WP_UnitTestCase;

class Test_Dropped_Placements_Reported extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );
		Settings_Helper::update( 'format_matching', true );
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

	public function test_rest_update_returns_the_dropped_placements(): void {
		$ad_id = $this->make_square_ad();

		$request = new WP_REST_Request( 'PUT', '/wbam/v1/ads/' . $ad_id );
		$request->set_body_params( array( 'placements' => array( 'header', 'widget' ) ) );
		$data = rest_do_request( $request )->get_data();

		$this->assertSame( array( 'header' ), wp_list_pluck( $data['dropped_placements'], 'id' ) );
		$this->assertStringContainsString( 'Header', $data['dropped_placements'][0]['label'] );
	}

	public function test_ability_update_returns_the_dropped_placements(): void {
		$ad_id = $this->make_square_ad();

		$result = ( new Abilities() )->execute_update_ad(
			array(
				'id'         => $ad_id,
				'placements' => array( 'header', 'widget' ),
			)
		);

		$this->assertSame( array( 'header' ), wp_list_pluck( $result['dropped_placements'], 'id' ) );
	}

	public function test_admin_editor_names_a_stored_placement_it_unticks(): void {
		$ad_id = $this->make_square_ad();
		update_post_meta( $ad_id, '_wbam_placements', array( 'header', 'widget' ) );

		ob_start();
		Admin::get_instance()->render_placements_metabox( get_post( $ad_id ) );
		$html = (string) ob_get_clean();

		$this->assertMatchesRegularExpression( '/class="[^"]*wbam-placements-dropped-notice[^"]*"[^>]*>(?:(?!<\/div>).)*Header/s', $html );
	}
}
