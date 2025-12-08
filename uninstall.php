<?php
/**
 * Uninstall WB Ad Manager
 *
 * Fired when the plugin is uninstalled.
 *
 * @package WB_Ad_Manager
 * @since   2.1.0
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Check if we should delete data on uninstall.
 *
 * By default, we keep data. Users must explicitly enable data deletion in settings.
 */
$settings = get_option( 'wbam_settings', array() );
$delete_data = isset( $settings['delete_data_on_uninstall'] ) && $settings['delete_data_on_uninstall'];

if ( ! $delete_data ) {
	return;
}

global $wpdb;

// Delete custom post types and their meta.
$ad_posts = get_posts(
	array(
		'post_type'      => 'wbam-ad',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

foreach ( $ad_posts as $post_id ) {
	wp_delete_post( $post_id, true );
}

// Drop custom database tables.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_links" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_link_categories" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_link_clicks" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_analytics" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_email_submissions" );
// phpcs:enable

// Delete options.
$options_to_delete = array(
	'wbam_settings',
	'wbam_db_version',
	'wbam_email_submissions', // Legacy option-based storage.
	'wbam_activation_redirect',
);

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

// Delete transients.
delete_transient( '_wbam_activation_redirect' );

// Clear any scheduled cron events.
wp_clear_scheduled_hook( 'wbam_daily_cleanup' );
wp_clear_scheduled_hook( 'wbam_hourly_stats' );

// Clean up user meta.
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wbam_%'" );

// Clean up post meta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_wbam_%'" );

// Flush rewrite rules.
flush_rewrite_rules();
