<?php
/**
 * A/B winner promotion follows the tests that exist, shows on the ad's
 * edit screen, and clicks count without the selection cookie.
 *
 * Regression guard for Basecamp card 10340183557:
 * - deleting a completed test left `_wbam_ab_winner` on the original ad,
 *   so the winner kept swapping in with no UI left to see or undo it;
 * - the original ad's edit screen never said the winner serves its slot;
 * - track_click() read the `wbam_ab_` cookie, which select_variant() only
 *   sets while headers are unsent, so on an unbuffered host no test click
 *   ever counted.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Plugin;
use WBAM_Pro\Modules\ABTesting\AB_Test;
use WBAM_Pro\Modules\ABTesting\AB_Test_Engine;
use WBAM_Pro\Modules\ABTesting\AB_Test_Manager;

class Test_AB_Winner_Lifecycle extends Pro_Test_Case {

	private function ad( string $title ): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
	}

	private function test_for( int $original, int $variant, string $status = 'draft', int $split = 50 ): AB_Test {
		$test = new AB_Test(
			array(
				'name'           => 'Winner guard',
				'status'         => $status,
				'original_ad_id' => $original,
				'variant_ad_ids' => array( $variant ),
				'traffic_split'  => $split,
			)
		);
		$test->save();

		return $test;
	}

	public function test_deleting_the_completed_test_stops_the_winner_swap(): void {
		$original = $this->ad( 'Original' );
		$variant  = $this->ad( 'Variant' );
		$test     = $this->test_for( $original, $variant );

		AB_Test_Manager::get_instance()->complete_test( $test->id, $variant );
		$this->assertSame( $variant, (int) get_post_meta( $original, '_wbam_ab_winner', true ) );

		AB_Test_Manager::get_instance()->get_test( $test->id )->delete();

		$this->assertSame( '', get_post_meta( $original, '_wbam_ab_winner', true ), 'No test left, so nothing may keep swapping.' );
	}

	public function test_deleting_the_newer_test_falls_back_to_the_older_winner(): void {
		$manager  = AB_Test_Manager::get_instance();
		$original = $this->ad( 'Original' );
		$first    = $this->ad( 'First winner' );
		$second   = $this->ad( 'Second winner' );

		$older = $this->test_for( $original, $first );
		$manager->complete_test( $older->id, $first );
		$newer = $this->test_for( $original, $second );
		$manager->complete_test( $newer->id, $second );
		$this->assertSame( $second, (int) get_post_meta( $original, '_wbam_ab_winner', true ) );

		$manager->get_test( $newer->id )->delete();

		$this->assertSame( $first, (int) get_post_meta( $original, '_wbam_ab_winner', true ) );
	}

	public function test_deleting_a_draft_test_keeps_the_existing_winner(): void {
		$manager  = AB_Test_Manager::get_instance();
		$original = $this->ad( 'Original' );
		$winner   = $this->ad( 'Winner' );

		$manager->complete_test( $this->test_for( $original, $winner )->id, $winner );
		$draft = $this->test_for( $original, $this->ad( 'Next idea' ) );

		$manager->get_test( $draft->id )->delete();

		$this->assertSame( $winner, (int) get_post_meta( $original, '_wbam_ab_winner', true ) );
	}

	public function test_edit_screen_names_the_winner_serving_the_slot(): void {
		$original = $this->ad( 'Original' );
		$variant  = $this->ad( 'Bold headline variant' );
		AB_Test_Manager::get_instance()->complete_test( $this->test_for( $original, $variant )->id, $variant );

		ob_start();
		Pro_Plugin::get_instance()->render_pro_options( get_post( $original ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Bold headline variant', $html );
		$this->assertStringContainsString( 'now serves in this ad', $html );
	}

	public function test_click_counts_without_the_selection_cookie(): void {
		wp_set_current_user( (int) self::factory()->user->create() );

		$original = $this->ad( 'Original' );
		$variant  = $this->ad( 'Variant' );
		// All traffic to the single variant: the served creative is known.
		$test = $this->test_for( $original, $variant, 'running', 0 );

		unset( $_COOKIE[ 'wbam_ab_' . $test->id ] );
		AB_Test_Engine::get_instance()->track_click( $variant, 'header' );
		AB_Test_Engine::get_instance()->track_click( $original, 'header' );

		$stats = AB_Test_Manager::get_instance()->get_test_stats( $test->id );

		$this->assertSame( 1, $stats[ $variant ]['clicks'] ?? 0, 'The served variant\'s click must count with no cookie.' );
		$this->assertArrayNotHasKey( $original, $stats, 'A creative this visitor was not served collects no test click.' );
	}
}
