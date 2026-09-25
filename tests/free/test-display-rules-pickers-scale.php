<?php
/**
 * Display Rules pickers stay bounded on big sites and keep saved choices.
 *
 * Categories and tags printed every term on the site into the ad edit
 * screen, and a saved private or draft page was missing from the page
 * select (get_pages() reads published only), so the next save dropped it.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Display_Options;
use WBAM\Tests\Helpers\Factory;
use WP_UnitTestCase;

class Test_Display_Rules_Pickers_Scale extends WP_UnitTestCase {

	private function render( array $rules ): string {
		$ad = Factory::make_ad();
		update_post_meta( $ad, '_wbam_display_rules', $rules );

		ob_start();
		Display_Options::get_instance()->render_display_rules( get_post( $ad ) );
		return (string) ob_get_clean();
	}

	private function options_in( string $html, string $id ): int {
		preg_match( '/<select id="' . $id . '".*?<\/select>/s', $html, $m );
		return substr_count( $m[0] ?? '', '<option' );
	}

	public function test_tag_picker_is_bounded_and_keeps_a_saved_rare_tag(): void {
		$rare = self::factory()->term->create( array( 'taxonomy' => 'post_tag', 'name' => 'Zz rare tag' ) );
		self::factory()->term->create_many( 60, array( 'taxonomy' => 'post_tag' ) );

		$html = $this->render( array( 'tags' => array( $rare ) ) );

		$this->assertLessThanOrEqual( 51, $this->options_in( $html, 'wbam_rules_tags' ) );
		$this->assertStringContainsString( 'Zz rare tag', $html );
		$this->assertStringContainsString( 'data-rest="wp/v2/tags"', $html );
	}

	public function test_saved_private_page_stays_selected(): void {
		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'private',
				'post_title'  => 'Members only',
			)
		);

		$html = $this->render( array( 'posts' => array( $page ) ) );

		$this->assertMatchesRegularExpression( '/value="' . $page . '"\s+selected/', $html );
	}
}
