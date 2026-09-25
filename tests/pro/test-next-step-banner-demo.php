<?php
/**
 * Next_Step_Banner's demo-data go-live step (10217449688 bullet 4): a
 * "This site still has demo data (N items)" step, counting everything in
 * wbam_pro_demo_data_ids except tracked pages, right after pending
 * advertisers and before every other step.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Next_Step_Banner;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Next_Step_Banner_Demo extends Pro_Test_Case {

	/**
	 * Other suites in this shared test DB create advertisers (pending by
	 * default) via dbDelta-created tables that survive the per-test
	 * rollback - same gotcha documented on Pro_Test_Case::truncate_credits_ledger().
	 * Isolate the priority-ordering assertions from that leftover state.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wpdb;
		$table = $wpdb->prefix . 'wbam_advertisers';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation on a known table name.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	public function tear_down(): void {
		delete_option( 'wbam_pro_demo_data_ids' );
		parent::tear_down();
	}

	public function test_demo_data_step_counts_only_non_page_ids(): void {
		update_option(
			'wbam_pro_demo_data_ids',
			array(
				'pages'       => array( 1, 2 ),
				'ads'         => array( 3, 4, 5 ),
				'classifieds' => array( 6 ),
				'advertisers' => array(),
			)
		);

		$state = Next_Step_Banner::collect_state();
		$this->assertSame( 4, $state['demo_data_count'] );

		$step = Next_Step_Banner::resolve_next_step();
		$this->assertNotNull( $step );
		$this->assertSame( 'demo-data-4', $step['slug'] );
		$this->assertStringContainsString( '4 items', $step['title'] );
		$this->assertSame(
			admin_url( 'edit.php?post_type=wbam-ad&page=wbam-tools' ),
			$step['cta_url']
		);
	}

	public function test_demo_data_step_is_absent_when_only_pages_are_tracked(): void {
		update_option(
			'wbam_pro_demo_data_ids',
			array(
				'pages' => array( 1, 2 ),
			)
		);

		$state = Next_Step_Banner::collect_state();
		$this->assertSame( 0, $state['demo_data_count'] );

		$step = Next_Step_Banner::resolve_next_step();
		if ( null !== $step ) {
			$this->assertStringStartsNotWith( 'demo-data-', $step['slug'] );
		} else {
			$this->assertNull( $step );
		}
	}

	/**
	 * Pending advertisers are a bigger blocker than leftover demo rows -
	 * they come first in the priority order.
	 */
	public function test_pending_advertisers_take_priority_over_demo_data(): void {
		update_option(
			'wbam_pro_demo_data_ids',
			array( 'ads' => array( 10 ) )
		);

		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		Advertiser_Manager::get_instance()->get_or_create( (int) $user );

		$step = Next_Step_Banner::resolve_next_step();
		$this->assertNotNull( $step );
		$this->assertStringStartsWith( 'review-applications-', $step['slug'] );
	}

	/**
	 * A dismissed banner must come back once more demo data shows up -
	 * the count is baked into the slug.
	 */
	public function test_step_slug_changes_when_demo_data_count_grows(): void {
		update_option( 'wbam_pro_demo_data_ids', array( 'ads' => array( 1 ) ) );
		$first = Next_Step_Banner::resolve_next_step();
		$this->assertSame( 'demo-data-1', $first['slug'] );

		update_option( 'wbam_pro_demo_data_ids', array( 'ads' => array( 1, 2 ) ) );
		$second = Next_Step_Banner::resolve_next_step();
		$this->assertSame( 'demo-data-2', $second['slug'] );
	}
}
