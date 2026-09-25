<?php
/**
 * All Ads list at scale: placement names instead of slugs, one batch of
 * event totals per page, type and placement filters, type/status sort.
 *
 * The placements column printed every raw slug (16 lines tall on an ad in
 * every slot), impressions and clicks ran two queries per row each, and the
 * list could not be narrowed by ad type or placement.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Admin;
use WBAM\Core\Ad_Type_Meta;
use WBAM\Tests\Helpers\Factory;
use WP_UnitTestCase;

class Test_Ads_List_Scale extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// Admin::init() only runs in wp-admin; hook the one filter under test.
		add_action( 'pre_get_posts', array( Admin::get_instance(), 'apply_status_filter' ) );
	}

	public function tear_down(): void {
		remove_action( 'pre_get_posts', array( Admin::get_instance(), 'apply_status_filter' ) );
		unset( $_GET['wbam_type'], $_GET['wbam_placement'], $_GET['wbam_enabled_filter'] );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new \WP_Query();
		set_current_screen( 'front' );
		parent::tear_down();
	}

	private function ad( string $type, array $placements ): int {
		$id = Factory::make_ad();
		update_post_meta( $id, '_wbam_ad_data', array( 'type' => $type ) );
		update_post_meta( $id, '_wbam_placements', $placements );
		do_action( 'wbam_save_ad_meta', $id );
		return $id;
	}

	private function main_query( array $args = array() ): \WP_Query {
		set_current_screen( 'edit-wbam-ad' );
		$query                   = new \WP_Query();
		$GLOBALS['wp_the_query'] = $query;
		$GLOBALS['wp_query']     = $query;
		$query->query( array_merge( array( 'post_type' => 'wbam-ad', 'posts_per_page' => 50 ), $args ) );
		return $query;
	}

	private function column( string $column, int $id ): string {
		ob_start();
		Admin::get_instance()->render_column( $column, $id );
		return (string) ob_get_clean();
	}

	public function test_placements_show_names_and_a_more_count(): void {
		$id   = $this->ad( 'image', array( 'header', 'footer', 'sticky', 'widget' ) );
		$html = $this->column( 'placements', $id );

		$this->assertStringNotContainsString( 'header, footer', $html );
		$this->assertStringContainsString( '+2 more', $html );
	}

	public function test_event_totals_cost_the_same_queries_for_any_page_size(): void {
		global $wpdb;

		$measure = function ( int $count ) use ( $wpdb ): int {
			$ids = array();
			foreach ( range( 1, $count ) as $i ) {
				$ids[] = $this->ad( 'image', array( 'header' ) );
			}
			$this->main_query( array( 'post__in' => $ids ) );
			foreach ( $ids as $id ) {
				Admin::flush_event_totals( $id );
			}
			$before = $wpdb->num_queries;
			foreach ( $ids as $id ) {
				$this->column( 'impressions', $id );
				$this->column( 'clicks', $id );
			}
			return $wpdb->num_queries - $before;
		};

		$measure( 1 ); // Warm the one-time table check.
		$this->assertSame( 2, $measure( 2 ) );
		$this->assertSame( 2, $measure( 12 ), 'Impressions and clicks must be totalled per page, not per row.' );
	}

	public function test_type_and_placement_filters_narrow_the_list(): void {
		$image = $this->ad( 'image', array( 'header' ) );
		$code  = $this->ad( 'code', array( 'footer' ) );

		$_GET['wbam_type'] = 'code';
		$this->assertSame( array( $code ), wp_list_pluck( $this->main_query()->posts, 'ID' ) );

		unset( $_GET['wbam_type'] );
		$_GET['wbam_placement'] = 'header';
		$this->assertSame( array( $image ), wp_list_pluck( $this->main_query()->posts, 'ID' ) );
	}

	public function test_type_sort_keeps_ads_without_the_key(): void {
		$b     = $this->ad( 'image', array() );
		$a     = $this->ad( 'code', array() );
		$blank = Factory::make_ad();
		delete_post_meta( $blank, Ad_Type_Meta::KEY );

		$ids = wp_list_pluck( $this->main_query( array( 'orderby' => 'wbam_type', 'order' => 'asc' ) )->posts, 'ID' );

		$this->assertCount( 3, $ids );
		$this->assertLessThan( array_search( $b, $ids, true ), array_search( $a, $ids, true ) );
	}

	public function test_type_mirror_is_written_on_save(): void {
		$id = $this->ad( 'rich-content', array() );
		$this->assertSame( 'rich-content', get_post_meta( $id, Ad_Type_Meta::KEY, true ) );
	}
}
