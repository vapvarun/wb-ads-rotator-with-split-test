<?php
/**
 * Regression tests for BC#10339874175 QA round 2 rejects:
 *  1. Revenue > Recent transactions must not double-apply the site timezone.
 *  4. Campaign edit must not silently unlink an ad that fell outside the
 *     admin's first 200 published ads, and must enforce ad/advertiser
 *     ownership server-side.
 *
 * Item 2 (Revenue "Other" tile / Credits_Bridge::topup()/adjust()) and item 3
 * (Display Rules category/tag round-trip) are covered separately — item 2 in
 * tests/pro/test-credits-bridge-manual-adjustment-revenue.php (its own
 * commit, once QA releases the wallet lane) and item 3 in
 * tests/free/test-display-rules-term-id-round-trip.php.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Revenue_Dashboard;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Core\Revenue_Query;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;

class Test_Admin_Screens_R2 extends Pro_Test_Case {

	private int $user;
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 100000, 'seed' );

		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'wbam_revenue' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation.
	}

	public function tear_down(): void {
		update_option( 'timezone_string', '' );
		parent::tear_down();
	}

	/**
	 * Item 1: Revenue > Recent transactions must show the same wall-clock
	 * time as Transactions — Revenue_Query::recent() already converts
	 * created_at with get_date_from_gmt(); render_recent() must not convert
	 * it a second time.
	 */
	public function test_recent_transactions_time_matches_ledger_under_non_utc_timezone(): void {
		update_option( 'timezone_string', 'Asia/Kolkata' );

		$ledger_id = Credits_Bridge::charge( $this->advertiser->id, 10.00, 1, 'tz check', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertNotWPError( $ledger_id );

		global $wpdb;
		$ledger_table      = $wpdb->prefix . 'wbam_credit_ledger';
		$ledger_created_at = $wpdb->get_var( $wpdb->prepare( "SELECT created_at FROM {$ledger_table} WHERE id = %d", $ledger_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- SDK table, id bound via prepare().

		// This is exactly how Transactions_List_Table::column_created_at()
		// derives the time shown on the Transactions screen.
		$expected_timestamp = Revenue_Ledger::ledger_timestamp( $ledger_created_at );
		$expected            = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expected_timestamp );

		$recent = Revenue_Query::recent( gmdate( 'Y-m-d', strtotime( '-1 day' ) ), gmdate( 'Y-m-d', strtotime( '+1 day' ) ) );
		$this->assertNotEmpty( $recent, 'recent() must return the row just charged.' );

		$dashboard = Revenue_Dashboard::get_instance();
		$method    = new \ReflectionMethod( $dashboard, 'render_recent' );
		$method->setAccessible( true );

		ob_start();
		$method->invoke( $dashboard, $recent );
		$html = ob_get_clean();

		$this->assertStringContainsString(
			esc_html( $expected ),
			$html,
			'Revenue > Recent transactions must show the same wall-clock time as Transactions, not shift it a second time.'
		);
	}

	/**
	 * Item 4 (select): the Ad picker must include the campaign's currently
	 * linked ad even when that ad is not published (pending/scheduled/draft)
	 * and would otherwise fall outside the publish-only query.
	 */
	public function test_campaign_form_ad_select_includes_pending_linked_ad(): void {
		$ad_id = self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'pending',
				'post_title'  => 'QA Pending Ad',
			)
		);
		update_post_meta( $ad_id, '_wbam_advertiser_id', $this->advertiser->id );

		$campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id' => $this->advertiser->id,
				'ad_id'         => $ad_id,
				'name'          => 'QA Campaign',
			)
		);
		$this->assertNotWPError( $campaign );

		$admin  = new Pro_Admin();
		$method = new \ReflectionMethod( $admin, 'render_campaign_form' );
		$method->setAccessible( true );

		ob_start();
		$method->invoke( $admin, $campaign->id );
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression(
			'/<option value="' . $ad_id . '"[^>]*selected/',
			$html,
			'A pending ad already linked to the campaign must appear pre-selected in the Ad picker, not silently fall out of the list.'
		);
	}

	/**
	 * Item 4 (ownership): the save handler must reject an ad that belongs to
	 * a different advertiser than the one selected on the campaign, not just
	 * rely on the JS-side filter.
	 */
	public function test_campaign_save_rejects_ad_owned_by_different_advertiser(): void {
		$other_user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$other_advertiser = Advertiser_Manager::get_instance()->get_or_create( $other_user );

		$foreign_ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $foreign_ad_id, '_wbam_advertiser_id', $other_advertiser->id );

		$campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id' => $this->advertiser->id,
				'name'          => 'QA Ownership Campaign',
			)
		);
		$this->assertNotWPError( $campaign );

		$admin_user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user_id );

		$_POST = array(
			'wbam_save_campaign' => '1',
			'wbam_campaign_nonce' => wp_create_nonce( 'wbam_save_campaign' ),
			'campaign_id'         => (string) $campaign->id,
			'name'                => 'QA Ownership Campaign',
			'advertiser_id'       => (string) $this->advertiser->id,
			'ad_id'               => (string) $foreign_ad_id,
			'pricing_model'       => 'flat',
			'price_per_unit'      => '0',
			'status'              => 'draft',
		);
		// check_admin_referer() reads the nonce from $_REQUEST, which PHP does
		// not auto-sync from a reassigned $_POST outside a real HTTP request.
		$_REQUEST = $_POST;

		$admin  = new Pro_Admin();
		$method = new \ReflectionMethod( $admin, 'handle_campaign_form_save' );
		$method->setAccessible( true );

		ob_start();
		$method->invoke( $admin );
		$html = ob_get_clean();

		$_POST    = array();
		$_REQUEST = array();

		$this->assertStringContainsString( 'different advertiser', $html, 'Linking an ad owned by another advertiser must be rejected with a notice.' );

		$reloaded = Campaign_Manager::get_instance()->get( $campaign->id );
		$this->assertSame( 0, (int) $reloaded->ad_id, 'The rejected ad must not have been linked.' );
	}

	/**
	 * Item 4 (no silent unlink): Campaign_Manager::update() only touches
	 * ad_id when the caller's $data array actually carries the key — this is
	 * what the (now fixed) admin handler relies on when ad_id was not posted.
	 * Explicitly posting "0" ("— None —") still clears the link.
	 */
	public function test_update_without_ad_id_key_preserves_existing_link(): void {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $ad_id, '_wbam_advertiser_id', $this->advertiser->id );

		$campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id' => $this->advertiser->id,
				'ad_id'         => $ad_id,
				'name'          => 'QA No-Change Campaign',
			)
		);
		$this->assertNotWPError( $campaign );

		$result = Campaign_Manager::get_instance()->update(
			$campaign->id,
			array( 'name' => 'QA No-Change Campaign (renamed)' )
		);
		$this->assertNotWPError( $result );

		$reloaded = Campaign_Manager::get_instance()->get( $campaign->id );
		$this->assertSame( $ad_id, (int) $reloaded->ad_id, 'A save with no ad_id key must not unlink the existing ad.' );

		$cleared = Campaign_Manager::get_instance()->update( $campaign->id, array( 'ad_id' => 0 ) );
		$this->assertNotWPError( $cleared );
		$reloaded_after_clear = Campaign_Manager::get_instance()->get( $campaign->id );
		$this->assertSame( 0, (int) $reloaded_after_clear->ad_id, 'Explicitly posting ad_id=0 must still clear the link.' );
	}

	/** Posts the campaign form as an admin; returns the refusal notice, or 'saved' when it redirected. */
	private function post_campaign_form( array $fields ): string {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST    = array_merge(
			array(
				'wbam_save_campaign'  => '1',
				'wbam_campaign_nonce' => wp_create_nonce( 'wbam_save_campaign' ),
				'advertiser_id'       => (string) $this->advertiser->id,
				'status'              => 'draft',
			),
			$fields
		);
		$_REQUEST = $_POST;
		$redirect = static function () {
			throw new \RuntimeException( 'saved' );
		};
		add_filter( 'wp_redirect', $redirect );

		$method = new \ReflectionMethod( Pro_Admin::class, 'handle_campaign_form_save' );
		$method->setAccessible( true );
		ob_start();
		try {
			$method->invoke( new Pro_Admin() );
			$out = ob_get_clean();
		} catch ( \RuntimeException $e ) {
			ob_end_clean();
			$out = $e->getMessage();
		}
		remove_filter( 'wp_redirect', $redirect );
		$_POST    = array();
		$_REQUEST = array();
		return $out;
	}

	/** Round 2: a package campaign is flat with budget = price paid; saving it unchanged must work. */
	public function test_flat_package_campaign_with_budget_saves(): void {
		$campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id' => $this->advertiser->id,
				'name'          => 'Starter campaign',
			)
		);
		$result = $this->post_campaign_form(
			array(
				'campaign_id'       => (string) $campaign->id,
				'name'              => 'Starter campaign',
				'pricing_model'     => 'flat',
				'price_per_unit'    => '0',
				'budget'            => '49',
				'impressions_limit' => '10000',
			)
		);
		$this->assertSame( 'saved', $result );
	}

	/** The guard still refuses a hand-made metered campaign that can never spend. */
	public function test_zero_rate_cpm_campaign_with_budget_is_refused(): void {
		$result = $this->post_campaign_form(
			array(
				'name'           => 'Broken CPM',
				'pricing_model'  => 'cpm',
				'price_per_unit' => '0',
				'budget'         => '50',
			)
		);
		$this->assertStringContainsString( 'rate is zero', $result );
	}
}
