<?php
/**
 * Admin Transactions: a ledger row whose WP user account was deleted (the
 * QA fixture scenario — a user row removed directly, without going through
 * wp_delete_user()'s 'deleted_user' hook, so the advertiser row survives
 * with no company name and no WP account to read a display name from)
 * shows "Deleted user #N", not a bare "Unknown" — the numeric ID stays
 * traceable to whoever is tracking down the transaction.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Transactions_List_Table;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Transactions_List_Deleted_User extends Pro_Test_Case {

	private int $user;

	public function set_up(): void {
		parent::set_up();

		require_once WBAM_PRO_PATH . 'includes/Admin/class-transactions-list-table.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );

		Credits_Bridge::topup( $advertiser->id, 100, 'Grant' );

		global $wpdb;
		$wpdb->delete( $wpdb->users, array( 'ID' => $this->user ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test-only simulation of a row removed outside wp_delete_user().
		clean_user_cache( $this->user );
	}

	public function test_column_user_shows_deleted_user_by_id(): void {
		$table = new Transactions_List_Table();
		$table->prepare_items();

		$this->assertCount( 1, $table->items );
		// Still linked to the surviving advertiser row (company name is just
		// blank), so the fallback text appears rather than exactly matching.
		$this->assertStringContainsString( "Deleted user #{$this->user}", $table->column_user( $table->items[0] ) );
		$this->assertStringNotContainsString( 'Unknown', $table->column_user( $table->items[0] ) );
	}

	public function test_csv_export_shows_deleted_user_by_id(): void {
		$handle = fopen( 'php://memory', 'w+' );
		Transactions_List_Table::stream_csv( $handle );
		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );

		$this->assertStringContainsString( "Deleted user #{$this->user}", $csv );
		$this->assertStringNotContainsString( 'Unknown', $csv );
	}
}
