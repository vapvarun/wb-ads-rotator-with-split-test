<?php
/**
 * Emails that ask the reader to act give them a way to.
 *
 * review-approved had no button to see the published review, and the
 * suspended / banned emails told the advertiser to contact support with no
 * link to do it.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Notifications\Email_Notifications;

class Test_Email_Next_Step_Links extends Pro_Test_Case {

	public function test_review_approved_links_to_the_sellers_profile(): void {
		$seller     = (int) self::factory()->user->create();
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $seller );

		$html = Email_Notifications::get_template(
			'review-approved',
			array(
				'user'   => get_user_by( 'id', (int) self::factory()->user->create() ),
				'review' => (object) array(
					'advertiser_id' => (int) $advertiser->id,
					'rating'        => 5,
					'title'         => 'Great seller',
					'comment'       => '',
				),
			)
		);

		$this->assertStringContainsString( 'href="' . esc_url( $advertiser->get_profile_url() ) . '"', $html );
	}

	public function test_suspended_and_banned_emails_link_to_support(): void {
		$user       = (int) self::factory()->user->create();
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		$contact    = esc_url( wbam_pro_get_invitation_contact_url() );
		$this->assertNotSame( '', $contact );

		foreach ( array( 'advertiser-account-suspended', 'advertiser-account-banned' ) as $template ) {
			$html = Email_Notifications::get_template(
				$template,
				array(
					'user'       => get_user_by( 'id', $user ),
					'advertiser' => $advertiser,
				)
			);
			$this->assertMatchesRegularExpression( '/<a href="' . preg_quote( $contact, '/' ) . '" class="btn"/', $html, $template );
		}
	}
}
