<?php
/**
 * Installer Class
 *
 * Handles database table creation and updates.
 *
 * @package WB_Ad_Manager
 * @since   2.1.0
 */

namespace WBAM\Core;

/**
 * Installer class.
 */
class Installer {

	use Singleton;

	/**
	 * Database version.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.2.0';

	/**
	 * Option name for database version.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'wbam_db_version';

	/**
	 * Run installer.
	 */
	public function install() {
		$this->create_tables();
		$this->update_db_version();
	}

	/**
	 * Check if database needs update.
	 *
	 * @return bool
	 */
	public function needs_update() {
		$current_version = get_option( self::DB_VERSION_OPTION, '0' );
		return version_compare( $current_version, self::DB_VERSION, '<' );
	}

	/**
	 * Create database tables.
	 */
	public function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Links table.
		$table_links = $wpdb->prefix . 'wbam_links';
		$sql_links   = "CREATE TABLE {$table_links} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			destination_url text NOT NULL,
			slug varchar(100) DEFAULT NULL,
			link_type varchar(20) DEFAULT 'affiliate',
			cloaking_enabled tinyint(1) DEFAULT 1,
			nofollow tinyint(1) DEFAULT 1,
			sponsored tinyint(1) DEFAULT 0,
			new_tab tinyint(1) DEFAULT 1,
			category_id bigint(20) UNSIGNED DEFAULT 0,
			description text,
			parameters text,
			redirect_type int(3) DEFAULT 307,
			click_count bigint(20) UNSIGNED DEFAULT 0,
			status varchar(20) DEFAULT 'active',
			expires_at datetime DEFAULT NULL,
			payment_amount decimal(10,2) DEFAULT 0.00,
			payment_type varchar(20) DEFAULT 'one_time',
			payment_currency varchar(3) DEFAULT 'USD',
			payment_status varchar(20) DEFAULT 'unpaid',
			commission_rate decimal(5,2) DEFAULT 0.00,
			total_revenue decimal(10,2) DEFAULT 0.00,
			created_by bigint(20) UNSIGNED DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status),
			KEY link_type (link_type),
			KEY category_id (category_id),
			KEY payment_status (payment_status)
		) {$charset_collate};";

		dbDelta( $sql_links );

		// Link categories table.
		$table_categories = $wpdb->prefix . 'wbam_link_categories';
		$sql_categories   = "CREATE TABLE {$table_categories} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			slug varchar(100) NOT NULL,
			description text,
			parent_id bigint(20) UNSIGNED DEFAULT 0,
			count int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY parent_id (parent_id)
		) {$charset_collate};";

		dbDelta( $sql_categories );

		// Link clicks table (basic tracking for FREE version).
		$table_clicks = $wpdb->prefix . 'wbam_link_clicks';
		$sql_clicks   = "CREATE TABLE {$table_clicks} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			link_id bigint(20) UNSIGNED NOT NULL,
			clicked_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY link_id (link_id),
			KEY clicked_at (clicked_at)
		) {$charset_collate};";

		dbDelta( $sql_clicks );

		// Analytics table for ad impressions and clicks.
		$table_analytics = $wpdb->prefix . 'wbam_analytics';
		$sql_analytics   = "CREATE TABLE {$table_analytics} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			ad_id bigint(20) UNSIGNED NOT NULL,
			event_type varchar(20) NOT NULL,
			placement varchar(100) DEFAULT NULL,
			visitor_hash varchar(64) DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			user_agent text,
			referer varchar(2000) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY ad_id (ad_id),
			KEY event_type (event_type),
			KEY created_at (created_at),
			KEY ad_event (ad_id, event_type)
		) {$charset_collate};";

		dbDelta( $sql_analytics );

		// Email submissions table.
		$table_submissions = $wpdb->prefix . 'wbam_email_submissions';
		$sql_submissions   = "CREATE TABLE {$table_submissions} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			ad_id bigint(20) UNSIGNED NOT NULL,
			email varchar(255) NOT NULL,
			name varchar(255) DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY ad_id (ad_id),
			KEY email (email),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_submissions );
	}

	/**
	 * Update database version.
	 */
	private function update_db_version() {
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Drop all plugin tables (used on uninstall).
	 */
	public function drop_tables() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_links" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_link_categories" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_link_clicks" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_analytics" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_email_submissions" );
		// phpcs:enable

		delete_option( self::DB_VERSION_OPTION );
	}
}
