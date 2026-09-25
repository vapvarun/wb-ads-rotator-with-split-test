<?php
/**
 * Before/After Archive placements stay block-safe on FSE themes.
 *
 * `loop_start`/`loop_end` fire while `get_the_block_template_html()` is
 * still building the page as a string, before `<!DOCTYPE html>` is echoed
 * (wp-includes/template-canvas.php) - a direct echo() there lands above the
 * doctype. On a block theme, both placements must route through the
 * `render_block_core/query` filter (a return value, never an echo) instead.
 * Card 10342783037, step 2.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Placements\Before_Archive_Placement;
use WBAM\Modules\Placements\After_Archive_Placement;
use WBAM\Modules\Placements\Placement_Engine;
use WBAM\Tests\Helpers\Factory;
use WP_UnitTestCase;

class Test_Block_Safe_Archive_Placement extends WP_UnitTestCase {

	private function ad_for( string $placement ): int {
		$ad_id = Factory::make_ad();
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_placements', array( $placement ) );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'rich-content', 'content' => 'Archive probe' ) );
		Placement_Engine::get_instance()->clear_placement_cache( $ad_id );

		return $ad_id;
	}

	public function tear_down(): void {
		Factory::reset_page_ads();
		parent::tear_down();
	}

	public function test_before_archive_injects_via_filter_with_no_echo_on_block_themes(): void {
		add_filter( 'wbam_enforce_page_cap', '__return_false' );
		$this->ad_for( 'before_archive' );

		// Simulate an archive request without depending on a real block
		// theme being installed in the test environment.
		$category = self::factory()->category->create();
		$this->go_to( get_category_link( $category ) );
		$this->assertTrue( is_archive() );

		$placement = new Before_Archive_Placement();
		$parsed_block = array( 'attrs' => array( 'query' => array( 'inherit' => true ) ) );

		ob_start();
		$result = $placement->inject_before_query_block( '<!--query-loop-->', $parsed_block );
		$echoed = ob_get_clean();

		$this->assertSame( '', $echoed, 'The block-safe path must return, never echo.' );
		$this->assertStringContainsString( 'wbam-placement-before-archive', $result, 'CSS class stays hyphenated for existing site overrides.' );
		$this->assertStringContainsString( 'Archive probe', $result );
		$this->assertStringEndsWith( '<!--query-loop-->', $result, 'Before-archive ads land before the query loop content.' );
	}

	public function test_before_archive_ignores_non_inherited_query_blocks(): void {
		add_filter( 'wbam_enforce_page_cap', '__return_false' );
		$this->ad_for( 'before_archive' );

		$category = self::factory()->category->create();
		$this->go_to( get_category_link( $category ) );

		$placement    = new Before_Archive_Placement();
		$parsed_block = array( 'attrs' => array( 'query' => array( 'inherit' => false ) ) );

		$result = $placement->inject_before_query_block( '<!--related-posts-->', $parsed_block );

		$this->assertSame( '<!--related-posts-->', $result, 'A secondary (non-inherited) Query Loop block must not receive archive ads.' );
	}

	public function test_after_archive_appends_via_filter_with_no_echo_on_block_themes(): void {
		add_filter( 'wbam_enforce_page_cap', '__return_false' );
		$this->ad_for( 'after_archive' );

		$category = self::factory()->category->create();
		$this->go_to( get_category_link( $category ) );

		$placement    = new After_Archive_Placement();
		$parsed_block = array( 'attrs' => array( 'query' => array( 'inherit' => true ) ) );

		ob_start();
		$result = $placement->inject_after_query_block( '<!--query-loop-->', $parsed_block );
		$echoed = ob_get_clean();

		$this->assertSame( '', $echoed, 'The block-safe path must return, never echo.' );
		$this->assertStringContainsString( 'wbam-placement-after-archive', $result );
		$this->assertStringStartsWith( '<!--query-loop-->', $result, 'After-archive ads land after the query loop content.' );
	}

	public function test_block_theme_filter_is_only_registered_for_block_themes(): void {
		$placement = new Before_Archive_Placement();

		remove_all_filters( 'render_block_core/query' );
		$placement->register();

		$this->assertSame(
			wp_is_block_theme(),
			(bool) has_filter( 'render_block_core/query', array( $placement, 'inject_before_query_block' ) ),
			'The block-safe filter registers if and only if the active theme is a block theme.'
		);
	}
}
