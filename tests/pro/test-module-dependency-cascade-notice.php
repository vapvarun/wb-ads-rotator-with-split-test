<?php
/**
 * Turning Wallet off cascades through Classifieds to 9 other modules
 * (card 10343765625, step 3). The Modules screen's "Required by" text
 * must list the full transitive chain, not only the modules that name
 * Wallet directly, or the owner only sees 5 of the 9 modules that go dark.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;

class Test_Module_Dependency_Cascade_Notice extends Pro_Test_Case {

	public function test_wallet_required_by_lists_the_full_cascade(): void {
		$modules = Settings_Helper::get_available_modules();

		$this->assertEqualsCanonicalizing(
			array(
				'campaigns',
				'packages',
				'ad_submissions',
				'classifieds',
				'geolocation',
				'custom_fields',
				'reviews',
				'messaging',
				'memberships',
			),
			$modules['wallet']['required_by'],
			'Turning Wallet off disables all 9 of these modules; the on-screen notice must name every one.'
		);
	}
}
