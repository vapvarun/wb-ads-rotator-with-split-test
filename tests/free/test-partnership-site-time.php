<?php
/**
 * Partnership requests are stamped and compared in site time.
 *
 * created_at used to fall back to the column default (the DB server's clock)
 * while the admin list read it as site time, so the Date column was hours
 * off, and the 24h duplicate window ran on the DB clock too.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Links\Partnership_Manager;
use WP_UnitTestCase;

class Test_Partnership_Site_Time extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// Far from any likely DB server clock, so a DB-time stamp is visibly wrong.
		update_option( 'timezone_string', 'Pacific/Honolulu' );
	}

	private function create( string $email ) {
		return Partnership_Manager::get_instance()->create(
			array(
				'name'        => 'Site time',
				'email'       => $email,
				'website_url' => 'https://example.org/' . md5( $email ),
			)
		);
	}

	private function set_created_at( int $id, string $mysql ): void {
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'wbam_link_partnerships', array( 'created_at' => $mysql ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function test_created_at_is_site_time(): void {
		$partnership = $this->create( 'site-time@example.org' );

		$this->assertNotFalse( $partnership );
		$drift = abs( strtotime( $partnership->created_at ) - strtotime( current_time( 'mysql' ) ) );
		$this->assertLessThan( 60, $drift );
	}

	public function test_time_ago_reads_site_time(): void {
		$partnership = $this->create( 'time-ago@example.org' );
		$this->set_created_at( (int) $partnership->id, wp_date( 'Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS ) );

		$partnership = Partnership_Manager::get_instance()->get( (int) $partnership->id );

		$this->assertSame( human_time_diff( time() - 2 * HOUR_IN_SECONDS, time() ), $partnership->get_time_ago() );
	}

	public function test_duplicate_window_uses_site_time(): void {
		$manager     = Partnership_Manager::get_instance();
		$partnership = $this->create( 'window@example.org' );

		$this->assertTrue( $manager->has_recent_submission( 'window@example.org', '', 24 ) );

		$this->set_created_at( (int) $partnership->id, wp_date( 'Y-m-d H:i:s', time() - 23 * HOUR_IN_SECONDS ) );
		$this->assertTrue( $manager->has_recent_submission( 'window@example.org', '', 24 ) );

		$this->set_created_at( (int) $partnership->id, wp_date( 'Y-m-d H:i:s', time() - 25 * HOUR_IN_SECONDS ) );
		$this->assertFalse( $manager->has_recent_submission( 'window@example.org', '', 24 ) );
	}
}
