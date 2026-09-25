<?php
/**
 * PHPStan bootstrap: define plugin constants so cross-plugin static
 * analysis resolves references without fatals.
 *
 * @package WBAM\Tests
 */

// Free plugin constants.
defined( 'WBAM_VERSION' ) || define( 'WBAM_VERSION', '3.1.1' );
defined( 'WBAM_FILE' ) || define( 'WBAM_FILE', __DIR__ . '/wb-ads-rotator-with-split-test.php' );
defined( 'WBAM_PATH' ) || define( 'WBAM_PATH', __DIR__ . '/' );
defined( 'WBAM_URL' ) || define( 'WBAM_URL', 'https://example.test/wp-content/plugins/wb-ads-rotator-with-split-test/' );
defined( 'WBAM_BASENAME' ) || define( 'WBAM_BASENAME', 'wb-ads-rotator-with-split-test/wb-ads-rotator-with-split-test.php' );

// WordPress constants defined at runtime by wp_cookie_constants().
defined( 'COOKIEPATH' ) || define( 'COOKIEPATH', '/' );
defined( 'COOKIE_DOMAIN' ) || define( 'COOKIE_DOMAIN', '' );

// Pro plugin constants (dir resolved relative to free plugin root).
$wbam_pro_dir = realpath( __DIR__ . '/../wb-ad-manager-pro' );
if ( $wbam_pro_dir ) {
	defined( 'WBAM_PRO_VERSION' ) || define( 'WBAM_PRO_VERSION', '3.0.0' );
	defined( 'WBAM_PRO_FILE' ) || define( 'WBAM_PRO_FILE', $wbam_pro_dir . '/wb-ad-manager-pro.php' );
	defined( 'WBAM_PRO_PATH' ) || define( 'WBAM_PRO_PATH', $wbam_pro_dir . '/' );
	defined( 'WBAM_PRO_URL' ) || define( 'WBAM_PRO_URL', 'https://example.test/wp-content/plugins/wb-ad-manager-pro/' );
	defined( 'WBAM_PRO_BASENAME' ) || define( 'WBAM_PRO_BASENAME', 'wb-ad-manager-pro/wb-ad-manager-pro.php' );
	defined( 'WBAM_PRO_MIN_FREE_VERSION' ) || define( 'WBAM_PRO_MIN_FREE_VERSION', '1.0.0' );
}
unset( $wbam_pro_dir );
