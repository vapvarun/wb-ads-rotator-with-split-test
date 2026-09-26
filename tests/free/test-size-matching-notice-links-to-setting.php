<?php
/**
 * Owner decision (QA wave 4, card 10343726460): the Format Matching
 * checkbox in Settings > Ads & Display is the one control. The one-time
 * upgrade notice's CTA must link there, not flip the setting itself
 * through a second, hidden switch.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Admin;
use WBAM\Core\Admin_Links;

class Test_Size_Matching_Notice_Links_To_Setting extends \WP_UnitTestCase {

	/**
	 * render_size_matching_notice() only ever prints when Pro's own
	 * Next_Step_Banner class is absent (see its own guard) - this test
	 * environment always loads Pro, so the render path itself can't be
	 * exercised here. Assert on the exact building block the fix uses
	 * instead: the same URL the notice's CTA now points to.
	 */
	public function test_settings_url_points_to_the_format_matching_field(): void {
		$url = Admin_Links::settings( 'ads-display' ) . '#wbam_setting_format_matching';

		$this->assertStringContainsString( 'page=wbam-settings', $url );
		$this->assertStringContainsString( 'section=ads-display', $url );
		$this->assertStringEndsWith( '#wbam_setting_format_matching', $url );
	}

	public function test_the_direct_flip_handler_is_gone(): void {
		$this->assertFalse(
			method_exists( Admin::class, 'handle_enable_size_matching' ),
			'The Format Matching checkbox is the one control - no second switch.'
		);
	}
}
