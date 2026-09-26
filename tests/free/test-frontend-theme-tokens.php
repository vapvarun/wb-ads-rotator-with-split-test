<?php
/**
 * Front-end colour tokens follow the active theme.
 *
 * - toast.css must not declare palette tokens: declared on body it beat
 *   frontend.css's :root theme chain and its dark palette for every ad on
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

		$inline = implode( '', (array) wp_styles()->get_data( 'wbam-frontend', 'after' ) );
		$this->assertStringContainsString( '--wbam-theme-button:var(--wp--preset--color--contrast)', $inline, 'TT5 buttons use the contrast colour.' );

		$css = file_get_contents( WBAM_PATH . 'assets/css/frontend.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertMatchesRegularExpression( '/--wbam-accent:[^;]*var\(--wbam-theme-button/', $css, 'The accent chain reads the theme button colour.' );
	}

	public function test_classic_theme_prints_no_button_token(): void {
		$this->assertFalse( wp_is_block_theme() );

		Frontend::get_instance()->enqueue_assets();

		$inline = implode( '', (array) wp_styles()->get_data( 'wbam-frontend', 'after' ) );
		$this->assertStringNotContainsString( '--wbam-theme-button', $inline );
	}
}
