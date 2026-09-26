<?php
/**
 * Shape-height-cap reservation (owner decision 13, card 10343726460):
 *
 * - The reservation selectors used a child combinator,
 *   ".wbam-ad-slot > .wbam-ad", but Placement_Engine::render_ad() puts
 *   both classes on the SAME wrapper element (one per rendered ad) - a
 *   child combinator requires two different elements, so the rule never
 *   matched anything and no height was ever reserved.
 * - Between-content (before/after content, after paragraph) reserved a
 *   flat 280px (Box's height) even on phones, where a Banner creative
 *   (after_paragraph/content both accept Banner) renders ~100px tall,
 *   leaving ~190px of dead space (QA wave 4).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;

class Test_Shape_Reserve_Selector_And_Phone_Banner extends WP_UnitTestCase {

	private function css(): string {
		return (string) file_get_contents( WBAM_PATH . 'assets/css/frontend.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	public function test_no_selector_uses_the_dead_child_combinator(): void {
		$css = $this->css();
		$this->assertDoesNotMatchRegularExpression(
			'/\.wbam-ad-slot\s*>\s*\.wbam-ad\b/',
			$css,
			'.wbam-ad-slot and .wbam-ad are the same element (Placement_Engine::render_ad()); a ">" combinator between them never matches.'
		);
	}

	public function test_reservation_selectors_are_compound_on_the_same_element(): void {
		$css = $this->css();
		$this->assertMatchesRegularExpression( '/\.wbam-placement-header \.wbam-ad-slot\.wbam-ad\b/', $css );
		$this->assertMatchesRegularExpression( '/\.wbam-placement-before-content \.wbam-ad-slot\.wbam-ad\b/', $css );
		$this->assertMatchesRegularExpression( '/\.wbam-placement-after-content \.wbam-ad-slot\.wbam-ad\b/', $css );
		$this->assertMatchesRegularExpression( '/\.wbam-placement-paragraph \.wbam-ad-slot\.wbam-ad\b/', $css );
	}

	public function test_between_content_drops_to_the_banner_floor_on_phones(): void {
		$css = $this->css();

		// Isolate the max-width:480px block (the last one in the file is the
		// shape-cap media query; a following block also matches 480px for
		// spacing tokens, so anchor on the shape-cap selectors instead).
		$start = strpos( $css, '.wbam-placement-header .wbam-ad-slot.wbam-ad,' );
		$this->assertNotFalse( $start, 'Could not locate the shape-cap rules to scope the phone-breakpoint check.' );
		$block_start = strpos( $css, '@media', $start );
		$this->assertNotFalse( $block_start );

		// Brace-balance scan for the media query's own closing "}", so this
		// does not care how many rules live inside it.
		$depth   = 0;
		$opened  = false;
		$pos     = $block_start;
		$len     = strlen( $css );
		while ( $pos < $len ) {
			if ( '{' === $css[ $pos ] ) {
				++$depth;
				$opened = true;
			} elseif ( '}' === $css[ $pos ] ) {
				--$depth;
			}
			++$pos;
			if ( $opened && 0 === $depth ) {
				break;
			}
		}
		$phone_block = substr( $css, $block_start, $pos - $block_start );

		$this->assertStringContainsString( '.wbam-placement-before-content .wbam-ad-slot.wbam-ad', $phone_block );
		$this->assertStringContainsString( '.wbam-placement-after-content .wbam-ad-slot.wbam-ad', $phone_block );
		$this->assertStringContainsString( '.wbam-placement-paragraph .wbam-ad-slot.wbam-ad', $phone_block );
		$this->assertSame(
			2,
			substr_count( $phone_block, 'min-height: var(--wbam-shape-banner-h-mobile);' ),
			'Both the header/footer/archive group and the between-content group must reserve the Banner mobile height inside this breakpoint.'
		);
		$this->assertStringNotContainsString( '--wbam-shape-box-h', $phone_block, 'Between-content must not still reserve the desktop Box height on phones.' );
	}

	public function test_frontend_rtl_css_matches(): void {
		$rtl = (string) file_get_contents( WBAM_PATH . 'assets/css/frontend-rtl.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertDoesNotMatchRegularExpression( '/\.wbam-ad-slot\s*>\s*\.wbam-ad\b/', $rtl );
		$this->assertMatchesRegularExpression( '/\.wbam-placement-before-content \.wbam-ad-slot\.wbam-ad\b/', $rtl );
	}
}
