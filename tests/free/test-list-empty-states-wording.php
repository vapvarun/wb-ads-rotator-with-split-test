<?php
/**
 * QA reject on card 10339874920: filtered-empty tables (a status filter or
 * search that matches nothing, on a table that isn't actually empty) must
 * say "No results match" / "No <status> <things>" — never "No … yet",
 * which implies the whole table has zero rows.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\List_Empty_States;
use WBAM\Modules\Links\Partnership_Admin;
use WP_UnitTestCase;

class Test_List_Empty_States_Wording extends WP_UnitTestCase {

	private function partnerships_admin(): Partnership_Admin {
		return ( new \ReflectionClass( Partnership_Admin::class ) )->newInstanceWithoutConstructor();
	}

	private function call_private( object $object, string $method, array $args ) {
		$reflection = new \ReflectionMethod( $object, $method );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $object, $args );
	}

	public function test_partnerships_true_empty_says_yet(): void {
		$args = $this->call_private( $this->partnerships_admin(), 'partnerships_empty_state_args', array( '', '', 0 ) );
		$this->assertStringContainsString( 'yet', $args['title'] );
	}

	public function test_partnerships_rejected_filter_with_zero_matches(): void {
		$args = $this->call_private( $this->partnerships_admin(), 'partnerships_empty_state_args', array( 'rejected', '', 5 ) );
		$this->assertSame( 'No rejected partnerships', $args['title'] );
		$this->assertStringNotContainsString( 'yet', $args['title'] );
	}

	public function test_partnerships_search_with_zero_matches(): void {
		$args = $this->call_private( $this->partnerships_admin(), 'partnerships_empty_state_args', array( '', 'zzqqnomatch', 5 ) );
		$this->assertSame( 'No results match', $args['title'] );
	}

	/**
	 * All Ads: a search that matches nothing on a site that has ads must
	 * be detected independently of the zero-ads-total check.
	 */
	public function test_ads_filtered_empty_detection_ignores_unrelated_searches(): void {
		self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_title' => 'Findable ad', 'post_status' => 'publish' ) );

		set_current_screen( 'edit-wbam-ad' );
		$_GET['s'] = 'zzqqnomatch';

		$instance   = new List_Empty_States();
		$reflection = new \ReflectionMethod( $instance, 'is_filtered_empty_ads_list_screen' );
		$reflection->setAccessible( true );
		$this->assertTrue( $reflection->invoke( $instance ) );

		$_GET['s'] = 'Findable';
		$this->assertFalse( $reflection->invoke( $instance ) );

		unset( $_GET['s'] );
		set_current_screen( 'front' );
	}
}
