<?php
/**
 * The daily aggregation rolls raw analytics up into daily totals but only
 * deletes raw rows once the owner has confirmed retention.
 *
 * Owner decision 12 (Basecamp card 10342784882): rolling up is fine;
 * deleting raw rows (visitor, device and country detail) waits for the same
 * one-time confirmation as retention. Totals must not change or double
 * whichever way it runs, and the daily row adds to a Free roll-up row
 * instead of replacing it.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Core\Analytics_Rollup;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser;
use WBAM_Pro\Modules\Analytics\Analytics_Tracker;
use WBAM_Pro\Modules\Analytics\Analytics_Window;

class Test_Analytics_Daily_Aggregation extends Pro_Test_Case {

	private int $ad_id;
	private int $author;
	private string $old_day;

	public function set_up(): void {
		parent::set_up();

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wbam_analytics" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wbam_analytics_daily" );
		// phpcs:enable
		delete_option( Analytics_Rollup::ROLLED_OPTION );
		Settings_Helper::delete( 'retention_cleanup_confirmed' );

		$this->author  = (int) self::factory()->user->create();
		$this->ad_id   = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_author' => $this->author,
			)
		);
		$this->old_day = wp_date( 'Y-m-d', strtotime( '-20 days' ) );

		$this->event( 'impression', $this->old_day . ' 09:00:00' );
		$this->event( 'impression', $this->old_day . ' 10:00:00' );
		$this->event( 'impression', $this->old_day . ' 23:00:00' );
		$this->event( 'click', $this->old_day . ' 10:05:00' );
		$this->event( 'impression', wp_date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ) );
	}

	public function tear_down(): void {
		global $wpdb;
		// The roll-up commits its own transaction; clean up explicitly.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wbam_analytics" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wbam_analytics_daily" );
		// phpcs:enable
		delete_option( Analytics_Rollup::ROLLED_OPTION );
		Settings_Helper::delete( 'retention_cleanup_confirmed' );
		parent::tear_down();
	}

	private function event( string $type, string $created_at ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wbam_analytics',
			array(
				'ad_id'      => $this->ad_id,
				'event_type' => $type,
				'ip_hash'    => md5( $created_at . $type ),
				'created_at' => $created_at,
			)
		);
	}

	private function raw_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wbam_analytics" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	private function daily(): ?array {
		global $wpdb;
		return $wpdb->get_row( "SELECT date, impressions, clicks FROM {$wpdb->prefix}wbam_analytics_daily", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Every reader of both tables reports the same lifetime numbers.
	 */
	private function assert_totals( int $impressions, int $clicks ): void {
		global $wpdb;

		$window = Analytics_Window::totals( wp_date( 'Y-m-d', strtotime( '-60 days' ) ), wp_date( 'Y-m-d' ), $wpdb->prepare( ' AND ad_id = %d', $this->ad_id ) );
		$this->assertSame( array( 'impressions' => $impressions, 'clicks' => $clicks ), $window, 'Analytics_Window::totals' );

		$free = Analytics_Rollup::event_totals( array( $this->ad_id ) );
		$this->assertSame( array( 'impression' => $impressions, 'click' => $clicks ), $free[ $this->ad_id ], 'Analytics_Rollup::event_totals' );

		$advertiser = new Advertiser( (object) array( 'user_id' => $this->author ) );
		$this->assertSame( $impressions, $advertiser->get_total_impressions(), 'Advertiser::get_total_impressions' );
		$this->assertSame( $clicks, $advertiser->get_total_clicks(), 'Advertiser::get_total_clicks' );
	}

	public function test_unconfirmed_site_rolls_up_but_keeps_every_raw_row(): void {
		( new Analytics_Tracker() )->aggregate_daily_stats();

		$this->assertSame( 5, $this->raw_count(), 'No raw row is deleted without the retention confirmation.' );
		$this->assertSame(
			array(
				'date'        => $this->old_day,
				'impressions' => '3',
				'clicks'      => '1',
			),
			$this->daily()
		);
		$this->assert_totals( 4, 1 );
	}

	public function test_running_twice_does_not_count_a_day_twice(): void {
		$tracker = new Analytics_Tracker();
		$tracker->aggregate_daily_stats();
		$tracker->aggregate_daily_stats();

		$this->assertSame( 5, $this->raw_count() );
		$this->assertSame( '3', $this->daily()['impressions'] );
		$this->assert_totals( 4, 1 );
	}

	public function test_daily_row_adds_to_an_existing_free_rollup_row(): void {
		global $wpdb;

		// Free's Analytics_Rollup already summed (and deleted) 5 impressions
		// and 2 clicks of the same day before Pro was activated.
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wbam_analytics_daily',
			array(
				'ad_id'       => $this->ad_id,
				'date'        => $this->old_day,
				'impressions' => 5,
				'clicks'      => 2,
			)
		);

		( new Analytics_Tracker() )->aggregate_daily_stats();

		$this->assertSame( '8', $this->daily()['impressions'] );
		$this->assertSame( '3', $this->daily()['clicks'] );
	}

	public function test_confirmed_site_deletes_rolled_raw_rows_only(): void {
		Settings_Helper::update( 'retention_cleanup_confirmed', true );

		( new Analytics_Tracker() )->aggregate_daily_stats();

		$this->assertSame( 1, $this->raw_count(), 'Only the recent, not yet rolled, event stays raw.' );
		$this->assert_totals( 4, 1 );
	}
}
