<?php
/**
 * Card 10343765758, E5: with Pro loaded, its A/B testing + campaign
 * options render inside the new "Pro Options" side metabox, registered
 * through the same wbam_ad_metabox_options action Pro has always used.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Admin\Admin;

class Test_Ad_Editor_Pro_Options_Metabox extends Pro_Test_Case {

	private function ad(): int {
		return (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
	}

	public function test_pro_options_metabox_registers_when_pro_is_loaded(): void {
		global $post, $wp_meta_boxes;
		$wp_meta_boxes = array();
		$post          = get_post( $this->ad() );

		Admin::get_instance()->add_metaboxes();

		$this->assertArrayHasKey(
			'wbam-ad-pro-options',
			$wp_meta_boxes['wbam-ad']['side']['default'],
			'Pro always hooks wbam_ad_metabox_options, so the box must register.'
		);
	}

	public function test_pro_options_metabox_renders_the_ab_testing_content(): void {
		ob_start();
		Admin::get_instance()->render_pro_options_metabox( get_post( $this->ad() ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'A/B Testing', $html );
		$this->assertStringContainsString( 'wbam_pro_nonce', $html, 'Pro\'s own nonce still renders inside the shared box.' );
	}
}
