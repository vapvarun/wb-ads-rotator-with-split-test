<?php
/**
 * The ad wizard review names the campaign the server will create.
 *
 * The review step showed no campaign name, "Ad Type: image" and "End:
 * Ongoing" right after step 5 said "Runs 30 days from approval". portal.js
 * now fills those from the form; the default campaign name has to match
 * Campaign_Manager::create_from_package(), so it comes from PHP.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

/**
 * @group pro
 * @group reports
 */
class Test_Ad_Wizard_Review_Campaign extends Pro_Test_Case {

	public function tear_down(): void {
		global $wp_scripts;
		$wp_scripts = null;
		parent::tear_down();
	}

	public function test_portal_config_carries_the_default_campaign_name(): void {
		global $wp_scripts;
		$wp_scripts = null;

		( new \WBAM_Pro\Modules\Advertisers\Advertiser_Shortcodes() )->register_assets();

		$config = (string) wp_scripts()->get_data( 'wbam-pro-portal', 'data' );
		$this->assertStringContainsString( '"campaignDefault":"%s Campaign"', $config );
	}
}
