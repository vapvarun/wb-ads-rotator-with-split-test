<?php
/**
 * Promote screen: each Featured duration shows what it costs over its term.
 *
 * Under recurring billing every option read "$10.00/mo", so 1, 3 and 6 months
 * looked identical.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Shortcodes;

class Test_Promote_Featured_Durations extends Pro_Test_Case {

	public function test_each_duration_shows_its_term_total(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_classifieds" ); // phpcs:ignore WordPress.DB -- test isolation, see Test_Classified_Bump_Charge.

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
		Settings_Helper::update_module( 'classifieds', 'featured_fee', 10 );
		Settings_Helper::update_module( 'classifieds', 'featured_billing_model', 'recurring' );
		Settings_Helper::update_module( 'classifieds', 'featured_duration_options', array( 1, 3 ) );

		$user       = (int) self::factory()->user->create();
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Factory::topup_user( $user, 100000 );

		$manager    = Classified_Manager::get_instance();
		$classified = $manager->create(
			array(
				'title'         => 'Promote probe',
				'description'   => 'Promote me.',
				'advertiser_id' => $advertiser->id,
			)
		);
		$manager->update( (int) $classified->id, array( 'status' => 'active' ) );

		$shortcodes = ( new \ReflectionClass( Classified_Shortcodes::class ) )->newInstanceWithoutConstructor();
		ob_start();
		( new \ReflectionMethod( $shortcodes, 'render_promote_form' ) )->invoke( $shortcodes, $advertiser, $manager->get( (int) $classified->id ), home_url( '/' ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( '$30.00', $html, 'The 3-month option must show its 3-month cost.' );
	}
}
