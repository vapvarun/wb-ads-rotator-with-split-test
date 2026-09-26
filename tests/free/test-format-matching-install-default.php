<?php
/**
 * format_matching's install-time default (owner decision 13, card
 * 10343726460): on for a fresh install, untouched for an upgrade.
 *
 * Mirrors the existing maybe_set_geo_enabled_default() pattern: a fresh
 * install has no DB_VERSION_OPTION stored yet; an upgrade already has one.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Core\Installer;
use WBAM\Core\Settings_Helper;

class Test_Format_Matching_Install_Default extends \WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		delete_option( Installer::DB_VERSION_OPTION );
		parent::tear_down();
	}

	public function test_fresh_install_turns_shape_matching_on(): void {
		delete_option( 'wbam_settings' );
		delete_option( Installer::DB_VERSION_OPTION ); // Sentinel: never set = fresh install.

		Installer::get_instance()->install( true );

		$this->assertTrue( Settings_Helper::format_matching_enabled(), 'A brand new 3.2.0+ install should default to shape matching on.' );
	}

	public function test_existing_site_upgrade_does_not_change_the_setting(): void {
		delete_option( 'wbam_settings' );
		update_option( Installer::DB_VERSION_OPTION, '1.9.0' ); // Sentinel: already stored = upgrade, not fresh.

		Installer::get_instance()->install( true );

		$this->assertFalse(
			Settings_Helper::format_matching_enabled(),
			"An existing site's serving must not change silently on upgrade — it keeps today's (off) default until the owner opts in."
		);
	}

	public function test_re_running_install_never_overwrites_an_owners_later_choice(): void {
		delete_option( 'wbam_settings' );
		delete_option( Installer::DB_VERSION_OPTION );
		Installer::get_instance()->install( true ); // Fresh install stamps it true.

		Settings_Helper::update( 'format_matching', false ); // Owner turns it back off.
		Installer::get_instance()->install( true ); // Re-running (e.g. maybe_update_database()) must not flip it back.

		$this->assertFalse( Settings_Helper::format_matching_enabled(), "Re-running install() must not overwrite the owner's own choice." );
	}
}
