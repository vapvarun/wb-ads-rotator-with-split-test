<?php
/**
 * Admin editor: every ticked placement must fit the ad's size once shape
 * matching is on (owner decision 13, card 10343726460).
 *
 * Before this fix, class-admin.php's save_meta() saved whatever placements
 * were ticked with no size check at all — a 300x250 square could be ticked
 * into the header and would be saved there regardless. This only enforces
 * once `format_matching` is on (existing sites keep today's behavior until
 * they opt in).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Admin;
use WBAM\Core\Settings_Helper;

class Test_Admin_Editor_Shape_Enforcement extends \WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		parent::tear_down();
	}

	private function post_square_ad_ticking( array $placements ): int {
		$ad_id = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );

		$original = $_POST;
		$_POST    = array(
			'wbam_nonce'       => wp_create_nonce( 'wbam_save_ad' ),
			'wbam_sizing_mode' => 'fixed',
			'wbam_ad_format'   => 'custom',
			'wbam_ad_width'    => '300',
			'wbam_ad_height'   => '250',
			'wbam_placements'  => $placements,
			'wbam_data'        => array(
				'type'      => 'image',
				'image_url' => 'https://example.com/square.png',
			),
		);
		Admin::get_instance()->save_meta( $ad_id, get_post( $ad_id ) );
		$_POST = $original;

		return $ad_id;
	}

	public function test_mismatched_placement_dropped_when_matching_enforced(): void {
		Settings_Helper::update( 'format_matching', true );

		$ad_id = $this->post_square_ad_ticking( array( 'header', 'widget' ) );

		$saved = (array) get_post_meta( $ad_id, '_wbam_placements', true );

		$this->assertNotContains( 'header', $saved, 'A 300x250 square does not fit the Banner-shaped header.' );
		$this->assertContains( 'widget', $saved, 'The sidebar (Box shape) still gets the ad.' );
	}

	public function test_existing_site_keeps_todays_behavior_when_matching_is_off(): void {
		// Simulates an existing (pre-3.2.0) site: the test bootstrap's own
		// "fresh install" already defaults format_matching to true (that's
		// the feature working), so an existing site is modeled by
		// explicitly turning it off, same as an untouched upgrade would be.
		Settings_Helper::update( 'format_matching', false );

		$ad_id = $this->post_square_ad_ticking( array( 'header', 'widget' ) );

		$saved = (array) get_post_meta( $ad_id, '_wbam_placements', true );

		$this->assertContains( 'header', $saved, 'Existing sites keep serving as today until they opt in.' );
		$this->assertContains( 'widget', $saved );
	}
}
