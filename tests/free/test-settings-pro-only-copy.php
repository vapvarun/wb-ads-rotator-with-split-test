<?php
/**
 * Card 10342783654, step 11: the Settings subtitle and the Placements
 * intro described billing, notifications, modules and the Advertisers
 * column - all Pro-only concepts - unconditionally, even on a Free-only
 * site where none of them exist.
 *
 * WBAM_PRO_VERSION is always defined in this test environment (Pro is
 * installed alongside Free for the suite), so this only exercises the
 * Pro-active branch - same constraint documented on
 * Test_First_Run_Wizard_Handoff::test_not_pending_when_pro_is_not_active.
 * The Free-only branch is a plain ternary read at a glance.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Settings;
use WP_UnitTestCase;

class Test_Settings_Pro_Only_Copy extends WP_UnitTestCase {

	public function test_placements_intro_mentions_advertisers_when_pro_is_active(): void {
		ob_start();
		Settings::get_instance()->render_placements_section();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'advertisers', $html );
		$this->assertStringNotContainsString( '—', $html, 'No em dash in a translated UI string.' );
	}
}
