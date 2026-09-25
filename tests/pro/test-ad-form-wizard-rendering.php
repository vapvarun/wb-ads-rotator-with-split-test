<?php
/**
 * Ad wizard (templates/portal/ad-form.php) step 1 type cards and the
 * step 6 terms checkbox.
 *
 * - Step 1 renders exactly the types `wbam_pro_valid_ad_types` allows, so
 *   the cards and validate_ad_data() never disagree about what can be
 *   submitted (Zoho #41830 - full per-package field is a 3.3.0 card).
 * - The terms checkbox required an advertiser to agree to a link that went
 *   nowhere when no advertising terms URL was configured.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Template_Loader;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Ad_Form_Wizard_Rendering extends Pro_Test_Case {

	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
	}

	public function tear_down(): void {
		remove_all_filters( 'wbam_pro_valid_ad_types' );
		remove_all_filters( 'wbam_advertising_terms_url' );
		parent::tear_down();
	}

	private function render_form(): string {
		return (string) Template_Loader::load_template(
			'portal/ad-form',
			array(
				'advertiser' => $this->advertiser,
				'ad_id'      => 0,
				'is_edit'    => false,
			),
			true
		);
	}

	public function test_type_cards_follow_the_filter(): void {
		$html = $this->render_form();
		$this->assertStringContainsString( 'name="ad_type" value="code"', $html );

		add_filter(
			'wbam_pro_valid_ad_types',
			static function ( $types ) {
				return array_values( array_diff( $types, array( 'code' ) ) );
			}
		);

		$html = $this->render_form();
		$this->assertStringNotContainsString(
			'name="ad_type" value="code"',
			$html,
			'A type removed from wbam_pro_valid_ad_types must not still offer a card for it.'
		);
		// The other built-in types are unaffected by removing just one.
		$this->assertStringContainsString( 'name="ad_type" value="image"', $html );
	}

	public function test_terms_checkbox_hidden_without_a_terms_url(): void {
		add_filter( 'wbam_advertising_terms_url', '__return_empty_string' );

		$html = $this->render_form();

		$this->assertStringNotContainsString(
			'name="agree_terms"',
			$html,
			'A required checkbox that links to nothing must not render.'
		);
	}

	public function test_terms_checkbox_shown_with_a_terms_url(): void {
		add_filter(
			'wbam_advertising_terms_url',
			static function () {
				return 'https://example.com/terms';
			}
		);

		$html = $this->render_form();

		$this->assertStringContainsString( 'name="agree_terms"', $html );
		$this->assertStringContainsString( 'https://example.com/terms', $html );
	}
}
