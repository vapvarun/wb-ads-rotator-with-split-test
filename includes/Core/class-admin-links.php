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
	 * Get settings page URL.
	 *
	 * @param string $tab Optional tab slug.
	 * @return string Admin URL.
	 */
	public static function settings( $tab = '' ) {
		$url = admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=wbam-settings' );

		return $tab ? add_query_arg( 'tab', $tab, $url ) : $url;
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
		$args['action'] = $action;
		$nonce_action   = $nonce ? $nonce : 'wbam_' . $action;
		$args['_wpnonce'] = wp_create_nonce( $nonce_action );

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
