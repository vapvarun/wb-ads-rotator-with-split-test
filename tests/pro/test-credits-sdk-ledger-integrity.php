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
		parent::set_up();
		$this->user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		Credits::topup( Credits_Bridge::SLUG, $this->user, 10000, 'seed' );
	}

	private function balance(): int {
		Credits::invalidate_cache( Credits_Bridge::SLUG, $this->user );
		return Credits::get_balance( Credits_Bridge::SLUG, $this->user );
	}

	public function test_the_bundled_sdk_is_1_9_1(): void {
		$this->assertSame( '1.9.1', WBCOM_CREDITS_SDK_VERSION );
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
}
