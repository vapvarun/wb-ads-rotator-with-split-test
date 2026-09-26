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
use WBAM\Tests\Helpers\Factory;

class Test_Placement_Options extends \WP_UnitTestCase {

	public function tear_down(): void {
		Factory::reset_page_ads();
		parent::tear_down();
	}

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
		$this->assertStringNotContainsString( 'popup_repeat_days', $metabox, 'How often the popup repeats is a filter, not a field.' );
		$this->assertStringNotContainsString( 'popup_mobile_first_view', $metabox, 'The phone first-view rule is a filter, not a field.' );
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
				'sticky_position'   => 'bottom-bar',
			),
		);
		$admin->save_meta( $ad_id, get_post( $ad_id ) );
		$_POST = $original;

		$saved = get_post_meta( $ad_id, '_wbam_ad_data', true );
		$this->assertSame( 'scroll', $saved['popup_trigger'] );
		$this->assertSame( 30, $saved['popup_scroll'] );
		$this->assertSame( 'bottom-bar', $saved['sticky_position'] );

		add_filter( 'wbam_enforce_page_cap', '__return_false' );
		ob_start();
		Placement_Engine::get_instance()->get_placement( 'popup' )->render_popup_ads();
		$popup = ob_get_clean();

		$this->assertStringContainsString( 'data-trigger="scroll"', $popup );
		$this->assertStringContainsString( 'data-repeat-days="1"', $popup, 'Once per visitor per day by default.' );
		$this->assertStringContainsString( 'data-mobile-first-view="1"', $popup, 'Phones see it on the first view by default.' );
		$this->assertStringContainsString( 'role="dialog" aria-modal="true"', $popup, 'The popup is announced as a modal dialog.' );
	}

	public function test_popup_repeat_and_phone_rule_are_filters_seeded_by_stored_values(): void {
		add_filter( 'wbam_enforce_page_cap', '__return_false' );
		$popup = Placement_Engine::get_instance()->get_placement( 'popup' );

		// An ad saved while these were fields keeps its values as the defaults.
		$this->ad( array( 'popup' ), array( 'popup_repeat_days' => 3, 'popup_mobile_first_view' => false ) );
		ob_start();
		$popup->render_popup_ads();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'data-repeat-days="3"', $html );
		$this->assertStringContainsString( 'data-mobile-first-view="0"', $html, 'The stored "not on a phone\'s first view" still holds.' );

		$days = static function () {
			return 7;
		};
		add_filter( 'wbam_popup_repeat_days', $days );
		add_filter( 'wbam_popup_skip_mobile_first_view', '__return_false' );
		ob_start();
		$popup->render_popup_ads();
		$html = ob_get_clean();
		remove_filter( 'wbam_popup_repeat_days', $days );
		remove_filter( 'wbam_popup_skip_mobile_first_view', '__return_false' );

		$this->assertStringContainsString( 'data-repeat-days="7"', $html );
		$this->assertStringContainsString( 'data-mobile-first-view="1"', $html );
	}

	public function test_content_ad_renders_once_at_its_position(): void {
		$this->ad( array( 'content' ), array( 'content_position' => 'after' ) );

		$this->go_to( get_permalink( self::factory()->post->create() ) );
		add_filter( 'wbam_skip_content_injection', '__return_false' );
		add_filter( 'wbam_enforce_page_cap', '__return_false' );
		$GLOBALS['wp_query']->in_the_loop = true;

		$html = Placement_Engine::get_instance()->get_placement( 'content' )->filter_content( '<p>Body</p>' );

		$this->assertStringContainsString( '<p>Body</p><div class="wbam-placement wbam-placement-after-content">', $html );
		$this->assertStringNotContainsString( 'wbam-placement-before-content', $html, 'No empty wrapper on the other side.' );
	}
}
