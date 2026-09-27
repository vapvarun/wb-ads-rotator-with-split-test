<?php
/**
 * Partnership requests are stamped in UTC and shown in site time
 * (docs/standards/dates.md, owner decision 2026-09-27, card 10344269919).
 *
 * created_at once fell back to the column default (the DB server's clock),
 * so the Date column was hours off and the 24h duplicate window ran on the
 * DB clock. It is now written in UTC from PHP and compared in UTC.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Links\Partnership_Manager;
use WP_UnitTestCase;

class Test_Partnership_Site_Time extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// Far from UTC and any likely DB server clock, so a wrong clock is visible.
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

	public function test_created_at_is_utc(): void {
		$partnership = $this->create( 'site-time@example.org' );

		$this->assertNotFalse( $partnership );
		$drift = abs( strtotime( $partnership->created_at . ' UTC' ) - time() );
		$this->assertLessThan( 60, $drift );
	}

	public function test_time_ago_reads_utc(): void {
		$partnership = $this->create( 'time-ago@example.org' );
		$this->set_created_at( (int) $partnership->id, gmdate( 'Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS ) );

		$partnership = Partnership_Manager::get_instance()->get( (int) $partnership->id );

		$this->assertSame( human_time_diff( time() - 2 * HOUR_IN_SECONDS, time() ), $partnership->get_time_ago() );
	}

	public function test_duplicate_window_uses_utc(): void {
		$manager     = Partnership_Manager::get_instance();
		$partnership = $this->create( 'window@example.org' );

		$this->assertTrue( $manager->has_recent_submission( 'window@example.org', '', 24 ) );

		$this->set_created_at( (int) $partnership->id, gmdate( 'Y-m-d H:i:s', time() - 23 * HOUR_IN_SECONDS ) );
		$this->assertTrue( $manager->has_recent_submission( 'window@example.org', '', 24 ) );

		$this->set_created_at( (int) $partnership->id, gmdate( 'Y-m-d H:i:s', time() - 25 * HOUR_IN_SECONDS ) );
		$this->assertFalse( $manager->has_recent_submission( 'window@example.org', '', 24 ) );
	}

	public function test_admin_list_shows_site_time(): void {
		// 20:00 UTC is 10:00 the same day in Honolulu (-10:00).
		$this->assertSame( '10:00', wbam_format_datetime( '2026-09-27 20:00:00', 'H:i' ) );
	}
}
