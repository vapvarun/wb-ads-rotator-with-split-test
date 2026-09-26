<?php
/**
 * With the free plugin inactive or too old, Pro never boots its modules, so
 * its shortcodes would print as raw text ("[wbam_advertise]" on the
 * Advertise page, card 10342783654). Every tag Pro registers must be in the
 * fallback list that quietly covers that case.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Pro_Shortcode_Fallbacks extends Pro_Test_Case {

	public function test_every_pro_shortcode_has_a_fallback(): void {
		global $shortcode_tags;

		$pro_tags = array();
		foreach ( $shortcode_tags as $tag => $callback ) {
			$owner = is_array( $callback ) ? $callback[0] : $callback;
			$class = is_object( $owner ) ? get_class( $owner ) : ( is_string( $owner ) ? $owner : '' );
			if ( 0 === strpos( $class, 'WBAM_Pro\\' ) ) {
				$pro_tags[] = $tag;
			}
		}

		$this->assertContains( 'wbam_advertise', $pro_tags );
		$this->assertSame( array(), array_values( array_diff( $pro_tags, wbam_pro_shortcode_tags() ) ) );
	}
}
