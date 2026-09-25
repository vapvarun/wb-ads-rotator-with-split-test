<?php
/**
 * Campaign billing does not depend on analytics or consent.
 *
 * Regression guard for Basecamp card 10342341754: Analytics_Tracker only
 * charged a campaign after writing the analytics row, so turning analytics
 * off, declining consent or not tracking logged-in visitors made every
 * CPM/CPC campaign free. Ownership is in plan/free-pro-architecture-contract.md
 * (Analytics event ownership).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Modules\Placements\Placement_Engine;
use WBAM_Pro\Core\Pro_Plugin;
use WBAM_Pro\Core\Settings_Helper;

class Test_Billing_Independent_Of_Analytics extends Pro_Test_Case {

	private int $ad_id;

	private int $campaign_id = 0;

	private int $advertiser_id = 0;

	private int $advertiser_user = 0;

	private $user_agent;

	/** @var int[] */
	private array $users = array();

	public function set_up(): void {
		parent::set_up();

		// Campaign_Manager sets its dedup cookie with setcookie(); under the
		// CLI runner headers are already out, so drop only that warning and
		// let every other one through to PHPUnit.
		$previous = set_error_handler(
			static function ( $errno, $errstr, ...$rest ) use ( &$previous ) {
				if ( false !== strpos( $errstr, 'headers already sent' ) ) {
					return true;
				}
				return $previous ? $previous( $errno, $errstr, ...$rest ) : false;
			}
		);

		$this->user_agent           = $_SERVER['HTTP_USER_AGENT'] ?? null;
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 Safari/605.1.15';
		unset( $_COOKIE['wbam_camp_imp'], $_COOKIE['wbam_camp_clk'] );

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
				'content' => 'Billing body',
			)
		);

		$this->make_campaign( 'cpm_cpc' );
	}

	/**
	 * Campaign::record_impression() runs its own START TRANSACTION/COMMIT,
	 * which commits the test's wrapping transaction, so the fixtures would
	 * outlive the rollback and leak into later tests. Delete them and commit.
	 */
	public function tear_down(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'wbam_campaigns', array( 'id' => $this->campaign_id ) );
		$wpdb->delete( $wpdb->prefix . 'wbam_analytics', array( 'ad_id' => $this->ad_id ) );
		$wpdb->delete( $wpdb->prefix . 'wbam_advertisers', array( 'id' => $this->advertiser_id ) );
		wp_delete_post( $this->ad_id, true );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $this->users as $user_id ) {
			wp_delete_user( $user_id );
		}
		$wpdb->query( 'COMMIT' );
		// phpcs:enable

		unset( $_COOKIE['wbam_camp_imp'], $_COOKIE['wbam_camp_clk'] );
		if ( null === $this->user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->user_agent;
		}
		wp_set_current_user( 0 );
		restore_error_handler();

		parent::tear_down();
	}

	private function user( string $role ): int {
		$this->users[] = (int) self::factory()->user->create( array( 'role' => $role ) );

		return end( $this->users );
	}

	private function make_campaign( string $model ): void {
		global $wpdb;

		$this->advertiser_user = $this->user( 'subscriber' );

		// User ids are reused after rollback, but advertiser rows written by
		// other tests' committed transactions are not; clear any for this id.
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
				'name'           => 'Billing probe',
				'status'         => 'active',
				'pricing_model'  => $model,
				'price_per_unit' => 5.0,
				'budget'         => 0,
				'spent'          => 0,
			)
		);
		$this->campaign_id = (int) $wpdb->insert_id;

		update_post_meta( $this->ad_id, '_wbam_campaign_id', $this->campaign_id );
	}

	/**
	 * @return array{impressions:int,clicks:int,spent:float}
	 */
	private function billed(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test assertion on a known table.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT impressions, clicks, spent FROM {$wpdb->prefix}wbam_campaigns WHERE id = %d", $this->campaign_id ) );

		return array(
			'impressions' => (int) $row->impressions,
			'clicks'      => (int) $row->clicks,
			'spent'       => round( (float) $row->spent, 4 ),
		);
	}

	private function rows(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test assertion on a known table.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wbam_analytics WHERE ad_id = %d", $this->ad_id ) );
	}

	private function render(): string {
		return Placement_Engine::get_instance()->render_ad(
			$this->ad_id,
			array(
				'placement'       => 'header',
				'allow_duplicate' => true,
				'skip_targeting'  => true,
			)
		);
	}

	/** Render-time impression plus a click, through the real entry points. */
	private function view_and_click(): void {
		$this->render();
		do_action( 'wbam_ad_clicked', $this->ad_id, 'header' );
	}

	private function assert_billed_once(): void {
		$this->assertSame(
			array(
				'impressions' => 1,
				'clicks'      => 1,
				'spent'       => 5.005, // 5/1000 for the impression + 5 for the click.
			),
			$this->billed()
		);
	}

	private function assert_not_billed(): void {
		$this->assertSame(
			array(
				'impressions' => 0,
				'clicks'      => 0,
				'spent'       => 0.0,
			),
			$this->billed()
		);
	}

	public function test_analytics_off_still_bills_once_without_a_row(): void {
		$this->view_and_click();

		$this->assert_billed_once();
		$this->assertSame( 0, $this->rows() );
	}

	public function test_consent_declined_still_bills_without_a_row(): void {
		Settings_Helper::update( 'enable_analytics', true );
		Settings_Helper::update( 'gdpr_require_consent', true );

		$this->view_and_click();

		$this->assert_billed_once();
		$this->assertSame( 0, $this->rows() );
	}

	public function test_untracked_logged_in_visitor_still_bills_without_a_row(): void {
		Settings_Helper::update( 'enable_analytics', true );
		Settings_Helper::update( 'track_logged_in', false );
		wp_set_current_user( $this->user( 'subscriber' ) );

		$this->view_and_click();

		$this->assert_billed_once();
		$this->assertSame( 0, $this->rows() );
	}

	/** Bots never bill, even with the bot-filtering setting off. */
	public function test_bot_is_not_billed(): void {
		Settings_Helper::update( 'enable_analytics', true );
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

		$this->view_and_click();

		$this->assert_not_billed();
	}

	public function test_admin_preview_is_not_billed(): void {
		Settings_Helper::update( 'enable_analytics', true );
		wp_set_current_user( $this->user( 'administrator' ) );

		$this->view_and_click();

		$this->assert_not_billed();
	}

	public function test_advertisers_own_view_is_not_billed(): void {
		Settings_Helper::update( 'enable_analytics', true );
		wp_set_current_user( $this->advertiser_user );

		$this->view_and_click();

		$this->assert_not_billed();
	}

	/**
	 * Pixel mode: render leaves the impression to the beacon, and the beacon
	 * request (handle_pixel_tracking() -> track_event(), which then exits)
	 * bills it once.
	 */
	public function test_pixel_mode_bills_once(): void {
		Settings_Helper::update( 'enable_pixel_tracking', true );

		$output = $this->render();
		$this->assertStringContainsString( 'wbam_track=1', $output );
		$this->assertSame( 0, $this->billed()['impressions'], 'Render must not bill when the beacon will.' );

		Pro_Plugin::get_instance()->get_module( 'analytics' )->track_event( $this->ad_id, 'impression', 'header' );

		$this->assertSame( 1, $this->billed()['impressions'] );
	}

	public function test_repeat_within_session_is_deduped(): void {
		$this->view_and_click();
		$this->view_and_click();

		$this->assert_billed_once();
	}

	public function test_analytics_on_writes_one_row_and_bills_once(): void {
		Settings_Helper::update( 'enable_analytics', true );

		$this->view_and_click();

		$this->assert_billed_once();
		$this->assertSame( 2, $this->rows(), 'One impression row and one click row.' );
	}

	/** A pixel URL can only record an impression; type=click must not bill. */
	public function test_pixel_accepts_impressions_only(): void {
		$this->assertSame( 'impression', \WBAM_Pro\Modules\Analytics\Analytics_Tracker::pixel_event_type( 'impression' ) );
		$this->assertSame( '', \WBAM_Pro\Modules\Analytics\Analytics_Tracker::pixel_event_type( 'click' ) );
		$this->assertSame( '', \WBAM_Pro\Modules\Analytics\Analytics_Tracker::pixel_event_type( '' ) );
	}
}
