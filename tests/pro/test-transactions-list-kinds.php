<?php
/**
 * Admin Transactions: the type filters match what the ledger stores, and a
 * refund is labelled a refund.
 *
 * The filters used to offer credit/debit/hold/release/refund while the ledger
 * only ever holds topup/deduction rows, so every filter returned an empty
 * table, and credit()'s refunds (topup rows tied to an item) read "Top-up"
 * while the portal wallet called the same row "Refund".
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Transactions_List_Table;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Transactions_List_Kinds extends Pro_Test_Case {

	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		require_once WBAM_PRO_PATH . 'includes/Admin/class-transactions-list-table.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		Credits_Bridge::topup( $this->advertiser->id, 100, 'Grant' );
		Credits_Bridge::charge( $this->advertiser->id, 40, 777, 'Package', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		Credits_Bridge::credit( $this->advertiser->id, 40, 777, 'Refund for rejected ad', Revenue_Ledger::SOURCE_AD_PACKAGE );
	}

	public function tear_down(): void {
		unset( $_GET['entry_type'], $_GET['advertiser_id'] );
		parent::tear_down();
	}

	private function list_for( string $kind ): Transactions_List_Table {
		$_GET['entry_type']    = $kind;
		$_GET['advertiser_id'] = (string) $this->advertiser->id;

		$table = new Transactions_List_Table();
		$table->prepare_items();

		return $table;
	}

	public function test_each_filter_returns_its_own_rows(): void {
		foreach ( array( 'topup', 'deduction', 'refund' ) as $kind ) {
			$table = $this->list_for( $kind );
			$this->assertCount( 1, $table->items, "The {$kind} filter should match exactly one ledger row." );
			$this->assertSame( $kind, $table->items[0]->get_kind() );
		}
	}

	public function test_refund_row_is_labelled_refund_not_top_up(): void {
		$table = $this->list_for( 'refund' );

		$this->assertStringContainsString( 'Refund', $table->column_entry_type( $table->items[0] ) );
		$this->assertStringNotContainsString( 'Top', $table->column_entry_type( $table->items[0] ) );
	}
}
