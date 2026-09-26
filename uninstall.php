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
$wbam_settings    = get_option( 'wbam_settings', array() );
$wbam_delete_data = isset( $wbam_settings['delete_data_on_uninstall'] ) && $wbam_settings['delete_data_on_uninstall'];

if ( ! $wbam_delete_data ) {
	return;
}

// Pro reads this switch from wbam_settings, which is deleted below. If Pro
// is still installed, leave it a flag so its own uninstall honours the
// same choice (it deletes the flag).
if ( file_exists( WP_PLUGIN_DIR . '/wb-ad-manager-pro/uninstall.php' ) ) {
	update_option( 'wbam_delete_data_on_uninstall', 1, false );
}

global $wpdb;

// Delete custom post types and their meta.
$wbam_ad_posts = get_posts(
	array(
		'post_type'      => 'wbam-ad',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

foreach ( $wbam_ad_posts as $wbam_post_id ) {
	wp_delete_post( $wbam_post_id, true );
}

/*
 * Remove ad tag terms.
 *
 * WordPress does not clean up terms when a taxonomy stops being registered - it
 * only stops showing them. Deleting the ads above removes the relationships but
 * leaves every term behind in wp_terms and wp_term_taxonomy, invisible and
 * permanent. The taxonomy is not registered at uninstall time either, so
 * get_terms() cannot be used; ask the term table for the taxonomy by name.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall runs with the taxonomy unregistered, so get_terms() is unavailable and there is no cache to prime; the table is a core one and the taxonomy name is bound.
$wbam_tag_term_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
		'wbam_ad_tag'
	)
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup; taxonomy is not registered here so the term APIs are unavailable.

foreach ( $wbam_tag_term_ids as $wbam_term_id ) {
	wp_delete_term( (int) $wbam_term_id, 'wbam_ad_tag' );
}

// Drop custom database tables.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_links" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_link_categories" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_link_clicks" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_analytics" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_analytics_daily" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_email_submissions" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_link_partnerships" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_rate_limits" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_links" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_link_clicks" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_link_categories" );
// phpcs:enable

// Delete options.
$wbam_options_to_delete = array(
	'wbam_settings',
	'wbam_db_version',
	'wbam_email_submissions', // Legacy option-based storage.
	'wbam_activation_redirect',
	'wbam_link_prefix',
	'wbam_demo_data_ids',
	'wbam_onboarding_pointers_enabled',
	'wbam_ad_type_backfilled',
	'wbam_demo_data_backfilled_v2',
	'wbam_setup_complete',
);

foreach ( $wbam_options_to_delete as $wbam_option ) {
	delete_option( $wbam_option );
}

// Delete transients.
delete_transient( '_wbam_activation_redirect' );

// Clear any scheduled cron events.
wp_clear_scheduled_hook( 'wbam_daily_cleanup' );
wp_clear_scheduled_hook( 'wbam_hourly_stats' );
wp_clear_scheduled_hook( 'wbam_analytics_rollup' );

// Clean up user meta.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup of plugin custom tables.
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wbam_%'" );

// Clean up post meta.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup of plugin custom tables.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_wbam_%'" );

// Flush rewrite rules.
flush_rewrite_rules();
