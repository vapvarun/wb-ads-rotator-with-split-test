<?php
/**
 * Single-listing meta actions: report trigger + favourite button.
 *
 * Regression guard for Basecamp cards 10163836807 and 10163841148(1):
 * report-a-listing had a complete pipeline (AJAX both-ways, modal JS,
 * admin screen, email) but nothing rendered the .wbam-report-link
 * trigger, so the moderation subsystem was unreachable; and the
 * favourite button existed on browse cards but not on the detail page
 * where buyers decide to save. The single template now renders the
 * .wbam-meta-footer block whose styles shipped all along.
 *
 * 3.2.0 frontend-presentability update: the favourite heart is now a
 * guest-visible login link (owner decision, matches the seller-profile
 * Follow button) rather than hidden for anonymous visitors.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Core\Template_Loader;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Classified_Single_Meta_Actions extends Pro_Test_Case {

	private int $owner_user;
	private object $classified;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$this->owner_user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser       = Advertiser_Manager::get_instance()->get_or_create( $this->owner_user );

		$classified = Classified_Manager::get_instance()->create(
			array(
				'title'         => 'Meta actions listing',
				'description'   => 'Meta actions test.',
				'advertiser_id' => $advertiser->id,
				'status'        => 'active',
			)
		);
		$this->assertNotWPError( $classified );
		$this->classified = $classified;
	}

	private function render_single(): string {
		ob_start();
		Template_Loader::load_template(
			'classifieds/single',
			array( 'classified' => new Classified( (int) $this->classified->id ) )
		);

		return (string) ob_get_clean();
	}

	public function test_anonymous_visitor_gets_the_report_trigger(): void {
		wp_set_current_user( 0 );

		$html = $this->render_single();

		$this->assertStringContainsString( 'wbam-report-link', $html, 'Without the trigger the whole report pipeline is unreachable.' );
		$this->assertStringContainsString( 'wbam-meta-favorite', $html, 'A signed-out visitor still sees the heart.' );
		$this->assertStringContainsString( 'wbam-favorite-login', $html, 'It must link to login, not perform the AJAX toggle.' );
	}

	public function test_logged_in_visitor_gets_favourite_and_report(): void {
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$html = $this->render_single();

		$this->assertStringContainsString( 'wbam-meta-favorite', $html, 'The detail page is where buyers decide to save a listing.' );
		$this->assertStringContainsString( 'wbam-report-link', $html );
	}

	public function test_owner_sees_no_report_trigger_on_own_listing(): void {
		wp_set_current_user( $this->owner_user );

		$html = $this->render_single();

		$this->assertStringNotContainsString( 'wbam-report-link', $html, 'Sellers do not report their own listings.' );
	}
}
