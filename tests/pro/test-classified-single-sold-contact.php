<?php
/**
 * Regression guard for Basecamp card 10339749933 (listing page
 * conversion): a sold listing hides phone/email even when the seller's
 * contact method allows them, and shows the sold banner instead of the
 * removed image badge.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Core\Template_Loader;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Classified_Single_Sold_Contact extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$classifieds                     = get_option( 'wbam_pro_classifieds_settings', array() );
		$classifieds['require_approval'] = false;
		update_option( 'wbam_pro_classifieds_settings', $classifieds );
	}

	/**
	 * Create an active classified for a fresh advertiser.
	 *
	 * @param array $overrides Data overrides passed to Classified_Manager::create().
	 * @return object Classified.
	 */
	private function make_active_classified( array $overrides = array() ): object {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		$classified = Classified_Manager::get_instance()->create(
			array_merge(
				array(
					'title'          => 'Presentability test listing',
					'description'    => 'Test.',
					'advertiser_id'  => $advertiser->id,
					'contact_phone'  => '555-0100',
					'contact_email'  => 'seller@example.com',
					'contact_method' => 'both',
				),
				$overrides
			)
		);
		$this->assertNotWPError( $classified );
		$this->assertSame( 'active', $classified->status );

		return $classified;
	}

	public function test_sold_listing_hides_phone_and_email(): void {
		$classified          = $this->make_active_classified();
		$classified->status  = 'sold';
		$classified->save();

		wp_set_current_user( 0 );

		ob_start();
		Template_Loader::load_template(
			'classifieds/single',
			array( 'classified' => new Classified( (int) $classified->id ) )
		);
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'wbam-sold-banner', $html, 'Sold status is a banner above the price.' );
		$this->assertStringNotContainsString( '555-0100', $html, 'A sold listing must not still hand out the phone number.' );
		$this->assertStringNotContainsString( 'seller@example.com', $html, 'A sold listing must not still hand out the email.' );
		$this->assertStringNotContainsString( 'wbam_inquiry_nonce', $html, 'A sold listing takes no new inquiries.' );
	}

	public function test_active_listing_still_shows_phone_and_email(): void {
		$classified = $this->make_active_classified();

		wp_set_current_user( 0 );

		ob_start();
		Template_Loader::load_template(
			'classifieds/single',
			array( 'classified' => new Classified( (int) $classified->id ) )
		);
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '555-0100', $html );
		$this->assertStringContainsString( 'seller@example.com', $html );
		$this->assertStringNotContainsString( 'wbam-sold-banner', $html );
	}
}
