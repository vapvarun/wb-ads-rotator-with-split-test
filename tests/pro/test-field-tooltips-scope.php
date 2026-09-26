<?php
/**
 * Field_Tooltips (chartjs-adjacent .css/.js on every wbam-* admin page)
 * must load only where a template actually calls
 * Field_Tooltips::tip_icon() - Advertisers, Packages, and Report_Shell's
 * own pages - not the Settings screen or any other wbam-* page with no
 * tip icon. Owner decision, card 10342761510.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Field_Tooltips;
use WBAM_Pro\Admin\Report_Shell;

class Test_Field_Tooltips_Scope extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		// Not PSR-4 autoloaded: Pro_Admin require_once's it directly on
		// admin boot (see the class docblock).
		require_once WBAM_PRO_PATH . 'includes/Admin/class-field-tooltips.php';
	}

	public function tear_down(): void {
		wp_dequeue_style( 'wbam-pro-field-tooltips' );
		wp_deregister_style( 'wbam-pro-field-tooltips' );
		wp_dequeue_script( 'wbam-pro-field-tooltips' );
		wp_deregister_script( 'wbam-pro-field-tooltips' );
		parent::tear_down();
	}

	public function test_settings_screen_does_not_load_tooltip_assets(): void {
		Field_Tooltips::enqueue_assets( 'wbam-ad_page_wbam-settings' );

		$this->assertArrayNotHasKey( 'wbam-pro-field-tooltips', wp_scripts()->registered );
	}

	public function test_advertisers_page_loads_tooltip_assets(): void {
		Field_Tooltips::enqueue_assets( 'wbam-ad_page_wbam-advertisers' );

		$this->assertArrayHasKey( 'wbam-pro-field-tooltips', wp_scripts()->registered );
	}

	public function test_packages_page_loads_tooltip_assets(): void {
		Field_Tooltips::enqueue_assets( 'wbam-ad_page_wbam-packages' );

		$this->assertArrayHasKey( 'wbam-pro-field-tooltips', wp_scripts()->registered );
	}

	public function test_report_shells_own_pages_load_tooltip_assets(): void {
		foreach ( Report_Shell::HOOKS as $hook ) {
			wp_dequeue_script( 'wbam-pro-field-tooltips' );
			wp_deregister_script( 'wbam-pro-field-tooltips' );

			Field_Tooltips::enqueue_assets( $hook );

			$this->assertArrayHasKey( 'wbam-pro-field-tooltips', wp_scripts()->registered, "Expected the tooltip gate to fire on {$hook}." );
		}
	}
}
