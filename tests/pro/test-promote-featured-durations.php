<?php
/**
 * Promote screen: one Featured price, one duration - no picker.
 *
 * Card 10343726590 (owner decision 15, "one Featured price and duration"):
 * the old duration-options selector let recurring billing read "$10.00/mo"
 * whatever the duration was picked, so 1, 3 and 6 months looked identical.
 * Rather than fix that selector, it is gone: promoting an already-live
 * listing now charges the same featured_price as the featured upgrade
 * offered while posting, with no duration to choose.
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

	public function test_promote_form_shows_the_one_featured_price_with_no_duration_picker(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_classifieds" ); // phpcs:ignore WordPress.DB -- test isolation, see Test_Classified_Bump_Charge.

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
		Settings_Helper::update_module( 'classifieds', 'featured_price', 12.5 );

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

		$this->assertStringContainsString( '$12.50', $html, 'The one Featured price must show.' );
		$this->assertStringNotContainsString( 'wbam-duration-selector', $html, 'There is no duration to pick any more.' );
		$this->assertStringNotContainsString( 'name="featured_duration"', $html );
		$this->assertStringNotContainsString( 'Monthly Recurring', $html, 'Featured is one-time only - there is no recurring option to show.' );
		$this->assertStringNotContainsString( 'wbam-billing-recurring', $html );
	}

	/** The expiry-warning/renew-reminder window is a filter, not a Settings field. */
	public function test_featured_expiry_warning_is_a_filter_not_a_field(): void {
		update_option( 'wbam_pro_classifieds_settings', array( 'featured_expiration_warning' => 5 ) );

		$this->assertSame( 5, Settings_Helper::featured_expiry_warning_days() );

		add_filter( 'wbam_pro_featured_expiry_warning_days', '__return_zero' );
		$this->assertSame( 0, Settings_Helper::featured_expiry_warning_days(), 'The filter must still be able to override the stored value.' );
		remove_filter( 'wbam_pro_featured_expiry_warning_days', '__return_zero' );
	}
}
