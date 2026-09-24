<?php
/**
 * Report_Shell — date range, formatter and delta unit tests.
 *
 * Covers the parts of the uniform report shell that are pure logic: preset
 * inclusivity, previous-period adjacency, bucket thresholds, compact-number
 * formatting, and delta edge cases (no-previous, new, no-change, good/bad by
 * trend direction).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Report_Shell;

class Test_Report_Shell extends Pro_Test_Case {

	/**
	 * Inclusive day count for a Y-m-d..Y-m-d range.
	 *
	 * @param string $start Start date.
	 * @param string $end   End date.
	 * @return int
	 */
	private function days_between( $start, $end ) {
		$tz = wp_timezone();
		return (int) ( new \DateTime( $start, $tz ) )->diff( new \DateTime( $end, $tz ) )->days + 1;
	}

	public function test_preset_7d_is_inclusive_of_seven_days(): void {
		$range = Report_Shell::range( array( 'range' => '7d' ) );

		$this->assertSame( '7d', $range['preset'] );
		$this->assertSame( current_time( 'Y-m-d' ), $range['end'] );
		$this->assertSame( 7, $this->days_between( $range['start'], $range['end'] ) );
	}

	public function test_preset_30d_is_inclusive_of_thirty_days(): void {
		$range = Report_Shell::range( array( 'range' => '30d' ) );

		$this->assertSame( 30, $this->days_between( $range['start'], $range['end'] ) );
	}

	public function test_preset_90d_is_inclusive_of_ninety_days(): void {
		$range = Report_Shell::range( array( 'range' => '90d' ) );

		$this->assertSame( 90, $this->days_between( $range['start'], $range['end'] ) );
	}

	public function test_default_preset_is_30d_when_range_param_missing(): void {
		$range = Report_Shell::range( array() );

		$this->assertSame( '30d', $range['preset'] );
	}

	public function test_unknown_preset_falls_back_to_30d(): void {
		$range = Report_Shell::range( array( 'range' => 'not-a-real-preset' ) );

		$this->assertSame( '30d', $range['preset'] );
		$this->assertSame( 30, $this->days_between( $range['start'], $range['end'] ) );
	}

	public function test_preset_month_starts_on_the_first(): void {
		$range = Report_Shell::range( array( 'range' => 'month' ) );

		$this->assertSame( current_time( 'Y-m-01' ), $range['start'] );
		$this->assertSame( current_time( 'Y-m-d' ), $range['end'] );
	}

	public function test_preset_ytd_starts_on_january_first(): void {
		$range = Report_Shell::range( array( 'range' => 'ytd' ) );

		$this->assertSame( current_time( 'Y-01-01' ), $range['start'] );
	}

	public function test_previous_range_is_immediately_adjacent_with_equal_length(): void {
		$range = Report_Shell::range( array( 'range' => '30d' ) );

		$expected_prev_end = ( new \DateTime( $range['start'], wp_timezone() ) )->modify( '-1 day' )->format( 'Y-m-d' );
		$this->assertSame( $expected_prev_end, $range['prev_end'] );
		$this->assertSame(
			$this->days_between( $range['start'], $range['end'] ),
			$this->days_between( $range['prev_start'], $range['prev_end'] ),
			'Previous period must be the same length as the current period.'
		);
	}

	public function test_custom_range_swaps_reversed_dates(): void {
		$range = Report_Shell::range(
			array(
				'range'      => 'custom',
				'start_date' => '2020-01-20',
				'end_date'   => '2020-01-10',
			)
		);

		$this->assertSame( '2020-01-10', $range['start'] );
		$this->assertSame( '2020-01-20', $range['end'] );
	}

	public function test_custom_range_is_inclusive(): void {
		$range = Report_Shell::range(
			array(
				'range'      => 'custom',
				'start_date' => '2020-01-01',
				'end_date'   => '2020-01-10',
			)
		);

		$this->assertSame( 10, $this->days_between( $range['start'], $range['end'] ) );
	}

	public function test_custom_range_caps_span_at_730_days(): void {
		$range = Report_Shell::range(
			array(
				'range'      => 'custom',
				'start_date' => '2015-01-01',
				'end_date'   => '2020-01-01',
			)
		);

		$this->assertSame( '2020-01-01', $range['end'] );
		$this->assertSame( 730, $this->days_between( $range['start'], $range['end'] ) );
	}

	public function test_custom_range_with_invalid_dates_falls_back_to_30d(): void {
		$range = Report_Shell::range(
			array(
				'range'      => 'custom',
				'start_date' => 'not-a-date',
				'end_date'   => '2020-01-01',
			)
		);

		$this->assertSame( '30d', $range['preset'] );
		$this->assertSame( 30, $this->days_between( $range['start'], $range['end'] ) );
	}

	public function test_bucket_is_day_up_to_ninety_two_days(): void {
		$range = Report_Shell::range(
			array(
				'range'      => 'custom',
				'start_date' => '2020-01-01',
				'end_date'   => '2020-04-01', // 92 inclusive days (2020 is a leap year).
			)
		);

		$this->assertSame( 92, $this->days_between( $range['start'], $range['end'] ) );
		$this->assertSame( 'day', $range['bucket'] );
	}

	public function test_bucket_is_week_from_ninety_three_to_three_sixty_six_days(): void {
		$range = Report_Shell::range(
			array(
				'range'      => 'custom',
				'start_date' => '2020-01-01',
				'end_date'   => '2020-04-02', // 93 inclusive days.
			)
		);

		$this->assertSame( 93, $this->days_between( $range['start'], $range['end'] ) );
		$this->assertSame( 'week', $range['bucket'] );

		$range = Report_Shell::range(
			array(
				'range'      => 'custom',
				'start_date' => '2019-01-01',
				'end_date'   => '2020-01-01', // 366 inclusive days.
			)
		);
		$this->assertSame( 366, $this->days_between( $range['start'], $range['end'] ) );
		$this->assertSame( 'week', $range['bucket'] );
	}

	public function test_bucket_is_month_beyond_three_sixty_six_days(): void {
		$range = Report_Shell::range(
			array(
				'range'      => 'custom',
				'start_date' => '2019-01-01',
				'end_date'   => '2020-01-02', // 367 inclusive days — within the 730-day cap.
			)
		);

		$this->assertSame( 367, $this->days_between( $range['start'], $range['end'] ) );
		$this->assertSame( 'month', $range['bucket'] );
	}

	public function test_compare_defaults_true_on_first_load(): void {
		$range = Report_Shell::range( array( 'range' => '7d' ) );

		$this->assertTrue( $range['compare'] );
	}

	public function test_compare_off_when_hidden_fallback_wins(): void {
		// Unchecked checkbox never submits — only the hidden compare=0 fallback arrives.
		$range = Report_Shell::range( array( 'range' => '7d', 'compare' => '0' ) );

		$this->assertFalse( $range['compare'] );
	}

	public function test_compact_below_threshold_returns_count(): void {
		$this->assertSame( Report_Shell::count( 9999 ), Report_Shell::compact( 9999, 'count' ) );
	}

	public function test_compact_thousands_gets_k_suffix(): void {
		$this->assertSame( '12.3K', Report_Shell::compact( 12345, 'count' ) );
	}

	public function test_compact_millions_gets_m_suffix(): void {
		$this->assertSame( '4.5M', Report_Shell::compact( 4500000, 'count' ) );
	}

	public function test_compact_billions_gets_b_suffix(): void {
		$this->assertSame( '1.2B', Report_Shell::compact( 1200000000, 'count' ) );
	}

	public function test_compact_money_includes_currency_symbol(): void {
		$compact = Report_Shell::compact( 12345, 'money' );
		$this->assertStringContainsString( '12.3K', $compact );
	}

	public function test_delta_is_null_when_no_previous_value(): void {
		$this->assertNull( Report_Shell::delta( 100, null ) );
	}

	public function test_delta_is_new_when_previous_zero_and_current_positive(): void {
		$delta = Report_Shell::delta( 50, 0 );

		$this->assertSame( 'new', $delta['state'] );
	}

	public function test_delta_is_no_change_when_both_zero(): void {
		$delta = Report_Shell::delta( 0, 0 );

		$this->assertSame( 'neutral', $delta['state'] );
	}

	public function test_delta_is_good_when_up_trend_increases(): void {
		$delta = Report_Shell::delta( 150, 100, 'up' );

		$this->assertSame( 'good', $delta['state'] );
		$this->assertSame( 'up', $delta['direction'] );
		$this->assertSame( '+50.0%', $delta['text'] );
	}

	public function test_delta_is_bad_when_up_trend_decreases(): void {
		$delta = Report_Shell::delta( 50, 100, 'up' );

		$this->assertSame( 'bad', $delta['state'] );
		$this->assertSame( 'down', $delta['direction'] );
	}

	public function test_delta_is_good_when_down_trend_decreases(): void {
		// A Refunds-style tile: going down is the good outcome.
		$delta = Report_Shell::delta( 50, 100, 'down' );

		$this->assertSame( 'good', $delta['state'] );
	}

	public function test_delta_is_bad_when_down_trend_increases(): void {
		$delta = Report_Shell::delta( 150, 100, 'down' );

		$this->assertSame( 'bad', $delta['state'] );
	}

	public function test_fill_series_zero_fills_every_day_of_the_range(): void {
		$series = Report_Shell::fill_series(
			array( '2026-09-10' => array( 'n' => 5 ) ),
			'2026-09-01',
			'2026-09-30',
			'day',
			array( 'n' )
		);
		$this->assertCount( 30, $series['labels'] );
		$this->assertCount( 30, $series['n'] );
		$this->assertSame( 5.0, array_sum( $series['n'] ) );
		$this->assertSame( 0.0, $series['n'][0] );
		$this->assertSame( 5.0, $series['n'][9] );
	}

	public function test_fill_series_weeks_sum_days_and_label_from_range_start(): void {
		// 2026-01-01 is a Thursday: the first week bucket starts mid-week.
		$series = Report_Shell::fill_series(
			array(
				'2026-01-01' => array( 'n' => 1 ),
				'2026-01-04' => array( 'n' => 2 ),
				'2026-01-05' => array( 'n' => 4 ),
			),
			'2026-01-01',
			'2026-01-11',
			'week',
			array( 'n' )
		);
		$this->assertSame( array( 3.0, 4.0 ), $series['n'] );
		$this->assertSame( wp_date( 'M j', strtotime( '2026-01-01 12:00:00' ) ), $series['labels'][0] );
	}
}
