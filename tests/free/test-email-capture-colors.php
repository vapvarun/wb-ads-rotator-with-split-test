<?php
/**
 * Card 10339876480, step 17: the email-capture ad's Background and Text
 * colour fields were saved (Email_Capture_Ad::sanitize()) but render()
 * never read them, so picking a colour there did nothing on the front end.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\AdTypes\Email_Capture_Ad;
use WP_UnitTestCase;

class Test_Email_Capture_Colors extends WP_UnitTestCase {

	private function ad( array $data ): int {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta(
			$ad_id,
			'_wbam_ad_data',
			array_merge( array( 'type' => 'email_capture' ), $data )
		);
		return $ad_id;
	}

	public function test_custom_background_and_text_colors_reach_the_front_end(): void {
		$ad_id = $this->ad(
			array(
				'bg_color'   => '#112233',
				'text_color' => '#ffee00',
			)
		);

		$html = ( new Email_Capture_Ad() )->render( $ad_id );

		$this->assertStringContainsString( '--wbam-email-bg: #112233', $html );
		$this->assertStringContainsString( '--wbam-email-text: #ffee00', $html );
	}

	public function test_default_colors_emit_no_custom_properties(): void {
		$ad_id = $this->ad( array() );

		$html = ( new Email_Capture_Ad() )->render( $ad_id );

		$this->assertStringNotContainsString( '--wbam-email-bg', $html );
		$this->assertStringNotContainsString( '--wbam-email-text', $html );
	}
}
