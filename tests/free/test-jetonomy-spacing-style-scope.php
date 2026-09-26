<?php
/**
 * The Jetonomy spacing <style> tag prints only when a Jetonomy ad
 * actually renders, not unconditionally on every page via wp_head - a
 * raw <style> tag applies wherever in the document it lands, so there is
 * no "too late for the head" concern the way there is for
 * wp_enqueue_style(). Owner decision, card 10342761510.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Jetonomy\Jetonomy_Module;
use WBAM\Modules\Jetonomy\Jetonomy_Placement;
use WBAM\Modules\Placements\Placement_Engine;
use WBAM\Tests\Helpers\Factory;
use WP_UnitTestCase;

class Test_Jetonomy_Spacing_Style_Scope extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		if ( ! defined( 'JETONOMY_VERSION' ) ) {
			define( 'JETONOMY_VERSION', '1.3.0' );
		}
	}

	public function tear_down(): void {
		Factory::reset_page_ads();
		$printed = new \ReflectionProperty( Jetonomy_Module::class, 'spacing_styles_printed' );
		$printed->setAccessible( true );
		$printed->setValue( null, false );
		parent::tear_down();
	}

	public function test_wp_head_no_longer_prints_the_style_unconditionally(): void {
		$this->assertFalse( has_action( 'wp_head', array( Jetonomy_Module::class, 'print_spacing_styles' ) ) );
	}

	public function test_style_prints_only_when_a_jetonomy_ad_renders(): void {
		$ad_id = Factory::make_ad();
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_placements', array( 'jetonomy_after_post_article' ) );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'rich-content', 'content' => 'Jetonomy probe' ) );
		Placement_Engine::get_instance()->clear_placement_cache( $ad_id );

		$placement = new Jetonomy_Placement(
			array(
				'id'   => 'jetonomy_after_post_article',
				'name' => 'After Topic Body',
				'hook' => 'jetonomy_after_post_article',
				'args' => 1,
			)
		);

		ob_start();
		$placement->render( 1 );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<style id="wbam-jetonomy-spacing">', $output, 'The style must print the moment an ad actually renders.' );
		$this->assertSame( 1, substr_count( $output, 'id="wbam-jetonomy-spacing"' ), 'Only one copy per request even if the same placement fires more than once.' );
	}

	public function test_style_does_not_print_when_no_ad_is_assigned(): void {
		$placement = new Jetonomy_Placement(
			array(
				'id'   => 'jetonomy_sidebar_before',
				'name' => 'Sidebar Top',
				'hook' => 'jetonomy_sidebar_before',
				'args' => 1,
			)
		);

		ob_start();
		$placement->render( 1 );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}
}
