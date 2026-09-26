<?php
/**
 * "Accept new listings" off must surface at Step 1 of the classified
 * wizard, not only after Submit fails on Step 6 (card 10343765625, step 4).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Template_Loader;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Classified_Wizard_New_Listings_Paused extends Pro_Test_Case {

	private object $advertiser;

	public function set_up(): void {
		parent::set_up();
		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
	}

	private function set_new_listings_enabled( bool $enabled ): void {
		$settings            = (array) get_option( 'wbam_pro_classifieds_settings', array() );
		$settings['enabled'] = $enabled;
		update_option( 'wbam_pro_classifieds_settings', $settings );
	}

	private function render_wizard( bool $is_edit ): string {
		return (string) Template_Loader::load_template(
			'portal/classified-form',
			array(
				'advertiser'    => $this->advertiser,
				'classified_id' => 0,
				'is_edit'       => $is_edit,
			),
			true
		);
	}

	public function test_step_1_warns_when_new_listings_are_paused(): void {
		$this->set_new_listings_enabled( false );

		$html = $this->render_wizard( false );

		// Card 10343726476 (Item decision): the item word is the site's
		// classifieds label, default "Classifieds".
		$this->assertStringContainsString( 'New Classifieds are paused', $html );
		// Must land inside Step 1's own content block, not a later step
		// (the mobile progress bar also prints a "data-step=2" chip, before
		// Step 1's content, so anchor on the Step 2 *content* div instead).
		$this->assertLessThan(
			strpos( $html, '<div class="wbam-wizard-step" data-step="2">' ),
			strpos( $html, 'New listings are paused' ),
			'The notice must appear in Step 1, before later steps.'
		);
	}

	public function test_no_warning_when_new_listings_are_open(): void {
		$this->set_new_listings_enabled( true );

		$html = $this->render_wizard( false );

		$this->assertStringNotContainsString( 'New listings are paused', $html );
	}

	public function test_editing_an_existing_listing_is_unaffected(): void {
		$this->set_new_listings_enabled( false );

		$html = $this->render_wizard( true );

		$this->assertStringNotContainsString( 'New listings are paused', $html );
	}
}
