<?php
/**
 * Chart.js registers exactly once, under a WBAM-owned handle ('wbam-pro-
 * chartjs', not the generic 'chartjs' three call sites duplicated), and
 * 'wbam-pro-report' style registers exactly once with a dependency that is
 * actually available on the calling screen - whichever screen (Ad
 * Analytics/Revenue or the Links analytics submenu) runs first.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Report_Shell;
use WBAM_Pro\Core\Pro_Plugin;
use WBAM_Pro\Modules\Links\Links_Pro_Module;

/**
 * @group pro
 */
class Test_Chartjs_And_Report_Style_Single_Registration extends Pro_Test_Case {

	public function tear_down(): void {
		foreach ( array( 'chartjs', 'wbam-pro-chartjs', 'wbam-pro-report', 'wbam-pro-portal', 'wbam-links-pro-admin' ) as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
		parent::tear_down();
	}

	public function test_chartjs_registers_once_under_the_wbam_owned_handle(): void {
		// Links submenu runs first (the order that used to leave two
		// separate 'chartjs' registrations, one per caller).
		Links_Pro_Module::get_instance()->enqueue_admin_scripts( 'wbam-ad_page_wbam-link-analytics' );
		Report_Shell::enqueue( 'wbam-ad_page_wbam-analytics' );

		$this->assertArrayNotHasKey( 'chartjs', wp_scripts()->registered, 'The generic, collision-prone handle name must not be used.' );
		$this->assertArrayHasKey( 'wbam-pro-chartjs', wp_scripts()->registered );
	}

	public function test_report_style_gets_a_registered_dependency_when_links_page_runs_first(): void {
		// The Links submenu never runs Pro_Admin::enqueue_assets(), so
		// 'wbam-pro-backend' is not registered on this request.
		Links_Pro_Module::get_instance()->enqueue_admin_scripts( 'wbam-ad_page_wbam-link-analytics' );

		$style = wp_styles()->registered['wbam-pro-report'] ?? null;
		$this->assertNotNull( $style );
		foreach ( $style->deps as $dep ) {
			$this->assertTrue( wp_style_is( $dep, 'registered' ) || wp_style_is( $dep, 'queue' ), "wbam-report depends on unregistered handle '{$dep}'." );
		}
	}

	public function test_report_style_gets_the_richer_backend_dependency_when_available(): void {
		wp_register_style( 'wbam-pro-backend', 'https://example.test/backend.css', array(), '1.0' );

		Report_Shell::enqueue( 'wbam-ad_page_wbam-analytics' );

		$style = wp_styles()->registered['wbam-pro-report'] ?? null;
		$this->assertNotNull( $style );
		$this->assertContains( 'wbam-pro-backend', $style->deps );

		wp_deregister_style( 'wbam-pro-backend' );
	}
}
