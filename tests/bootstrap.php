<?php
/**
 * PHPUnit Bootstrap File
 *
 * @package WB_Ad_Manager
 */

// Define test mode.
if ( ! defined( 'WBAM_TESTING' ) ) {
	define( 'WBAM_TESTING', true );
}

// Load WordPress test environment.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Check if WP test suite is available.
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// Fall back to standalone mode without WordPress.
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	}
	if ( ! defined( 'WBAM_PATH' ) ) {
		define( 'WBAM_PATH', dirname( __DIR__ ) . '/' );
	}
	if ( ! defined( 'WBAM_URL' ) ) {
		define( 'WBAM_URL', 'http://example.com/wp-content/plugins/wb-ad-manager/' );
	}
	if ( ! defined( 'WBAM_VERSION' ) ) {
		define( 'WBAM_VERSION', '1.1.0' );
	}
	if ( ! defined( 'DAY_IN_SECONDS' ) ) {
		define( 'DAY_IN_SECONDS', 86400 );
	}

	// Load Composer autoloader if available.
	if ( file_exists( dirname( __DIR__ ) . '/vendor/autoload.php' ) ) {
		require_once dirname( __DIR__ ) . '/vendor/autoload.php';
	}

	// Mock WordPress functions for standalone testing.
	require_once __DIR__ . '/mocks/wp-functions.php';

	// Load plugin files (WordPress-style class names need manual loading).
	$plugin_dir = dirname( __DIR__ ) . '/includes';

	// Core.
	require_once $plugin_dir . '/Core/trait-singleton.php';

	// Targeting.
	require_once $plugin_dir . '/Modules/Targeting/class-content-analyzer.php';

	// Geo Targeting.
	require_once $plugin_dir . '/Modules/GeoTargeting/class-geo-engine.php';

	return;
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/wb-ad-manager.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
