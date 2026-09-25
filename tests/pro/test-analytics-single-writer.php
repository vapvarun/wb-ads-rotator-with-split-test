<?php
/**
 * With Pro active, every ad event is written to wbam_analytics once.
 *
 * Regression guard for Basecamp card 10340183508: FREE's click handler
 * (wbam_track_click -> wbam_ad_clicked) and the render-time impression
 * hook already reach Pro's tracker, and Pro's tracking.js reported the
 * same impression and click again through wbam_track_event - two rows per
 * event. Pixel tracking did the same for impressions. Ownership is in
 * plan/free-pro-architecture-contract.md (Analytics event ownership).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Modules\Placements\Placement_Engine;
use WBAM_Pro\Core\Pro_Plugin;
use WBAM_Pro\Core\Settings_Helper;

class Test_Analytics_Single_Writer extends Pro_Test_Case {

	private int $ad_id;

	public function set_up(): void {
		parent::set_up();

		Settings_Helper::update( 'enable_analytics', true );
		Settings_Helper::update( 'enable_bot_filtering', false );
		Settings_Helper::update( 'gdpr_require_consent', false );
		Settings_Helper::update( 'enable_pixel_tracking', false );

		$this->ad_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $this->ad_id, '_wbam_enabled', '1' );
		update_post_meta(
			$this->ad_id,
			'_wbam_ad_data',
			array(
				'type'    => 'rich-content',
				'content' => 'Single writer body',
			)
		);
	}

	private function rows( string $event ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test assertion on a known table.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wbam_analytics WHERE ad_id = %d AND event_type = %s",
				$this->ad_id,
				$event
			)
		);
	}

	private function render(): string {
		return Placement_Engine::get_instance()->render_ad(
			$this->ad_id,
			array(
				'placement'       => 'header',
				'allow_duplicate' => true,
				'skip_targeting'  => true,
			)
		);
	}

	public function test_no_second_client_side_writer(): void {
		// tracking.js re-reported every impression and click FREE already
		// sends; without this endpoint nothing can write that second row.
		$this->assertFalse( has_action( 'wp_ajax_nopriv_wbam_track_event' ) );
		$this->assertFalse( has_action( 'wp_ajax_wbam_track_event' ) );
	}

	/**
	 * MediaShield's wbam_claim_video_impression needs the 'wbam_track' nonce
	 * from window.wbamTracking: kept, data only, when video ads are available.
	 */
	public function test_video_claim_nonce_is_exposed_only_with_video_available(): void {
		$tracker = Pro_Plugin::get_instance()->get_module( 'analytics' );

		add_filter( 'wbam_pro_video_ads_available', '__return_false' );
		$tracker->enqueue_video_claim_data();
		remove_filter( 'wbam_pro_video_ads_available', '__return_false' );
		$this->assertFalse( wp_script_is( 'wbam-pro-tracking-data', 'enqueued' ) );

		add_filter( 'wbam_pro_video_ads_available', '__return_true' );
		$tracker->enqueue_video_claim_data();
		remove_filter( 'wbam_pro_video_ads_available', '__return_true' );

		$this->assertTrue( wp_script_is( 'wbam-pro-tracking-data', 'enqueued' ) );
		$data = (string) wp_scripts()->get_data( 'wbam-pro-tracking-data', 'data' );
		$this->assertStringContainsString( 'var wbamTracking', $data );
		$this->assertStringContainsString( wp_create_nonce( 'wbam_track' ), $data );
		$this->assertFalse( wp_scripts()->registered['wbam-pro-tracking-data']->src, 'Data only: no event-sending script.' );

		wp_dequeue_script( 'wbam-pro-tracking-data' );
		wp_deregister_script( 'wbam-pro-tracking-data' );
	}

	public function test_render_and_click_write_one_row_each(): void {
		$this->render();
		do_action( 'wbam_ad_clicked', $this->ad_id, 'header' );

		$this->assertSame( 1, $this->rows( 'impression' ) );
		$this->assertSame( 1, $this->rows( 'click' ) );
	}

	public function test_pixel_mode_leaves_the_impression_to_the_beacon(): void {
		Settings_Helper::update( 'enable_pixel_tracking', true );

		$output = $this->render();

		$this->assertStringContainsString( 'wbam_track=1', $output, 'The beacon writes the impression in pixel mode.' );
		$this->assertStringContainsString( 'placement=header', $output );
		$this->assertSame( 0, $this->rows( 'impression' ), 'Render-time and beacon writes together counted it twice.' );
	}
}
