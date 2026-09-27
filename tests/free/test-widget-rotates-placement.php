<?php
/**
 * The WB Ad Manager widget (card 10344381767, owner-seat audit): placing
 * it unlocks 'Widget' for advertisers, so with no ad chosen it must rotate
 * the Widget placement, paid ads included, not show nothing.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Placements\Placement_Engine;
use WBAM\Modules\Placements\WBAM_Ad_Widget;
use WP_UnitTestCase;

class Test_Widget_Rotates_Placement extends WP_UnitTestCase {

	private function widget_html( array $instance ): string {
		ob_start();
		( new WBAM_Ad_Widget() )->widget(
			array(
				'before_widget' => '<section>',
				'after_widget'  => '</section>',
				'before_title'  => '<h2>',
				'after_title'   => '</h2>',
			),
			$instance
		);
		return (string) ob_get_clean();
	}

	private function widget_ad( string $html ): int {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_placements', array( 'widget' ) );
		update_post_meta( $ad_id, '_wbam_is_responsive', '1' );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'rich', 'content' => $html ) );
		Placement_Engine::get_instance()->clear_placement_cache( $ad_id );
		return $ad_id;
	}

	public function test_no_ad_chosen_rotates_the_widget_placement(): void {
		$this->widget_ad( '<p>Assigned to Widget</p>' );

		$html = $this->widget_html( array( 'title' => '', 'ad_id' => 0 ) );

		$this->assertStringContainsString( 'Assigned to Widget', $html );
		$this->assertSame( 1, substr_count( $html, 'wbam-placement-widget' ), 'One wrapper, not two.' );
	}

	public function test_a_chosen_ad_is_shown_on_its_own(): void {
		$this->widget_ad( '<p>Rotating one</p>' );
		$pinned = $this->widget_ad( '<p>Pinned one</p>' );
		update_post_meta( $pinned, '_wbam_placements', array() );

		$html = $this->widget_html( array( 'title' => '', 'ad_id' => $pinned ) );

		$this->assertStringContainsString( 'Pinned one', $html );
		$this->assertStringNotContainsString( 'Rotating one', $html );
	}
}
