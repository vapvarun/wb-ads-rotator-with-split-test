<?php
/**
 * PHPUnit bootstrap for WB Ad Manager (free + pro).
 *
 * Loads the standard WordPress test harness, then manually loads the free
 * plugin and, when WBAM_RUN_PRO_TESTS=1, the pro plugin. Pro tests are
 * skipped individually if the pro constants are not defined after load.
 *
 * @package WBAM\Tests
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	fwrite( STDERR, "Could not find {$_tests_dir}/includes/functions.php — run bin/install-wp-tests.sh first.\n" );
	exit( 1 );
}

// Polyfills required for PHPUnit 9 on WP core test harness.
$polyfills = dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills';
if ( is_dir( $polyfills ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $polyfills );
}

require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load both plugins during muplugins_loaded so they are active
 * when WordPress boots the test harness.
 */
tests_add_filter(
	'muplugins_loaded',
	function () {
		$plugin_dir = dirname( __DIR__ );
		require $plugin_dir . '/wb-ads-rotator-with-split-test.php';

		if ( getenv( 'WBAM_RUN_PRO_TESTS' ) === '1' ) {
			$pro_file = dirname( $plugin_dir ) . '/wb-ad-manager-pro/wb-ad-manager-pro.php';
			if ( file_exists( $pro_file ) ) {
				require $pro_file;
			}
		}
	}
);

/**
 * Run installers after WP test harness has set up $wpdb.
 */
tests_add_filter(
	'setup_theme',
	function () {
		if ( class_exists( '\\WBAM\\Core\\Installer' ) ) {
			\WBAM\Core\Installer::get_instance()->install();
		}

		if ( getenv( 'WBAM_RUN_PRO_TESTS' ) === '1' && class_exists( '\\WBAM_Pro\\Core\\Installer' ) ) {
			\WBAM_Pro\Core\Installer::install();

			// Installer::install() on a fresh DB now defaults new installs to
			// Site_Mode::PUBLISHER (owner decision, card 10342783654) - the
			// least-exposed mode, with campaigns, classifieds, wallet, etc. all
			// off until a wizard runs. The test suite exercises those modules
			// directly and predates site modes, so simulate "the wizard
			// finished and picked Full" here, matching what
			// get_module_defaults() always returned before site modes existed.
			// Individual tests that want a different mode apply their own and
			// rely on WP_UnitTestCase's per-test transaction rollback to undo it.
			if ( class_exists( '\\WBAM_Pro\\Core\\Site_Mode' ) ) {
				\WBAM_Pro\Core\Site_Mode::apply( \WBAM_Pro\Core\Site_Mode::FULL );
			}
		}
	}
);

require "{$_tests_dir}/includes/bootstrap.php";

// Shared factory helpers live under tests/helpers/.
$helpers = __DIR__ . '/helpers';
foreach ( glob( $helpers . '/*.php' ) as $helper ) {
	require_once $helper;
}
