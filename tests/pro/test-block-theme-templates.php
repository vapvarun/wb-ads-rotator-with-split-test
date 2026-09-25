<?php
/**
 * Classifieds taxonomy archives + the seller profile page register a block
 * template on block (FSE) themes - so they render inside the theme's own
 * header/footer template parts instead of the no-op get_header()/
 * get_footer() (WP core's get_header() locate_template()s for header.php,
 * which a block theme like Twenty Twenty-Five does not ship).
 *
 * Card 10342783037, step 1.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Block_Theme_Templates;
use WP_Block_Templates_Registry;

class Test_Block_Theme_Templates extends Pro_Test_Case {

	/**
	 * Theme active before the test, restored in tear_down().
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
		parent::tear_down();
	}

	public function test_baseline_classic_theme_registers_nothing(): void {
		// The default test-suite theme (WP_DEFAULT_THEME in wp-tests-config.php)
		// is classic - the same theme every other test in this suite runs
		// against. Asserts the classic-theme path is untouched.
		$this->assertFalse( wp_is_block_theme(), 'Test baseline is expected to be a classic theme.' );

		( new Block_Theme_Templates() )->register();

		$registry = WP_Block_Templates_Registry::get_instance();
		$this->assertNull( $registry->get_by_slug( 'taxonomy-wbam-classified-cat' ) );
		$this->assertNull( $registry->get_by_slug( Block_Theme_Templates::SELLER_PROFILE_SLUG ) );
	}

	public function test_taxonomy_and_seller_profile_templates_register_on_a_block_theme(): void {
		switch_theme( 'twentytwentyfive' );
		$this->assertTrue( wp_is_block_theme(), 'Twenty Twenty-Five is a block (FSE) theme.' );

		( new Block_Theme_Templates() )->register();

		$registry = WP_Block_Templates_Registry::get_instance();

		foreach ( array( 'taxonomy-wbam-classified-cat', 'taxonomy-wbam-classified-loc', Block_Theme_Templates::SELLER_PROFILE_SLUG ) as $slug ) {
			$template = $registry->get_by_slug( $slug );
			$this->assertNotNull( $template, "Expected a registered block template for slug \"{$slug}\"." );
			$this->assertStringContainsString( '"slug":"header"', $template->content, "\"{$slug}\" must include the theme's header template part." );
			$this->assertStringContainsString( '"slug":"footer"', $template->content, "\"{$slug}\" must include the theme's footer template part." );
		}

		$cat_template = $registry->get_by_slug( 'taxonomy-wbam-classified-cat' );
		$this->assertStringContainsString( '[wbam_pro_taxonomy_archive]', $cat_template->content );

		$loc_template = $registry->get_by_slug( 'taxonomy-wbam-classified-loc' );
		$this->assertStringContainsString( '[wbam_pro_taxonomy_archive]', $loc_template->content );

		$seller_template = Block_Theme_Templates::get_seller_profile_template();
		$this->assertNotNull( $seller_template, 'get_seller_profile_template() must find the registered template.' );
		$this->assertStringContainsString( '[wbam_pro_seller_profile]', $seller_template->content );
	}

	public function test_taxonomy_shortcode_renders_via_the_registered_template_content(): void {
		switch_theme( 'twentytwentyfive' );

		$term = self::factory()->term->create_and_get( array( 'taxonomy' => 'wbam-classified-cat' ) );
		$GLOBALS['wbam_taxonomy_data'] = array(
			'term'        => $term,
			'classifieds' => array(),
			'categories'  => array(),
			'locations'   => array(),
			'args'        => array(),
			'total'       => 0,
			'page'        => 1,
		);

		$html = do_shortcode( '[wbam_pro_taxonomy_archive]' );

		unset( $GLOBALS['wbam_taxonomy_data'] );

		$this->assertStringNotContainsString( '[wbam_pro_taxonomy_archive]', $html, 'A registered shortcode must not fall through to WordPress\'s literal-text fallback.' );
		$this->assertStringContainsString( 'wbam-classifieds-archive', $html, 'The taxonomy archive markup must actually render.' );
	}
}
