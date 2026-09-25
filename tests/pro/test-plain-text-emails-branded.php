<?php
/**
 * Plain-text emails go out in the branded layout.
 *
 * The new-message notification was plain text sent as text/html, so it
 * collapsed onto one line with no clickable link and no branding. The
 * featured-listing billing emails and the link-partnership emails were
 * unbranded plain text, and the partnership ones ignored the From set in
 * Settings > Emails. deliver() now wraps a plain-text body in the shared
 * header/footer (line breaks kept, URLs linked), and partnership mail is
 * routed through it when Pro is active.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Modules\Links\Partnership;
use WBAM\Modules\Links\Partnership_Emails;
use WBAM_Pro\Modules\Classifieds\Classified_Billing;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Notifications\Email_Notifications;

class Test_Plain_Text_Emails_Branded extends Pro_Test_Case {

	private array $sent = array();

	public function set_up(): void {
		parent::set_up();
		update_option(
			'wbam_pro_email_settings',
			array(
				'from_name'  => 'Ad Desk',
				'from_email' => 'ads@example.org',
			)
		);
		$this->sent = array();
		add_filter( 'pre_wp_mail', array( $this, 'capture' ), 10, 2 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture' ), 10 );
		delete_option( 'wbam_pro_email_settings' );
		parent::tear_down();
	}

	public function capture( $short_circuit, $atts ) {
		$this->sent[] = $atts;
		return true;
	}

	private function assert_branded( array $mail, string $label ): void {
		$this->assertStringContainsString( '<html', $mail['message'], $label . ': wrapped in the layout.' );
		$this->assertStringContainsString( 'This email was sent by', $mail['message'], $label . ': branded footer.' );
		$this->assertStringContainsString( '<p>', $mail['message'], $label . ': line breaks kept as paragraphs.' );
		$this->assertContains( 'Content-Type: text/html; charset=UTF-8', (array) $mail['headers'], $label );
		$this->assertContains( 'From: Ad Desk <ads@example.org>', (array) $mail['headers'], $label );
	}

	public function test_message_notification_is_branded_with_a_clickable_link(): void {
		$recipient = (int) self::factory()->user->create();
		$sender    = (int) self::factory()->user->create( array( 'display_name' => 'Bea Buyer' ) );

		Email_Notifications::get_instance()->send_message_notification( 1, 2, $sender, $recipient, null );

		$this->assertCount( 1, $this->sent );
		$this->assert_branded( $this->sent[0], 'message' );
		$this->assertMatchesRegularExpression( '/<a href="https?:\/\/[^"]+"/', $this->sent[0]['message'], 'The conversation link is clickable.' );
	}

	public function test_featured_billing_email_is_branded(): void {
		$user       = (int) self::factory()->user->create();
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		$term       = wp_insert_term( 'Brand ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );
		$classified = Classified_Manager::get_instance()->submit(
			$advertiser,
			array(
				'title'      => 'Green bike',
				'categories' => array( (int) $term['term_id'] ),
			)
		);
		update_option( 'wbam_pro_classifieds_settings', array_merge( get_option( 'wbam_pro_classifieds_settings', array() ), array( 'featured_downgrade_notification' => true ) ) );
		$this->sent = array();

		Classified_Billing::notify_featured_downgrade( Classified_Manager::get_instance()->get( (int) $classified->id ), 'insufficient_funds' );

		$this->assertCount( 1, $this->sent );
		$this->assert_branded( $this->sent[0], 'featured downgrade' );
	}

	public function test_partnership_email_is_branded_and_uses_the_configured_from(): void {
		$partnership = new Partnership(
			array(
				'id'          => 3,
				'name'        => 'Pat Partner',
				'email'       => 'pat@example.test',
				'website_url' => 'https://pat.example.test',
			)
		);

		Partnership_Emails::get_instance()->notify_requester_accepted( $partnership );

		$this->assertCount( 1, $this->sent );
		$this->assert_branded( $this->sent[0], 'partnership' );
		$this->assertCount( 1, array_filter( (array) $this->sent[0]['headers'], static fn( $h ) => 0 === stripos( $h, 'From:' ) ), 'One From header.' );
	}
}
