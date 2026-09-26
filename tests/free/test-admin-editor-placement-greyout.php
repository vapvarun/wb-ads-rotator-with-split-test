<?php
/**
 * Admin editor: with shape matching on, a placement that does not fit the
 * ad's resolved size is greyed out (disabled + a note on the size it
 * accepts) in the Placements metabox — never silently ticked-then-dropped
 * after save (owner decision, card 10343726460, comment 10343765689).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Admin;
use WBAM\Core\Settings_Helper;

class Test_Admin_Editor_Placement_Greyout extends \WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		parent::tear_down();
	}

	private function make_square_ad(): \WP_Post {
		$ad_id = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_is_responsive', '0' );
		update_post_meta( $ad_id, '_wbam_ad_format', 'custom' );
		update_post_meta( $ad_id, '_wbam_ad_width', 300 );
		update_post_meta( $ad_id, '_wbam_ad_height', 250 );
		update_post_meta( $ad_id, '_wbam_placements', array( 'header' ) );

		return get_post( $ad_id );
	}

	public function test_mismatched_placement_checkbox_is_disabled_with_a_size_note(): void {
		Settings_Helper::update( 'format_matching', true );

		$post = $this->make_square_ad();

		ob_start();
		Admin::get_instance()->render_placements_metabox( $post );
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression(
			'/value="header"[^>]*disabled/',
			$html,
			'A 300x250 square does not fit the Banner-shaped header, so its checkbox must be disabled.'
		);
		$this->assertStringContainsString( 'wbam-placement-option--disabled', $html );
	}

	public function test_fitting_placement_checkbox_stays_enabled(): void {
		Settings_Helper::update( 'format_matching', true );

		$post = $this->make_square_ad();

		ob_start();
		Admin::get_instance()->render_placements_metabox( $post );
		$html = ob_get_clean();

		$this->assertDoesNotMatchRegularExpression(
			'/value="widget"[^>]*disabled/',
			$html,
			'The sidebar (Box shape) accepts a square and must stay tickable.'
		);
	}

	public function test_no_greyout_when_matching_is_off(): void {
		Settings_Helper::update( 'format_matching', false );

		$post = $this->make_square_ad();

		ob_start();
		Admin::get_instance()->render_placements_metabox( $post );
		$html = ob_get_clean();

		$this->assertDoesNotMatchRegularExpression(
			'/value="header"[^>]*disabled/',
			$html,
			'Existing sites that have not opted in keep every placement tickable.'
		);
	}

	public function test_every_placement_states_the_size_it_accepts(): void {
		Settings_Helper::update( 'format_matching', true );

		$post = $this->make_square_ad();

		ob_start();
		Admin::get_instance()->render_placements_metabox( $post );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'wbam-option-size', $html );
	}

	public function test_the_drop_after_save_notice_path_is_gone(): void {
		$this->assertFalse(
			method_exists( Admin::class, 'render_placement_mismatch_notice' ),
			'The grey-out replaces the after-the-fact admin notice; the notice path must be removed.'
		);
	}
}
