<?php
/**
 * Per-placement options can be set, are saved, and reach the visitor.
 *
 * Card 10342823390: every placement class had render_options() and
 * save_options() but nothing called them, so the popup always opened after
 * 5s, the sticky ad was always bottom-right, and before/after-content had
 * no position. Owner decision 9 sets restrained popup defaults.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Admin;
use WBAM\Modules\Placements\Placement_Engine;

class Test_Placement_Options extends \WP_UnitTestCase {

	private function ad( array $placements, array $data = array() ): int {
		$ad_id = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_placements', $placements );
		update_post_meta( $ad_id, '_wbam_ad_data', array_merge( array( 'type' => 'rich-content', 'content' => 'Option probe' ), $data ) );
		Placement_Engine::get_instance()->clear_placement_cache( $ad_id );

		return $ad_id;
	}

	public function test_options_are_shown_saved_and_used(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$admin = Admin::get_instance();
		$ad_id = $this->ad( array( 'popup' ) );

		ob_start();
		$admin->render_placements_metabox( get_post( $ad_id ) );
		$metabox = ob_get_clean();

		$this->assertMatchesRegularExpression( '/data-placement="popup"(?! hidden)[^>]*>/', $metabox, 'A ticked placement shows its options.' );
		$this->assertStringContainsString( 'name="wbam_data[popup_trigger]"', $metabox );
		$this->assertMatchesRegularExpression( '/data-placement="sticky" hidden/', $metabox, 'An unticked placement keeps its options hidden.' );

		$original = $_POST;
		$_POST    = array(
			'wbam_nonce'      => wp_create_nonce( 'wbam_save_ad' ),
			'wbam_placements' => array( 'popup' ),
			'wbam_data'       => array(
				'type'              => 'rich-content',
				'content'           => 'Option probe',
				'popup_trigger'     => 'scroll',
				'popup_scroll'      => '30',
				'popup_repeat_days' => '3',
				'sticky_position'   => 'bottom-bar',
			),
		);
		$admin->save_meta( $ad_id, get_post( $ad_id ) );
		$_POST = $original;

		$saved = get_post_meta( $ad_id, '_wbam_ad_data', true );
		$this->assertSame( 'scroll', $saved['popup_trigger'] );
		$this->assertSame( 30, $saved['popup_scroll'] );
		$this->assertSame( 3, $saved['popup_repeat_days'] );
		$this->assertSame( 'bottom-bar', $saved['sticky_position'] );

		add_filter( 'wbam_enforce_page_cap', '__return_false' );
		ob_start();
		Placement_Engine::get_instance()->get_placement( 'popup' )->render_popup_ads();
		$popup = ob_get_clean();

		$this->assertStringContainsString( 'data-trigger="scroll"', $popup );
		$this->assertStringContainsString( 'data-repeat-days="3"', $popup );
		$this->assertStringContainsString( 'role="dialog" aria-modal="true"', $popup, 'The popup is announced as a modal dialog.' );
	}

	public function test_popup_defaults_are_restrained(): void {
		$defaults = Placement_Engine::get_instance()->get_placement( 'popup' )->save_options( 0, array() );

		$this->assertSame( 'delay', $defaults['popup_trigger'] );
		$this->assertSame( 1, $defaults['popup_repeat_days'], 'Once per visitor per day.' );
		$this->assertFalse( $defaults['popup_mobile_first_view'], 'Never on a phone\'s first page view unless the owner allows it.' );
	}

	public function test_content_ad_renders_once_at_its_position(): void {
		$this->ad( array( 'content' ), array( 'content_position' => 'after' ) );

		$this->go_to( get_permalink( self::factory()->post->create() ) );
		add_filter( 'wbam_skip_content_injection', '__return_false' );
		$GLOBALS['wp_query']->in_the_loop = true;

		$html = Placement_Engine::get_instance()->get_placement( 'content' )->filter_content( '<p>Body</p>' );

		$this->assertStringContainsString( '<p>Body</p><div class="wbam-placement wbam-placement-after-content">', $html );
		$this->assertStringNotContainsString( 'wbam-placement-before-content', $html, 'No empty wrapper on the other side.' );
	}
}
