<?php
/**
 * First-run payments spec (cards 10342783654 / 10342784279, owner decision
 * 10): a credit through Adjust Balance must let the admin say whether real
 * cash was received ("Payment received offline", counted as revenue) or the
 * credit is a courtesy grant ("Complimentary credit", not counted). A fresh
 * PRO install must start in Publisher mode, not Full, and owe a rewrite
 * flush so single listings and /seller/ do not 404.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Installer;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Core\Site_Mode;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_First_Run_3_2_Payments extends Pro_Test_Case {

	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$user             = (int) self::factory()->user->create();
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
	}

	private function revenue_amount_minor_for_note( string $note ): ?int {
		global $wpdb;
		$table = Revenue_Ledger::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test assertion on a known table.
		$ledger_table = $wpdb->prefix . 'wbam_credit_ledger';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test assertion joining two known tables.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT r.amount, r.source FROM {$table} r INNER JOIN {$ledger_table} l ON l.id = r.ledger_id WHERE l.note = %s ORDER BY r.id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$note
			)
		);
		return $row ? (int) $row->amount : null;
	}

	/**
	 * Owner decision 10: "Payment received offline" counts as revenue - the
	 * revenue row must be POSITIVE, same sign as the credit ledger row,
	 * because real cash came in.
	 */
	public function test_offline_payment_credit_records_positive_revenue(): void {
		$result = Advertiser_Manager::get_instance()->adjust_balance(
			$this->advertiser->id,
			25.00,
			'offline-payment-test',
			Revenue_Ledger::SOURCE_OFFLINE_PAYMENT
		);

		$this->assertTrue( $result );
		$amount = $this->revenue_amount_minor_for_note( 'offline-payment-test' );
		$this->assertNotNull( $amount, 'A revenue row must be written.' );
		$this->assertGreaterThan( 0, $amount, 'An offline payment must record positive revenue.' );
	}

	/**
	 * Owner decision 10: "Complimentary credit" is not revenue - the
	 * courtesy-grant behavior (negative revenue row) must be unchanged.
	 */
	public function test_complimentary_credit_records_negative_revenue(): void {
		$result = Advertiser_Manager::get_instance()->adjust_balance(
			$this->advertiser->id,
			25.00,
			'complimentary-credit-test',
			Revenue_Ledger::SOURCE_COMPLIMENTARY_CREDIT
		);

		$this->assertTrue( $result );
		$amount = $this->revenue_amount_minor_for_note( 'complimentary-credit-test' );
		$this->assertNotNull( $amount, 'A revenue row must be written.' );
		$this->assertLessThan( 0, $amount, 'A complimentary credit must not count as revenue.' );
	}

	/**
	 * Backward compatibility: a caller that never passes $revenue_source
	 * (every existing call site except the Adjust Balance screen) keeps the
	 * pre-3.2.0 courtesy-grant behavior.
	 */
	public function test_default_source_keeps_courtesy_grant_behavior(): void {
		$result = Advertiser_Manager::get_instance()->adjust_balance(
			$this->advertiser->id,
			25.00,
			'default-source-test'
		);

		$this->assertTrue( $result );
		$amount = $this->revenue_amount_minor_for_note( 'default-source-test' );
		$this->assertNotNull( $amount, 'A revenue row must be written.' );
		$this->assertLessThan( 0, $amount, 'The default source must keep the old negative-revenue behavior.' );
	}

	/**
	 * A debit ignores $revenue_source entirely - it always takes back value
	 * already given, so it is always positive revenue regardless of which
	 * radio (if any) was posted.
	 */
	public function test_debit_ignores_revenue_source(): void {
		Advertiser_Manager::get_instance()->adjust_balance( $this->advertiser->id, 25.00, 'debit-setup', Revenue_Ledger::SOURCE_OFFLINE_PAYMENT );

		$result = Advertiser_Manager::get_instance()->adjust_balance(
			$this->advertiser->id,
			-10.00,
			'debit-test',
			Revenue_Ledger::SOURCE_OFFLINE_PAYMENT
		);

		$this->assertTrue( $result );
		$amount = $this->revenue_amount_minor_for_note( 'debit-test' );
		$this->assertNotNull( $amount, 'A revenue row must be written.' );
		$this->assertGreaterThan( 0, $amount, 'A debit must record positive revenue regardless of $revenue_source.' );
	}

	/**
	 * Owner decision 3: a brand-new PRO install starts in Publisher, the
	 * least-exposed mode, until the wizard finishes - not FULL.
	 */
	public function test_fresh_install_applies_publisher_mode(): void {
		delete_option( 'wbam_pro_db_version' );
		delete_option( 'wbam_pro_settings' );

		Installer::install( false );

		$this->assertTrue( Site_Mode::is_applied() );
		$this->assertSame( Site_Mode::PUBLISHER, Site_Mode::current() );
	}

	/**
	 * A fresh install owes a rewrite flush (CPT permalinks + /seller/ both
	 * register on `init`, too late for the activation hook itself) -
	 * maybe_flush_rewrite_rules() must consume the flag exactly once.
	 */
	public function test_fresh_install_flags_rewrite_flush_and_is_consumed_once(): void {
		delete_option( 'wbam_pro_db_version' );
		delete_option( Installer::REWRITE_FLUSH_PENDING_OPTION );

		Installer::install( false );
		$this->assertEquals( 1, get_option( Installer::REWRITE_FLUSH_PENDING_OPTION ) );

		Installer::maybe_flush_rewrite_rules();
		$this->assertEquals( 0, get_option( Installer::REWRITE_FLUSH_PENDING_OPTION ) );
	}
}
