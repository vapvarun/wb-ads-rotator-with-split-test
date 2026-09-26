<?php
/**
 * The Google Maps script registers once, under a WBAM-owned handle, always
 * with the places library - regardless of whether the view-page map or the
 * submit-form fields render first on a request. The old bare 'google-maps'
 * handle registered a different URL (with/without &libraries=places)
 * depending on caller, and whichever ran first silently won for the rest
 * of the request.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Geolocation\Geolocation_Manager;

/**
 * @group pro
 * @group geolocation
 */
class Test_Google_Maps_Single_Registration extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		update_option(
			'wbam_pro_geolocation_settings',
			array(
				'provider'            => 'google',
				'google_maps_api_key' => 'test-key-123',
			)
		);
	}

	public function tear_down(): void {
		delete_option( 'wbam_pro_geolocation_settings' );
		wp_dequeue_script( 'wbam-pro-google-maps' );
		wp_deregister_script( 'wbam-pro-google-maps' );
		wp_dequeue_script( 'google-maps' );
		wp_deregister_script( 'google-maps' );
		parent::tear_down();
	}

	public function test_view_page_map_then_submit_form_both_get_the_places_library(): void {
		$manager = Geolocation_Manager::get_instance();

		// View page (render_single_map()) renders FIRST - the order that
		// used to lock the handle to the no-places URL.
		$classified = (object) array( 'id' => 1 );
		$manager->save_location( 1, array( 'latitude' => 1.23, 'longitude' => 4.56 ) );
		ob_start();
		$manager->render_single_map( $classified );
		ob_end_clean();

		// Submit form (render_location_fields()) renders second on the
		// same request (e.g. the portal's classifieds list + inline edit).
		ob_start();
		$manager->render_location_fields();
		ob_end_clean();

		$script = wp_scripts()->registered['wbam-pro-google-maps'] ?? null;
		$this->assertNotNull( $script, 'The maps script must register under its own handle.' );
		$this->assertStringContainsString( 'libraries=places', $script->src, 'Whichever caller ran first must not lock out the places library the other needs.' );
		$this->assertArrayNotHasKey( 'google-maps', wp_scripts()->registered, 'The generic, collision-prone handle name must not be used any more.' );
	}
}
