<?php
/**
 * An inquiry reply is sent From the site, Reply-To the seller.
 *
 * send_inquiry_reply() set "From: <seller's name> <seller's email>", so the
 * site mailed as an address it does not own (SPF/DMARC fail, spam folder)
 * and the configured From name/email were skipped. The seller now goes in
 * Reply-To, and the email carries exactly one From: the site's.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Notifications\Email_Notifications;

class Test_Inquiry_Reply_From extends Pro_Test_Case {

	public function test_reply_is_from_the_site_and_reply_to_the_seller(): void {
		update_option(
			'wbam_pro_email_settings',
			array(
				'from_name'  => 'Ad Desk',
				'from_email' => 'ads@example.org',
			)
		);
		$sent = array();
		add_filter(
			'pre_wp_mail',
			function ( $short, $atts ) use ( &$sent ) {
				$sent[] = $atts;
				return true;
			},
			10,
			2
		);

		$inquiry = (object) array(
			'sender_name'   => 'Gary Guest',
			'sender_email'  => 'gary@example.test',
			'message'       => 'Is it still available?',
			'listing_title' => 'Blue bike',
		);
		Email_Notifications::get_instance()->send_inquiry_reply( $inquiry, 'Yes it is.', 'Sam Seller', 'sam@seller.test' );

		delete_option( 'wbam_pro_email_settings' );

		$this->assertCount( 1, $sent );
		$from = array_values( array_filter( $sent[0]['headers'], static fn( $h ) => 0 === stripos( $h, 'From:' ) ) );
		$this->assertSame( array( 'From: Ad Desk <ads@example.org>' ), $from, 'Exactly one From, the configured one.' );
		$this->assertContains( 'Reply-To: Sam Seller <sam@seller.test>', $sent[0]['headers'] );
	}
}
