<?php
/**
 * Revenue backfill — classifies pre-existing (pre-3.2.0) credit-ledger rows
 * by proving the item exists and belongs to the advertiser. Never guesses:
 * unproven or colliding items land on `unclassified`.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Revenue_Backfill extends Pro_Test_Case {

	private int $user;
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 100000, 'seed' );

		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'wbam_revenue' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation.

		delete_option( 'wbam_pro_revenue_backfill' );
	}

	/**
	 * Write a ledger row directly via the SDK primitive, bypassing
	 * Credits_Bridge/Revenue_Ledger entirely — simulating a row written
	 * before 3.2.0 existed.
	 */
	private function write_legacy_ledger_row( string $entry_type, int $amount, int $item_id, string $note ): int {
		return (int) \Wbcom\Credits\Ledger::insert( 'wbam', $this->user, $entry_type, $amount, $item_id, $note );
	}

	private function run_backfill(): void {
		global $wpdb;

		$max_id = (int) $wpdb->get_var( 'SELECT MAX(id) FROM ' . $wpdb->prefix . 'wbam_credit_ledger' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		update_option(
			'wbam_pro_revenue_backfill',
			array(
				'v'      => 1,
				'cursor' => 0,
				'max_id' => $max_id,
			)
		);

		Revenue_Ledger::run_backfill_batch();
	}

	private function revenue_row_for_ledger( int $ledger_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . 'wbam_revenue WHERE ledger_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$ledger_id
			)
		);
	}

	public function test_backfill_classifies_a_proven_campaign_reserve(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_campaigns',
			array(
				'advertiser_id' => $this->advertiser->id,
				'name'          => 'Legacy campaign',
				'status'        => 'active',
				'pricing_model' => 'cpm',
				'budget'        => 1000.0,
			)
		);
		$campaign_id = (int) $wpdb->insert_id;

		$ledger_id = $this->write_legacy_ledger_row( 'deduction', -100000, $campaign_id, 'Budget reserved for campaign: Legacy campaign' );

		$this->run_backfill();

		$row = $this->revenue_row_for_ledger( $ledger_id );
		$this->assertNotNull( $row );
		$this->assertSame( Revenue_Ledger::SOURCE_CAMPAIGN_RESERVE, $row->source );
		$this->assertSame( 'campaign', $row->item_type );
		$this->assertSame( '100000', $row->amount, 'A deduction (charge) flips to positive revenue.' );
	}

	public function test_backfill_classifies_classified_sub_source_from_known_english_note(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_classifieds',
			array(
				'post_id'       => self::factory()->post->create(),
				'advertiser_id' => $this->advertiser->id,
				'listing_type'  => 'standard',
				'status'        => 'active',
			)
		);
		$classified_id = (int) $wpdb->insert_id;

		$ledger_id = $this->write_legacy_ledger_row( 'deduction', -500, $classified_id, 'Classified listing: Legacy listing' );

		$this->run_backfill();

		$row = $this->revenue_row_for_ledger( $ledger_id );
		$this->assertSame( Revenue_Ledger::SOURCE_CLASSIFIED_LISTING, $row->source );
		$this->assertSame( 'classified', $row->item_type );
	}

	public function test_backfill_unrecognized_note_lands_unclassified_with_item_type_set(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_classifieds',
			array(
				'post_id'       => self::factory()->post->create(),
				'advertiser_id' => $this->advertiser->id,
				'listing_type'  => 'standard',
				'status'        => 'active',
			)
		);
		$classified_id = (int) $wpdb->insert_id;

		// The item TYPE is provable (a classified row exists and belongs to
		// this advertiser) but the note carries no known sub-source prefix.
		$ledger_id = $this->write_legacy_ledger_row( 'deduction', -500, $classified_id, 'Manual price adjustment' );

		$this->run_backfill();

		$row = $this->revenue_row_for_ledger( $ledger_id );
		$this->assertSame( Revenue_Ledger::SOURCE_UNCLASSIFIED, $row->source );
		$this->assertSame( 'classified', $row->item_type, 'The proven item TYPE must still be recorded even when the sub-source is unknown.' );
	}

	public function test_backfill_excludes_item_id_zero(): void {
		$this->write_legacy_ledger_row( 'topup', 10000, 0, 'Credits from WooCommerce order #1' );

		$this->run_backfill();

		global $wpdb;
		$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wbam_revenue' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->assertSame( 0, $count, 'A topup with item_id=0 is not a charge/credit fingerprint and must never become a revenue row.' );
	}

	public function test_backfill_id_collision_across_item_types_lands_unclassified_with_no_item_type(): void {
		global $wpdb;

		// Force a campaign row and an ad post to share the same numeric ID,
		// both provably owned by the same advertiser — an unresolvable
		// collision the classifier must never guess through.
		$post_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $post_id, '_wbam_advertiser_id', $this->advertiser->id );

		$wpdb->insert(
			$wpdb->prefix . 'wbam_campaigns',
			array(
				'id'            => $post_id,
				'advertiser_id' => $this->advertiser->id,
				'name'          => 'Collision campaign',
				'status'        => 'active',
				'pricing_model' => 'cpm',
				'budget'        => 200.0,
			)
		);

		$ledger_id = $this->write_legacy_ledger_row( 'deduction', -200, $post_id, 'Ambiguous charge' );

		$this->run_backfill();

		$row = $this->revenue_row_for_ledger( $ledger_id );
		$this->assertSame( Revenue_Ledger::SOURCE_UNCLASSIFIED, $row->source );
		$this->assertSame( '', $row->item_type, 'A collision across item types must never guess — empty item_type.' );
	}

	public function test_backfill_is_idempotent(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_campaigns',
			array(
				'advertiser_id' => $this->advertiser->id,
				'name'          => 'Idempotency campaign',
				'status'        => 'active',
				'pricing_model' => 'cpc',
				'budget'        => 50.0,
			)
		);
		$campaign_id = (int) $wpdb->insert_id;
		$this->write_legacy_ledger_row( 'deduction', -5000, $campaign_id, 'Budget reserved for campaign: Idempotency campaign' );

		$this->run_backfill();
		$after_first = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wbam_revenue' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Re-run the exact same batch again (as a retried cron tick would).
		$this->run_backfill();
		$after_second = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wbam_revenue' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->assertSame( $after_first, $after_second, 'Re-running the backfill must not duplicate rows.' );
		$this->assertGreaterThan( 0, $after_first );
	}
}
