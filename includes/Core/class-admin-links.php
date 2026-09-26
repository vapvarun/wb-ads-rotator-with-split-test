<?php
/**
 * Admin Links Helper
 *
 * Centralized admin URL generation for WB Ad Manager.
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin_Links class.
 */
class Admin_Links {

	/**
	 * Post type slug.
	 */
	const POST_TYPE = 'wbam-ad';

	/**
	 * Get ads list URL.
	 *
	 * @param array $args Optional query arguments.
	 * @return string Admin URL.
	 */
	public static function ads_list( $args = array() ) {
		$url = admin_url( 'edit.php?post_type=' . self::POST_TYPE );

		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/**
	 * Get create new ad URL.
	 *
	 * @return string Admin URL.
	 */
	public static function ads_new() {
		return admin_url( 'post-new.php?post_type=' . self::POST_TYPE );
	}

	/**
	 * Get edit ad URL.
	 *
	 * @param int $ad_id Ad post ID.
	 * @return string Admin URL.
	 */
	public static function ads_edit( $ad_id ) {
		return admin_url( 'post.php?post=' . absint( $ad_id ) . '&action=edit' );
	}

	/**
	 * Get the one Settings screen URL, optionally for a specific section.
	 *
	 * The canonical URL builder for the sidebar Settings screen (`wbam-settings`).
	 * Both Free and Pro link here rather than hard-coding `page=`/`section=`
	 * query args, so a future section rename only touches this method.
	 *
	 * @since 3.2.0 Renamed from a `$tab`/`tab=` pair to `$section`/`section=`
	 *              to match the sidebar-section settings screen.
	 * @param string $section Optional section slug (e.g. 'general', 'classifieds').
	 * @return string Admin URL.
	 */
	public static function settings( $section = '' ) {
		$url = admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=wbam-settings' );

		return $section ? add_query_arg( 'section', $section, $url ) : $url;
	}

	/**
	 * Get the Email Captures list screen URL.
	 *
	 * Its own submenu under Advertisers (card 10343706274) — no longer
	 * embedded on the Settings screen's Tools section.
	 *
	 * @since 3.2.0
	 * @param array $args Optional query arguments (e.g. `deleted`, `paged`).
	 * @return string Admin URL.
	 */
	public static function email_captures( $args = array() ) {
		$url = admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=wbam-email-captures' );

		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/**
	 * Get display options page URL.
	 *
	 * @return string Admin URL.
	 */
	public static function display_options() {
		return admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=wbam-display-options' );
	}

	/**
	 * Get setup wizard URL.
	 *
	 * @param string $step Optional step slug.
	 * @return string Admin URL.
	 */
	public static function setup( $step = '' ) {
		$url = admin_url( 'index.php?page=wbam-setup' );

		return $step ? add_query_arg( 'step', $step, $url ) : $url;
	}

	/**
	 * Get help docs page URL.
	 *
	 * @return string Admin URL.
	 */
	public static function help() {
		return admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=wbam-help' );
	}

	/**
	 * Get links manager page URL.
	 *
	 * @param array $args Optional query arguments.
	 * @return string Admin URL.
	 */
	public static function links( $args = array() ) {
		$url = admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=wbam-links' );

		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/**
	 * Get analytics page URL.
	 *
	 * @param array $args Optional query arguments.
	 * @return string Admin URL.
	 */
	public static function analytics( $args = array() ) {
		$url = admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=wbam-analytics' );

		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/**
	 * Get upgrade to pro page URL.
	 *
	 * @return string Admin URL.
	 */
	public static function upgrade() {
		return admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=wbam-upgrade' );
	}

	/**
	 * Generate an admin action link with nonce.
	 *
	 * @param string $action Action slug.
	 * @param array  $args   Additional query arguments.
	 * @param string $nonce  Nonce action name (default: wbam_{action}).
	 * @return string Admin URL with nonce.
	 */
	public static function action_link( $action, $args = array(), $nonce = '' ) {
		$args['action']   = $action;
		$nonce_action     = $nonce ? $nonce : 'wbam_' . $action;
		$args['_wpnonce'] = wp_create_nonce( $nonce_action );

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
