<?php
/**
 * Front-end colour tokens follow the active theme.
 *
 * - toast.css must not declare palette tokens: declared on body it beat
 *   frontend-tokens.css's :root theme chain and its dark palette for every ad on
 *   the page (light surface under BuddyX dark text, about 1:1).
 * - On a block theme the accent follows the theme.json button colour, so
 *   Twenty Twenty-Five buttons are not WordPress-admin blue.
 *
 * Card 10343301318.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Frontend\Frontend;
use WP_UnitTestCase;

class Test_Frontend_Theme_Tokens extends WP_UnitTestCase {

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
		wp_dequeue_style( 'wbam-frontend' );
		wp_deregister_style( 'wbam-frontend' );
		// Its inline theme-button token must not leak into the next test.
		wp_deregister_style( 'wbam-frontend-tokens' );
		parent::tear_down();
	}

	public function test_toast_css_declares_no_palette_tokens(): void {
		$css = file_get_contents( WBAM_PATH . 'assets/css/toast.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$this->assertSame( 0, preg_match( '/--wbam-[a-z-]+\s*:/', $css ), 'toast.css must inherit the page tokens, not redeclare them.' );
	}

	public function test_accent_follows_the_block_theme_button_colour(): void {
		switch_theme( 'twentytwentyfive' );
		wp_clean_theme_json_cache();

		Frontend::get_instance()->enqueue_assets();

		$inline = implode( '', (array) wp_styles()->get_data( 'wbam-frontend-tokens', 'after' ) );
		$this->assertStringContainsString( '--wbam-theme-button:var(--wp--preset--color--contrast)', $inline, 'TT5 buttons use the contrast colour.' );

		$css = file_get_contents( WBAM_PATH . 'assets/css/frontend-tokens.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertMatchesRegularExpression( '/--wbam-accent:[^;]*var\(--wbam-theme-button/', $css, 'The accent chain reads the theme button colour.' );
	}

	/**
	 * frontend.css only loads where an ad renders, so the palette lives in
	 * its own always-registered handle that both the ad CSS and Pro's
	 * portal depend on. QA wave 5: with the palette inside frontend.css,
	 * portal pages without an ad lost every --wbam-* token and dark mode.
	 */
	public function test_palette_lives_in_its_own_handle_the_ad_css_depends_on(): void {
		Frontend::get_instance()->enqueue_assets();
		$this->assertTrue( wp_style_is( 'wbam-frontend-tokens', 'registered' ) );
		$this->assertContains( 'wbam-frontend-tokens', wp_styles()->registered['wbam-frontend']->deps );

		$tokens = file_get_contents( WBAM_PATH . 'assets/css/frontend-tokens.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		foreach ( array( '--wbam-text', '--wbam-accent', '--wbam-card-bg', '--wbam-r-md' ) as $token ) {
			$this->assertMatchesRegularExpression( '/' . preg_quote( $token, '/' ) . '\s*:/', $tokens, "{$token} must be defined in the tokens file." );
		}
		$this->assertStringContainsString( 'html[data-bx-mode="dark"]', $tokens, 'The dark palette ships with the tokens.' );

		$ads = file_get_contents( WBAM_PATH . 'assets/css/frontend.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertSame( 0, preg_match( '/--wbam-(text|accent|card-bg|bg|surface)\s*:/', $ads ), 'frontend.css must not redeclare the palette.' );
	}

	public function test_classic_theme_prints_no_button_token(): void {
		$this->assertFalse( wp_is_block_theme() );

		Frontend::get_instance()->enqueue_assets();

		$inline = implode( '', (array) wp_styles()->get_data( 'wbam-frontend-tokens', 'after' ) );
		$this->assertStringNotContainsString( '--wbam-theme-button', $inline );
	}
}
