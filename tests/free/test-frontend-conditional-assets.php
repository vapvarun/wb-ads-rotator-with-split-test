<?php
/**
 * The frontend ad CSS/JS load only where an ad can actually show, never
 * unconditionally - but a live ad in a site-wide slot, a page-specific
 * placement, or any render path the page-level prediction could not see
 * (the render_ad() safety net) all still get it.
 *
 * Owner decision, card 10342761510 (2026-09-26): "be smart as ads can be
 * displayed where they are included" - load assets only where used, but
 * never miss an ad.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Frontend\Frontend;
use WBAM\Modules\Placements\Placement_Engine;
use WBAM\Tests\Helpers\Factory;
use WP_UnitTestCase;

class Test_Frontend_Conditional_Assets extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// wp_styles()/wp_scripts() are process-global, not reset by WP's
		// per-test transaction rollback. Any other suite in this run that
		// called render_ad() before us left 'wbam-frontend' enqueued via
		// the safety net - start each test from a known "not yet on" state.
		wp_dequeue_style( 'wbam-frontend' );
		wp_dequeue_script( 'wbam-frontend' );
	}

	public function tear_down(): void {
		wp_dequeue_style( 'wbam-frontend' );
		wp_deregister_style( 'wbam-frontend' );
		wp_dequeue_script( 'wbam-frontend' );
		wp_deregister_script( 'wbam-frontend' );
		Factory::reset_page_ads();
		parent::tear_down();
	}

	/**
	 * Other suites in the same run (Pro's demo/onboarding fixtures) can
	 * leave a wbam-ad post outside this test's own transaction. Force a
	 * clean slate so "no ads" genuinely means no eligible ads, rather than
	 * depending on run order.
	 */
	private function disable_every_existing_ad(): void {
		global $wpdb;
		$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wbam-ad'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- test isolation, not production code.
		foreach ( $ids as $id ) {
			update_post_meta( (int) $id, '_wbam_enabled', '0' );
		}
		Placement_Engine::get_instance()->clear_placement_cache( 0 );
	}

	private function ad_in_placement( string $placement ): int {
		$ad_id = Factory::make_ad();
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_placements', array( $placement ) );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'rich-content', 'content' => 'Probe ad' ) );
		Placement_Engine::get_instance()->clear_placement_cache( $ad_id );

		return $ad_id;
	}

	public function test_page_with_no_ads_does_not_enqueue_frontend_assets(): void {
		$this->disable_every_existing_ad();

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $page_id ) );

		Frontend::get_instance()->enqueue_assets();

		$this->assertFalse( wp_style_is( 'wbam-frontend', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'wbam-frontend', 'enqueued' ) );
	}

	public function test_singular_with_content_placement_ad_enqueues_assets(): void {
		$this->ad_in_placement( 'content' );

		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		Frontend::get_instance()->enqueue_assets();

		$this->assertTrue( wp_style_is( 'wbam-frontend', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'wbam-frontend', 'enqueued' ) );
	}

	public function test_sitewide_header_ad_enqueues_assets_on_an_unrelated_page(): void {
		$this->ad_in_placement( 'header' );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $page_id ) );

		Frontend::get_instance()->enqueue_assets();

		$this->assertTrue( wp_style_is( 'wbam-frontend', 'enqueued' ), 'A site-wide slot ad must load assets everywhere.' );
	}

	public function test_render_ad_safety_net_enqueues_assets_even_when_unpredicted(): void {
		$this->disable_every_existing_ad();

		// Simulate a widget ad on a page the page-level prediction has no
		// signal for (a "widget" placement ad rendered outside the loop).
		$ad_id = Factory::make_ad();
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'rich-content', 'content' => 'Widget probe' ) );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $page_id ) );

		// Register (but do not predictively enqueue) the handles, exactly as
		// the real wp_enqueue_scripts hook does for this unrelated page.
		Frontend::get_instance()->enqueue_assets();
		$this->assertFalse( wp_style_is( 'wbam-frontend', 'enqueued' ), 'Sanity: nothing predicted an ad on this page.' );

		$output = Placement_Engine::get_instance()->render_ad( $ad_id, array( 'placement' => 'widget', 'skip_targeting' => true ) );

		$this->assertNotSame( '', $output );
		$this->assertTrue( wp_style_is( 'wbam-frontend', 'enqueued' ), 'render_ad() must turn the CSS on as a safety net.' );
		$this->assertTrue( wp_script_is( 'wbam-frontend', 'enqueued' ) );
	}
}
