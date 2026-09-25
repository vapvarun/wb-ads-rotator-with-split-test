<?php
/**
 * Block Registry
 *
 * Registers the `wb-ads/ad` and `wb-ads/placement` blocks so block (FSE)
 * theme owners can place ads from the Site Editor, not just via shortcode.
 * No build pipeline exists for this plugin (Grunt owns the legacy CSS/JS
 * copy-and-minify step) - the editor script is plain ES5-safe JS against
 * `wp` globals, registered with explicit script dependencies since there is
 * no `*.asset.php` for block.json's file-based auto-registration to read.
 *
 * @package WB_Ad_Manager
 * @since   3.2.0
 */

namespace WBAM\Modules\Blocks;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block Registry class.
 */
class Block_Registry {

	/**
	 * Block folders under blocks/, each holding a block.json + render.php.
	 *
	 * @var string[]
	 */
	private const BLOCKS = array( 'wb-ad', 'wb-ad-placement' );

	/**
	 * Wire hooks.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register' ), 5 );
		add_filter( 'block_categories_all', array( $this, 'register_category' ) );
	}

	/**
	 * Register the editor script/style handles, then the block types.
	 */
	public function register() {
		wp_register_script(
			'wbam-blocks-editor',
			WBAM_URL . 'blocks/editor.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-server-side-render',
				'wp-i18n',
				'wp-data',
				'wp-api-fetch',
			),
			WBAM_VERSION,
			true
		);
		wp_set_script_translations( 'wbam-blocks-editor', 'wb-ads-rotator-with-split-test' );

		foreach ( self::BLOCKS as $slug ) {
			register_block_type(
				WBAM_PATH . 'blocks/' . $slug,
				array( 'editor_script' => 'wbam-blocks-editor' )
			);
		}
	}

	/**
	 * Group both blocks under a dedicated "WB Ads" inserter category.
	 *
	 * @param array $categories Existing block categories.
	 * @return array
	 */
	public function register_category( $categories ) {
		foreach ( $categories as $category ) {
			if ( 'wb-ads' === $category['slug'] ) {
				return $categories;
			}
		}

		array_unshift(
			$categories,
			array(
				'slug'  => 'wb-ads',
				'title' => __( 'WB Ads', 'wb-ads-rotator-with-split-test' ),
				'icon'  => 'megaphone',
			)
		);

		return $categories;
	}
}
