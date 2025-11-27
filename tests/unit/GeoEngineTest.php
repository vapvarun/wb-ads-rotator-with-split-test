<?php
/**
 * Geo Engine Unit Tests
 *
 * @package WB_Ad_Manager
 */

namespace WBAM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WBAM\Modules\GeoTargeting\Geo_Engine;

/**
 * Geo Engine Test class.
 */
class GeoEngineTest extends TestCase {

	/**
	 * Geo Engine instance.
	 *
	 * @var Geo_Engine
	 */
	private $geo_engine;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Reset singleton.
		$reflection = new \ReflectionClass( Geo_Engine::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		// Clear mock globals.
		global $wbam_mock_options, $wbam_mock_post_meta, $wbam_mock_transients;
		$wbam_mock_options    = array();
		$wbam_mock_post_meta  = array();
		$wbam_mock_transients = array();

		$this->geo_engine = Geo_Engine::get_instance();
	}

	/**
	 * Test get_providers returns expected providers.
	 */
	public function test_get_providers() {
		$providers = $this->geo_engine->get_providers();

		$this->assertIsArray( $providers );
		$this->assertArrayHasKey( 'ip-api', $providers );
		$this->assertArrayHasKey( 'ipinfo', $providers );
		$this->assertArrayHasKey( 'ipapi-co', $providers );
	}

	/**
	 * Test provider structure.
	 */
	public function test_provider_structure() {
		$providers = $this->geo_engine->get_providers();

		foreach ( $providers as $id => $provider ) {
			$this->assertArrayHasKey( 'name', $provider );
			$this->assertArrayHasKey( 'requires_key', $provider );
			$this->assertArrayHasKey( 'limit', $provider );
		}
	}

	/**
	 * Test ipinfo requires API key.
	 */
	public function test_ipinfo_requires_key() {
		$providers = $this->geo_engine->get_providers();

		$this->assertTrue( $providers['ipinfo']['requires_key'] );
	}

	/**
	 * Test ip-api does not require API key.
	 */
	public function test_ip_api_no_key_required() {
		$providers = $this->geo_engine->get_providers();

		$this->assertFalse( $providers['ip-api']['requires_key'] );
	}

	/**
	 * Test get_countries_list returns array.
	 */
	public function test_get_countries_list() {
		$countries = $this->geo_engine->get_countries_list();

		$this->assertIsArray( $countries );
		$this->assertNotEmpty( $countries );
	}

	/**
	 * Test countries list has expected countries.
	 */
	public function test_countries_list_has_expected_countries() {
		$countries = $this->geo_engine->get_countries_list();

		$this->assertArrayHasKey( 'US', $countries );
		$this->assertArrayHasKey( 'GB', $countries );
		$this->assertArrayHasKey( 'IN', $countries );
		$this->assertArrayHasKey( 'CA', $countries );
		$this->assertArrayHasKey( 'AU', $countries );
	}

	/**
	 * Test country code to name mapping.
	 */
	public function test_country_code_to_name() {
		$countries = $this->geo_engine->get_countries_list();

		$this->assertEquals( 'United States', $countries['US'] );
		$this->assertEquals( 'United Kingdom', $countries['GB'] );
		$this->assertEquals( 'India', $countries['IN'] );
	}

	/**
	 * Test matches_targeting returns true when no rules.
	 */
	public function test_matches_targeting_no_rules() {
		global $wbam_mock_post_meta;

		$ad_id                            = 123;
		$wbam_mock_post_meta[ $ad_id ]    = array();

		$this->assertTrue( $this->geo_engine->matches_targeting( $ad_id ) );
	}

	/**
	 * Test matches_targeting returns true when geo not enabled.
	 */
	public function test_matches_targeting_geo_not_enabled() {
		global $wbam_mock_post_meta;

		$ad_id = 123;
		$wbam_mock_post_meta[ $ad_id ] = array(
			'_wbam_geo_targeting' => array(
				'enabled' => false,
			),
		);

		$this->assertTrue( $this->geo_engine->matches_targeting( $ad_id ) );
	}

	/**
	 * Test cache prefix constant.
	 */
	public function test_cache_prefix_constant() {
		$reflection = new \ReflectionClass( Geo_Engine::class );
		$constant   = $reflection->getConstant( 'CACHE_PREFIX' );

		$this->assertEquals( 'wbam_geo_', $constant );
	}

	/**
	 * Test cache expiration constant.
	 */
	public function test_cache_expiration_constant() {
		$reflection = new \ReflectionClass( Geo_Engine::class );
		$constant   = $reflection->getConstant( 'CACHE_EXPIRATION' );

		$this->assertEquals( DAY_IN_SECONDS, $constant );
	}

	/**
	 * Test singleton pattern.
	 */
	public function test_singleton_pattern() {
		$instance1 = Geo_Engine::get_instance();
		$instance2 = Geo_Engine::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test clear_cache method exists and is callable.
	 */
	public function test_clear_cache_callable() {
		$this->assertTrue( method_exists( $this->geo_engine, 'clear_cache' ) );
	}

	/**
	 * Test test_provider method exists.
	 */
	public function test_test_provider_method_exists() {
		$this->assertTrue( method_exists( $this->geo_engine, 'test_provider' ) );
	}

	/**
	 * Test get_visitor_location returns array.
	 */
	public function test_get_visitor_location_returns_array() {
		$location = $this->geo_engine->get_visitor_location();

		$this->assertIsArray( $location );
	}
}
