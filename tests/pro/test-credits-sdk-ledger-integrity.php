<?php
/**
 * Credits SDK 1.9.0 on real MySQL, through the copy Pro bundles.
 *
 * The SDK's own suite runs on an in-memory fake; this proves the parts a
 * fake cannot: the ALTERs that bring an old ledger up to date, MySQL named
 * locks, hold-by-id settling on a real table, legacy rows, UTC date filters
 * and transaction rollback (docs/AUDIT-2026-09-27.md in the SDK repo).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use Wbcom\Credits\Credits;
use Wbcom\Credits\Ledger;
use WBAM_Pro\Core\Credits_Bridge;

class Test_Credits_Sdk_Ledger_Integrity extends Pro_Test_Case {

	private int $user;

	public function set_up(): void {
		// The coupon and webhook tests configure a gateway and a coupon:
		// put both back so later tests still see a site with no payment
		// method.
		$this->snapshot_options( array( 'wbcom_credits_gateway_settings_' . Credits_Bridge::SLUG, 'wbcom_credits_coupons_' . Credits_Bridge::SLUG ) );
		parent::set_up();
		$this->user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		Credits::topup( Credits_Bridge::SLUG, $this->user, 10000, 'seed' );
	}

	private function balance(): int {
		Credits::invalidate_cache( Credits_Bridge::SLUG, $this->user );
		return Credits::get_balance( Credits_Bridge::SLUG, $this->user );
	}

	public function test_the_bundled_sdk_is_1_9_4(): void {
		$this->assertSame( '1.9.4', WBCOM_CREDITS_SDK_VERSION );
	}

	public function test_an_old_ledger_table_is_upgraded_in_place(): void {
		global $wpdb;
		$table = Ledger::table_name( 'wbamold' );
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		$wpdb->query(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				entry_type VARCHAR(20) NOT NULL,
				amount INT NOT NULL,
				note VARCHAR(255) NOT NULL DEFAULT '',
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				INDEX idx_user_id (user_id)
			)"
		);
		$wpdb->insert( $table, array( 'user_id' => 7, 'entry_type' => 'topup', 'amount' => 50 ) );

		Ledger::maybe_create_table( 'wbamold' );
		Ledger::maybe_create_table( 'wbamold' );

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
		foreach ( array( 'expires_at', 'reason', 'reference', 'hold_id' ) as $col ) {
			$this->assertContains( $col, $columns );
		}
		$keys = array_unique( $wpdb->get_col( "SHOW INDEX FROM {$table}", 2 ) );
		foreach ( array( 'idx_user_item_type', 'idx_hold', 'idx_reason', 'idx_user_created' ) as $key ) {
			$this->assertContains( $key, $keys );
		}
		$this->assertSame( '50', $wpdb->get_var( "SELECT SUM(amount) FROM {$table}" ), 'Existing rows survive.' );
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	public function test_hold_settle_release_by_id(): void {
		$hold = Credits::try_hold( Credits_Bridge::SLUG, $this->user, 3000, 55 );
		$this->assertIsInt( $hold );
		$this->assertSame( 7000, $this->balance() );

		$this->assertIsInt( Credits::settle_hold( Credits_Bridge::SLUG, $this->user, $hold ) );
		$this->assertFalse( Credits::settle_hold( Credits_Bridge::SLUG, $this->user, $hold ) );
		$this->assertFalse( Credits::release_hold( Credits_Bridge::SLUG, $this->user, $hold ) );
		Credits::cancel_hold( Credits_Bridge::SLUG, $this->user, 55 );
		$this->assertSame( 7000, $this->balance(), 'A settled hold is never undone.' );

		$this->assertFalse( Credits::try_hold( Credits_Bridge::SLUG, $this->user, 7001, 56 ) );
		$this->assertFalse( Credits::deduct( Credits_Bridge::SLUG, $this->user, 100, 57 ), 'No open hold, no charge.' );
		$this->assertSame( 7000, $this->balance() );
	}

	public function test_legacy_rows_are_read_by_net_amount(): void {
		Ledger::insert( Credits_Bridge::PREFIX, $this->user, 'hold', -500, 60, 'old hold' );
		Ledger::insert( Credits_Bridge::PREFIX, $this->user, 'refund', 500, 60, 'Hold released on approval' );
		Ledger::insert( Credits_Bridge::PREFIX, $this->user, 'deduction', -500, 60, 'Credits deducted' );
		Ledger::insert( Credits_Bridge::PREFIX, $this->user, 'hold', -200, 61, 'old open hold' );

		$this->assertSame( array(), Ledger::open_holds( Credits_Bridge::PREFIX, $this->user, 60 ) );
		$this->assertCount( 1, Ledger::open_holds( Credits_Bridge::PREFIX, $this->user, 61 ) );
		$this->assertTrue( Credits::deduct( Credits_Bridge::SLUG, $this->user, 200, 61 ) );
		$this->assertFalse( Credits::deduct( Credits_Bridge::SLUG, $this->user, 200, 61 ) );
		$this->assertSame( 10000 - 500 - 200, $this->balance() );
	}

	public function test_spend_takes_a_mysql_named_lock(): void {
		global $wpdb;
		$seen = null;
		Credits::with_user_lock(
			Credits_Bridge::SLUG,
			$this->user,
			function () use ( $wpdb, &$seen ) {
				$name = 'wbcc_' . substr( md5( Ledger::table_name( Credits_Bridge::PREFIX ) ), 0, 16 ) . '_' . $this->user;
				$seen = $wpdb->get_var( $wpdb->prepare( 'SELECT IS_USED_LOCK( %s )', $name ) );
				// Re-entrant: a spend inside the lock runs.
				$this->assertIsInt( Credits::spend( Credits_Bridge::SLUG, $this->user, 100, 0, 'inside' ) );
			}
		);
		$this->assertNotNull( $seen, 'The lock is held while the callback runs.' );
		$this->assertSame( 9900, $this->balance() );
	}

	public function test_query_ledger_filters_on_mysql(): void {
		global $wpdb;
		Credits::spend( Credits_Bridge::SLUG, $this->user, 100, 1 );
		Credits::spend( Credits_Bridge::SLUG, $this->user, 250, 2 );
		$wpdb->update( Ledger::table_name( Credits_Bridge::PREFIX ), array( 'created_at' => '2025-01-01 00:00:00' ), array( 'user_id' => $this->user, 'reason' => 'topup' ) );

		$rows = Credits::query_ledger( Credits_Bridge::SLUG, array( 'user_id' => $this->user, 'reason' => array( 'spend' ) ) );
		$this->assertCount( 2, $rows );
		$this->assertSame( '2', (string) $rows[0]->item_id );
		$this->assertSame( -350, Credits::sum_ledger( Credits_Bridge::SLUG, array( 'user_id' => $this->user, 'reason' => 'spend' ) ) );
		$this->assertSame( 2, Credits::count_ledger_rows( Credits_Bridge::SLUG, array( 'user_id' => $this->user, 'since' => '2026-01-01 00:00:00' ) ) );
		$this->assertSame( 1, Credits::count_ledger_rows( Credits_Bridge::SLUG, array( 'user_id' => $this->user, 'until' => '2026-01-01 00:00:00' ) ) );
	}

	public function test_topup_once_is_atomic_on_mysql(): void {
		$this->assertIsInt( Credits::topup_once( Credits_Bridge::SLUG, 'adapter:test', 'order:' . $this->user, $this->user, 500 ) );
		$this->assertNull( Credits::topup_once( Credits_Bridge::SLUG, 'adapter:test', 'order:' . $this->user, $this->user, 500 ) );
		$this->assertSame( 10500, $this->balance() );
	}

	public function test_charge_goes_through_spend_and_refuses_an_overdraft(): void {
		$advertiser = \WBAM_Pro\Modules\Advertisers\Advertiser_Manager::get_instance()->get_or_create_member( $this->user );
		$major      = Credits::is_money( Credits_Bridge::SLUG ) ? Credits::balance_money( Credits_Bridge::SLUG, $this->user ) : 10000.0;

		$row = Credits_Bridge::charge( (int) $advertiser->id, $major / 2, 9, 'half', false, \WBAM_Pro\Core\Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertIsInt( $row );

		$refused = Credits_Bridge::charge( (int) $advertiser->id, $major, 9, 'too much', false, \WBAM_Pro\Core\Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertWPError( $refused );
		$this->assertSame( 'wbam_credits_insufficient', $refused->get_error_code() );

		$rows = Credits::query_ledger( Credits_Bridge::SLUG, array( 'user_id' => $this->user, 'reason' => 'spend' ) );
		$this->assertCount( 1, $rows );
		$this->assertSame( 5000, $this->balance() );
	}

	// SDK 1.9.2 on MySQL ------------------------------------------------

	public function test_a_lock_taken_in_a_transaction_is_held_until_commit(): void {
		global $wpdb;
		$name = 'wbcc_' . substr( md5( Ledger::table_name( Credits_Bridge::PREFIX ) ), 0, 16 ) . '_' . $this->user;

		Ledger::begin();
		Credits::with_user_lock( Credits_Bridge::SLUG, $this->user, static fn () => true );
		$this->assertNotNull( $wpdb->get_var( $wpdb->prepare( 'SELECT IS_USED_LOCK( %s )', $name ) ), 'Held after the callback, while the transaction is open.' );
		Ledger::commit();
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SELECT IS_USED_LOCK( %s )', $name ) ) );
	}

	public function test_a_refund_credit_is_not_a_purchase(): void {
		global $wpdb;
		$advertiser = \WBAM_Pro\Modules\Advertisers\Advertiser_Manager::get_instance()->get_or_create_member( $this->user );
		$emails     = did_action( 'wbam_credits_added' );

		$row = Credits_Bridge::credit( (int) $advertiser->id, 2.5, 55, 'Refund: ad 55', \WBAM_Pro\Core\Revenue_Ledger::SOURCE_AD_PACKAGE );

		$this->assertIsInt( $row );
		$ledger = Credits::get_ledger_row( Credits_Bridge::SLUG, $row );
		$this->assertSame( 'topup', $ledger->entry_type );
		$this->assertSame( 'refund', $ledger->reason );
		$this->assertSame( '55', (string) $ledger->item_id );
		$this->assertSame( $emails, did_action( 'wbam_credits_added' ), 'No "funds added" email for a refund.' );
	}

	public function test_a_paid_top_up_books_revenue_on_its_own_row(): void {
		global $wpdb;
		$id    = Credits::topup( Credits_Bridge::SLUG, $this->user, 777, 'same note' );
		$again = Credits::topup( Credits_Bridge::SLUG, $this->user, 777, 'same note' );
		$table = \WBAM_Pro\Core\Revenue_Ledger::table_name();

		$booked = $wpdb->get_col( $wpdb->prepare( "SELECT ledger_id FROM {$table} WHERE ledger_id IN (%d, %d) ORDER BY ledger_id", $id, $again ) ); // phpcs:ignore WordPress.DB
		$this->assertSame( array( (string) $id, (string) $again ), $booked, 'Each top-up gets its own revenue row (ledger id from the event).' );
	}

	public function test_grouped_totals_run_on_mysql(): void {
		$other = (int) self::factory()->user->create();
		Credits::topup( Credits_Bridge::SLUG, $other, 500, 'other' );
		Credits::spend( Credits_Bridge::SLUG, $this->user, 100 );

		$by_user = Credits::sum_ledger_grouped( Credits_Bridge::SLUG, array( 'user_ids' => array( $this->user, $other ) ), 'user_id' );
		$this->assertSame( 9900, $by_user[ (string) $this->user ]['total'] );
		$this->assertSame( 500, $by_user[ (string) $other ]['total'] );
	}

	public function test_a_limit_one_free_coupon_credits_one_of_two_buyers(): void {
		update_option( 'wbcom_credits_gateway_settings_' . Credits_Bridge::SLUG, array( 'stripe' => array( 'enabled' => '1', 'mode' => 'test', 'secret_key_test' => 'sk_test_x', 'publishable_key_test' => 'pk_test_x' ) ) );
		update_option( \Wbcom\Credits\Gateways\Coupons::option_name( Credits_Bridge::SLUG ), array( 'FREEONE' => array( 'type' => 'percent', 'amount' => 100, 'expires' => '', 'usage_limit' => 1, 'active' => true ) ) );

		$buy = function ( int $user ) {
			wp_set_current_user( $user );
			$request = new \WP_REST_Request( 'POST' );
			$request->set_param( 'gateway', 'stripe' );
			$request->set_param( 'credits', 10 );
			$request->set_param( 'coupon', 'FREEONE' );
			$request->set_param( 'billing', array( 'billing_first_name' => 'A', 'billing_last_name' => 'B', 'billing_email' => 'a@b.test', 'billing_country' => 'US' ) );
			return ( new \Wbcom\Credits\Gateways\Webhook_Controller( Credits_Bridge::SLUG ) )->create_checkout( $request );
		};
		$second_user = (int) self::factory()->user->create();

		$first  = $buy( $this->user );
		$second = $buy( $second_user );

		$this->assertNotWPError( $first );
		$this->assertWPError( $second );
		$this->assertSame( 'coupon_used_up', $second->get_error_code() );
		$this->assertSame( 0, Credits::get_balance( Credits_Bridge::SLUG, $second_user ) );
	}

	public function test_pack_buy_repeat_webhook_and_two_partial_refunds(): void {
		$stripe  = new \Wbcom\Credits\Gateways\Stripe();
		$session = 'cs_mysql_' . $this->user;
		$minor   = \Wbcom\Credits\Money::to_minor( 10, wbam_get_currency_code() );
		\Wbcom\Credits\Gateways\Pending_Checkouts::put(
			Credits_Bridge::SLUG,
			$session,
			array( 'gateway' => 'stripe', 'user_id' => $this->user, 'credits' => 10, 'price_cents' => 1000, 'currency' => wbam_get_currency_code() )
		);
		$paid = array(
			'id'   => 'evt_paid_' . $this->user,
			'type' => 'checkout.session.completed',
			'data' => array( 'object' => array( 'id' => $session, 'payment_status' => 'paid', 'amount_total' => 1000, 'currency' => strtolower( wbam_get_currency_code() ), 'payment_intent' => 'pi_' . $this->user ) ),
		);

		$stripe->handle_webhook( Credits_Bridge::SLUG, $paid );
		$stripe->handle_webhook( Credits_Bridge::SLUG, $paid ); // Provider retry.
		$this->assertSame( 10000 + $minor, $this->balance(), 'Credited once.' );

		foreach ( array( 300, 600 ) as $n => $cumulative ) { // Stripe sends the running total.
			$stripe->handle_webhook(
				Credits_Bridge::SLUG,
				array(
					'id'   => 'evt_refund_' . $n . '_' . $this->user,
					'type' => 'charge.refunded',
					'data' => array( 'object' => array( 'payment_intent' => 'pi_' . $this->user, 'amount_refunded' => $cumulative, 'currency' => strtolower( wbam_get_currency_code() ), 'metadata' => array( 'wbcom_session' => $session ) ) ),
				)
			);
		}
		$this->assertSame( 10000 + $minor - (int) floor( $minor * 600 / 1000 ), $this->balance(), 'Two refunds take 60% back, not 90%.' );
	}
}
