<?php
/**
 * Pro installer - tables, options, DB version.
 *
 * Two of these asserted things the plugin never produces:
 *
 * - The SDK table names were given as `wbam_ledger` and `wbam_ledger_hold`.
 *   Ledger::table_name() builds `{prefix}_credit_ledger`, and the Registry also
 *   creates `_credit_gateway_log` and `_credit_processed_events`. Neither of the
 *   asserted names has ever existed, on the live site or anywhere else.
 * - `wbam_pro_setup_complete` is written by the setup wizard when an admin
 *   finishes it, not by the installer, so asserting it after a bare install
 *   could only fail.
 *
 * Rewritten to assert what installation actually guarantees.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Pro_Installer extends Pro_Test_Case {

	public function test_db_version_is_set(): void {
		$this->assertSame( \WBAM_Pro\Core\Installer::DB_VERSION, get_option( 'wbam_pro_db_version' ) );
	}

	/**
	 * The setup flag is a wizard completion marker, not an install artefact. A
	 * fresh install must NOT look already-configured, or the wizard never
	 * offers itself.
	 */
	public function test_setup_complete_flag_is_absent_until_the_wizard_runs(): void {
		delete_option( 'wbam_pro_setup_complete' );

		$this->assertFalse(
			get_option( 'wbam_pro_setup_complete', false ),
			'A fresh install must not claim setup is complete - the wizard would never show.'
		);

		update_option( 'wbam_pro_setup_complete', true );
		$this->assertNotFalse( get_option( 'wbam_pro_setup_complete', false ) );
	}

	/**
	 * The credits system reads and writes through these three. A missing one
	 * does not fail loudly - it degrades to "no balance", which looks like an
	 * empty wallet rather than a broken install.
	 *
	 * @dataProvider sdk_tables
	 * @param string $suffix Table suffix the SDK appends after the consumer prefix.
	 */
	public function test_credits_sdk_table_exists( string $suffix ): void {
		global $wpdb;

		// Consumer prefix is 'wbam' (Credits_Bridge::PREFIX); the SDK appends
		// its own suffix, e.g. wp_wbam_credit_ledger.
		$table = $wpdb->prefix . 'wbam' . $suffix;

		$this->assertSame(
			$table,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ),
			"Missing SDK table {$table}"
		);
	}

	/**
	 * Run Installer::install() with its DDL switched off. The schema already
	 * exists, and under WP_UnitTestCase a CREATE becomes CREATE TEMPORARY,
	 * which would shadow the real tables for every later test.
	 *
	 * @param bool|null $run_upgrades Argument for install(); null for none.
	 */
	private function install_without_ddl( ?bool $run_upgrades = null ): void {
		$no_ddl = static function ( $query ) {
			return preg_match( '/^\s*(CREATE|ALTER|DROP)\s/i', (string) $query ) ? '' : $query;
		};
		add_filter( 'query', $no_ddl, 1 );
		try {
			null === $run_upgrades
				? \WBAM_Pro\Core\Installer::install()
				: \WBAM_Pro\Core\Installer::install( $run_upgrades );
		} finally {
			remove_filter( 'query', $no_ddl, 1 );
		}
	}

	/**
	 * A fresh install gets the current schema from create_tables() and is
	 * stamped at DB_VERSION. The upgrade routines are for older installs: on
	 * a fresh one they reach for classes the activation hook has not loaded
	 * (3.2.0 fataled on Revenue_Ledger on every new site).
	 */
	public function test_fresh_install_stamps_the_version_without_running_upgrades(): void {
		delete_option( 'wbam_pro_db_version' );
		// Written only by upgrade_to_3_6_0().
		delete_option( 'wbam_pro_demo_migration_360_done' );

		$this->install_without_ddl();

		$this->assertSame( \WBAM_Pro\Core\Installer::DB_VERSION, get_option( 'wbam_pro_db_version' ) );
		$this->assertFalse( get_option( 'wbam_pro_demo_migration_360_done' ), 'A fresh install ran the upgrade routines.' );
	}

	/**
	 * The activation hook runs before PRO's classes load, so on an existing
	 * install it leaves the version alone and admin_init
	 * (Pro_Plugin::maybe_run_upgrades) runs the upgrades.
	 */
	public function test_activation_leaves_an_older_install_for_admin_init_to_upgrade(): void {
		update_option( 'wbam_pro_db_version', '4.3.1' );

		$this->install_without_ddl( false );

		$this->assertSame( '4.3.1', get_option( 'wbam_pro_db_version' ) );
	}

	/**
	 * The classified taxonomies only exist from init, after the activation
	 * hook. The default categories are seeded on init instead, so a seller's
	 * first listing has a category to pick.
	 */
	public function test_default_classified_categories_are_seeded_on_init_after_install(): void {
		$this->assertTrue( taxonomy_exists( 'wbam-classified-cat' ), 'Classifieds module should be on by default.' );

		$term_ids = get_terms(
			array(
				'taxonomy'   => 'wbam-classified-cat',
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		foreach ( $term_ids as $term_id ) {
			wp_delete_term( $term_id, 'wbam-classified-cat' );
		}
		update_option( \WBAM_Pro\Core\Installer::CLASSIFIEDS_PENDING_OPTION, 1 );

		\WBAM_Pro\Core\Installer::finish_classifieds_install();

		$this->assertNotEmpty( term_exists( 'General', 'wbam-classified-cat' ) );
		$this->assertSame( 0, (int) get_option( \WBAM_Pro\Core\Installer::CLASSIFIEDS_PENDING_OPTION ) );
		$this->assertSame( 20, has_action( 'init', array( \WBAM_Pro\Core\Installer::class, 'finish_classifieds_install' ) ) );
	}

	public function sdk_tables(): array {
		return array(
			array( '_credit_ledger' ),
			array( '_credit_gateway_log' ),
			array( '_credit_processed_events' ),
		);
	}
}
