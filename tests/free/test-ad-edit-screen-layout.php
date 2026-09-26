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

	public function tear_down(): void {
		remove_all_actions( 'wbam_ad_metabox_options' );
		parent::tear_down();
	}

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

	/**
	 * Card 10343765758, E5: the old single 1,031px "Ad Status" box is three
	 * normal side-column metaboxes now, registered in order so a fresh
	 * install sees Ad Status, then Sizing, then Pro Options (when present).
	 */
	public function test_three_metaboxes_registered_in_side_column_in_order(): void {
		global $post, $wp_meta_boxes;
		add_action( 'wbam_ad_metabox_options', '__return_null' );
		$wp_meta_boxes = array();
		$post          = get_post( $this->ad() );

		Admin::get_instance()->add_metaboxes();

		$side = $wp_meta_boxes['wbam-ad']['side']['default'];
		$this->assertArrayHasKey( 'wbam-ad-status', $side );
		$this->assertArrayHasKey( 'wbam-ad-sizing', $side );
		$this->assertArrayHasKey( 'wbam-ad-pro-options', $side );

		$order      = array_keys( $side );
		$status_at  = array_search( 'wbam-ad-status', $order, true );
		$sizing_at  = array_search( 'wbam-ad-sizing', $order, true );
		$pro_opt_at = array_search( 'wbam-ad-pro-options', $order, true );
		$this->assertLessThan( $sizing_at, $status_at, 'Ad Status registers before Sizing.' );
		$this->assertLessThan( $pro_opt_at, $sizing_at, 'Sizing registers before Pro Options.' );
	}

	/**
	 * The Pro Options box is plug-and-play: it only exists when something
	 * (Pro, or any third party) actually has content for it. No setting
	 * decides this - has_action() does.
	 *
	 * This test suite runs with Pro loaded (see the sibling skipped test in
	 * Test_First_Run_Wizard_Handoff), so Pro's own listener is temporarily
	 * cleared to prove the Free-only case; WP_UnitTestCase snapshots and
	 * restores $wp_filter around every test, so nothing else in the run
	 * sees this removal.
	 */
	public function test_pro_options_metabox_absent_without_a_listener(): void {
		global $post, $wp_meta_boxes;
		remove_all_actions( 'wbam_ad_metabox_options' );
		$wp_meta_boxes = array();
		$post          = get_post( $this->ad() );

		Admin::get_instance()->add_metaboxes();

		$this->assertArrayNotHasKey(
			'wbam-ad-pro-options',
			$wp_meta_boxes['wbam-ad']['side']['default'],
			'No box registers when nothing hooks wbam_ad_metabox_options.'
		);
	}

	/**
	 * Each split box renders only its own fields - Sizing no longer lives
	 * inside Ad Status, and vice versa.
	 */
	public function test_status_metabox_renders_only_status_fields(): void {
		ob_start();
		Admin::get_instance()->render_status_metabox( get_post( $this->ad() ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="wbam_enabled"', $html );
		$this->assertStringContainsString( 'name="wbam_priority"', $html );
		$this->assertStringContainsString( 'name="wbam_session_limit"', $html );
		$this->assertStringNotContainsString( 'wbam-sizing-section', $html, 'Sizing moved to its own metabox.' );
	}

	public function test_sizing_metabox_renders_only_sizing_fields(): void {
		ob_start();
		Admin::get_instance()->render_sizing_metabox( get_post( $this->ad() ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="wbam_sizing_mode"', $html );
		$this->assertStringContainsString( 'name="wbam_is_responsive"', $html );
		$this->assertStringContainsString( 'name="wbam_ad_format"', $html );
		$this->assertStringNotContainsString( 'name="wbam_priority"', $html, 'Status fields stayed in the Ad Status box.' );
	}

	public function test_pro_options_metabox_renders_whatever_is_hooked(): void {
		add_action(
			'wbam_ad_metabox_options',
			static function ( $post ) {
				echo '<p class="probe">Hooked content for ' . (int) $post->ID . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- test probe.
			}
		);

		ob_start();
		Admin::get_instance()->render_pro_options_metabox( get_post( $this->ad() ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'class="probe"', $html, 'Whatever is hooked to wbam_ad_metabox_options still renders inside the new box.' );
	}

	/**
	 * The split changed which box wraps each field, not save_meta() - one
	 * form submission still round-trips every Ad Status + Sizing value.
	 */
	public function test_save_round_trip_keeps_every_status_and_sizing_value(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$admin = Admin::get_instance();
		$ad_id = $this->ad();

		$original = $_POST;
		$_POST    = array(
			'wbam_nonce'         => wp_create_nonce( 'wbam_save_ad' ),
			'wbam_enabled'       => '0',
			'wbam_priority'      => '8',
			'wbam_session_limit' => '3',
			'wbam_sizing_mode'   => 'fixed',
			'wbam_ad_format'     => 'custom',
			'wbam_ad_width'      => '300',
			'wbam_ad_height'     => '199',
		);
		$admin->save_meta( $ad_id, get_post( $ad_id ) );
		$_POST = $original;

		$this->assertSame( '0', get_post_meta( $ad_id, '_wbam_enabled', true ) );
		$this->assertSame( 8, (int) get_post_meta( $ad_id, '_wbam_priority', true ) );
		$this->assertSame( 3, (int) get_post_meta( $ad_id, '_wbam_session_limit', true ) );
		$this->assertSame( '0', get_post_meta( $ad_id, '_wbam_is_responsive', true ) );
		$this->assertSame( 300, (int) get_post_meta( $ad_id, '_wbam_ad_width', true ) );
		$this->assertSame( 199, (int) get_post_meta( $ad_id, '_wbam_ad_height', true ) );
	}
}
