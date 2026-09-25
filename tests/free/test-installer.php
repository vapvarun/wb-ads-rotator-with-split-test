<?php
/**
 * Installer: tables, options, DB version.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;

class Test_Installer extends WP_UnitTestCase {

	private const EXPECTED_TABLES = array(
		'wbam_links',
		'wbam_link_categories',
		'wbam_link_clicks',
		'wbam_analytics',
		'wbam_analytics_daily',
		'wbam_email_submissions',
		'wbam_link_partnerships',
		'wbam_rate_limits',
	);

	public function test_all_tables_created_on_activation(): void {
		global $wpdb;

		foreach ( self::EXPECTED_TABLES as $base ) {
			$full  = $wpdb->prefix . $base;
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) );
			$this->assertSame( $full, $found, "Missing table {$full}" );
		}
	}

	public function test_db_version_option_matches_installer_constant(): void {
		$this->assertSame(
			\WBAM\Core\Installer::DB_VERSION,
			get_option( \WBAM\Core\Installer::DB_VERSION_OPTION )
		);
	}

	/**
	 * Seeding happens in wbam_activate(), the register_activation_hook callback.
	 *
	 * The test bootstrap loads the plugin but never activates it, so this used
	 * to assert a post-activation state that nothing in the run had produced -
	 * a guaranteed failure that said nothing about the product. Run the seeding
	 * the way activation does, then assert, so the test covers the contract
	 * "activation leaves wbam_settings present" instead of "something else
	 * happened to create the option".
	 */
	public function test_activation_seeds_default_settings_option(): void {
		delete_option( 'wbam_settings' );
		$this->assertFalse( get_option( 'wbam_settings', false ), 'Precondition: option cleared.' );

		wbam_activate();

		$this->assertNotFalse(
			get_option( 'wbam_settings', false ),
			'Activation must seed wbam_settings so first-run reads have a value.'
		);
	}

	public function test_cpt_registered_after_init(): void {
		$this->assertTrue( post_type_exists( 'wbam-ad' ), 'wbam-ad CPT must be registered' );
	}
}
