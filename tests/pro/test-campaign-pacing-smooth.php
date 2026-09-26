<?php
/**
 * Smooth pacing: daily target with carry-over and a minimum delivery so a
 * live campaign with budget left never goes dark for the rest of the day.
 *
 * Owner decision, card 10342512361 / 10342761510 (2026-09-26): overspend at
 * most one billable event beyond the daily target, carry the difference
 * into the following days, and never go fully dark - a low floor chance of
 * delivery instead of a hard block once a campaign is ahead of pace.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Modules\Placements\Placement_Engine;
use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Modules\Targeting\Campaign_Pacing;

class Test_Campaign_Pacing_Smooth extends Pro_Test_Case {

	private int $advertiser_id = 0;

	private int $advertiser_user = 0;

	private array $ad_ids = array();

	private array $campaign_ids = array();

	public function set_up(): void {
		parent::set_up();
		Factory::reset_page_ads();
		$this->reset_pacing_cache();

		// Pin the intra-day pacing curve to "end of day" (fraction = 1) so
		// the ceiling equals the full daily target plus one event's cost,
		// independent of what time the test actually runs.
		add_filter( 'wbam_pacing_time_of_day_fraction', array( __CLASS__, 'full_day_fraction' ) );
	}

	public function tear_down(): void {
		global $wpdb;

		remove_filter( 'wbam_pacing_time_of_day_fraction', array( __CLASS__, 'full_day_fraction' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		foreach ( $this->campaign_ids as $campaign_id ) {
			$wpdb->delete( $wpdb->prefix . 'wbam_campaigns', array( 'id' => $campaign_id ) );
		}
		foreach ( $this->ad_ids as $ad_id ) {
			wp_delete_post( $ad_id, true );
		}
		if ( $this->advertiser_id ) {
			$wpdb->delete( $wpdb->prefix . 'wbam_advertisers', array( 'id' => $this->advertiser_id ) );
		}
		if ( $this->advertiser_user ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $this->advertiser_user );
		}
		$wpdb->query( 'COMMIT' );
		// phpcs:enable

		$this->reset_pacing_cache();
		parent::tear_down();
	}

	private function reset_pacing_cache(): void {
		$prop = new \ReflectionProperty( Campaign_Pacing::class, 'decision_cache' );
		$prop->setValue( null, array() );
	}

	/** WP core has no __return_one(). */
	public static function full_day_fraction(): float {
		return 1.0;
	}

	private function invoke_private( string $method, array $args ) {
		$ref = new \ReflectionMethod( Campaign_Pacing::class, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( null, $args );
	}

	/**
	 * A CPC campaign with $rate/click, $budget total, no end_date unless
	 * given, already spent $spent (all attributed to "today").
	 */
	private function make_ad_and_campaign( float $budget, float $rate, float $spent = 0.0, ?string $end_date = null ): int {
		global $wpdb;

		if ( ! $this->advertiser_id ) {
			$this->advertiser_user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
			$wpdb->insert(
				$wpdb->prefix . 'wbam_advertisers',
				array(
					'user_id' => $this->advertiser_user,
					'status'  => 'active',
				)
			);
			$this->advertiser_id = (int) $wpdb->insert_id;
		}

		$ad_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'code', 'code' => '<span>Paced ad</span>' ) );
		update_post_meta( $ad_id, '_wbam_placements', array( 'header' ) );
		update_post_meta( $ad_id, '_wbam_priority', 10 );
		$this->ad_ids[] = $ad_id;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_campaigns',
			array(
				'advertiser_id'    => $this->advertiser_id,
				'ad_id'            => $ad_id,
				'name'             => 'Smooth pacing probe',
				'status'           => 'active',
				'pricing_model'    => 'cpc',
				'price_per_unit'   => $rate,
				'budget'           => $budget,
				'spent'            => $spent,
				'spent_today'      => $spent,
				'spent_today_date' => wp_date( 'Y-m-d' ),
				'end_date'         => $end_date,
			)
		);
		$campaign_id          = (int) $wpdb->insert_id;
		$this->campaign_ids[] = $campaign_id;

		update_post_meta( $ad_id, '_wbam_campaign_id', $campaign_id );

		return $ad_id;
	}

	private function paced_through( int $ad_id ): bool {
		$this->reset_pacing_cache();
		return (bool) Campaign_Pacing::apply_pacing( true, $ad_id );
	}

	private function bill_click( int $ad_id, float $amount ): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}wbam_campaigns
				SET clicks = clicks + 1, spent = spent + %f,
					spent_today = spent_today + %f, spent_today_date = %s
				WHERE ad_id = %d",
				$amount,
				$amount,
				wp_date( 'Y-m-d' ),
				$ad_id
			)
		);
	}

	/**
	 * A $10/30-day CPC campaign at $1/click: one click ($1) crosses the
	 * ~$0.33 daily target, but it must still be showing afterwards - the
	 * owner's "doesn't vanish for the day after one click" scenario.
	 */
	public function test_small_campaign_is_not_dark_after_one_click(): void {
		$ad_id = $this->make_ad_and_campaign( 10.0, 1.0 );

		$this->assertTrue( $this->paced_through( $ad_id ), 'Nothing spent yet: full delivery.' );

		$this->bill_click( $ad_id, 1.0 );

		$this->assertTrue(
			$this->paced_through( $ad_id ),
			'One click over a $0.33 daily target is the one allowed overspend event - must not go dark.'
		);
	}

	/** A second event once already over the ceiling is capped/blocked. */
	public function test_overspend_capped_at_one_event(): void {
		add_filter( 'wbam_pacing_minimum_delivery_floor', '__return_zero' );

		$ad_id = $this->make_ad_and_campaign( 10.0, 1.0 );
		$this->bill_click( $ad_id, 1.0 );
		$this->assertTrue( $this->paced_through( $ad_id ), 'First click: the one allowed overspend event.' );

		$this->bill_click( $ad_id, 1.0 );
		$this->assertFalse(
			$this->paced_through( $ad_id ),
			'A second click beyond the ceiling, with the minimum-delivery floor disabled, must be blocked.'
		);

		remove_filter( 'wbam_pacing_minimum_delivery_floor', '__return_zero' );
	}

	/** Throttled (over ceiling) still delivers at the floor rate, never fully dark. */
	public function test_minimum_delivery_floor_prevents_going_dark(): void {
		$ad_id = $this->make_ad_and_campaign( 10.0, 1.0 );
		$this->bill_click( $ad_id, 1.0 );
		$this->bill_click( $ad_id, 1.0 ); // Now over the ceiling.

		add_filter( 'wbam_pacing_floor_roll', fn() => 0.05 ); // Under the 10% default floor.
		$this->assertTrue( $this->paced_through( $ad_id ), 'A roll under the floor still delivers.' );
		remove_all_filters( 'wbam_pacing_floor_roll' );

		add_filter( 'wbam_pacing_floor_roll', fn() => 0.95 ); // Over the floor.
		$this->assertFalse( $this->paced_through( $ad_id ), 'A roll over the floor does not.' );
		remove_all_filters( 'wbam_pacing_floor_roll' );
	}

	/** The floor never overrides the hard total-budget cap. */
	public function test_never_serves_beyond_total_budget(): void {
		// Budget 10, rate 1: spent 9.70 today leaves 0.30 remaining - less
		// than one more click's cost - even though the roll would pass.
		add_filter( 'wbam_pacing_floor_roll', '__return_zero' );

		$ad_id = $this->make_ad_and_campaign( 10.0, 1.0, 9.70 );

		$this->assertFalse(
			$this->paced_through( $ad_id ),
			'Remaining budget (0.30) cannot cover one more $1 click - must block even at a passing floor roll.'
		);

		remove_all_filters( 'wbam_pacing_floor_roll' );
	}

	/** No end_date: pace against the configured default window, not opt-out. */
	public function test_no_end_date_uses_configured_default(): void {
		add_filter( 'wbam_pacing_default_days', fn() => 10 );

		$campaign = (object) array(
			'end_date' => null,
		);

		$this->assertSame( 10, $this->invoke_private( 'remaining_days', array( $campaign ) ) );
		$this->assertSame( 10, Campaign_Pacing::pacing_window_days( $campaign ) );

		$label_campaign = (object) array(
			'pricing_model' => 'cpc',
			'budget'        => 10.0,
			'end_date'      => null,
		);
		$this->assertStringContainsString( '10 days', Campaign_Pacing::get_pacing_label( $label_campaign ) );

		remove_all_filters( 'wbam_pacing_default_days' );
	}

	/**
	 * Carry-over: the same remaining_days, but a different history of
	 * spend (spent minus spent_today), raises or lowers today's target.
	 */
	public function test_carry_over_shifts_the_daily_target(): void {
		// A 10-day, $100 campaign (naive target $10/day) is now on day 2:
		// 9 days including today remain to the end_date.
		$campaign = (object) array(
			'end_date' => wp_date( 'Y-m-d', time() + 8 * DAY_IN_SECONDS ),
		);

		// On-track: day 1 spent exactly its $10 target.
		$on_track   = $this->invoke_private( 'derive_daily_target', array( $campaign, 10.0, 0.0, 100.0 ) );
		// Underspent day 1: only $4 of the $10 target went out.
		$underspent = $this->invoke_private( 'derive_daily_target', array( $campaign, 4.0, 0.0, 100.0 ) );
		// Overspent day 1: $14 went out against the $10 target.
		$overspent  = $this->invoke_private( 'derive_daily_target', array( $campaign, 14.0, 0.0, 100.0 ) );

		$this->assertEqualsWithDelta( 10.0, $on_track, 0.01, 'On-track spend leaves tomorrow at the same $10/day rate.' );
		$this->assertGreaterThan( $on_track, $underspent, 'Underspending carries the difference forward and raises tomorrow\'s target.' );
		$this->assertLessThan( $on_track, $overspent, 'Overspending carries the difference forward and lowers tomorrow\'s target.' );
	}

	/** Pacing composes with tiering: a throttled paid ad still yields to a house ad. */
	public function test_paid_first_tiering_unaffected_by_pacing(): void {
		add_filter( 'wbam_pacing_minimum_delivery_floor', '__return_zero' );

		$paid_ad = $this->make_ad_and_campaign( 10.0, 1.0 );
		$this->bill_click( $paid_ad, 1.0 );
		$this->bill_click( $paid_ad, 1.0 ); // Throttled: over the ceiling, floor disabled.

		$house_ad = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $house_ad, '_wbam_enabled', '1' );
		update_post_meta( $house_ad, '_wbam_ad_data', array( 'type' => 'code', 'code' => '<span>House ad</span>' ) );
		update_post_meta( $house_ad, '_wbam_placements', array( 'header' ) );
		update_post_meta( $house_ad, '_wbam_priority', 10 );
		$this->ad_ids[] = $house_ad;

		$engine = Placement_Engine::get_instance();
		$engine->clear_placement_cache( 0 );
		$this->assertSame(
			array( $house_ad ),
			$engine->get_ads_for_placement( 'header' ),
			'The paid ad is paced out, but the paid tier still tries first - the house ad fills the slot instead of the slot going empty.'
		);

		remove_filter( 'wbam_pacing_minimum_delivery_floor', '__return_zero' );

		// On pace again: the paid ad still outranks the house ad. Reset the
		// pacing decision cache too - it's a per-request static memo keyed
		// by campaign ID, and the first assertion already cached "blocked".
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'wbam_campaigns', array( 'spent' => 0, 'spent_today' => 0 ), array( 'ad_id' => $paid_ad ) );
		$this->reset_pacing_cache();
		$engine->clear_placement_cache( 0 );
		$this->assertSame(
			array( $paid_ad ),
			$engine->get_ads_for_placement( 'header' ),
			'On pace, the paid tier wins the slot as usual.'
		);
	}
}
