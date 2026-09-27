<?php
/**
 * Credits and Classifieds settings render flat, like General.
 *
 * Both sections were wrapped in an extra .wbam-card by map_settings_sections(),
 * so their own cards (Pricing & Payments, the gateways, Credit Mappings,
 * Posting without a plan) sat inside a second card: depth 2. General puts
 * each heading outside its one card. Card 10343706274.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Admin;

class Test_Settings_Sections_Flat_Cards extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function test_credits_and_classifieds_have_no_card_in_card_and_headings_outside_cards(): void {
		$sections = ( new Pro_Admin() )->map_settings_sections( array() );
		$card     = "contains(concat(' ', normalize-space(@class), ' '), ' wbam-card ')";

		foreach ( array( 'credits', 'classifieds' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $sections );
			ob_start();
			call_user_func( $sections[ $slug ]['render'] );
			$html = ob_get_clean();

			$dom = new \DOMDocument();
			libxml_use_internal_errors( true );
			$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
			libxml_clear_errors();
			$xpath = new \DOMXPath( $dom );

			$this->assertSame( 0, $xpath->query( "//*[{$card}]//*[{$card}]" )->length, "{$slug}: a card sits inside another card." );
			$this->assertSame( 0, $xpath->query( "//*[{$card}]//h2" )->length, "{$slug}: section headings belong outside the card, as on General." );
		}
	}

	/** Wave 6: Tools & License had the demo card and a License Benefits box inside a section card. */
	public function test_tools_and_license_have_no_card_in_card(): void {
		$admin = new Pro_Admin();
		$tools = static function () use ( $admin ) {
			$admin->render_tools_page( true );
		};
		$license = array( \WBAM_Pro_License_Manager::get_instance(), 'render_license_tab' );
		add_action( 'wbam_settings_tools_content', $tools );
		add_action( 'wbam_settings_tools_content', $license );

		ob_start();
		( new \WBAM\Admin\Settings() )->render_tools_section();
		$html = (string) ob_get_clean();
		remove_action( 'wbam_settings_tools_content', $tools );
		remove_action( 'wbam_settings_tools_content', $license );

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
		libxml_clear_errors();
		$xpath = new \DOMXPath( $dom );
		$card  = "contains(concat(' ', normalize-space(@class), ' '), ' wbam-card ')";
		$boxed = "{$card} or contains(@class, 'form-table') or contains(@class, 'license-info-box')";

		$this->assertGreaterThan( 0, $xpath->query( "//*[{$card}]" )->length, 'The demo tool renders its card.' );
		$this->assertSame( 0, $xpath->query( "//*[{$boxed}]//*[{$boxed}]" )->length, 'A box sits inside another box.' );
		$this->assertSame( 0, $xpath->query( "//*[{$card}]//h2" )->length, 'Headings belong outside the card.' );
		$this->assertStringNotContainsString( '<style', $html );
		$this->assertStringNotContainsString( 'License Benefits</h4>', $html );
	}

	/** Owner review: no empty 'Classifieds Settings' heading directly above 'Label & URL'. */
	public function test_classifieds_has_no_empty_heading(): void {
		$sections = ( new Pro_Admin() )->map_settings_sections( array() );
		ob_start();
		call_user_func( $sections['classifieds']['render'] );
		$html = ob_get_clean();

		$this->assertDoesNotMatchRegularExpression( '#</h2>\s*<h2#', $html, 'A heading with nothing under it before the next heading.' );
	}
}
