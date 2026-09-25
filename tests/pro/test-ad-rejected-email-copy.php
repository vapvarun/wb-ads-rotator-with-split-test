<?php
/**
 * QA reject on card 10339874920: the ad-rejected email (sent when a
 * pending ad transitions to draft/trash — Advertiser_Email_Notifications,
 * not the separate ad-submission-review email) said "requires changes" /
 * "Changes Required" for an ad that was actually rejected/taken down. The
 * copy must say rejected, with the reason.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Email_Notifications;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Ad_Rejected_Email_Copy extends Pro_Test_Case {

	public function test_ad_rejected_email_says_rejected_not_requires_changes(): void {
		$user       = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		$post_id    = self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_author' => $user,
				'post_status' => 'draft',
				'post_title'  => 'Reject Copy Ad',
			)
		);

		$captured = null;
		add_filter(
			'wbam_email_before_send',
			function ( $email ) use ( &$captured ) {
				$captured = $email;
				return $email;
			}
		);
		add_filter( 'pre_wp_mail', '__return_true' ); // Short-circuit the real send.

		$reflection = new \ReflectionMethod( Advertiser_Email_Notifications::class, 'send_ad_rejected' );
		$reflection->setAccessible( true );
		$reflection->invoke( Advertiser_Email_Notifications::get_instance(), $advertiser, get_post( $post_id ), 'Broken destination link.' );

		$this->assertNotNull( $captured, 'wbam_email_before_send never fired.' );
		$this->assertStringContainsString( 'was rejected', $captured['subject'] );
		$this->assertStringNotContainsString( 'requires changes', $captured['subject'] );
		$this->assertStringContainsString( 'Rejected', $captured['message'] );
		$this->assertStringNotContainsString( 'Changes Required', $captured['message'] );
		$this->assertStringContainsString( 'Broken destination link.', $captured['message'] );
	}
}
