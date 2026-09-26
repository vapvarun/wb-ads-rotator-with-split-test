<?php
/**
 * Regression guard for Basecamp card 10339750352 (classifieds browse):
 * an empty min_price/max_price/geo_lat/geo_lng GET param must stay
 * empty instead of being coerced to a literal 0 by floatval(''), which
 * used to redisplay as value="0" and get resubmitted as a real (and
 * wrong) filter on the next Apply Filters click - and
 * wbam_pro_classifieds_has_active_filters() must correctly distinguish
 * a genuinely unfiltered empty result (offer "Post a Listing") from a
 * filtered one (offer "Clear filters").
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Core\Template_Loader;

class Test_Classifieds_Browse_Empty_Filters extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$_GET = array();
	}

	public function tear_down(): void {
		$_GET = array();
		parent::tear_down();
	}

	// ------------------------------------------------------------------
	// Empty-param handling: a blank filter field must render blank, not
	// as a literal "0" that gets resubmitted as a real filter.
	// ------------------------------------------------------------------

	public function test_sidebar_filters_blank_price_renders_empty_not_zero(): void {
		$_GET['min_price'] = '';
		$_GET['max_price'] = '';

		$html = Template_Loader::get_template(
			'classifieds/sidebar-filters',
			array(
				'categories' => array(),
				'locations'  => array(),
			)
		);

		$this->assertMatchesRegularExpression( '/name="min_price"[^>]*value=""/', $html, 'A blank min_price must render as an empty value.' );
		$this->assertMatchesRegularExpression( '/name="max_price"[^>]*value=""/', $html, 'A blank max_price must render as an empty value.' );
		$this->assertDoesNotMatchRegularExpression( '/name="min_price"[^>]*value="0"/', $html, 'A blank field must never redisplay as a literal 0 - that gets resubmitted as a real filter.' );
		$this->assertDoesNotMatchRegularExpression( '/name="max_price"[^>]*value="0"/', $html );
	}

	public function test_sidebar_filters_real_zero_price_is_preserved(): void {
		$_GET['min_price'] = '0';

		$html = Template_Loader::get_template(
			'classifieds/sidebar-filters',
			array(
				'categories' => array(),
				'locations'  => array(),
			)
		);

		$this->assertMatchesRegularExpression( '/name="min_price"[^>]*value="0"/', $html, 'A deliberately-typed 0 must still round-trip.' );
	}

	public function test_geolocation_radius_filter_blank_lat_lng_renders_empty(): void {
		if ( ! class_exists( '\\WBAM_Pro\\Modules\\Geolocation\\Geolocation_Manager' ) ) {
			$this->markTestSkipped( 'Geolocation module not available.' );
		}

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['geolocation'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
		self::enable_geolocation_opt_in(); // The radius filter only renders once the owner opts in.

		$_GET['geo_address'] = '';
		$_GET['geo_lat']     = '';
		$_GET['geo_lng']     = '';

		ob_start();
		\WBAM_Pro\Modules\Geolocation\Geolocation_Manager::get_instance()->render_radius_filter();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="wbam-geo-filter-lat" value=""', $html );
		$this->assertStringContainsString( 'id="wbam-geo-filter-lng" value=""', $html );
	}

	// ------------------------------------------------------------------
	// has_active_filters().
	// ------------------------------------------------------------------

	public function test_has_active_filters_false_with_no_params(): void {
		$this->assertFalse( wbam_pro_classifieds_has_active_filters() );
	}

	public function test_has_active_filters_false_with_only_empty_params(): void {
		$_GET['q']         = '';
		$_GET['min_price'] = '';
		$_GET['condition'] = array();

		$this->assertFalse( wbam_pro_classifieds_has_active_filters() );
	}

	public function test_has_active_filters_true_with_keyword(): void {
		$_GET['q'] = 'sofa';

		$this->assertTrue( wbam_pro_classifieds_has_active_filters() );
	}

	public function test_has_active_filters_true_with_real_zero_price(): void {
		$_GET['max_price'] = '0';

		$this->assertTrue( wbam_pro_classifieds_has_active_filters(), 'A deliberate max_price=0 is still an active filter.' );
	}

	public function test_has_active_filters_true_with_custom_field(): void {
		$_GET['cf_material'] = 'Oak';

		$this->assertTrue( wbam_pro_classifieds_has_active_filters() );
	}
}
