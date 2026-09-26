<?php
/**
 * Classified geocoding follows the owner's geolocation opt-in (Basecamp
 * 10343031101, owner decision 17): off by default, on when the owner enables
 * Geolocation and picks a provider. wbam_pro_allow_geocoding can only force
 * it off. While it's off the browse radius search is not offered.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Classifieds\Shortcodes\Browse_Shortcode;
use WBAM_Pro\Modules\Geolocation\Geolocation_Manager;

/**
 * @group pro
 * @group classifieds
 */
class Test_Geocoding_Opt_In extends Pro_Test_Case {

	/**
	 * URLs requested during the test.
	 *
	 * @var string[]
	 */
	private $requests = array();

	/**
	 * Snapshot of $_GET.
	 *
	 * @var array
	 */
	private $get_backup = array();

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		$enabled['geolocation'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$this->get_backup = $_GET;
		$this->requests   = array();
		add_filter( 'pre_http_request', array( $this, 'record_request' ), 10, 3 );
	}

	public function tear_down(): void {
		$_GET = $this->get_backup;
		parent::tear_down();
	}

	/**
	 * Record and short-circuit every outbound request with a Nominatim match.
	 */
	public function record_request( $preempt, $args, $url ) {
		$this->requests[] = (string) $url;
		return array(
			'body'     => wp_json_encode( array( array( 'lat' => '39.7817', 'lon' => '-89.6501' ) ) ),
			'response' => array( 'code' => 200 ),
			'headers'  => array(),
		);
	}

	/**
	 * The browse page HTML for a place search.
	 */
	private function browse_html(): string {
		$_GET['geo_address'] = 'Springfield';
		return ( new Browse_Shortcode() )->render( array() );
	}

	public function test_off_by_default_no_lookup_and_no_radius_field(): void {
		$result = Geolocation_Manager::get_instance()->geocode_address( 'Opt In Springfield ' . wp_rand() );
		$html   = $this->browse_html();

		$this->assertWPError( $result );
		$this->assertSame( array(), $this->requests, 'A fresh install must not send a typed address to a third-party geocoder.' );
		$this->assertStringNotContainsString( 'id="wbam-geo-filter-radius"', $html, 'No radius search is offered while geocoding is off.' );
		$this->assertStringNotContainsString( 'wbam-geo-error', $html );
	}

	public function test_enabled_without_a_provider_is_still_off(): void {
		$free                         = (array) get_option( 'wbam_settings', array() );
		$free['geo_enabled']          = true;
		$free['geo_primary_provider'] = '';
		update_option( 'wbam_settings', $free );

		$this->assertWPError( Geolocation_Manager::get_instance()->geocode_address( 'No Provider ' . wp_rand() ) );
		$this->assertSame( array(), $this->requests );
	}

	public function test_owner_opt_in_geocodes_and_shows_the_radius_field(): void {
		self::enable_geolocation_opt_in();

		$result = Geolocation_Manager::get_instance()->geocode_address( 'Opt In Springfield ' . wp_rand() );
		$html   = $this->browse_html();

		$this->assertIsArray( $result );
		$this->assertEqualsWithDelta( 39.7817, $result['lat'], 0.001 );
		$this->assertNotEmpty( $this->requests );
		$this->assertStringContainsString( 'nominatim.openstreetmap.org', $this->requests[0] );
		$this->assertStringContainsString( 'id="wbam-geo-filter-radius"', $html );
	}

	public function test_filter_can_force_geocoding_off(): void {
		self::enable_geolocation_opt_in();
		add_filter( 'wbam_pro_allow_geocoding', '__return_false' );

		$result = Geolocation_Manager::get_instance()->geocode_address( 'Forced Off ' . wp_rand() );
		$html   = $this->browse_html();

		$this->assertWPError( $result );
		$this->assertSame( array(), $this->requests );
		$this->assertStringNotContainsString( 'id="wbam-geo-filter-radius"', $html );
	}
}
