<?php
/**
 * The single listing's sticky offset is not tuned to one theme.
 *
 * The 96px default cleared Reign's sticky header but left a gap on themes
 * without one. It is now the Reign/BuddyX fallback only; other themes print
 * no offset, so classified.js measures the pinned header (or the stylesheet
 * uses 24px). Card 10343301318.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Classifieds\Classified_Shortcodes;

class Test_Single_Sticky_Offset extends Pro_Test_Case {

	/**
	 * Pretend the given theme is the active parent theme.
	 *
	 * @param string $template Theme directory slug.
	 */
	private function use_template( string $template ): void {
		add_filter(
			'template',
			static function () use ( $template ) {
				return $template;
			}
		);
	}

	public function test_no_offset_on_a_theme_without_a_known_header(): void {
		$this->use_template( 'twentytwentyfive' );

		$this->assertSame( '', Classified_Shortcodes::single_sticky_offset() );
	}

	public function test_reign_and_buddyx_keep_the_96px_fallback(): void {
		foreach ( array( 'reign-theme', 'buddyx', 'buddyx-pro' ) as $template ) {
			remove_all_filters( 'template' );
			$this->use_template( $template );

			$this->assertSame( '96px', Classified_Shortcodes::single_sticky_offset(), $template );
		}
	}

	public function test_filter_overrides_the_default_and_is_sanitised(): void {
		$this->use_template( 'twentytwentyfive' );
		add_filter(
			'wbam_pro_single_sticky_offset',
			static function () {
				return 'calc(4rem + 8px);}body{x:y';
			}
		);

		$this->assertSame( 'calc(4rem + 8px)bodyxy', Classified_Shortcodes::single_sticky_offset() );
	}

	public function tear_down(): void {
		remove_all_filters( 'template' );
		remove_all_filters( 'wbam_pro_single_sticky_offset' );
		parent::tear_down();
	}
}
