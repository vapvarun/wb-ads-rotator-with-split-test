<?php
/**
 * The inquiries toggle gates the DISPLAY, not only the submission.
 *
 * Regression guard for Basecamp card 10163940076: enable_inquiries was
 * read only by the server-side submission gate, so with the toggle off
 * visitors still saw the inquiry form, filled it in, submitted — and
 * only then got rejected. The single-listing template now gates the
 * form and the Send Message button on the same setting (the
 * enable_upgrades pattern), while the server gate stays in place.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Core\Template_Loader;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Classified_Inquiry_Display_Gate extends Pro_Test_Case {

	private object $classified;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		$classified = Classified_Manager::get_instance()->create(
			array(
				'title'          => 'Inquiry gate listing',
				'description'    => 'Inquiry gate test.',
				'advertiser_id'  => $advertiser->id,
				'status'         => 'active',
				'contact_method' => 'form',
			)
		);
		$this->assertNotWPError( $classified );
		$this->classified = $classified;

		wp_set_current_user( 0 ); // Anonymous visitor.
	}

	private function set_inquiries( bool $enabled ): void {
		$settings                     = get_option( 'wbam_pro_classifieds_settings', array() );
		$settings['enable_inquiries'] = $enabled;
		update_option( 'wbam_pro_classifieds_settings', $settings );
	}

	private function render_single(): string {
		ob_start();
		Template_Loader::load_template(
			'classifieds/single',
			array( 'classified' => new Classified( (int) $this->classified->id ) )
		);

		return (string) ob_get_clean();
	}

	public function test_disabled_inquiries_render_no_form(): void {
		$this->set_inquiries( false );

		$html = $this->render_single();

		$this->assertStringNotContainsString( 'wbam_inquiry_nonce', $html, 'With inquiries off, the form must not render for visitors to fill in and be rejected.' );
		// The element itself, not the inert JS lookup string that null-guards.
		$this->assertStringNotContainsString( 'id="wbam-contact-form-wrap"', $html );
	}

	public function test_enabled_inquiries_render_the_form(): void {
		$this->set_inquiries( true );

		$html = $this->render_single();

		$this->assertStringContainsString( 'wbam_inquiry_nonce', $html );
	}
}
