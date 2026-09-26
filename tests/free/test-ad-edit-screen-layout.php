<?php
/**
 * Card 10339876480, step 3: Publish box below a long status box, all
 * metaboxes open by default, and the field-tooltip "?" icon sitting on its
 * own line between a label and its input.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Admin;
use WBAM\Admin\First_Install_Pointers;

class Test_Ad_Edit_Screen_Layout extends \WP_UnitTestCase {

	private function ad(): int {
		$ad_id = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_priority', 5 );

		return $ad_id;
	}

	/**
	 * The Ad Status side metabox must not outrank core's Publish box
	 * ('core' priority) — 'high' pushed Update below the fold.
	 */
	public function test_ad_status_metabox_does_not_outrank_publish_box(): void {
		global $post, $wp_meta_boxes;
		$wp_meta_boxes = array();
		$post          = get_post( $this->ad() );

		Admin::get_instance()->add_metaboxes();

		$this->assertArrayHasKey( 'wbam-ad-status', $wp_meta_boxes['wbam-ad']['side']['default'], 'Ad Status is registered at default priority, after core\'s "core" priority Publish box.' );
		$this->assertArrayNotHasKey( 'wbam-ad-status', $wp_meta_boxes['wbam-ad']['side']['high'] ?? array(), 'Ad Status no longer claims "high" priority.' );
	}

	/**
	 * The tooltip icon must sit inside the label, on the same line as the
	 * label text, not as a block-level sibling that wraps to its own line.
	 */
	public function test_tooltip_icon_sits_inside_the_label(): void {
		ob_start();
		Admin::get_instance()->render_status_metabox( get_post( $this->ad() ) );
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression(
			'/<label for="wbam_priority">Priority<span class="wbam-tip"/',
			$html,
			'The Priority tooltip renders inside the label, on the same line as the text.'
		);
		$this->assertMatchesRegularExpression(
			'/<label for="wbam_session_limit">Max views per visitor per day<span class="wbam-tip"/',
			$html,
			'The Session Limit tooltip renders inside the label, on the same line as the text.'
		);
	}

	/**
	 * On a fresh install (pointers flag on), the heaviest metaboxes start
	 * collapsed. On an upgrade (flag off), nothing changes.
	 */
	public function test_heavy_metaboxes_default_closed_only_on_fresh_install(): void {
		update_option( First_Install_Pointers::OPTION_ENABLED, 1 );
		global $post, $wp_meta_boxes;
		$wp_meta_boxes = array();
		$post          = get_post( $this->ad() );

		Admin::get_instance()->add_metaboxes();

		$closed = apply_filters( 'get_user_option_closedpostboxes_wbam-ad', false );
		$this->assertSame( array( 'wbam-ad-preview', 'wbam-ad-comparison' ), $closed );

		// A user who already made their own choice (a real array, even
		// empty) is left alone — the filter only fills in the "never
		// touched it" (false) case.
		$this->assertSame( array(), apply_filters( 'get_user_option_closedpostboxes_wbam-ad', array() ) );

		update_option( First_Install_Pointers::OPTION_ENABLED, 0 );
		remove_all_filters( 'get_user_option_closedpostboxes_wbam-ad' );
		$wp_meta_boxes = array();
		Admin::get_instance()->add_metaboxes();
		$this->assertFalse( apply_filters( 'get_user_option_closedpostboxes_wbam-ad', false ), 'Upgrades keep every metabox exactly as WordPress\'s own default leaves it.' );
	}
}
