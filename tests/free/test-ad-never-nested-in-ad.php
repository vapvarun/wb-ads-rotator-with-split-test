<?php
/**
 * An ad is never injected inside another ad.
 *
 * Regression guard for Basecamp card 10340184996: a rich-content ad placed
 * by [wbam_ad] carried its own paragraphs, After-Paragraph injection counted
 * them as the post's paragraphs, and other ads landed inside the rich ad's
 * click link. Injection now skips .wbam-ad subtrees, and the rich ad's click
 * URL is an overlay link beside the creative instead of an <a> around it.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Placements\Paragraph_Placement;
use WBAM\Modules\Placements\Placement_Engine;

class Test_Ad_Never_Nested_In_Ad extends \WP_UnitTestCase {

	private function make_ad( array $data, array $placements = array() ): int {
		$ad_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_ad_data', $data );
		update_post_meta( $ad_id, '_wbam_placements', $placements );

		return $ad_id;
	}

	private function render( int $ad_id ): string {
		return Placement_Engine::get_instance()->render_ad(
			$ad_id,
			array(
				'allow_duplicate' => true,
				'skip_targeting'  => true,
			)
		);
	}

	public function test_paragraph_injection_skips_paragraphs_inside_an_ad(): void {
		$embedded = $this->render(
			$this->make_ad(
				array(
					'type'     => 'rich-content',
					'content'  => "Inside A\n\nInside B",
					'link_url' => 'https://rich.example/landing',
				)
			)
		);
		$this->make_ad(
			array(
				'type'            => 'rich-content',
				'content'         => 'INJECTED-MARKER',
				'after_paragraph' => 2,
			),
			array( 'after_paragraph' )
		);
		wp_cache_delete( 'wbam_placement_ads_after_paragraph', 'wbam' );

		$this->go_to( get_permalink( self::factory()->post->create() ) );
		$GLOBALS['wp_query']->the_post();

		$content = '<p>One</p>' . $embedded . '<p>Two</p><p>Three</p>';
		$output  = ( new Paragraph_Placement() )->inject_ads( $content );

		$this->assertStringContainsString( $embedded, $output, 'The embedded ad must come out untouched - nothing injected inside it.' );
		$this->assertSame( 1, substr_count( $output, 'INJECTED-MARKER' ) );
		$this->assertLessThan(
			strpos( $output, 'INJECTED-MARKER' ),
			strpos( $output, '<p>Two</p>' ),
			'Paragraph 2 is the post\'s second paragraph, not one inside the ad.'
		);
	}

	public function test_rich_click_url_is_an_overlay_not_a_wrapper(): void {
		$output = $this->render(
			$this->make_ad(
				array(
					'type'     => 'rich-content',
					'content'  => "Headline\n\nBody copy",
					'link_url' => 'https://rich.example/landing',
				)
			)
		);

		$this->assertStringContainsString( 'wbam-ad-rich-content--linked', $output );
		$this->assertMatchesRegularExpression(
			'#<a class="wbam-ad-rich-content__link" href="https://rich\.example/landing"[^>]*aria-label="Visit rich\.example"></a>#',
			$output,
			'The click URL is an empty full-cover link.'
		);
		$this->assertSame( 1, substr_count( $output, '<a ' ), 'The creative itself is not wrapped in a link.' );
		$this->assertStringContainsString( '<p>Headline</p>', $output );
	}
}
