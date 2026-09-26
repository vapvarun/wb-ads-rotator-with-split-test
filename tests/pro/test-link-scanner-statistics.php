<?php
/**
 * Card 10339876480, step 9: the admin Link Analytics "Links by Type" chart
 * read `get_statistics()['by_type']` as a `{ type: count }` map, but the
 * scanner handed it the raw list of `{ link_type, count }` row objects from
 * $wpdb->get_results(). JSON-encoded and read by Object.keys()/values() in
 * JS, that walked the array's numeric indexes instead of the link types, so
 * the chart always drew one "0" slice no matter how many links existed.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Links\Link_Scanner;

class Test_Link_Scanner_Statistics extends Pro_Test_Case {

	public function tear_down(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_post_links" );
		wp_cache_delete( 'scanner_stats', 'wbam_pro_link_scanner' );
		parent::tear_down();
	}

	public function test_by_type_is_a_type_to_count_map_not_a_row_list(): void {
		global $wpdb;
		$post_id = self::factory()->post->create();

		$wpdb->insert(
			$wpdb->prefix . 'wbam_post_links',
			array(
				'post_id'      => $post_id,
				'url'          => 'https://example.com/a',
				'link_type'    => 'affiliate',
				'is_affiliate' => 1,
			)
		);
		$wpdb->insert(
			$wpdb->prefix . 'wbam_post_links',
			array(
				'post_id'      => $post_id,
				'url'          => 'https://example.com/b',
				'link_type'    => 'external',
				'is_affiliate' => 0,
			)
		);
		wp_cache_delete( 'scanner_stats', 'wbam_pro_link_scanner' );

		$stats = Link_Scanner::get_instance()->get_statistics();

		$this->assertSame( 2, $stats['total_links'] );
		$this->assertIsArray( $stats['by_type'] );
		$this->assertSame(
			array(
				'affiliate' => 1,
				'external'  => 1,
			),
			$stats['by_type'],
			'by_type must be keyed by link_type so wp_json_encode() produces a JS object, not an array of row objects.'
		);

		// The exact failure mode this guards: JSON-encoding the old shape and
		// reading it back the way the JS does (Object.keys/values) must not
		// silently produce a single "0" bucket.
		$decoded = json_decode( wp_json_encode( $stats['by_type'] ), true );
		$this->assertArrayHasKey( 'affiliate', $decoded );
		$this->assertArrayHasKey( 'external', $decoded );
		$this->assertArrayNotHasKey( '0', $decoded );
	}
}
