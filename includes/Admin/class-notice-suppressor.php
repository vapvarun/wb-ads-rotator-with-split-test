<?php
/**
 * Notice Suppressor
 *
 * Third-party admin notices (upgrade nags, unrelated review prompts, plugin
 * banners) make our own settings screens feel cluttered and also push our
 * real notices off-screen. On WB Ad Manager admin pages we strip everything
 * hooked to the notice actions EXCEPT our own callbacks and WordPress core.
 *
 * @package WB_Ad_Manager
 * @since   2.8.0
 */

namespace WBAM\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notice_Suppressor class.
 */
class Notice_Suppressor {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'in_admin_header', array( $this, 'suppress_foreign_notices' ), 1 );
	}

	/**
	 * Strip foreign callbacks from the notice hooks on our screens.
	 *
	 * @return void
	 */
	public function suppress_foreign_notices() {
		if ( ! $this->is_our_screen() ) {
			return;
		}

		$hooks = array(
			'admin_notices',
			'all_admin_notices',
			'user_admin_notices',
			'network_admin_notices',
		);

		foreach ( $hooks as $hook ) {
			$this->strip_hook( $hook );
		}
	}

	/**
	 * Is the current admin screen owned by WB Ad Manager?
	 *
	 * @return bool
	 */
	private function is_our_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		// The wbam-ad and wbam-classified CPT edit list, add-new, and
		// single-post edit screens. Pro's Classifieds CPT sits under its own
		// top-level menu, not the wbam-ad one, so it needs its own check —
		// without it, the classified category/location taxonomy screens (and
		// the CPT list/edit screens) fell through to the generic `?page=`
		// fallback below, which only catches admin.php subpages, not
		// edit.php/edit-tags.php screens.
		if ( in_array( $screen->post_type, array( 'wbam-ad', 'wbam-classified' ), true ) ) {
			return true;
		}

		// Classified category/location taxonomy screens (edit-tags.php),
		// where post_type is empty but taxonomy is set.
		if ( ! empty( $screen->taxonomy ) && in_array( $screen->taxonomy, array( 'wbam-classified-cat', 'wbam-classified-loc' ), true ) ) {
			return true;
		}

		// Subpages registered under the wbam-ad CPT (Settings, Upgrade, Help…).
		if ( ! empty( $screen->id ) && false !== strpos( $screen->id, 'wbam-ad_page_' ) ) {
			return true;
		}

		// Setup wizard (registered under Dashboard).
		if ( ! empty( $screen->id ) && 'dashboard_page_wbam-setup' === $screen->id ) {
			return true;
		}

		// Belt-and-suspenders: any admin page whose `page` query is a wbam-*.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( '' !== $page && 0 === strpos( $page, 'wbam-' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Remove every callback on $hook whose owner is not WB Ad Manager.
	 *
	 * Callbacks we keep:
	 *   - Any class method whose class is in the `WBAM\` namespace.
	 *   - Any function whose name starts with `wbam_`.
	 *
	 * Everything else (third-party plugins, themes, rogue closures) is pulled.
	 *
	 * @param string $hook Hook name.
	 * @return void
	 */
	private function strip_hook( $hook ) {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook ] ) ) {
			return;
		}

		$filter = $wp_filter[ $hook ];
		if ( ! is_object( $filter ) || empty( $filter->callbacks ) ) {
			return;
		}

		foreach ( $filter->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$cb_callable = isset( $cb['function'] ) ? $cb['function'] : null;
				if ( null === $cb_callable ) {
					continue;
				}

				if ( $this->is_our_callback( $cb_callable ) ) {
					continue;
				}

				remove_action( $hook, $cb_callable, $priority );
			}
		}
	}

	/**
	 * Does the given callback belong to WB Ad Manager?
	 *
	 * @param mixed $cb_callable A WP hook callable: string, array, or Closure.
	 * @return bool
	 */
	/**
	 * Whether a class / static callback name belongs to this plugin family.
	 *
	 * @param string $name Class or "Class::method" name.
	 * @return bool
	 */
	private function is_own_namespace( $name ) {
		/**
		 * Filter the namespaces whose admin notices are kept on WB Ads screens.
		 * Companion plugins add their own so their notices are not stripped as
		 * third-party.
		 *
		 * @since 3.2.0
		 *
		 * @param string[] $namespaces Namespace prefixes, with trailing backslash.
		 */
		foreach ( (array) apply_filters( 'wbam_notice_suppressor_namespaces', array( 'WBAM\\' ) ) as $prefix ) {
			if ( '' !== $prefix && 0 === strpos( $name, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	private function is_our_callback( $cb_callable ) {
		// [ $object, 'method' ] or [ 'ClassName', 'method' ].
		if ( is_array( $cb_callable ) && isset( $cb_callable[0] ) ) {
			$class = is_object( $cb_callable[0] ) ? get_class( $cb_callable[0] ) : (string) $cb_callable[0];
			return $this->is_own_namespace( $class );
		}

		// Plain function name or static "Class::method".
		if ( is_string( $cb_callable ) ) {
			if ( $this->is_own_namespace( $cb_callable ) ) {
				return true;
			}
			if ( 0 === strpos( $cb_callable, 'wbam_' ) ) {
				return true;
			}
			return false;
		}

		// Closures — only keep if declared inside a WBAM file.
		if ( $cb_callable instanceof \Closure ) {
			try {
				$ref  = new \ReflectionFunction( $cb_callable );
				$file = (string) $ref->getFileName();
				if ( '' !== $file && false !== strpos( $file, DIRECTORY_SEPARATOR . 'wb-ads-rotator-with-split-test' . DIRECTORY_SEPARATOR ) ) {
					return true;
				}
			} catch ( \ReflectionException $e ) {
				return false;
			}
		}

		return false;
	}
}
