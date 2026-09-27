<?php
/**
 * Block themes, wave 6 (card 10342783037):
 *
 * - A dark block style (TT5 Evening) swaps the base/contrast presets with no
 *   dark class, so every surface and muted text token must end in those
 *   presets, never a fixed light hex, or cards stay white under light text.
 * - Block templates run wptexturize() after shortcodes, which broke the
 *   Browse filter script inline in sidebar-filters.php.
 * - Shortcode pages use the theme's wide width, not the 645px content column.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Block_Theme_Wave6 extends Pro_Test_Case {

	public function test_surface_and_text_tokens_follow_the_theme_presets(): void {
		$css = (string) file_get_contents( WBAM_PATH . 'assets/css/frontend-tokens.css' );
		preg_match( '/:root\s*\{(.*?)\n\}/s', $css, $root );

		foreach ( array( 'bg', 'surface', 'card-bg', 'surface-alt', 'text', 'text-muted', 'text-subtle', 'border', 'border-strong', 'border-subtle', 'input-border', 'danger-bg', 'error-bg' ) as $token ) {
			$this->assertMatchesRegularExpression( '/--wbam-' . preg_quote( $token, '/' ) . ':[^;]*var\(--wbam-(base|contrast)\)[^;]*;/', $root[1], "--wbam-{$token} must end in the theme's base/contrast presets." );
		}
	}

	public function test_the_browse_filters_template_has_no_inline_script(): void {
		$template = (string) file_get_contents( WBAM_PRO_PATH . 'templates/classifieds/sidebar-filters.php' );
		$this->assertStringNotContainsString( '<script', $template );
		$this->assertStringContainsString( 'initFilterForm: function', (string) file_get_contents( WBAM_PRO_PATH . 'assets/js/classified.js' ) );
	}

	public function test_plugin_pages_take_the_wide_width_in_a_constrained_layout(): void {
		$css = (string) file_get_contents( WBAM_PRO_PATH . 'assets/css/portal.css' );
		$this->assertMatchesRegularExpression( '/\.is-layout-constrained > :is\([^)]*\.wbam-portal[^)]*\.wbam-browse-classifieds[^)]*\)\s*\{\s*max-width: var\(--wp--style--global--wide-size/', $css );
	}
}
