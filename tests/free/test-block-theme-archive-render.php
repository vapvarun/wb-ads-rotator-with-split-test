<?php
/**
 * Archive ads on a block theme render inside <body>, never above <!DOCTYPE>.
 *
 * Fires the real block-theme render path: Twenty Twenty-Five, the category
 * query template resolved by core, and wp-includes/template-canvas.php
 * included end to end. `loop_start`/`loop_end` fire while
 * get_the_block_template_html() builds the page, before the doctype is
 * printed, so any echo there lands above it (quirks mode).
 * Card 10342783037.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Placements\After_Archive_Placement;
use WBAM\Modules\Placements\Before_Archive_Placement;
use WBAM\Modules\Placements\Placement_Engine;
use WBAM\Tests\Helpers\Factory;
use WP_UnitTestCase;

class Test_Block_Theme_Archive_Render extends WP_UnitTestCase {

	/**
	 * Theme active before the test.
	 *
	 * @var string
	 */
	private $previous_theme;

	public function set_up(): void {
		parent::set_up();
		$this->previous_theme = get_stylesheet();
	}

	public function tear_down(): void {
		switch_theme( $this->previous_theme );
		remove_theme_support( 'block-templates' );
		Factory::reset_page_ads();
		parent::tear_down();
	}

	public function test_archive_ads_print_inside_body_on_twenty_twenty_five(): void {
		add_filter( 'wbam_enforce_page_cap', '__return_false' );

		foreach ( array( 'before_archive' => 'Before probe', 'after_archive' => 'After probe' ) as $placement => $text ) {
			$ad_id = Factory::make_ad();
			update_post_meta( $ad_id, '_wbam_enabled', '1' );
			update_post_meta( $ad_id, '_wbam_placements', array( $placement ) );
			update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'rich-content', 'content' => $text ) );
			Placement_Engine::get_instance()->clear_placement_cache( $ad_id );
		}

		$category = self::factory()->category->create();
		self::factory()->post->create( array( 'post_category' => array( $category ) ) );

		switch_theme( 'twentytwentyfive' );
		add_theme_support( 'block-templates' );
		$this->assertTrue( wp_is_block_theme() );
		_register_theme_block_patterns(); // TT5's archive template is a pattern reference.

		// The boot-time instances were registered under the classic test
		// theme; register fresh ones the way plugins_loaded would on TT5.
		remove_all_actions( 'loop_start' );
		remove_all_actions( 'loop_end' );
		remove_all_filters( 'render_block_core/query' );
		( new Before_Archive_Placement() )->register();
		( new After_Archive_Placement() )->register();

		$this->go_to( get_category_link( $category ) );
		$template = get_query_template( 'archive', array( 'archive.php' ) );
		$this->assertStringEndsWith( 'template-canvas.php', $template, 'Core must resolve the block template canvas.' );

		ob_start();
		include $template;
		$html = ob_get_clean();

		$this->assertStringStartsWith( '<!DOCTYPE html>', ltrim( $html ), 'Nothing may print above the doctype.' );

		$body = substr( $html, strpos( $html, '<body' ) );
		$this->assertStringContainsString( 'Before probe', $body, 'Before-archive ad renders inside <body>.' );
		$this->assertStringContainsString( 'After probe', $body, 'After-archive ad renders inside <body>.' );
		$this->assertSame( 1, substr_count( $html, 'wbam-placement-before-archive' ), 'Before-archive ad renders exactly once.' );
		$this->assertSame( 1, substr_count( $html, 'wbam-placement-after-archive' ), 'After-archive ad renders exactly once.' );
	}
}
