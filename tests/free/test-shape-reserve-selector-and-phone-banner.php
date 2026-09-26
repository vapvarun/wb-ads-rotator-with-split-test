<?php
/**
 * Shape-height-cap reservation (owner decision 13, card 10343726460):
 *
 * - The reserve belongs on the ad's CREATIVE: render_ad() prints the outer
 *   .wbam-ad.wbam-ad-slot wrapper, then the label, then the creative as its
 *   own .wbam-ad.wbam-ad-{type} child. A reserve on the outer wrapper made it
 *   a flex row and put the "Advertisement" label beside the ad.
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

	public function test_reserve_targets_the_creative_not_the_slot_wrapper(): void {
		$css = $this->css();
		$this->assertStringNotContainsString( '.wbam-ad-slot.wbam-ad', $css, 'A reserve on the outer slot puts the label beside the ad.' );
		foreach ( array( 'header', 'before-content', 'after-content', 'paragraph' ) as $placement ) {
			$this->assertStringContainsString( ".wbam-placement-{$placement} .wbam-ad-slot > .wbam-ad", $css );
		}
	}

	/** The child selector only works if the creative really is a child of the slot. */
	public function test_rendered_creative_is_a_child_of_the_slot_below_the_label(): void {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'rich-content', 'content' => '<p>Hello</p>' ) );

		$html = (string) \WBAM\Modules\Placements\Placement_Engine::get_instance()->render_ad( $ad_id, array( 'placement' => 'header' ) );
		$this->assertNotSame( '', $html, 'The test ad must render.' );

		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
		libxml_clear_errors();
		$xpath = new \DOMXPath( $doc );
		$creative = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' wbam-ad-slot ')]/*[contains(concat(' ', normalize-space(@class), ' '), ' wbam-ad ')]" );
		$this->assertGreaterThan( 0, $creative->length, 'The creative (.wbam-ad) must be a direct child of .wbam-ad-slot.' );
	}

	public function test_between_content_drops_to_the_banner_floor_on_phones(): void {
		$css = $this->css();

		// Isolate the max-width:480px block (the last one in the file is the
		// shape-cap media query; a following block also matches 480px for
		// spacing tokens, so anchor on the shape-cap selectors instead).
		$start = strpos( $css, '.wbam-placement-header .wbam-ad-slot > .wbam-ad,' );
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

		$this->assertStringContainsString( '.wbam-placement-before-content .wbam-ad-slot > .wbam-ad', $phone_block );
		$this->assertStringContainsString( '.wbam-placement-after-content .wbam-ad-slot > .wbam-ad', $phone_block );
		$this->assertStringContainsString( '.wbam-placement-paragraph .wbam-ad-slot > .wbam-ad', $phone_block );
		$this->assertSame(
			2,
			substr_count( $phone_block, 'min-height: var(--wbam-shape-banner-h-mobile);' ),
			'Both the header/footer/archive group and the between-content group must reserve the Banner mobile height inside this breakpoint.'
		);
		$this->assertStringNotContainsString( '--wbam-shape-box-h', $phone_block, 'Between-content must not still reserve the desktop Box height on phones.' );
	}

	public function test_frontend_rtl_css_matches(): void {
		$rtl = (string) file_get_contents( WBAM_PATH . 'assets/css/frontend-rtl.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertStringNotContainsString( '.wbam-ad-slot.wbam-ad', $rtl );
		$this->assertStringContainsString( '.wbam-placement-before-content .wbam-ad-slot > .wbam-ad', $rtl );
	}

	/** QA wave 5: the slot carries the ad's own shape, so its creative can reserve by it. */
	public function test_rendered_slot_carries_the_ads_own_shape(): void {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'rich-content', 'content' => '<p>Hello</p>' ) );
		update_post_meta( $ad_id, '_wbam_is_responsive', '0' );
		update_post_meta( $ad_id, '_wbam_ad_format', 'custom' );
		update_post_meta( $ad_id, '_wbam_ad_width', 728 );
		update_post_meta( $ad_id, '_wbam_ad_height', 90 );

		$html = (string) \WBAM\Modules\Placements\Placement_Engine::get_instance()->render_ad( $ad_id, array( 'placement' => 'after_paragraph' ) );

		$this->assertMatchesRegularExpression( '/class="[^"]*wbam-ad-slot[^"]*wbam-ad-slot--shape-banner/', $html );
	}

	/** QA wave 5: a Banner in between-content reserves the Banner height on desktop, not Box's 280px. */
	public function test_between_content_reserves_by_the_ads_own_shape_on_desktop(): void {
		$css   = $this->css();
		$rule  = strpos( $css, '.wbam-placement-paragraph .wbam-ad-slot--shape-banner > .wbam-ad' );
		$phone = strpos( $css, '@media', (int) strpos( $css, '.wbam-placement-header .wbam-ad-slot > .wbam-ad,' ) );

		$this->assertNotFalse( $rule );
		$this->assertLessThan( $phone, $rule, 'Must come before the phone block so the phone floor still wins.' );
		$this->assertStringContainsString( 'min-height: var(--wbam-shape-banner-h);', substr( $css, $rule, (int) strpos( $css, '}', $rule ) - $rule ) );
	}

	private function render_sized_ad( array $meta ): string {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'rich-content', 'content' => '<p>Hello</p>' ) );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $ad_id, $key, $value );
		}

		return (string) \WBAM\Modules\Placements\Placement_Engine::get_instance()->render_ad( $ad_id, array( 'placement' => 'after_paragraph' ) );
	}

	/** QA wave 5 follow-up: the slot carries the creative's own aspect ratio and width. */
	public function test_slot_carries_the_creatives_aspect_ratio(): void {
		$custom = $this->render_sized_ad(
			array(
				'_wbam_is_responsive' => '0',
				'_wbam_ad_format'     => 'custom',
				'_wbam_ad_width'      => 970,
				'_wbam_ad_height'     => 250,
			)
		);
		$named  = $this->render_sized_ad(
			array(
				'_wbam_is_responsive' => '0',
				'_wbam_ad_format'     => 'leaderboard',
			)
		);
		$fluid  = $this->render_sized_ad( array( '_wbam_is_responsive' => '1' ) );

		$this->assertStringContainsString( 'style="--wbam-ad-ar:970 / 250;--wbam-ad-w:970px;--wbam-ad-h:250px"', $custom );
		$this->assertStringContainsString( 'wbam-ad-slot--sized', $custom );
		$this->assertStringContainsString( '--wbam-ad-ar:728 / 90;', $named, 'A named format uses its own pixel size.' );
		$this->assertStringNotContainsString( '--wbam-ad-ar', $fluid, 'Responsive ads keep the default reserve.' );
	}

	/** Between-content sized creatives reserve by aspect ratio, capped at their own width, over the px floors. */
	public function test_between_content_reserves_by_aspect_ratio(): void {
		$css   = $this->css();
		$start = strpos( $css, '.wbam-placement-paragraph .wbam-ad-slot--sized.wbam-ad-slot > .wbam-ad-image' );
		$this->assertNotFalse( $start, 'Needs the extra .wbam-ad-slot so it outranks the phone floor.' );
		$rule = substr( $css, $start, (int) strpos( $css, '}', $start ) - $start );

		$this->assertStringContainsString( 'aspect-ratio: var(--wbam-ad-ar);', $rule );
		$this->assertStringContainsString( 'max-width: var(--wbam-ad-w);', $rule );
		$this->assertStringContainsString( 'min-height: auto;', $rule, 'Content taller than the ratio still grows, never clips.' );
	}

	/**
	 * Every CSS rule (selector => declarations) whose selector names $class.
	 *
	 * @return array<string,string>
	 */
	private function rules_for( string $class ): array {
		preg_match_all( '/([^{}]+)\{([^{}]*)\}/', (string) preg_replace( '#/\*.*?\*/#s', '', $this->css() ), $m, PREG_SET_ORDER );
		$out = array();
		foreach ( $m as $rule ) {
			if ( preg_match( '/' . preg_quote( $class, '/' ) . '(?![\w-])/', $rule[1] ) ) {
				$out[ trim( $rule[1] ) ] = $rule[2];
			}
		}
		return $out;
	}

	/** Owner/QA: code and AdSense creatives are never clipped or ratio-locked; image still is. */
	public function test_code_and_adsense_grow_while_image_keeps_the_ratio(): void {
		foreach ( array( '.wbam-ad-code', '.wbam-ad-adsense' ) as $class ) {
			$rules = $this->rules_for( $class );
			$this->assertNotEmpty( $rules, "{$class} needs its declared-height reserve." );
			$all = implode( "\n", $rules );
			$this->assertStringNotContainsString( 'aspect-ratio', $all, "{$class} must not be ratio-locked." );
			$this->assertStringNotContainsString( 'max-height', $all, "{$class} must not be height-capped." );
			$this->assertDoesNotMatchRegularExpression( '/overflow(-y)?\s*:\s*(hidden|clip|auto|scroll)/', $all, "{$class} must never clip vertically." );
			$this->assertStringContainsString( 'min-height: var(--wbam-ad-h);', $all );
		}

		$this->assertStringContainsString( 'aspect-ratio: var(--wbam-ad-ar);', implode( "\n", $this->rules_for( '.wbam-ad-image' ) ) );
	}
}
