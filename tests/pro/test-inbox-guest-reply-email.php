<?php
/**
 * One inbox (Basecamp #10342786624): when a seller replies, in-app, to a
 * guest inquiry thread, the guest - who has no account to read an in-app
 * reply - gets it by email. Before this, send_message_notification() only
 * looked the recipient up by user ID, so a guest thread's recipient_id (the
 * 0 sentinel) resolved to no user and the reply silently went nowhere.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Messaging\Message_Manager;

class Test_Inbox_Guest_Reply_Email extends Pro_Test_Case {

	public function test_a_sellers_reply_on_a_guest_thread_emails_the_guest(): void {
		$seller_user_id = (int) self::factory()->user->create();
		Advertiser_Manager::get_instance()->create( $seller_user_id, array( 'status' => 'active' ) );

		$message_manager = Message_Manager::get_instance();
		$thread_id       = $message_manager->get_or_create_guest_thread( $seller_user_id, 0, 'Gary Guest', 'gary@example.test' );
		$message_manager->add_guest_message( $thread_id, 'Is it still available?' );

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

		// send_message() itself fires wbam_message_sent, which
		// Email_Notifications::send_message_notification() is already
		// listening to (registered during module bootstrap) - no separate
		// call needed, same as the real reply flow.
		$message_id = $message_manager->send_message( $thread_id, $seller_user_id, 'Yes, still available!' );
		$this->assertNotFalse( $message_id, 'The seller is a thread participant and can reply.' );

		$this->assertCount( 1, $sent, 'Exactly one email went out for the guest reply.' );
		$to = is_array( $sent[0]['to'] ) ? $sent[0]['to'][0] : $sent[0]['to'];
		$this->assertSame( 'gary@example.test', $to );
		$this->assertStringContainsString( 'Yes, still available!', $sent[0]['message'] );
	}
}
