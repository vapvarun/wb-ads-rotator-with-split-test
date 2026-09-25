<?php
/**
 * Audit Log reads as sentences: object types as words, logged changes
 * without empty fields, advertiser IDs as names, loopback IPs as "Local",
 * and view counts from one query.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Audit_Log_List_Table;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Audit_Log_Presentation extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'toplevel_page_wbam-audit-log' );
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_audit_log" );
	}

	public function tear_down(): void {
		set_current_screen( 'front' );
		parent::tear_down();
	}

	private function entry( string $type, array $new, string $ip = '203.0.113.9' ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_audit_log',
			array(
				'user_id'     => get_current_user_id(),
				'action'      => 'ad_approved',
				'object_type' => $type,
				'object_id'   => 5,
				'new_value'   => wp_json_encode( $new ),
				'ip_address'  => $ip,
				'created_at'  => current_time( 'mysql' ),
			)
		);
	}

	private function table(): Audit_Log_List_Table {
		$table = new Audit_Log_List_Table();
		$table->prepare_items();
		return $table;
	}

	public function test_entry_reads_as_words(): void {
		$user       = (int) self::factory()->user->create();
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update( $advertiser->id, array( 'company_name' => 'Acme' ) );
		$this->entry(
			'ad_submission',
			array(
				'notes'         => '',
				'status'        => 'approved',
				'advertiser_id' => $advertiser->id,
			),
			'::1'
		);

		$table   = $this->table();
		$item    = $table->items[0];
		$details = $table->column_details( $item );

		$this->assertSame( 'Ad submission', $table->column_object_type( $item ) );
		$this->assertStringNotContainsString( 'Notes', $details );
		$this->assertStringContainsString( 'Acme', $details );
		$this->assertStringContainsString( 'Approved', $details );
		$this->assertSame( 'Local', $table->column_ip_address( $item ) );
	}

	public function test_view_counts_are_one_query(): void {
		global $wpdb;
		foreach ( array( 'campaign', 'package', 'advertiser', 'ad', 'classified' ) as $type ) {
			$this->entry( $type, array() );
		}

		$table  = new Audit_Log_List_Table();
		$before = $wpdb->num_queries;
		$views  = ( new \ReflectionMethod( $table, 'get_views' ) )->invoke( $table );

		$this->assertSame( 1, $wpdb->num_queries - $before );
		$this->assertStringContainsString( '(1)', $views['campaign'] );
	}
}
