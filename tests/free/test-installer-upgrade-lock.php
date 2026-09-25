<?php
/**
 * Upgrade migrations must not run inside the activation request, and
 * Plugin::maybe_update_database() (the admin_init runner) must not let two
 * concurrent requests upgrade at once.
 *
 * Regression guard for Basecamp card 10342784882, step 1: install() used to
 * run create_tables()/run_migrations() unconditionally, and
 * Plugin::init() called it on every plugins_loaded (front-end requests
 * included), unlocked.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;

class Test_Installer_Upgrade_Lock extends WP_UnitTestCase {

	private function lock_key(): string {
		global $wpdb;
		return substr( $wpdb->prefix . 'wbam_db_upgrade', 0, 64 );
	}

	/**
	 * Run Installer::install() with DDL switched off. The schema already
	 * exists under WP_UnitTestCase, and a CREATE/ALTER there becomes
	 * CREATE TEMPORARY, which would shadow the real tables for every later
	 * test in the run.
	 */
	private function install_without_ddl( ?bool $run_upgrades = null ): void {
		$no_ddl = static function ( $query ) {
			return preg_match( '/^\s*(CREATE|ALTER|DROP)\s/i', (string) $query ) ? '' : $query;
		};
		add_filter( 'query', $no_ddl, 1 );
		try {
			null === $run_upgrades
				? \WBAM\Core\Installer::get_instance()->install()
				: \WBAM\Core\Installer::get_instance()->install( $run_upgrades );
		} finally {
			remove_filter( 'query', $no_ddl, 1 );
		}
	}

	public function tear_down(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $this->lock_key() ) );
		update_option( \WBAM\Core\Installer::DB_VERSION_OPTION, \WBAM\Core\Installer::DB_VERSION );
		parent::tear_down();
	}

	/**
	 * The activation hook (`install( false )`) must leave an existing site's
	 * stored version untouched - the migrations are deferred to
	 * Plugin::maybe_update_database() on the next admin_init, not run inside
	 * the activation request.
	 */
	public function test_activation_on_an_existing_site_defers_migrations(): void {
		update_option( \WBAM\Core\Installer::DB_VERSION_OPTION, '1.3.0' );

		$this->install_without_ddl( false );

		$this->assertSame( '1.3.0', get_option( \WBAM\Core\Installer::DB_VERSION_OPTION ) );
	}

	/**
	 * admin_init's runner performs the deferred upgrade when nothing else
	 * holds the lock.
	 */
	public function test_maybe_update_database_upgrades_when_unlocked(): void {
		update_option( \WBAM\Core\Installer::DB_VERSION_OPTION, '1.3.0' );

		$no_ddl = static function ( $query ) {
			return preg_match( '/^\s*(CREATE|ALTER|DROP)\s/i', (string) $query ) ? '' : $query;
		};
		add_filter( 'query', $no_ddl, 1 );
		try {
			\WBAM\Core\Plugin::get_instance()->maybe_update_database();
		} finally {
			remove_filter( 'query', $no_ddl, 1 );
		}

		$this->assertSame( \WBAM\Core\Installer::DB_VERSION, get_option( \WBAM\Core\Installer::DB_VERSION_OPTION ) );
	}

	/**
	 * A second runner must not upgrade while another one holds the lock -
	 * two admin requests loaded before either finished must not both run
	 * every pending migration. GET_LOCK() is per-connection: taking it
	 * again on $wpdb's own connection would just re-grant it to ourselves,
	 * so a genuinely separate connection plays "the other request", the
	 * same technique test-credits-charge-concurrency.php (pro) uses.
	 */
	public function test_maybe_update_database_skips_while_locked(): void {
		update_option( \WBAM\Core\Installer::DB_VERSION_OPTION, '1.3.0' );

		$other = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$acquired = $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s, 0)', $this->lock_key() ) );
		$this->assertSame( '1', (string) $acquired, 'Precondition: the other connection must hold the lock itself.' );

		\WBAM\Core\Plugin::get_instance()->maybe_update_database();

		$this->assertSame(
			'1.3.0',
			get_option( \WBAM\Core\Installer::DB_VERSION_OPTION ),
			'A runner that could not acquire the lock must leave the stored version untouched.'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$other->query( $other->prepare( 'SELECT RELEASE_LOCK(%s)', $this->lock_key() ) );
		$other->close();

		// Now that the lock is free, the same call upgrades.
		$no_ddl = static function ( $query ) {
			return preg_match( '/^\s*(CREATE|ALTER|DROP)\s/i', (string) $query ) ? '' : $query;
		};
		add_filter( 'query', $no_ddl, 1 );
		try {
			\WBAM\Core\Plugin::get_instance()->maybe_update_database();
		} finally {
			remove_filter( 'query', $no_ddl, 1 );
		}

		$this->assertSame( \WBAM\Core\Installer::DB_VERSION, get_option( \WBAM\Core\Installer::DB_VERSION_OPTION ) );
	}
}
