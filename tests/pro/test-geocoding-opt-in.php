<?php
/**
 * Classified geocoding makes no outbound lookup until the owner opts in
 * (Basecamp 10343031101, owner decision: no third-party lookups by default).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
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

		$classifieds                     = get_option( 'wbam_pro_classifieds_settings', array() );
		$classifieds['require_approval'] = false;
		update_option( 'wbam_pro_classifieds_settings', $classifieds );

		$this->get_backup = $_GET;
		$this->requests   = array();
		add_filter( 'pre_http_request', array( $this, 'record_request' ), 10, 3 );
	}

	public function tear_down(): void {
		$_GET = $this->get_backup;
		parent::tear_down();
	}

	/**
	 * Record and short-circuit every outbound request.
	 */
	public function record_request( $preempt, $args, $url ) {
		$this->requests[] = (string) $url;
		return array(
			'body'     => wp_json_encode( array( array( 'lat' => '39.7817', 'lon' => '-89.6501' ) ) ),
			'response' => array( 'code' => 200 ),
			'headers'  => array(),
		);
	}

	private function make_listing( string $title, string $address ): object {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		$classified = Classified_Manager::get_instance()->create(
			array(
				'title'         => $title,
				'description'   => 'Test.',
				'advertiser_id' => $advertiser->id,
			)
		);
		$this->assertNotWPError( $classified );
		Geolocation_Manager::get_instance()->save_location( $classified->id, array( 'formatted_address' => $address ) );
		return $classified;
	}

	public function test_no_outbound_geocode_until_the_owner_opts_in(): void {
		$result = Geolocation_Manager::get_instance()->geocode_address( 'Opt In Springfield ' . wp_rand() );

		$this->assertWPError( $result );
		$this->assertSame( array(), $this->requests, 'A fresh install must not send a typed address to a third-party geocoder.' );
	}

	public function test_radius_search_without_geocoding_falls_back_to_a_text_location_match(): void {
		$this->make_listing( 'Geo optin near listing', 'Springfield, IL' );
		$this->make_listing( 'Geo optin far listing', 'Chicago, IL' );

		$_GET['geo_address'] = 'Springfield';
		$html                = ( new Browse_Shortcode() )->render( array() );

		$this->assertSame( array(), $this->requests );
		$this->assertStringContainsString( 'Geo optin near listing', $html );
		$this->assertStringNotContainsString( 'Geo optin far listing', $html );
		$this->assertStringNotContainsString( 'wbam-geo-error', $html, 'The fallback is a match, never an error.' );
	}
}
