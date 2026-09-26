<?php
/**
 * Placement sizes by shape (owner decision 13, card 10343726460).
 *
 * Banner/Box/Tower/Any and the placement -> shape assignment. Covers the
 * exact bug named on the card: a 970x250 "Billboard" ad warned "No
 * placements match this size yet" in the header because the taxonomy had
 * no 970x250 entry, and the header's accepted-formats list only carried 3
 * of the 6 Banner sizes.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Core\Ad_Formats;
use WBAM\Core\Placement_Format_Map;

class Test_Placement_Shapes extends \WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'wbam_placement_shapes' );
		remove_all_filters( 'wbam_placement_format_map' );
		Placement_Format_Map::reset_cache();
		parent::tear_down();
	}

	private function ad_with_format( string $format, int $width = 0, int $height = 0 ): int {
		$ad_id = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_ad_format', $format );
		update_post_meta( $ad_id, '_wbam_ad_width', $width );
		update_post_meta( $ad_id, '_wbam_ad_height', $height );
		update_post_meta( $ad_id, '_wbam_is_responsive', Ad_Formats::RESPONSIVE === $format ? '1' : '0' );

		return $ad_id;
	}

	/**
	 * Every one of the six Banner sizes must fit the header — the original
	 * bug was the header only recognizing 3 of them (728x90, 970x90, 468x60)
	 * and had no taxonomy entry at all for 970x250.
	 *
	 * @dataProvider banner_sizes_provider
	 */
	public function test_every_banner_size_fits_header( string $format ): void {
		$ad_id = $this->ad_with_format( $format, 1, 1 );

		$this->assertTrue(
			Ad_Formats::fits( $ad_id, 'header' ),
			"Banner size '{$format}' should fit the header placement."
		);
	}

	public function banner_sizes_provider(): array {
		return array(
			'728x90 leaderboard'        => array( 'leaderboard' ),
			'970x90 large-leaderboard'  => array( 'large-leaderboard' ),
			'970x250 billboard'         => array( 'billboard' ),
			'468x60 banner'             => array( 'banner' ),
			'320x50 mobile-banner'      => array( 'mobile-banner' ),
			'320x100 mobile-large-banner' => array( 'mobile-large-banner' ),
		);
	}

	public function test_box_sizes_fit_widget_not_header(): void {
		$rect_id = $this->ad_with_format( 'medium-rectangle', 300, 250 );
		$this->assertTrue( Ad_Formats::fits( $rect_id, 'widget' ), 'A 300x250 Box ad fits the sidebar.' );
		$this->assertFalse( Ad_Formats::fits( $rect_id, 'header' ), 'A 300x250 Box ad does not fit the header Banner shape.' );
	}

	public function test_tower_sizes_fit_widget_not_header(): void {
		$tower_id = $this->ad_with_format( 'skyscraper', 160, 600 );
		$this->assertTrue( Ad_Formats::fits( $tower_id, 'widget' ), 'A 160x600 Tower ad fits the sidebar.' );
		$this->assertFalse( Ad_Formats::fits( $tower_id, 'header' ), 'A 160x600 Tower ad does not fit the header Banner shape.' );
	}

	/**
	 * "Any" shape: a responsive ad (text/rich/code default to responsive)
	 * fits every placement, including the header and the sidebar.
	 */
	public function test_responsive_any_shape_fits_every_placement(): void {
		$ad_id = $this->ad_with_format( Ad_Formats::RESPONSIVE );

		foreach ( array( 'header', 'footer', 'widget', 'after_paragraph', 'shortcode' ) as $placement ) {
			$this->assertTrue( Ad_Formats::fits( $ad_id, $placement ), "Responsive ad should fit '{$placement}'." );
		}
	}

	public function test_a_box_ad_does_not_fit_the_footer(): void {
		$rect_id = $this->ad_with_format( 'medium-rectangle', 300, 250 );
		$this->assertFalse( Ad_Formats::fits( $rect_id, 'footer' ), 'The footer is a Banner-shaped placement.' );
	}

	/**
	 * wbam_placement_shapes lets a site redefine (or narrow) a shape's
	 * size list, and every placement composed from that shape picks up
	 * the change (single source of truth: shapes() feeds map()).
	 */
	public function test_wbam_placement_shapes_filter_overrides_shape_sizes(): void {
		add_filter(
			'wbam_placement_shapes',
			function ( $shapes ) {
				$shapes['banner'] = array( 'leaderboard' ); // Only 728x90 now.
				return $shapes;
			}
		);

		$billboard_id = $this->ad_with_format( 'billboard', 970, 250 );
		$this->assertFalse(
			Ad_Formats::fits( $billboard_id, 'header' ),
			'A site narrowing the Banner shape via the filter should stop accepting sizes it removed.'
		);

		$leaderboard_id = $this->ad_with_format( 'leaderboard', 728, 90 );
		$this->assertTrue(
			Ad_Formats::fits( $leaderboard_id, 'header' ),
			'The size the filter kept should still fit.'
		);
	}

	/**
	 * wbam_placement_format_map (existing filter) still wins outright —
	 * shapes() only feeds the built-in map's composition; a site hooking
	 * the map directly can still hand-pick a placement's accepted list.
	 */
	public function test_wbam_placement_format_map_filter_still_overrides_directly(): void {
		add_filter(
			'wbam_placement_format_map',
			function ( $map ) {
				$map['header'] = array( 'medium-rectangle' ); // Header now Box-only, hypothetically.
				return $map;
			}
		);

		$rect_id = $this->ad_with_format( 'medium-rectangle', 300, 250 );
		$this->assertTrue(
			Ad_Formats::fits( $rect_id, 'header' ),
			'The wbam_placement_format_map filter overrides the shape-derived default entirely.'
		);
	}

	public function test_get_mismatched_ads_lists_ads_that_do_not_fit_their_placements(): void {
		$fits_id   = $this->ad_with_format( 'leaderboard', 728, 90 );
		update_post_meta( $fits_id, '_wbam_placements', array( 'header' ) );

		$mismatch_id = (int) self::factory()->post->create(
			array( 'post_type' => 'wbam-ad', 'post_status' => 'publish', 'post_title' => 'Square in the header' )
		);
		update_post_meta( $mismatch_id, '_wbam_ad_format', 'medium-rectangle' );
		update_post_meta( $mismatch_id, '_wbam_ad_width', 300 );
		update_post_meta( $mismatch_id, '_wbam_ad_height', 250 );
		update_post_meta( $mismatch_id, '_wbam_is_responsive', '0' );
		update_post_meta( $mismatch_id, '_wbam_placements', array( 'header' ) );

		$result = Placement_Format_Map::get_mismatched_ads( 10 );

		$this->assertGreaterThanOrEqual( 1, $result['count'] );
		$this->assertContains( 'Square in the header', $result['titles'] );
	}
}
