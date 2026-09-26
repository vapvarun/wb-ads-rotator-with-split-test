<?php
/**
 * The legacy geolocation provider notice must not point at "the option
 * below": it also prints on All Ads, where nothing is below it
 * (card 10344005566).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Settings;

class Test_Legacy_Geo_Notice_Copy extends \WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		parent::tear_down();
	}

	public function test_notice_names_the_provider_instead_of_a_position(): void {
		update_option( 'wbam_settings', array( 'geo_primary_provider' => 'ip-api' ) );
		$settings = Settings::get_instance();
		$method   = new \ReflectionMethod( $settings, 'get_legacy_geo_provider_notice' );
		$notice   = $method->invoke( $settings );

		$this->assertStringContainsString( 'ip-api.com', $notice );
		$this->assertStringNotContainsString( 'below', $notice );
	}
}
