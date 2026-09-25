<?php
/**
 * What a visitor sees from a fresh install: sample ads and the email ad.
 *
 * Card 10342625787: the setup wizard's sample ads shipped developer copy,
 * an emoji, hard-coded colours (1.03:1 in dark mode), a hot-linked
 * placeholder image and a href="#" button. The email-capture ad had
 * placeholder-only fields and silent success/error boxes.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Setup_Wizard;
use WBAM\Modules\Placements\Placement_Engine;

class Test_Visitor_Ad_Surfaces extends \WP_UnitTestCase {

	public function test_sample_ads_are_visitor_ready(): void {
		$wizard = new \ReflectionMethod( Setup_Wizard::class, 'create_sample_ads' );
		$wizard->setAccessible( true );
		$wizard->invoke( new Setup_Wizard(), array( 'header_banner', 'sidebar_widget', 'content_promo' ) );

		$ads = get_posts(
			array(
				'post_type'  => 'wbam-ad',
				'meta_key'   => '_wbam_sample_ad', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'fields'     => 'ids',
				'numberposts' => -1,
			)
		);
		$this->assertCount( 3, $ads );

		foreach ( $ads as $ad_id ) {
			$html = Placement_Engine::get_instance()->render_ad( $ad_id, array( 'skip_targeting' => true ) );

			$this->assertNotSame( '', $html );
			$this->assertStringNotContainsString( 'style=', $html, 'Colours come from theme tokens, so dark mode works.' );
			$this->assertStringNotContainsString( 'placehold.co', $html, 'No hot-linked images.' );
			$this->assertStringNotContainsString( 'href="#"', $html, 'Every link goes somewhere.' );
			$this->assertStringNotContainsString( 'paragraph 2', $html, 'No developer copy.' );
			$this->assertDoesNotMatchRegularExpression( '/[\x{1F300}-\x{1FAFF}]/u', $html, 'No emoji.' );
		}
	}

	public function test_email_capture_fields_are_labelled_and_announced(): void {
		$ad_id = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'email_capture', 'show_name_field' => 1, 'button_color' => '#2271b1' ) );

		$html = Placement_Engine::get_instance()->get_ad_type( 'email_capture' )->render( $ad_id );

		$this->assertStringContainsString( '<label class="wbam-email-label" for="wbam-email-form-' . $ad_id . '-email">', $html );
		$this->assertStringContainsString( 'id="wbam-email-form-' . $ad_id . '-name"', $html );
		$this->assertStringNotContainsString( 'placeholder=', $html, 'Visible labels, not placeholder-only fields.' );
		$this->assertStringContainsString( 'class="wbam-email-success" role="status"', $html );
		$this->assertStringContainsString( 'class="wbam-email-error" role="alert"', $html );
		$this->assertStringNotContainsString( '--wbam-accent', $html, 'The default button follows the theme accent.' );
	}
}
