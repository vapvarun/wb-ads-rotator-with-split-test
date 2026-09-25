<?php
/**
 * The single-listing title-hiding CSS also covers block (FSE) themes.
 *
 * A block theme's single.html renders the post title via the Post Title
 * block (`.wp-block-post-title`), not the `.entry-header` classic
 * template-tag markup - so on Twenty Twenty-Five the theme's own title
 * showed above this plugin's, duplicating it. Card 10342783037, step 4.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Classifieds\Classified_Shortcodes;

class Test_Single_Classified_Title_Block_Theme extends Pro_Test_Case {

	public function test_hide_css_covers_the_block_theme_post_title_and_featured_image(): void {
		ob_start();
		Classified_Shortcodes::get_instance()->hide_featured_image_css();
		$css = ob_get_clean();

		// Classic themes - already covered, must not regress.
		$this->assertStringContainsString( '.single-wbam-classified .entry-header', $css );

		// Block (FSE) themes - core block output, not theme-specific markup.
		$this->assertStringContainsString( '.single-wbam-classified .wp-block-post-title', $css );
		$this->assertStringContainsString( '.single-wbam-classified .wp-block-post-featured-image', $css );
	}
}
