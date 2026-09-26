<?php
/**
 * Raw analytics older than the retention window roll up into daily totals.
 *
 * Free kept one wbam_analytics row per impression forever: no retention,
 * no roll-up, so the table grew without limit (Basecamp card 10342824516).
 * Rolling up must not lose a single count from the lifetime totals.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Core\Analytics_Rollup;
use WBAM\Tests\Helpers\Factory;
use WP_UnitTestCase;

class Test_Analytics_Rollup extends WP_UnitTestCase {

	private int $ad_id = 0;

	/**
	 * The roll-up commits its own transaction, which also commits the test's
	 * wrapping one, so remove the fixtures explicitly.
	 */
	public function tear_down(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'wbam_analytics', array( 'ad_id' => $this->ad_id ) );
		$wpdb->delete( $wpdb->prefix . 'wbam_analytics_daily', array( 'ad_id' => $this->ad_id ) );
		wp_delete_post( $this->ad_id, true );
		delete_option( 'wbam_analytics_rolled_before' );
		$wpdb->query( 'COMMIT' );
		// phpcs:enable

		parent::tear_down();
	}

	private function event( string $type, string $created_at ): void {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wbam_analytics',
			array(
				'ad_id'        => $this->ad_id,
				'event_type'   => $type,
				'visitor_hash' => md5( $created_at . $type . wp_rand() ),
				'created_at'   => $created_at,
			)
		);
	}

	public function test_old_events_move_to_daily_totals_without_changing_lifetime_totals(): void {
		global $wpdb;

		$this->ad_id = Factory::make_ad();
		$old_day     = wp_date( 'Y-m-d', strtotime( '-200 days' ) );
		$recent      = wp_date( 'Y-m-d H:i:s', strtotime( '-1 day' ) );

		$this->event( 'impression', $old_day . ' 09:00:00' );
		$this->event( 'impression', $old_day . ' 10:00:00' );
		$this->event( 'impression', $old_day . ' 23:00:00' );
		$this->event( 'click', $old_day . ' 10:05:00' );
		$this->event( 'impression', $recent );

		Analytics_Rollup::rollup_batch();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$raw   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wbam_analytics WHERE ad_id = %d", $this->ad_id ) );
		$daily = $wpdb->get_row( $wpdb->prepare( "SELECT date, impressions, clicks FROM {$wpdb->prefix}wbam_analytics_daily WHERE ad_id = %d", $this->ad_id ), ARRAY_A );
		// phpcs:enable

		$this->assertSame( 1, $raw, 'Only the event inside the retention window stays raw.' );
		$this->assertSame(
			array(
				'date'        => $old_day,
				'impressions' => '3',
				'clicks'      => '1',
			),
			$daily
		);

		// The ads list shows lifetime totals: raw plus rolled-up.
		$admin = \WBAM\Admin\Admin::get_instance();
		$total = new \ReflectionMethod( $admin, 'get_event_total' );
		\WBAM\Admin\Admin::flush_event_totals( $this->ad_id );
		$this->assertSame( 4, $total->invoke( $admin, $this->ad_id, 'impression' ) );
		$this->assertSame( 1, $total->invoke( $admin, $this->ad_id, 'click' ) );
	}

	/**
	 * Pro's daily aggregation sums raw rows into daily totals and keeps them
	 * until the owner confirms retention, recording how far it has summed.
	 * Those rows must count once: in the ads list, and again when Free's own
	 * roll-up takes over after Pro is deactivated.
	 */
	public function test_rows_pro_already_summed_are_not_counted_twice(): void {
		global $wpdb;

		$this->ad_id = Factory::make_ad();
		$old_day     = wp_date( 'Y-m-d', strtotime( '-200 days' ) );

		$this->event( 'impression', $old_day . ' 09:00:00' );
		$this->event( 'impression', $old_day . ' 10:00:00' );
		$this->event( 'click', $old_day . ' 10:05:00' );
		$this->event( 'impression', wp_date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ) );

		// What Pro's aggregation leaves behind: the day summed, raw rows kept.
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wbam_analytics_daily',
			array(
				'ad_id'       => $this->ad_id,
				'date'        => $old_day,
				'impressions' => 2,
				'clicks'      => 1,
			)
		);
		update_option( 'wbam_analytics_rolled_before', wp_date( 'Y-m-d', strtotime( '-199 days' ) ) . ' 00:00:00', false );

		$expected = array(
			'impression' => 3,
			'click'      => 1,
		);
		$this->assertSame( $expected, Analytics_Rollup::event_totals( array( $this->ad_id ) )[ $this->ad_id ] );

		Analytics_Rollup::rollup_batch();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$daily = $wpdb->get_row( $wpdb->prepare( "SELECT impressions, clicks FROM {$wpdb->prefix}wbam_analytics_daily WHERE ad_id = %d", $this->ad_id ), ARRAY_A );
		$this->assertSame(
			array(
				'impressions' => '2',
				'clicks'      => '1',
			),
			$daily
		);
		$this->assertSame( $expected, Analytics_Rollup::event_totals( array( $this->ad_id ) )[ $this->ad_id ] );
	}
}
