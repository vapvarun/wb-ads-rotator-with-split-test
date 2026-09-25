<?php
/**
 * The advertiser portal script is registered once, with Chart.js.
 *
 * Three places registered `wbam-pro-portal`. WordPress keeps the first
 * registration of a handle, so when the BuddyPress integration or the
 * classifieds form got there first (without `chartjs`), the full
 * registration was ignored: `Chart` was undefined and every portal chart
 * rendered blank while the REST data was there. Each also printed its own
 * short `wbamPortal` config over the full one.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Shortcodes;
use WBAM_Pro\Modules\Classifieds\Classified_Shortcodes;

/**
 * @group pro
 * @group reports
 */
class Test_Portal_Script_Registration extends Pro_Test_Case {

	/**
	 * The registry this test replaced; later tests need Free's handles.
	 *
	 * @var \WP_Scripts|null
	 */
	private $saved_scripts;

	public function set_up(): void {
		parent::set_up();
		$this->saved_scripts = $GLOBALS['wp_scripts'] ?? null;
	}

	public function tear_down(): void {
		$GLOBALS['wp_scripts'] = $this->saved_scripts;
		parent::tear_down();
	}

	public function test_portal_script_keeps_chartjs_whoever_registers_first(): void {
		global $wp_scripts;
		$wp_scripts = null;

		// A classified form enqueues the portal before the dashboard's
		// registration runs.
		$enqueue = new \ReflectionMethod( Classified_Shortcodes::class, 'enqueue_portal_assets' );
		$enqueue->invoke( Classified_Shortcodes::get_instance() );

		( new Advertiser_Shortcodes() )->register_assets();

		$registered = wp_scripts()->registered['wbam-pro-portal'];
		$this->assertContains( 'chartjs', $registered->deps, 'Portal charts need Chart.js loaded before portal.js.' );

		$config = (string) wp_scripts()->get_data( 'wbam-pro-portal', 'data' );
		$this->assertSame( 1, substr_count( $config, 'var wbamPortal' ), 'One wbamPortal config, not a short copy printed over the full one.' );
		$this->assertStringContainsString( 'chartEmptyEver', $config );
	}
}
