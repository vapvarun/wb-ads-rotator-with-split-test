<?php
/**
 * Balance history speaks the plugin's words (card 10343726476, wave 6):
 * the Credits SDK's own top-up notes ("Credits from WooCommerce order #73",
 * "gateway:stripe:cs_...") are rewritten on display; the plugin's own
 * notes are shown as written.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Ledger_Row;

class Test_Ledger_Display_Note extends Pro_Test_Case {

	public function test_sdk_notes_read_as_the_plugin_words(): void {
		$this->assertSame( 'Paid top-up, WooCommerce order #73', Ledger_Row::display_note( 'Credits from WooCommerce order #73' ) );
		$this->assertSame( 'Subscription payment, Gold, order #9', Ledger_Row::display_note( 'Credits from subscription Gold payment — order #9' ) );
		$this->assertSame( 'Included with membership: Pro', Ledger_Row::display_note( 'Credits from PMPro membership: Pro' ) );
		$this->assertSame( 'Paid top-up through Stripe', Ledger_Row::display_note( 'gateway:stripe:cs_test_123' ) );
		$this->assertSame( 'Top-up refunded through PayPal', Ledger_Row::display_note( 'gateway:paypal:refund:ABC' ) );
	}

	public function test_plugin_notes_are_left_alone(): void {
		$this->assertSame( 'Package purchase: Starter', Ledger_Row::display_note( 'Package purchase: Starter' ) );
		$this->assertSame( '', Ledger_Row::display_note( '' ) );
		$this->assertSame( 'Credits from WooCommerce order #abc and more', Ledger_Row::display_note( 'Credits from WooCommerce order #abc and more' ), 'Only a whole-note match is rewritten.' );
	}

	public function test_the_row_and_every_surface_use_it(): void {
		$row = new Ledger_Row( (object) array( 'note' => 'Credits from WooCommerce order #55' ) );
		$this->assertSame( 'Paid top-up, WooCommerce order #55', $row->get_display_note() );

		$wallet = (string) file_get_contents( WBAM_PRO_PATH . 'templates/portal/tabs/wallet.php' );
		$this->assertStringContainsString( '$entry->get_display_note()', $wallet );
	}
}
