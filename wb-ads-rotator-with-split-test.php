<?php
/**
 * WB Ad Manager
 *
 * @link              https://wbcomdesigns.com
 * @since             1.0.0
 * @package           WB_Ad_Manager
 *
 * @wordpress-plugin
 * Plugin Name:       Wbcom Designs - WB Ad Manager
 * Plugin URI:        https://wordpress.org/plugins/wb-ads-rotator-with-split-test/
 * Description:       Comprehensive ad management for WordPress with ad rotation, split testing, multiple placements, Google AdSense, BuddyPress and bbPress integration.
 * Version:           2.4.0
 * Author:            Wbcom Designs
 * Author URI:        https://wbcomdesigns.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wb-ads-rotator-with-split-test
 * Domain Path:       /languages
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'WBAM_VERSION', '2.4.0' );
define( 'WBAM_FILE', __FILE__ );
define( 'WBAM_PATH', plugin_dir_path( __FILE__ ) );
define( 'WBAM_URL', plugin_dir_url( __FILE__ ) );
define( 'WBAM_BASENAME', plugin_basename( __FILE__ ) );

// Manually load trait (traits need special handling).
require_once WBAM_PATH . 'includes/Core/trait-singleton.php';

/**
 * Autoloader for plugin classes.
 *
 * @param string $class Class name.
 */
spl_autoload_register( function ( $class ) {
	$prefix = 'WBAM\\';
	$len    = strlen( $prefix );

	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );

	// Split into namespace parts and class name.
	$parts      = explode( '\\', $relative_class );
	$class_name = array_pop( $parts );
	$namespace  = implode( '/', $parts );

	// Convert class name: Underscores to hyphens, CamelCase to hyphen-separated.
	$file_name = str_replace( '_', '-', $class_name );
	$file_name = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $file_name );
	$file_name = strtolower( $file_name );

	// Determine file type prefix.
	$file_prefix = 'class-';
	if ( strpos( $class_name, 'Interface' ) !== false || substr( $class_name, -9 ) === 'Interface' ) {
		$file_prefix = 'interface-';
		$file_name   = str_replace( '-interface', '', $file_name );
	} elseif ( strpos( $class_name, 'Trait' ) !== false || substr( $class_name, 0, 5 ) === 'Trait' ) {
		$file_prefix = 'trait-';
		$file_name   = str_replace( 'trait-', '', $file_name );
	}

	// Build file path.
	$file = WBAM_PATH . 'includes/' . $namespace . '/' . $file_prefix . $file_name . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

/**
 * Plugin activation.
 */
function wbam_activate() {
	flush_rewrite_rules();

	if ( false === get_option( 'wbam_settings' ) ) {
		add_option( 'wbam_settings', array() );
	}

	// Run installer to create database tables.
	require_once WBAM_PATH . 'includes/Core/trait-singleton.php';
	require_once WBAM_PATH . 'includes/Core/class-installer.php';
	$installer = WBAM\Core\Installer::get_instance();
	$installer->install();

	set_transient( '_wbam_activation_redirect', true, 30 );
}
register_activation_hook( __FILE__, 'wbam_activate' );

/**
 * Plugin deactivation.
 */
function wbam_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wbam_deactivate' );

/**
 * Initialize the plugin.
 */
function wbam_init() {
	// Initialize plugin.
	WBAM\Core\Plugin::get_instance()->init();
}
add_action( 'plugins_loaded', 'wbam_init' );

/**
 * Helper function to get plugin instance.
 *
 * @return WBAM\Core\Plugin
 */
function wbam() {
	return WBAM\Core\Plugin::get_instance();
}

/**
 * Helper function to display an ad.
 *
 * @param int   $ad_id   Ad post ID.
 * @param array $options Display options.
 * @return string
 */
function wbam_display_ad( $ad_id, $options = array() ) {
	return wbam()->placements()->render_ad( $ad_id, $options );
}

/**
 * Helper function to get ads for a placement.
 *
 * @param string $placement_id Placement ID.
 * @return array
 */
function wbam_get_ads( $placement_id ) {
	return wbam()->placements()->get_ads_for_placement( $placement_id );
}
