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
}
