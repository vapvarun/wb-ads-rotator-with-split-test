<?php
/**
 * Every FREE-plugin CSS/JS asset ships both its source and its .min sibling.
 *
 * wbam_asset_url() decides which file a handle loads by switching on
 * SCRIPT_DEBUG. If the build ever ships a source with no .min (or a .min
 * with no source), one of the two SCRIPT_DEBUG states 404s in production.
 * Card 10340188730.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;

class Test_Asset_Suffix_Files_Exist extends WP_UnitTestCase {

	/**
	 * Every plugin-owned CSS/JS source file, as a path relative to assets/
	 * (or, for the one non-assets/ case, the full plugin-relative path).
	 * Vendor libraries shipped minified-only (assets/vendor/lucide.min.js)
	 * are outside assets/css and assets/js, so the glob never reaches them.
	 *
	 * @return string[]
	 */
	private static function relative_paths(): array {
		$paths = array();

		foreach ( array( 'css', 'js' ) as $dir ) {
			foreach ( (array) glob( WBAM_PATH . 'assets/' . $dir . '/*.' . $dir ) as $file ) {
				$name = basename( $file );
				if ( '.min.' . $dir === substr( $name, -strlen( '.min.' . $dir ) ) ) {
					continue; // Skip the .min siblings themselves; we assert their presence below.
				}
				$paths[] = $dir . '/' . $name;
			}
		}

		$paths[] = 'blocks/editor.js';

		return $paths;
	}

	/**
	 * Resolve wbam_asset_url() to a filesystem path, forcing the suffix via
	 * the filter rather than defining SCRIPT_DEBUG globally.
	 */
	private function resolved_path( string $relative_path, bool $script_debug_on ): string {
		$force = static function () use ( $script_debug_on ) {
			return $script_debug_on ? '' : '.min';
		};

		add_filter( 'wbam_asset_suffix', $force );
		$url = wbam_asset_url( $relative_path );
		remove_filter( 'wbam_asset_suffix', $force );

		return WBAM_PATH . substr( $url, strlen( WBAM_URL ) );
	}

	public function test_every_asset_exists_on_disk_in_both_script_debug_states(): void {
		foreach ( self::relative_paths() as $relative_path ) {
			$this->assertFileExists(
				$this->resolved_path( $relative_path, false ),
				"Missing .min sibling for {$relative_path} (SCRIPT_DEBUG off would 404)."
			);
			$this->assertFileExists(
				$this->resolved_path( $relative_path, true ),
				"Missing source sibling for {$relative_path} (SCRIPT_DEBUG on would 404)."
			);
		}
	}
}
