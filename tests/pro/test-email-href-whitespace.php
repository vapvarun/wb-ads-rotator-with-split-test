<?php
/**
 * Rejection email buttons carry a clean href.
 *
 * ad-rejected.php and advertiser-ad-rejected.php opened the href attribute on
 * one line and echoed the URL on the next, so the link began with "\n\t" -
 * some mail clients treat that as a relative link and the button goes
 * nowhere.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Notifications\Email_Notifications;

class Test_Email_Href_Whitespace extends Pro_Test_Case {

	public function test_rejection_templates_render_hrefs_without_leading_whitespace(): void {
		$user       = (int) self::factory()->user->create();
		$advertiser = \WBAM_Pro\Modules\Advertisers\Advertiser_Manager::get_instance()->get_or_create( $user );
		$ad         = get_post( self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) ) );

		foreach ( array( 'ad-rejected', 'advertiser-ad-rejected' ) as $template ) {
			$html = Email_Notifications::get_template(
				$template,
				array(
					'user'       => get_user_by( 'id', $user ),
					'advertiser' => $advertiser,
					'ad'         => $ad,
					'reason'     => 'Broken link.',
				)
			);

			preg_match_all( '/href="([^"]*)"/', $html, $m );
			$this->assertNotEmpty( $m[1], $template );
			foreach ( $m[1] as $href ) {
				$this->assertSame( trim( $href ), $href, $template . ' href has surrounding whitespace.' );
			}
		}
	}
}
