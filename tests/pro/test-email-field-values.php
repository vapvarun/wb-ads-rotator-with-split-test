<?php
/**
 * Money and title fields in customer emails show real values.
 *
 * - Inquiry-to-seller subject read $classified->title, which does not exist,
 *   so it went out as "Inquiry about: ".
 * - Featured-listing billing emails read $advertiser->wallet_balance, which
 *   does not exist, so every one said "Your current balance is $0.00".
 * - The credits-added email printed the SDK's ledger minor units ("+250")
 *   next to a balance in major units, and neither was formatted as money.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Billing;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Email_Field_Values extends Pro_Test_Case {

	private array $sent = array();
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();
		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		$this->sent       = array();
		add_filter( 'pre_wp_mail', array( $this, 'capture' ), 10, 2 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture' ), 10 );
		parent::tear_down();
	}

	public function capture( $short_circuit, $atts ) {
		$this->sent[] = $atts;
		return true;
	}

	private function mail_about( string $needle ): array {
		foreach ( $this->sent as $mail ) {
			if ( false !== strpos( $mail['subject'], $needle ) ) {
				return $mail;
			}
		}
		$this->fail( 'No email with subject containing ' . $needle . ': ' . implode( ' | ', array_column( $this->sent, 'subject' ) ) );
	}

	private function listing(): object {
		$term       = wp_insert_term( 'Fields ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );
		$classified = Classified_Manager::get_instance()->submit(
			$this->advertiser,
			array(
				'title'      => 'Blue bike',
				'categories' => array( (int) $term['term_id'] ),
			)
		);
		$this->assertNotWPError( $classified );
		return Classified_Manager::get_instance()->get( (int) $classified->id );
	}

	public function test_inquiry_to_seller_subject_names_the_listing(): void {
		Classified_Manager::get_instance()->submit_inquiry(
			$this->listing(),
			array(
				'name'    => 'Gary',
				'email'   => 'gary@example.test',
				'message' => 'Still available?',
			)
		);

		$this->assertStringContainsString( 'inquiry about: Blue bike', $this->mail_about( 'inquiry about' )['subject'] );
	}

	/**
	 * Featured is one-time only (owner decision, card 10343726590 follow-up):
	 * restoring featured status is a new purchase, not a wallet top-up, so
	 * the downgrade email quotes the Featured price to buy it again - it no
	 * longer shows a wallet balance at all.
	 */
	public function test_featured_downgrade_email_quotes_the_real_price_to_buy_again(): void {
		$classified = $this->listing();
		update_option( 'wbam_pro_classifieds_settings', array_merge( get_option( 'wbam_pro_classifieds_settings', array() ), array( 'featured_downgrade_notification' => true, 'featured_price' => 12.5 ) ) );

		Classified_Billing::notify_featured_downgrade( $classified, 'insufficient_funds' );

		$this->assertStringContainsString( wbam_format_price( 12.5 ), $this->mail_about( 'Featured Status Removed' )['message'] );
	}

	public function test_credits_added_email_shows_money_not_ledger_units(): void {
		Credits_Bridge::topup( (int) $this->advertiser->id, 2.5, 'Gift' );

		$message = $this->mail_about( 'Credits added' )['message'];
		$this->assertStringContainsString( '+' . wbam_format_price( 2.5 ), $message );
		$this->assertStringNotContainsString( '+250', $message );
	}
}
