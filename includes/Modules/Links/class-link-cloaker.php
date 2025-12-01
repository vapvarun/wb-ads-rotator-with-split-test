<?php
/**
 * Link Cloaker Class
 *
 * Handles link cloaking and redirects.
 *
 * @package WB_Ad_Manager
 * @since   2.1.0
 */

namespace WBAM\Modules\Links;

use WBAM\Core\Settings_Helper;
use WBAM\Core\Singleton;

/**
 * Link Cloaker class.
 */
class Link_Cloaker {

	use Singleton;

	/**
	 * Cloaking prefix.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Initialize cloaker.
	 */
	public function init() {
		$this->prefix = $this->get_cloak_prefix();

		add_action( 'init', array( $this, 'add_rewrite_rules' ), 10 );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_redirect' ), 1 );
	}

	/**
	 * Get cloaking prefix from settings.
	 *
	 * @return string
	 */
	public function get_cloak_prefix() {
		$prefix = Settings_Helper::get( 'link_cloak_prefix', 'go' );
		return ! empty( $prefix ) ? sanitize_title( $prefix ) : 'go';
	}

	/**
	 * Add rewrite rules for cloaked links.
	 */
	public function add_rewrite_rules() {
		$prefix = $this->get_cloak_prefix();

		add_rewrite_rule(
			'^' . preg_quote( $prefix, '/' ) . '/([^/]+)/?$',
			'index.php?wbam_link=$matches[1]',
			'top'
		);

		// Check if rules need flushing.
		$current_prefix = get_option( 'wbam_link_prefix', '' );
		if ( $current_prefix !== $prefix ) {
			flush_rewrite_rules( false );
			update_option( 'wbam_link_prefix', $prefix );
		}
	}

	/**
	 * Add query variables.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'wbam_link';
		return $vars;
	}

	/**
	 * Handle link redirect.
	 */
	public function handle_redirect() {
		$slug = get_query_var( 'wbam_link' );

		if ( empty( $slug ) ) {
			return;
		}

		$link_manager = Link_Manager::get_instance();
		$link         = $link_manager->get_by_slug( $slug );

		if ( ! $link ) {
			$this->handle_404();
			return;
		}

		if ( ! $link->is_active() ) {
			$this->handle_inactive_link( $link );
			return;
		}

		// Record click.
		$link_manager->increment_clicks( $link->id );

		// Allow filtering before redirect.
		$destination = apply_filters( 'wbam_link_redirect_url', $link->get_destination_url(), $link );
		$redirect_type = apply_filters( 'wbam_link_redirect_type', $link->redirect_type, $link );

		do_action( 'wbam_before_link_redirect', $link, $destination );

		// Perform redirect.
		$this->redirect( $destination, $redirect_type );
	}

	/**
	 * Perform redirect.
	 *
	 * @param string $url           Destination URL.
	 * @param int    $redirect_type Redirect type (301, 302, 307).
	 */
	private function redirect( $url, $redirect_type = 307 ) {
		// Validate redirect type.
		$valid_types = array( 301, 302, 307 );
		if ( ! in_array( (int) $redirect_type, $valid_types, true ) ) {
			$redirect_type = 307;
		}

		// Clean output buffer.
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// Set no-cache headers.
		nocache_headers();

		// Perform redirect.
		wp_redirect( $url, $redirect_type );
		exit;
	}

	/**
	 * Handle 404 for non-existent links.
	 */
	private function handle_404() {
		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );

		// Allow custom handling.
		do_action( 'wbam_link_not_found' );
	}

	/**
	 * Handle inactive/expired links.
	 *
	 * @param Link $link The link object.
	 */
	private function handle_inactive_link( $link ) {
		$inactive_action = Settings_Helper::get( 'link_inactive_action', '404' );

		switch ( $inactive_action ) {
			case 'home':
				wp_safe_redirect( home_url(), 302 );
				exit;

			case 'custom':
				$custom_url = Settings_Helper::get( 'link_inactive_url', '' );
				if ( ! empty( $custom_url ) ) {
					wp_redirect( $custom_url, 302 );
					exit;
				}
				// Fall through to 404.

			case '404':
			default:
				$this->handle_404();
				break;
		}

		do_action( 'wbam_inactive_link_accessed', $link );
	}

	/**
	 * Get full cloaked URL for a link.
	 *
	 * @param Link|string $link Link object or slug.
	 * @return string
	 */
	public function get_cloaked_url( $link ) {
		$slug = is_object( $link ) ? $link->slug : $link;
		return home_url( '/' . $this->get_cloak_prefix() . '/' . $slug );
	}

	/**
	 * Check if a URL is a cloaked link.
	 *
	 * @param string $url URL to check.
	 * @return bool
	 */
	public function is_cloaked_url( $url ) {
		$prefix  = $this->get_cloak_prefix();
		$pattern = '/' . preg_quote( $prefix, '/' ) . '\/[^\/]+\/?$/';

		return (bool) preg_match( $pattern, $url );
	}

	/**
	 * Extract slug from cloaked URL.
	 *
	 * @param string $url Cloaked URL.
	 * @return string|null
	 */
	public function extract_slug( $url ) {
		$prefix  = $this->get_cloak_prefix();
		$pattern = '/' . preg_quote( $prefix, '/' ) . '\/([^\/]+)\/?$/';

		if ( preg_match( $pattern, $url, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Flush rewrite rules (call when settings change).
	 */
	public function flush_rules() {
		delete_option( 'wbam_link_prefix' );
		flush_rewrite_rules( false );
	}
}
