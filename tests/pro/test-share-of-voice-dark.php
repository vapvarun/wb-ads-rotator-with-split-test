<?php
/**
 * Share of Voice status colours are redeclared in the dark block.
 *
 * The dark block used to swap only the tint backgrounds and keep the light
 * text colours (#1565c0, #2e7d32, ...), which read at about 1.5-2:1 on a
 * dark surface. Card 10343301318.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Share_Of_Voice_Dark extends Pro_Test_Case {

	public function test_dark_block_redeclares_every_status_text_colour(): void {
		$css = file_get_contents( WBAM_PRO_PATH . 'assets/css/share-of-voice.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$start = strpos( $css, 'html[data-bx-mode="dark"]' );
		$this->assertNotFalse( $start );
		$dark = substr( $css, $start, strpos( $css, '}', $start ) - $start );

		foreach ( array( 'info', 'impressions', 'share', 'placements', 'fairness' ) as $status ) {
			$this->assertStringContainsString( "--sov-{$status}-text:", $dark, "Dark mode must set --sov-{$status}-text." );
		}
	}
}
