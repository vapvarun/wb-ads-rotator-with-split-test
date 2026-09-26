<?php
/**
 * Activation runs Installer::install() before the plugin's autoloader is up
 * (wbam_pro_activate() only requires class-installer.php). Any plugin class a
 * fresh-install method names must be require_once'd by the installer itself,
 * or activating Pro fatals. The PHPUnit bootstrap loads the autoloader, so a
 * behavioural test cannot see this; this reads the installer source instead.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Activation_Install_Loads_Its_Classes extends Pro_Test_Case {

	/** Methods install( false ) runs on activation (upgrades run later, on admin_init). */
	private const FRESH_INSTALL_METHODS = array( 'install', 'create_tables', 'create_sdk_tables', 'add_list_indexes', 'create_options', 'create_roles', 'create_default_packages', 'create_pages', 'set_db_version' );

	public function test_every_plugin_class_used_on_fresh_install_is_required_by_the_installer(): void {
		$source = (string) file_get_contents( WBAM_PRO_PATH . 'includes/Core/class-installer.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$code   = preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $source ); // Ignore class names in comments.

		$missing = array();
		foreach ( self::FRESH_INSTALL_METHODS as $method ) {
			$body = $this->method_body( $code, $method );
			preg_match_all( '/(\\\\?[A-Z][A-Za-z_\\\\]+)::/', $body, $m );
			foreach ( array_unique( $m[1] ) as $class ) {
				$short = substr( strrchr( '\\' . $class, '\\' ), 1 );
				if ( in_array( $short, array( 'Installer' ), true ) || 0 === strpos( ltrim( $class, '\\' ), 'Wbcom\\' ) ) {
					continue; // Self, and the SDK (create_sdk_tables() requires its entry file first).
				}
				$file = 'class-' . str_replace( '_', '-', strtolower( $short ) ) . '.php';
				if ( ! preg_match( '#require_once[^;]*' . preg_quote( $file, '#' ) . '#', $body ) && ! preg_match( '#require_once[^;]*' . preg_quote( $file, '#' ) . '#', $this->method_body( $code, 'install' ) ) ) {
					$missing[] = "{$method}() uses {$class} but the installer never require_once's {$file}";
				}
			}
		}

		$this->assertSame( array(), $missing );
	}

	private function method_body( string $code, string $method ): string {
		if ( ! preg_match( '/function\s+' . $method . '\s*\(/', $code, $m, PREG_OFFSET_CAPTURE ) ) {
			return '';
		}
		$start = strpos( $code, '{', $m[0][1] );
		$depth = 0;
		$len   = strlen( $code );
		for ( $i = $start; $i < $len; $i++ ) {
			if ( '{' === $code[ $i ] ) {
				++$depth;
			} elseif ( '}' === $code[ $i ] && 0 === --$depth ) {
				return substr( $code, $start, $i - $start );
			}
		}
		return '';
	}
}
