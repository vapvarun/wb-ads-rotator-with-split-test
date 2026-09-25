<?php
/**
 * Every email subject reads "[Site] What happened".
 *
 * Subjects came in four styles: "[site] ...", "... - site", no site name at
 * all, and a mix; the day count read "1 days". deliver() now puts the
 * "[Site] " prefix on any subject that lacks it, so the suffix forms were
 * dropped, and day counts are pluralised.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Modules\Links\Partnership;
use WBAM\Modules\Links\Partnership_Emails;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified;
use WBAM_Pro\Modules\Notifications\Email_Notifications;

class Test_Email_Subjects_Consistent extends Pro_Test_Case {

	public function test_every_subject_is_prefixed_once_with_the_site_name(): void {
		$subjects = array();
		add_filter(
			'pre_wp_mail',
			function ( $short, $atts ) use ( &$subjects ) {
				$subjects[] = $atts['subject'];
				return true;
			},
			10,
			2
		);

		$user       = (int) self::factory()->user->create();
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		$site       = get_bloginfo( 'name' );

		// "[site] ..." sender.
		Email_Notifications::get_instance()->send_advertiser_approved( $advertiser );
		// "... - site" sender.
		do_action( 'wbam_advertiser_status_changed', $advertiser, 'suspended', 'active' );
		// No-site sender.
		$recipient = (int) self::factory()->user->create();
		Email_Notifications::get_instance()->send_message_notification( 1, 2, $user, $recipient, null );
		// Free plugin sender with a "- site" suffix.
		Partnership_Emails::get_instance()->notify_requester_accepted(
			new Partnership(
				array(
					'name'  => 'Pat',
					'email' => 'pat@example.test',
				)
			)
		);
		// Singular day count.
		$post_id                   = (int) self::factory()->post->create( array( 'post_title' => 'Old lamp' ) );
		$classified                = new Classified();
		$classified->post_id       = $post_id;
		$classified->advertiser_id = (int) $advertiser->id;
		Email_Notifications::get_instance()->send_classified_expiring( $classified, 1 );

		$this->assertCount( 5, $subjects, implode( ' | ', $subjects ) );
		foreach ( $subjects as $subject ) {
			$this->assertStringStartsWith( '[' . $site . '] ', $subject );
			$this->assertSame( 1, substr_count( $subject, $site ), 'Site named once: ' . $subject );
		}
		$this->assertStringContainsString( 'expires in 1 day', $subjects[4] );
		$this->assertStringNotContainsString( '1 days', $subjects[4] );
	}
}
