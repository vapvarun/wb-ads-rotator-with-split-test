<?php
/**
 * Geolocation (card 10344381767, owner-seat audit): a broken provider setup
 * saved as 'Settings saved.' while country rules matched no one, and a
 * failed lookup cost one provider call per page view.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Settings;
use WBAM\Modules\GeoTargeting\Geo_Engine;
use WP_UnitTestCase;

class Test_Geo_Provider_Problem extends WP_UnitTestCase {

	public function test_the_check_names_what_is_missing(): void {
		$this->assertStringContainsString( 'ipinfo.io API key', Geo_Engine::provider_problem( array( 'geo_primary_provider' => 'ipinfo', 'geo_ipinfo_key' => '' ) ) );
		$this->assertSame( '', Geo_Engine::provider_problem( array( 'geo_primary_provider' => 'ipinfo', 'geo_ipinfo_key' => 'abc123' ) ) );
		$this->assertStringContainsString( "can't be read: /nope/GeoLite2.mmdb", Geo_Engine::provider_problem( array( 'geo_primary_provider' => 'maxmind', 'geo_maxmind_db_path' => '/nope/GeoLite2.mmdb' ) ) );
		$this->assertSame( '', Geo_Engine::provider_problem( array( 'geo_primary_provider' => 'maxmind', 'geo_maxmind_db_path' => __FILE__ ) ) );
	}

	public function test_saving_a_broken_setup_reports_it(): void {
		$GLOBALS['wp_settings_errors'] = array();

		Settings::get_instance()->sanitize_settings(
			array(
				'geo_enabled'          => '1',
				'geo_primary_provider' => 'ipinfo',
				'geo_ipinfo_key'       => '',
			)
		);

		$errors = get_settings_errors( 'wbam_messages' );
		$this->assertSame( 'wbam_geo_provider', $errors[0]['code'] ?? '' );
		$this->assertStringContainsString( 'location lookups are not working', $errors[0]['message'] );
		$GLOBALS['wp_settings_errors'] = array();
	}

	public function test_a_failed_lookup_is_remembered(): void {
		$calls = 0;
		$count = static function ( $pre ) use ( &$calls ) {
			++$calls;
			return array( 'response' => array( 'code' => 401 ), 'body' => '{}' );
		};
		add_filter( 'pre_http_request', $count );
		update_option( 'wbam_settings', array_merge( (array) get_option( 'wbam_settings', array() ), array( 'geo_enabled' => true, 'geo_primary_provider' => 'ipinfo', 'geo_ipinfo_key' => 'bad-key' ) ) );

		$lookup = new \ReflectionMethod( Geo_Engine::class, 'get_location_by_ip' );
		$lookup->setAccessible( true );
		$lookup->invoke( Geo_Engine::get_instance(), '8.8.8.8' );
		$lookup->invoke( Geo_Engine::get_instance(), '8.8.8.8' );
		remove_filter( 'pre_http_request', $count );

		$this->assertSame( 1, $calls, 'The second page view reuses the remembered miss.' );
	}
}
