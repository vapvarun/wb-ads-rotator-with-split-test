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

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
	const DB_VERSION = '1.9.1';

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
		// Detect fresh install vs. upgrade BEFORE we touch DB_VERSION.
		// Fresh install = DB version option has never been set (get_option
		// returns the default we pass in, not a stored value). Upgrade =
		// any stored value exists, even '0'.
		$this->maybe_set_onboarding_pointers_default();
		$this->maybe_set_geo_enabled_default();

		$this->create_tables();
		$this->run_migrations();
		$this->update_db_version();
	}

	/**
	 * Set the default for the onboarding-pointers flag exactly once,
	 * at install time. Fresh installs get pointers enabled; upgrades
	 * from a previous version leave them off so power users aren't
	 * re-taught things they already know.
	 *
	 * Uses add_option so re-running install() (e.g. via
	 * maybe_update_database()) never flips the flag back on after
	 * a user has disabled it.
	 *
	 * @since 2.8.1
	 */
	private function maybe_set_onboarding_pointers_default() {
		// Sentinel lets us distinguish "never set" from "set to 0".
		$stored_db_version = get_option( self::DB_VERSION_OPTION, null );
		$is_fresh_install  = ( null === $stored_db_version );

		$default = $is_fresh_install ? 1 : 0;

		add_option( 'wbam_onboarding_pointers_enabled', $default );
	}

	/**
	 * Stamp `geo_enabled`'s default exactly once, at install time (owner
	 * decision 8, 3.2.0).
	 *
	 * A fresh install gets geolocation off - Settings::$defaults already
	 * ships `geo_enabled => false`, so there's nothing to write here. An
	 * existing site upgrading from a version that had no such gate was
	 * already relying on IP lookups (ad geo rules, PRO's country
	 * analytics), so it must not go dark on upgrade: `geo_enabled` is
	 * stamped `true` and whatever provider it already had keeps running.
	 * Settings::get_legacy_geo_provider_notice() recommends switching off
	 * the two legacy-only providers afterwards.
	 *
	 * Checks the settings array itself (not a separate flag) so re-running
	 * install() - e.g. via maybe_update_database() - never overwrites an
	 * owner's own later choice to turn geolocation off.
	 *
	 * @since 1.9.1
	 */
	private function maybe_set_geo_enabled_default() {
		$stored_db_version = get_option( self::DB_VERSION_OPTION, null );
		$is_fresh_install  = ( null === $stored_db_version );

		if ( $is_fresh_install ) {
			return; // Settings::$defaults already ships geo_enabled = false.
		}

		$settings = get_option( 'wbam_settings', array() );
		if ( is_array( $settings ) && array_key_exists( 'geo_enabled', $settings ) ) {
			return; // Already decided - this ran before, or the owner set it.
		}

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings['geo_enabled'] = true;
		update_option( 'wbam_settings', $settings );
	}

	/**
	 * Run database migrations for existing installs.
	 */
	private function run_migrations() {
		global $wpdb;

		$current_version = get_option( self::DB_VERSION_OPTION, '0' );

		// Migration to 1.4.0: Add PRO-compatible columns to analytics table.
		if ( version_compare( $current_version, '1.4.0', '<' ) ) {
			$table_analytics = $wpdb->prefix . 'wbam_analytics';

			// Check if table exists.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_analytics ) );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- WBAM custom table name from $wpdb->prefix, not user input.
			if ( $table_exists ) {
				// Add campaign_id column if it doesn't exist.
				$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_analytics}` LIKE %s", 'campaign_id' ) );
				if ( empty( $column_exists ) ) {
					$wpdb->query( "ALTER TABLE `{$table_analytics}` ADD COLUMN `campaign_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `ad_id`" );
					$wpdb->query( "ALTER TABLE `{$table_analytics}` ADD INDEX `campaign_id` (`campaign_id`)" );
				}

				// Add user_id column if it doesn't exist.
				$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_analytics}` LIKE %s", 'user_id' ) );
				if ( empty( $column_exists ) ) {
					$wpdb->query( "ALTER TABLE `{$table_analytics}` ADD COLUMN `user_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `event_type`" );
				}

				// Add ip_hash column if it doesn't exist.
				$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_analytics}` LIKE %s", 'ip_hash' ) );
				if ( empty( $column_exists ) ) {
					$wpdb->query( "ALTER TABLE `{$table_analytics}` ADD COLUMN `ip_hash` varchar(64) DEFAULT NULL AFTER `user_id`" );
				}

				// Add country column if it doesn't exist.
				$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_analytics}` LIKE %s", 'country' ) );
				if ( empty( $column_exists ) ) {
					$wpdb->query( "ALTER TABLE `{$table_analytics}` ADD COLUMN `country` varchar(2) DEFAULT NULL AFTER `ip_hash`" );
				}

				// Add device_type column if it doesn't exist.
				$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_analytics}` LIKE %s", 'device_type' ) );
				if ( empty( $column_exists ) ) {
					$wpdb->query( "ALTER TABLE `{$table_analytics}` ADD COLUMN `device_type` varchar(20) DEFAULT NULL AFTER `country`" );
				}

				// Add browser column if it doesn't exist.
				$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_analytics}` LIKE %s", 'browser' ) );
				if ( empty( $column_exists ) ) {
					$wpdb->query( "ALTER TABLE `{$table_analytics}` ADD COLUMN `browser` varchar(50) DEFAULT NULL AFTER `device_type`" );
				}

				// Add page_url column if it doesn't exist.
				$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_analytics}` LIKE %s", 'page_url' ) );
				if ( empty( $column_exists ) ) {
					$wpdb->query( "ALTER TABLE `{$table_analytics}` ADD COLUMN `page_url` varchar(2000) DEFAULT NULL AFTER `placement`" );
				}
			}
			// phpcs:enable
		}

		// Migration to 1.5.0: Create rate limits table if it doesn't exist.
		if ( version_compare( $current_version, '1.5.0', '<' ) ) {
			// Re-run create_tables to ensure rate limits table exists.
			// dbDelta is safe to run multiple times - it will only create missing tables.
			$this->create_tables();
		}

		// Migration to 1.6.0: Backfill ad format metadata so the
		// format-aware placement matching layer has something to work
		// with on existing installs. See Phase C of the format-matching
		// plan. Safe to run multiple times — skips ads that already
		// have _wbam_ad_format set.
		if ( version_compare( $current_version, '1.6.0', '<' ) ) {
			$this->backfill_ad_formats();
		}

		// Migration to 1.7.0: add visitor_hash + referrer columns to
		// wbam_link_clicks. The REST link-tracking endpoint and stats query
		// always wrote/read these columns, but the table only had
		// (id, link_id, clicked_at) — so every REST click INSERT silently
		// failed and unique-click stats errored to 0. dbDelta adds the two
		// columns in place. Safe to re-run.
		if ( version_compare( $current_version, '1.7.0', '<' ) ) {
			$this->create_tables();
		}

		// Migration to 1.8.0: ip_address columns widen from 45 to 64 chars.
		// Privacy_Helper::get_storage_ip() returns a 64-char SHA-256 hash
		// when IP anonymisation is on (the default), which a varchar(45)
		// rejects, so every partnership inquiry and email capture failed
		// to insert. install() runs create_tables() before this method, and
		// dbDelta alters the column type in place, so nothing to do here.

		// Migration to 1.9.0: wbam_analytics_daily, where Analytics_Rollup
		// keeps the totals of raw events past retention. Created by
		// create_tables() above, like 1.8.0.

		// Migration to 1.9.1: geolocation moves from always-on to an
		// explicit owner opt-in (owner decision 8). Handled by
		// maybe_set_geo_enabled_default(), called from install() before
		// this method runs (not here) so it can tell fresh-install apart
		// from upgrade using the same sentinel as the onboarding-pointers
		// default, before run_migrations() ever sees a stored DB version.

		// Phase K: backfill the `_wbam_is_demo` meta + `wbam_demo_data_ids`
		// tracking option so existing installs benefit from the safe
		// one-click demo cleanup without re-running the wizard. Runs
		// exactly once, guarded by the `wbam_demo_data_backfilled_v2`
		// option. Safe if the wizard was never run — it will simply
		// find zero matches and mark itself done.
		$this->maybe_backfill_demo_markers();
	}

	/**
	 * One-shot migration that marks previously-seeded sample ads with
	 * `_wbam_is_demo = 1` and populates `wbam_demo_data_ids`.
	 *
	 * Matches on BOTH the known sample titles AND the pre-existing
	 * `_wbam_sample_ad` meta set by older wizard runs — either signal
	 * is sufficient. This is intentionally tight: we never fuzzy-match
	 * on content or placement to avoid touching the admin's real ads.
	 *
	 * Guarded by the `wbam_demo_data_backfilled_v2` option so it runs
	 * exactly once per site.
	 *
	 * @since 2.8.0
	 */
	private function maybe_backfill_demo_markers() {
		if ( get_option( 'wbam_demo_data_backfilled_v2' ) ) {
			return;
		}

		$sample_titles = array(
			'Sample Header Banner',
			'Sample Sidebar Ad',
			'Sample In-Content Promo',
		);

		// Collect candidate ad IDs by title OR by the legacy sample marker.
		$candidate_ids = array();

		// 1) By legacy `_wbam_sample_ad` meta — set by every wizard run.
		$by_meta = get_posts(
			array(
				'post_type'      => 'wbam-ad',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'     => '_wbam_sample_ad',
						'compare' => 'EXISTS',
					),
				),
			)
		);
		if ( is_array( $by_meta ) ) {
			$candidate_ids = array_merge( $candidate_ids, array_map( 'intval', $by_meta ) );
		}

		// 2) By exact title match against the known sample titles.
		foreach ( $sample_titles as $title ) {
			$query = new \WP_Query(
				array(
					'post_type'              => 'wbam-ad',
					'title'                  => $title,
					'post_status'            => 'all',
					'posts_per_page'         => 1,
					'no_found_rows'          => true,
					'ignore_sticky_posts'    => true,
					'update_post_term_cache' => false,
					'update_post_meta_cache' => false,
				)
			);
			$page  = $query->have_posts() ? $query->next_post() : null;
			if ( $page instanceof \WP_Post ) {
				$candidate_ids[] = (int) $page->ID;
			}
		}

		$candidate_ids = array_values( array_unique( array_filter( $candidate_ids ) ) );

		if ( ! empty( $candidate_ids ) ) {
			$registry = get_option( 'wbam_demo_data_ids', array() );
			if ( ! is_array( $registry ) ) {
				$registry = array();
			}
			foreach ( array( 'ads', 'pages', 'links' ) as $bucket ) {
				if ( ! isset( $registry[ $bucket ] ) || ! is_array( $registry[ $bucket ] ) ) {
					$registry[ $bucket ] = array();
				}
			}

			foreach ( $candidate_ids as $ad_id ) {
				update_post_meta( $ad_id, '_wbam_is_demo', 1 );
				if ( ! in_array( $ad_id, $registry['ads'], true ) ) {
					$registry['ads'][] = $ad_id;
				}
			}

			update_option( 'wbam_demo_data_ids', $registry, false );
		}

		update_option( 'wbam_demo_data_backfilled_v2', 1, false );
	}

	/**
	 * Populate _wbam_ad_format / _wbam_ad_width / _wbam_ad_height on
	 * every existing ad that doesn't already have them.
	 *
	 * Resolution rules (preserves current rendering behavior):
	 *  - Image ad + local attachment: detect W x H, match to taxonomy.
	 *  - Image ad + external URL:     responsive (we don't fetch remote).
	 *  - Code / AdSense / Rich / Email Capture: responsive.
	 *
	 * Runs in-line during plugin upgrade. Installs typically have a few
	 * dozen ads at most; if a site has thousands we can convert this
	 * to an async batch via wp-cron — for now keep it simple and
	 * synchronous so everything is consistent right after upgrade.
	 *
	 * @since 2.8.1
	 */
	private function backfill_ad_formats() {
		$ads = get_posts(
			array(
				'post_type'      => 'wbam-ad',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// Only touch ads that don't already have a format set.
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'     => '_wbam_ad_format',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		if ( empty( $ads ) ) {
			return;
		}

		foreach ( $ads as $ad_id ) {
			$data = get_post_meta( $ad_id, '_wbam_ad_data', true );
			$type = is_array( $data ) && ! empty( $data['type'] ) ? (string) $data['type'] : '';

			$format = 'responsive';
			$width  = 0;
			$height = 0;

			if ( 'image' === $type && ! empty( $data['image_url'] ) ) {
				$attachment_id = attachment_url_to_postid( (string) $data['image_url'] );
				if ( $attachment_id > 0 ) {
					$src = wp_get_attachment_image_src( $attachment_id, 'full' );
					if ( is_array( $src ) && ! empty( $src[1] ) && ! empty( $src[2] ) ) {
						$width  = (int) $src[1];
						$height = (int) $src[2];

						if ( class_exists( '\\WBAM\\Core\\Ad_Formats' ) ) {
							$format = \WBAM\Core\Ad_Formats::detect_by_dimensions( $width, $height );
						}
					}
				}
			}

			update_post_meta( $ad_id, '_wbam_ad_format', $format );
			update_post_meta( $ad_id, '_wbam_ad_width', $width );
			update_post_meta( $ad_id, '_wbam_ad_height', $height );

			// Keep _wbam_is_responsive in sync with the resolved format
			// so existing admin UI toggles stay coherent.
			if ( 'responsive' === $format ) {
				update_post_meta( $ad_id, '_wbam_is_responsive', '1' );
			} elseif ( '' === (string) get_post_meta( $ad_id, '_wbam_is_responsive', true ) ) {
				update_post_meta( $ad_id, '_wbam_is_responsive', '0' );
			}
		}
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
			visitor_hash varchar(64) DEFAULT '',
			referrer varchar(255) DEFAULT '',
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
			campaign_id bigint(20) UNSIGNED DEFAULT NULL,
			event_type varchar(20) NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			ip_hash varchar(64) DEFAULT NULL,
			country varchar(2) DEFAULT NULL,
			device_type varchar(20) DEFAULT NULL,
			browser varchar(50) DEFAULT NULL,
			referrer varchar(2000) DEFAULT NULL,
			placement varchar(100) DEFAULT NULL,
			page_url varchar(2000) DEFAULT NULL,
			visitor_hash varchar(64) DEFAULT NULL,
			ip_address varchar(64) DEFAULT NULL,
			user_agent text,
			referer varchar(2000) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY ad_id (ad_id),
			KEY campaign_id (campaign_id),
			KEY event_type (event_type),
			KEY created_at (created_at),
			KEY ad_event (ad_id, event_type)
		) {$charset_collate};";

		dbDelta( $sql_analytics );

		// Daily totals of raw events past retention (Analytics_Rollup). Pro
		// reads and writes the same table; keep this definition identical to
		// Pro's so neither plugin's dbDelta alters the other's.
		$table_daily = $wpdb->prefix . 'wbam_analytics_daily';
		$sql_daily   = "CREATE TABLE {$table_daily} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ad_id bigint(20) unsigned NOT NULL,
			campaign_id bigint(20) unsigned DEFAULT NULL,
			date date NOT NULL,
			impressions int(11) unsigned NOT NULL DEFAULT 0,
			clicks int(11) unsigned NOT NULL DEFAULT 0,
			unique_impressions int(11) unsigned NOT NULL DEFAULT 0,
			unique_clicks int(11) unsigned NOT NULL DEFAULT 0,
			conversions int(11) unsigned NOT NULL DEFAULT 0,
			revenue decimal(10,4) NOT NULL DEFAULT 0.0000,
			PRIMARY KEY  (id),
			UNIQUE KEY ad_date (ad_id,date),
			KEY campaign_id (campaign_id),
			KEY date (date)
		) {$charset_collate};";

		dbDelta( $sql_daily );

		// Email submissions table.
		$table_submissions = $wpdb->prefix . 'wbam_email_submissions';
		$sql_submissions   = "CREATE TABLE {$table_submissions} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			ad_id bigint(20) UNSIGNED NOT NULL,
			email varchar(255) NOT NULL,
			name varchar(255) DEFAULT NULL,
			ip_address varchar(64) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY ad_id (ad_id),
			KEY email (email),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_submissions );

		// Link partnerships table.
		$table_partnerships = $wpdb->prefix . 'wbam_link_partnerships';
		$sql_partnerships   = "CREATE TABLE {$table_partnerships} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			email varchar(255) NOT NULL,
			website_url varchar(2000) NOT NULL,
			partnership_type varchar(30) NOT NULL DEFAULT 'paid_link',
			target_post_id bigint(20) UNSIGNED DEFAULT NULL,
			anchor_text varchar(255) DEFAULT NULL,
			message text,
			budget_min decimal(10,2) DEFAULT NULL,
			budget_max decimal(10,2) DEFAULT NULL,
			status varchar(20) DEFAULT 'pending',
			admin_notes text,
			ip_address varchar(64) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			responded_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY partnership_type (partnership_type),
			KEY email (email),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_partnerships );

		// Rate limits table (for atomic rate limiting).
		$table_rate_limits = $wpdb->prefix . 'wbam_rate_limits';
		$sql_rate_limits   = "CREATE TABLE {$table_rate_limits} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			rate_key varchar(255) NOT NULL,
			rate_count int(11) NOT NULL DEFAULT 0,
			expires bigint(20) UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY rate_key (rate_key),
			KEY expires (expires)
		) {$charset_collate};";

		dbDelta( $sql_rate_limits );
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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-time custom-table DDL during install/upgrade migration.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_links" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_link_categories" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_link_clicks" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_analytics" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_analytics_daily" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_email_submissions" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_link_partnerships" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbam_rate_limits" );
		// phpcs:enable

		delete_option( self::DB_VERSION_OPTION );
	}
}
