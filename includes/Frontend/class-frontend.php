<?php
/**
 * Frontend Class
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Frontend;

use WBAM\Core\Singleton;

/**
 * Frontend class.
 */
class Frontend {

	use Singleton;

	/**
	 * Initialize.
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'wbam-frontend',
			WBAM_URL . 'assets/css/frontend.css',
			array(),
			WBAM_VERSION
		);

		wp_enqueue_script(
			'wbam-frontend',
			WBAM_URL . 'assets/js/frontend.js',
			array(),
			WBAM_VERSION,
			true
		);
	}
}
