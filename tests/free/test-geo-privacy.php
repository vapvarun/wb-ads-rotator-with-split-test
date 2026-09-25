<?php
/**
 * Geolocation privacy (owner decision 8, 3.2.0): off by default, no visitor
 * IP lookup - let alone a third-party HTTP call - without an explicit opt-in
 * and a chosen provider. Everything fails open to "unknown" rather than
 * blocking render or fataling on a bad/missing local database.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\GeoTargeting\Geo_Engine;
use WP_UnitTestCase;

class Test_Geo_Privacy extends WP_UnitTestCase {

	private $http_request_seen = false;

	public function set_up(): void {
		parent::set_up();

		update_option( 'wbam_settings', array() );
		$this->http_request_seen = false;
		$this->reset_geo_engine_state();
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'fail_if_http_called' ), 10 );
		remove_filter( 'pre_http_request', array( $this, 'mock_ipinfo_success' ), 10 );
		parent::tear_down();
	}

	/**
	 * Geo_Engine::get_instance() is a Singleton; its per-request location
	 * cache (`current_location`) must not leak between tests/provider
	 * settings within the same PHPUnit process.
	 */
	private function reset_geo_engine_state(): void {
		$engine     = Geo_Engine::get_instance();
		$reflection = new \ReflectionProperty( Geo_Engine::class, 'current_location' );
		$reflection->setValue( $engine, null );
	}

	public function fail_if_http_called( $preempt, $args, $url ) {
		$this->http_request_seen = true;
		return $preempt;
	}

	public function mock_ipinfo_success( $preempt, $args, $url ) {
		return array(
			'body'     => wp_json_encode(
				array(
					'country' => 'US',
					'region'  => 'California',
					'city'    => 'Mountain View',
				)
			),
			'response' => array( 'code' => 200 ),
			'headers'  => array(),
		);
	}

	/**
	 * Owner decision 8: a fresh install (Settings::$defaults) never
	 * attempts a lookup, no matter how the visitor's IP resolves.
	 */
	public function test_geo_disabled_by_default_never_calls_http(): void {
		update_option( 'wbam_settings', array() ); // Nothing set - defaults apply.
		$_SERVER['REMOTE_ADDR'] = '8.8.4.4';

		add_filter( 'pre_http_request', array( $this, 'fail_if_http_called' ), 10, 3 );
		$location = Geo_Engine::get_instance()->get_visitor_location();

		$this->assertFalse( $this->http_request_seen, 'Geolocation off must never make an HTTP request.' );
		$this->assertSame( '', $location['country_code'] );
	}

	/**
	 * Turning geolocation on without picking a provider yet must still
	 * never call out - "enabled but unconfigured" is not the same as
	 * "enabled and ready".
	 */
	public function test_geo_enabled_without_provider_never_calls_http(): void {
		update_option(
			'wbam_settings',
			array(
				'geo_enabled'          => true,
				'geo_primary_provider' => '',
			)
		);
		$_SERVER['REMOTE_ADDR'] = '8.8.4.5';

		add_filter( 'pre_http_request', array( $this, 'fail_if_http_called' ), 10, 3 );
		$location = Geo_Engine::get_instance()->get_visitor_location();

		$this->assertFalse( $this->http_request_seen, 'No provider chosen yet must never make an HTTP request.' );
		$this->assertSame( '', $location['country_code'] );
	}

	/**
	 * The MaxMind provider never touches the network, even with
	 * geolocation fully turned on - it fails open to "unknown" instead of
	 * fataling when the configured .mmdb path doesn't exist.
	 */
	public function test_maxmind_missing_database_fails_open_without_http(): void {
		update_option(
			'wbam_settings',
			array(
				'geo_enabled'          => true,
				'geo_primary_provider' => 'maxmind',
				'geo_maxmind_db_path'  => '/nonexistent/GeoLite2-Country.mmdb',
			)
		);
		$_SERVER['REMOTE_ADDR'] = '8.8.4.6';

		add_filter( 'pre_http_request', array( $this, 'fail_if_http_called' ), 10, 3 );
		$location = Geo_Engine::get_instance()->get_visitor_location();

		$this->assertFalse( $this->http_request_seen, 'MaxMind must never make an HTTP request.' );
		$this->assertSame( '', $location['country_code'], 'A missing .mmdb file must fail open, not fatal.' );
	}

	/**
	 * The one provider that does call out (ipinfo.io) only does so once
	 * geolocation is on AND it is the chosen provider - and only that
	 * provider is tried, per owner decision 8 (no silent fallback to an
	 * unconsented service).
	 */
	public function test_ipinfo_provider_resolves_when_explicitly_chosen(): void {
		update_option(
			'wbam_settings',
			array(
				'geo_enabled'          => true,
				'geo_primary_provider' => 'ipinfo',
				'geo_ipinfo_key'       => 'test-key',
			)
		);
		$_SERVER['REMOTE_ADDR'] = '8.8.4.7';

		add_filter( 'pre_http_request', array( $this, 'mock_ipinfo_success' ), 10, 3 );
		$location = Geo_Engine::get_instance()->get_visitor_location();

		$this->assertSame( 'US', $location['country_code'] );
		$this->assertSame( 'ipinfo', $location['provider'] );
	}
}
