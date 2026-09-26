<?php
/**
 * Admin A/B Testing list at scale: paginated past 50 rows, real status-view
 * links (not href="#"), one batched stats query per page instead of one
 * query per row, and the demo-data "active" status normalised to "running".
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\ABTesting\AB_Test;
use WBAM_Pro\Modules\ABTesting\AB_Test_Manager;

class Test_AB_Testing_List_Scale extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'wbam-ad_page_wbam-ab-testing' );
	}

	public function tear_down(): void {
		unset( $_GET['status'], $_GET['paged'] );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	private function ad(): int {
		return (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
	}

	private function test_row( string $status = 'running' ): int {
		$test                 = new AB_Test();
		$test->name           = 'Test ' . wp_rand();
		$test->status         = $status;
		$test->original_ad_id = $this->ad();
		$test->variant_ad_ids = array( $this->ad() );
		return (int) $test->save();
	}

	public function test_get_tests_is_paginated_past_fifty(): void {
		foreach ( range( 1, 55 ) as $i ) {
			$this->test_row();
		}

		$manager = AB_Test_Manager::get_instance();
		$page_1  = $manager->get_tests( array( 'limit' => 20, 'offset' => 0 ) );
		$page_2  = $manager->get_tests( array( 'limit' => 20, 'offset' => 20 ) );

		$this->assertCount( 20, $page_1 );
		$this->assertCount( 20, $page_2 );
		$this->assertNotSame(
			wp_list_pluck( $page_1, 'id' ),
			wp_list_pluck( $page_2, 'id' ),
			'A second page of 20 must return different rows, not the same capped 50.'
		);
		$this->assertSame( 55, $manager->get_test_count() );
	}

	public function test_batched_stats_query_count_does_not_grow_with_tests(): void {
		global $wpdb;

		$ids = array();
		foreach ( range( 1, 3 ) as $i ) {
			$ids[] = $this->test_row();
		}
		$before = $wpdb->num_queries;
		$manager = AB_Test_Manager::get_instance();
		$stats   = $manager->get_stats_for_tests( $ids );
		$small   = $wpdb->num_queries - $before;

		$ids = array();
		foreach ( range( 1, 30 ) as $i ) {
			$ids[] = $this->test_row();
		}
		$before = $wpdb->num_queries;
		$stats  = $manager->get_stats_for_tests( $ids );
		$big    = $wpdb->num_queries - $before;

		$this->assertSame( 1, $small, 'A page of tests must fetch stats in one query.' );
		$this->assertSame( $small, $big, 'The stats query must not grow per test (this was 174 queries for 50 rows).' );
		$this->assertIsArray( $stats );
	}

	public function test_running_view_link_is_a_real_url_not_a_hash(): void {
		$this->test_row( 'running' );

		require_once WBAM_PRO_PATH . 'includes/Modules/ABTesting/class-ab-test-admin.php';
		$admin = \WBAM_Pro\Modules\ABTesting\AB_Test_Admin::get_instance();

		ob_start();
		( new \ReflectionMethod( $admin, 'render_list' ) )->invoke( $admin );
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression( '/href="[^"]*status=running[^"]*"[^>]*>\s*Running/', $html );
		$this->assertDoesNotMatchRegularExpression( '/href="#"[^>]*>\s*Running/', $html );
	}

	public function test_list_screen_paged_param_moves_to_a_second_page(): void {
		$ids = array();
		foreach ( range( 1, 25 ) as $i ) {
			$ids[] = $this->test_row();
		}

		require_once WBAM_PRO_PATH . 'includes/Modules/ABTesting/class-ab-test-admin.php';
		$admin = \WBAM_Pro\Modules\ABTesting\AB_Test_Admin::get_instance();
		$method = new \ReflectionMethod( $admin, 'render_list' );

		ob_start();
		$method->invoke( $admin );
		$page_1_html = ob_get_clean();

		$_GET['paged'] = 2;
		ob_start();
		$method->invoke( $admin );
		$page_2_html = ob_get_clean();
		unset( $_GET['paged'] );

		preg_match_all( '/test_id=(\d+)/', $page_1_html, $page_1_matches );
		preg_match_all( '/test_id=(\d+)/', $page_2_html, $page_2_matches );

		$this->assertNotEmpty( $page_1_matches[1] );
		$this->assertNotEmpty( $page_2_matches[1], 'Page 2 must render rows - the old screen ignored $_GET["paged"] entirely.' );
		$this->assertNotSame(
			array_unique( $page_1_matches[1] ),
			array_unique( $page_2_matches[1] ),
			'Page 2 must show different tests than page 1.'
		);
	}

	public function test_status_filter_narrows_the_list(): void {
		$this->test_row( 'running' );
		$this->test_row( 'paused' );

		$_GET['status'] = 'paused';
		$manager        = AB_Test_Manager::get_instance();
		$tests          = $manager->get_tests( array( 'status' => 'paused' ) );

		$this->assertCount( 1, $tests );
		$this->assertSame( 'paused', $tests[0]->status );
	}

	public function test_demo_seed_status_is_running_not_active(): void {
		// The demo seeder must write a status AB_Test_Manager actually
		// recognises as in-flight - 'active' matched nothing, so the
		// "Running" view always read (0) while the badge showed "Active"
		// (raw slug, title-cased) for demo tests.
		$demo_file = WBAM_PRO_PATH . 'demo-data-setup.php';
		$this->assertFileExists( $demo_file );

		$source = file_get_contents( $demo_file );
		preg_match_all( "/'status'\\s*=>\\s*'([a-z]+)',\\n\\s*'original_ad_id'/", $source, $matches );

		$this->assertNotEmpty( $matches[1], 'Expected to find AB test seed rows in demo-data-setup.php.' );
		foreach ( $matches[1] as $status ) {
			$this->assertContains( $status, array( 'running', 'paused', 'completed', 'draft' ), "Demo A/B test status '{$status}' is not a status the admin list understands." );
		}
	}

	public function test_upgrade_normalises_existing_active_rows_to_running(): void {
		global $wpdb;

		$id = $this->test_row( 'running' );
		$wpdb->update( $wpdb->prefix . 'wbam_ab_tests', array( 'status' => 'active' ), array( 'id' => $id ) );

		$reflection = new \ReflectionClass( '\\WBAM_Pro\\Core\\Installer' );
		$method     = $reflection->getMethod( 'upgrade_to_4_3_13' );
		$method->setAccessible( true );
		$method->invoke( null );

		$test = AB_Test_Manager::get_instance()->get_test( $id );
		$this->assertSame( 'running', $test->status );
	}
}
