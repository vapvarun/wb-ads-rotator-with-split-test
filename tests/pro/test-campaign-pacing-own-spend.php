<?php
/**
 * Campaign pacing reads the campaign's own daily spend, not analytics rows.
 *
 * Regression guard for Basecamp card 10342512361: Campaign_Pacing counted
 * today's spend from wbam_analytics. Billing no longer depends on analytics
 * (card 10342341754), so events billed with analytics off, no consent or an
 * untracked logged-in visitor had no row and pacing never throttled. It also
 * bounded "today" with a UTC midnight against site-time created_at.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Modules\Placements\Placement_Engine;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Campaigns\Campaign;
use WBAM_Pro\Modules\Targeting\Campaign_Pacing;

class Test_Campaign_Pacing_Own_Spend extends Pro_Test_Case {

	private int $ad_id;

	private int $campaign_id = 0;

	private int $advertiser_id = 0;

	private int $advertiser_user = 0;

	private $user_agent;

	private $timezone_string;

	private $gmt_offset;

	public function set_up(): void {
		parent::set_up();

		$this->user_agent           = $_SERVER['HTTP_USER_AGENT'] ?? null;
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 Safari/605.1.15';
		unset( $_COOKIE['wbam_camp_imp'], $_COOKIE['wbam_camp_clk'] );

		$this->timezone_string = get_option( 'timezone_string' );
		$this->gmt_offset      = get_option( 'gmt_offset' );

		Settings_Helper::update( 'enable_analytics', false );
		Settings_Helper::update( 'track_logged_in', true );
		Settings_Helper::update( 'enable_bot_filtering', false );
		Settings_Helper::update( 'gdpr_require_consent', false );
		Settings_Helper::update( 'enable_pixel_tracking', false );

		$this->ad_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $this->ad_id, '_wbam_enabled', '1' );
		update_post_meta(
			$this->ad_id,
			'_wbam_ad_data',
			array(
				'type'    => 'rich-content',
				'content' => 'Pacing body',
			)
		);

		$this->reset_pacing_cache();
	}

	/**
	 * Campaign::record_*() runs its own START TRANSACTION/COMMIT, which
	 * commits the test's wrapping transaction; delete fixtures, restore the
	 * timezone and commit.
	 */
	public function tear_down(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'wbam_campaigns', array( 'id' => $this->campaign_id ) );
		$wpdb->delete( $wpdb->prefix . 'wbam_analytics', array( 'ad_id' => $this->ad_id ) );
		$wpdb->delete( $wpdb->prefix . 'wbam_advertisers', array( 'id' => $this->advertiser_id ) );
		wp_delete_post( $this->ad_id, true );
		if ( $this->advertiser_user ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $this->advertiser_user );
		}
		// Restore before COMMIT: the charge already committed the timezone
		// change, and anything after COMMIT is rolled back.
		update_option( 'timezone_string', $this->timezone_string );
		update_option( 'gmt_offset', $this->gmt_offset );
		$wpdb->query( 'COMMIT' );
		// phpcs:enable

		unset( $_COOKIE['wbam_camp_imp'], $_COOKIE['wbam_camp_clk'] );
		if ( null === $this->user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->user_agent;
		}
		wp_set_current_user( 0 );
		$this->reset_pacing_cache();

		parent::tear_down();
	}

	/**
	 * A 30-day campaign: budget 3000 gives a 100/day pacing budget, so a
	 * single 200 click is over the whole day's allowance at any hour.
	 */
	private function make_campaign( string $model, float $rate ): void {
		global $wpdb;

		$this->advertiser_user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$wpdb->delete( $wpdb->prefix . 'wbam_advertisers', array( 'user_id' => $this->advertiser_user ) );
		$wpdb->insert(
			$wpdb->prefix . 'wbam_advertisers',
			array(
				'user_id' => $this->advertiser_user,
				'status'  => 'active',
			)
		);
		$this->advertiser_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_campaigns',
			array(
				'advertiser_id'  => $this->advertiser_id,
				'ad_id'          => $this->ad_id,
				'name'           => 'Pacing probe',
				'status'         => 'active',
				'pricing_model'  => $model,
				'price_per_unit' => $rate,
				'budget'         => 3000,
				'spent'          => 0,
				'start_date'     => wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
				'end_date'       => wp_date( 'Y-m-d H:i:s', time() + 29 * DAY_IN_SECONDS ),
			)
		);
		$this->campaign_id = (int) $wpdb->insert_id;

		update_post_meta( $this->ad_id, '_wbam_campaign_id', $this->campaign_id );
	}

	private function reset_pacing_cache(): void {
		$prop = new \ReflectionProperty( Campaign_Pacing::class, 'decision_cache' );
		$prop->setValue( null, array() );
	}

	/** A fresh request's pacing decision for the ad. */
	private function paced_through(): bool {
		$this->reset_pacing_cache();
		return (bool) Campaign_Pacing::apply_pacing( true, $this->ad_id );
	}

	private function row(): object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test assertion on a known table.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wbam_campaigns WHERE id = %d", $this->campaign_id ) );
	}

	private function set_counter( float $amount, string $day ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'wbam_campaigns',
			array(
				'spent_today'      => $amount,
				'spent_today_date' => $day,
			),
			array( 'id' => $this->campaign_id )
		);
	}

	private function rows( string $type ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test assertion on a known table.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wbam_analytics WHERE ad_id = %d AND event_type = %s", $this->ad_id, $type ) );
	}

	/** A new visitor: clear the per-session dedup cookies. */
	private function new_visitor(): void {
		unset( $_COOKIE['wbam_camp_imp'], $_COOKIE['wbam_camp_clk'] );
	}

	private function render(): void {
		Placement_Engine::get_instance()->render_ad(
			$this->ad_id,
			array(
				'placement'       => 'header',
				'allow_duplicate' => true,
				'skip_targeting'  => true,
			)
		);
	}

	public function test_analytics_off_throttles_once_daily_budget_is_spent(): void {
		$this->make_campaign( 'cpc', 200 );

		$this->assertTrue( $this->paced_through(), 'Nothing spent yet today.' );

		do_action( 'wbam_ad_clicked', $this->ad_id, 'header' );

		$this->assertSame( 0, $this->rows( 'click' ), 'Analytics is off: no row.' );
		$this->assertEqualsWithDelta( 200.0, (float) $this->row()->spent, 0.0001, 'The click was billed.' );
		$this->assertFalse( $this->paced_through(), 'Billed spend is over the daily budget, so pacing must throttle.' );
	}

	/**
	 * Pick a zone whose calendar day differs from UTC's right now, so a test
	 * that reads the UTC date can't pass by accident.
	 */
	public function test_day_boundary_uses_site_local_day(): void {
		update_option( 'gmt_offset', 0 );
		update_option( 'timezone_string', (int) gmdate( 'G' ) >= 12 ? 'Pacific/Kiritimati' : 'Etc/GMT+12' );
		$this->assertNotSame( gmdate( 'Y-m-d' ), wp_date( 'Y-m-d' ), 'Fixture: site day must differ from UTC day.' );

		$this->make_campaign( 'cpc', 200 );
		$local_today     = wp_date( 'Y-m-d' );
		$local_yesterday = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS );

		$this->set_counter( 1000, gmdate( 'Y-m-d' ) );
		$this->assertTrue( $this->paced_through(), 'A counter dated the UTC day is not today on this site.' );

		$this->set_counter( 1000, $local_today );
		$this->assertFalse( $this->paced_through(), 'A counter dated the site day is today.' );

		$this->set_counter( 1000, $local_yesterday );
		$this->assertTrue( $this->paced_through(), 'Yesterday\'s spend does not count today.' );

		do_action( 'wbam_ad_clicked', $this->ad_id, 'header' );

		$row = $this->row();
		$this->assertSame( $local_today, $row->spent_today_date );
		$this->assertEqualsWithDelta( 200.0, (float) $row->spent_today, 0.0001, 'The first charge of a new day restarts the counter.' );
		$this->assertFalse( $this->paced_through() );
	}

	/** With analytics on, spend today equals what the old analytics-row math gave. */
	public function test_analytics_on_matches_analytics_row_spend(): void {
		Settings_Helper::update( 'enable_analytics', true );
		$this->make_campaign( 'cpm', 5 );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->new_visitor();
			$this->render();
		}

		$imps = $this->rows( 'impression' );
		$this->assertSame( 3, $imps );

		$from_rows = ( $imps / 1000.0 ) * 5;
		$this->assertEqualsWithDelta( $from_rows, Campaign::spent_today_from_row( $this->row() ), 0.000001 );
		$this->assertEqualsWithDelta( (float) $this->row()->spent, Campaign::spent_today_from_row( $this->row() ), 0.000001 );
	}

	/**
	 * Two requests holding the same stale campaign object both charge: the
	 * counter is incremented in SQL, so neither overwrites the other.
	 */
	public function test_increments_are_atomic_across_stale_instances(): void {
		$this->make_campaign( 'cpc', 2 );

		$first  = new Campaign( $this->campaign_id );
		$second = new Campaign( $this->campaign_id );

		$this->assertTrue( $first->record_click() );
		$this->assertTrue( $second->record_click() );
		$this->assertTrue( $second->record_click() );

		$row = $this->row();
		$this->assertSame( 3, (int) $row->clicks );
		$this->assertEqualsWithDelta( 6.0, (float) $row->spent, 0.0001 );
		$this->assertEqualsWithDelta( 6.0, (float) $row->spent_today, 0.0001 );
		$this->assertSame( wp_date( 'Y-m-d' ), $row->spent_today_date );
	}
}
