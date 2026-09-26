<?php
/**
 * A single listing shows its title once, and only the listing's own title
 * is touched.
 *
 * - Block themes: the listing gets its own single template (header part,
 *   post content, footer part), so the theme's Post Title, byline and
 *   "More posts" query never print. The old hide-CSS on
 *   .wp-block-post-title also hid every title in TT5's "More posts" list.
 * - BuddyX prints the page title as h1.entry-title in its sub-header, which
 *   the hide-CSS did not cover (two H1s).
 *
 * Card 10342783037.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Block_Theme_Templates;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Shortcodes;
use WP_Block_Templates_Registry;

class Test_Single_Listing_Title extends Pro_Test_Case {

	/**
	 * Theme active before the test.
	 *
	 * @var string
	 */
	private $previous_theme;

	/**
	 * Script and style registries before the test; the render enqueues.
	 *
	 * @var array
	 */
	private $saved_registries;

	public function set_up(): void {
		parent::set_up();
		$this->previous_theme   = get_stylesheet();
		$this->saved_registries = array( $GLOBALS['wp_scripts'] ?? null, $GLOBALS['wp_styles'] ?? null );
		self::unregister_templates();
	}

	public function tear_down(): void {
		self::unregister_templates();
		list( $GLOBALS['wp_scripts'], $GLOBALS['wp_styles'] ) = $this->saved_registries;
		switch_theme( $this->previous_theme );
		parent::tear_down();
	}

	/**
	 * The template registry outlives a test; start and end clean.
	 */
	private static function unregister_templates(): void {
		$registry = WP_Block_Templates_Registry::get_instance();
		foreach ( array( 'single-wbam-classified', 'taxonomy-wbam-classified-cat', 'taxonomy-wbam-classified-loc', Block_Theme_Templates::SELLER_PROFILE_SLUG ) as $slug ) {
			if ( $registry->is_registered( 'wb-ad-manager-pro//' . $slug ) ) {
				$registry->unregister( 'wb-ad-manager-pro//' . $slug );
			}
		}
	}

	public function test_block_theme_single_listing_renders_one_h1_through_the_canvas(): void {
		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
		update_option( 'wbam_pro_classifieds_settings', array( 'require_approval' => false ) );

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		$classified = Classified_Manager::get_instance()->create(
			array(
				'title'         => 'Canvas listing',
				'description'   => 'Rendered through template-canvas.',
				'advertiser_id' => $advertiser->id,
				'status'        => 'active',
			)
		);
		$this->assertNotWPError( $classified );
		self::factory()->post->create_many( 3 ); // Would fill a theme "More posts" list.

		switch_theme( 'twentytwentyfive' );
		add_theme_support( 'block-templates' );
		_register_theme_block_patterns();
		( new Block_Theme_Templates() )->register();

		// Render against copies of the registries, with the shared toast and
		// Lucide handles Free registers on init (an earlier test may have
		// rebuilt the registries without them).
		$GLOBALS['wp_scripts'] = clone wp_scripts();
		$GLOBALS['wp_styles']  = clone wp_styles();
		wbam()->register_shared_assets();
		wbam_register_lucide();

		$this->go_to( get_permalink( $classified->post_id ) );
		$this->assertTrue( is_singular( 'wbam-classified' ) );
		$template = get_query_template( 'single', array( 'single-wbam-classified.php', 'single.php' ) );
		$this->assertStringEndsWith( 'template-canvas.php', $template );

		ob_start();
		include $template;
		$html = (string) ob_get_clean();
		remove_theme_support( 'block-templates' );

		$this->assertStringStartsWith( '<!DOCTYPE html>', ltrim( $html ) );
		$this->assertStringContainsString( 'wbam-single', $html, 'The listing renders through Post Content.' );
		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ), 'Exactly one H1: the listing title.' );
		$this->assertStringNotContainsString( 'wp-block-post-title', $html );
	}

	public function test_hide_css_targets_only_the_page_title(): void {
		ob_start();
		Classified_Shortcodes::get_instance()->hide_featured_image_css();
		$css = ob_get_clean();

		$this->assertStringContainsString( '.single-wbam-classified .entry-header', $css, 'Classic theme title stays hidden.' );
		$this->assertStringNotContainsString( '.wp-block-post-title', $css, 'Must not hide titles in a "More posts" list.' );
		$this->assertStringContainsString( '.single-wbam-classified .site-sub-header .entry-title', $css, 'BuddyX sub-header title is hidden.' );
	}
}
