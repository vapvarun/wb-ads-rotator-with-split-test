<?php
/**
 * WordPress Abilities API Registration
 *
 * Registers plugin abilities for WP 6.9+ so 3rd-party AI agents can
 * discover and execute plugin features via wp-json/wp-abilities/v1/.
 *
 * @package WB_Ad_Manager
 * @since   2.8.0
 */

namespace WBAM\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abilities class.
 *
 * Plain class (no Singleton) — instantiated once inside Plugin::init_components().
 * Categories MUST be registered on wp_abilities_api_categories_init.
 * Abilities MUST be registered on wp_abilities_api_init.
 * Registering outside those hooks triggers _doing_it_wrong() and fails silently.
 */
class Abilities {

	/**
	 * Constructor — hooks registration onto the correct actions.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	// -------------------------------------------------------------------------
	// Category & ability registration
	// -------------------------------------------------------------------------

	/**
	 * Register ability categories.
	 *
	 * Must only be called from the wp_abilities_api_categories_init hook.
	 */
	public function register_categories() {
		// The Abilities API ships in WP 6.9+. This method only runs on the
		// 6.9+ wp_abilities_api_categories_init hook, but guard the call so
		// the plugin stays compatible with its declared 5.8 minimum.
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'wbam-ads',
			array(
				'label'       => __( 'Ad Management', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Create, manage, and serve ads across your WordPress site', 'wb-ads-rotator-with-split-test' ),
			)
		);

		wp_register_ability_category(
			'wbam-analytics',
			array(
				'label'       => __( 'Ad Analytics', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Track and analyze ad performance metrics', 'wb-ads-rotator-with-split-test' ),
			)
		);

		wp_register_ability_category(
			'wbam-links',
			array(
				'label'       => __( 'Link Management', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Manage tracked affiliate and partnership links', 'wb-ads-rotator-with-split-test' ),
			)
		);
	}

	/**
	 * Register all plugin abilities.
	 *
	 * Must only be called from the wp_abilities_api_init hook.
	 */
	public function register_abilities() {
		// The Abilities API ships in WP 6.9+. This method only runs on the
		// 6.9+ wp_abilities_api_init hook, but guard the calls so the plugin
		// stays compatible with its declared 5.8 minimum.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_ad_abilities();
		$this->register_analytics_abilities();
		$this->register_link_abilities();
	}

	// -------------------------------------------------------------------------
	// Ad Management abilities (8)
	// -------------------------------------------------------------------------

	/**
	 * Register ad management abilities.
	 */
	private function register_ad_abilities() {

		// wbam/list-ads
		wp_register_ability(
			'wbam/list-ads',
			array(
				'label'               => __( 'List Ads', 'wb-ads-rotator-with-split-test' ),
				'description'         => __( 'List all ads with optional filters by type, placement, and status. Returns ad IDs, titles, types, and enabled status.', 'wb-ads-rotator-with-split-test' ),
				'category'            => 'wbam-ads',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'status'    => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'draft', 'any' ),
							'description' => __( 'Filter ads by post status.', 'wb-ads-rotator-with-split-test' ),
						),
						'type'      => array(
							'type'        => 'string',
							'description' => __( 'Filter ads by ad type.', 'wb-ads-rotator-with-split-test' ),
						),
						'placement' => array(
							'type'        => 'string',
							'description' => __( 'Filter ads assigned to a specific placement.', 'wb-ads-rotator-with-split-test' ),
						),
						'per_page'  => array(
							'type'        => 'integer',
							'default'     => 20,
							'description' => __( 'Number of ads per page.', 'wb-ads-rotator-with-split-test' ),
						),
						'page'      => array(
							'type'        => 'integer',
							'default'     => 1,
							'description' => __( 'Page number for pagination.', 'wb-ads-rotator-with-split-test' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'items' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
						'total' => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_list_ads' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
				),
			)
		);

		// wbam/get-ad
		wp_register_ability(
			'wbam/get-ad',
			array(
				'label'               => __( 'Get Ad Details', 'wb-ads-rotator-with-split-test' ),
				'description'         => __( 'Get complete details for a single ad including content, targeting rules, placements, and performance stats.', 'wb-ads-rotator-with-split-test' ),
				'category'            => 'wbam-ads',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'Ad post ID.', 'wb-ads-rotator-with-split-test' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => array( 'type' => 'integer' ),
						'title'      => array( 'type' => 'string' ),
						'type'       => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'object' ),
						'placements' => array( 'type' => 'array' ),
						'targeting'  => array( 'type' => 'object' ),
						'enabled'    => array( 'type' => 'boolean' ),
						'stats'      => array( 'type' => 'object' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_get_ad' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
				),
			)
		);

		// wbam/create-ad
		wp_register_ability(
			'wbam/create-ad',
			array(
				'label'               => __( 'Create Ad', 'wb-ads-rotator-with-split-test' ),
				'description'         => __( 'Create a new ad with specified type (image, code, rich-content, email_capture, adsense), content, and placement assignments.', 'wb-ads-rotator-with-split-test' ),
				'category'            => 'wbam-ads',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'title', 'type', 'content' ),
					'properties' => array(
						'title'      => array(
							'type'        => 'string',
							'description' => __( 'Ad title.', 'wb-ads-rotator-with-split-test' ),
						),
						'type'       => array(
							'type'        => 'string',
							'enum'        => array( 'image', 'code', 'rich-content', 'rich_content', 'email_capture', 'adsense' ),
							'description' => __( 'Ad format type.', 'wb-ads-rotator-with-split-test' ),
						),
						'content'    => array(
							'type'        => 'object',
							'description' => __( 'Type-specific ad content fields.', 'wb-ads-rotator-with-split-test' ),
						),
						'placements' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Placement IDs to assign this ad to.', 'wb-ads-rotator-with-split-test' ),
						),
						'enabled'    => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'Whether the ad is active.', 'wb-ads-rotator-with-split-test' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer' ),
						'title'    => array( 'type' => 'string' ),
						'edit_url' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_create_ad' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array( 'destructive' => true ),
				),
			)
		);

		// wbam/update-ad
		wp_register_ability(
			'wbam/update-ad',
			array(
				'label'               => __( 'Update Ad', 'wb-ads-rotator-with-split-test' ),
				'description'         => __( "Update an existing ad's title, content, placements, targeting rules, or enabled status.", 'wb-ads-rotator-with-split-test' ),
				'category'            => 'wbam-ads',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id'         => array(
							'type'        => 'integer',
							'description' => __( 'Ad post ID to update.', 'wb-ads-rotator-with-split-test' ),
						),
						'title'      => array(
							'type'        => 'string',
							'description' => __( 'New ad title.', 'wb-ads-rotator-with-split-test' ),
						),
						'content'    => array(
							'type'        => 'object',
							'description' => __( 'Updated ad content fields.', 'wb-ads-rotator-with-split-test' ),
						),
						'placements' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Updated placement assignments.', 'wb-ads-rotator-with-split-test' ),
						),
						'enabled'    => array(
							'type'        => 'boolean',
							'description' => __( 'Whether the ad is active.', 'wb-ads-rotator-with-split-test' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer' ),
						'updated' => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_update_ad' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array( 'destructive' => true ),
				),
			)
		);

		// wbam/delete-ad
		wp_register_ability(
			'wbam/delete-ad',
			array(
				'label'               => __( 'Delete Ad', 'wb-ads-rotator-with-split-test' ),
				'description'         => __( 'Permanently delete an ad and its associated analytics data.', 'wb-ads-rotator-with-split-test' ),
				'category'            => 'wbam-ads',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'Ad post ID to delete.', 'wb-ads-rotator-with-split-test' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'deleted' => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_delete_ad' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array( 'destructive' => true ),
				),
			)
		);

		// wbam/serve-ad
		wp_register_ability(
			'wbam/serve-ad',
			array(
				'label'               => __( 'Serve Ad for Placement', 'wb-ads-rotator-with-split-test' ),
				'description'         => __( 'Get the best ad for a given placement, applying all targeting rules (device, geo, frequency, content analysis). Returns rendered HTML ready for display.', 'wb-ads-rotator-with-split-test' ),
				'category'            => 'wbam-ads',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'placement' ),
					'properties' => array(
						'placement' => array(
							'type'        => 'string',
							'description' => __( 'Placement ID to serve an ad for.', 'wb-ads-rotator-with-split-test' ),
						),
						'post_id'   => array(
							'type'        => 'integer',
							'description' => __( 'Current post ID for content targeting.', 'wb-ads-rotator-with-split-test' ),
						),
						'page_url'  => array(
							'type'        => 'string',
							'description' => __( 'Current page URL for targeting context.', 'wb-ads-rotator-with-split-test' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'html'      => array( 'type' => 'string' ),
						'ad_id'     => array( 'type' => 'integer' ),
						'placement' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_serve_ad' ),
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array( 'readonly' => true ),
				),
			)
		);

		// wbam/list-placements
		wp_register_ability(
			'wbam/list-placements',
			array(
				'label'               => __( 'List Ad Placements', 'wb-ads-rotator-with-split-test' ),
				'description'         => __( 'List all available ad placement types (header, footer, content, sidebar, popup, etc.) with their configuration.', 'wb-ads-rotator-with-split-test' ),
				'category'            => 'wbam-ads',
				'input_schema'        => array(
					'type'        => 'object',
					'properties'  => array(),
					'description' => __( 'No input required.', 'wb-ads-rotator-with-split-test' ),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'          => array( 'type' => 'string' ),
							'label'       => array( 'type' => 'string' ),
							'description' => array( 'type' => 'string' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'execute_list_placements' ),
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
				),
			)
		);

		// wbam/list-ad-types
		wp_register_ability(
			'wbam/list-ad-types',
			array(
				'label'               => __( 'List Ad Types', 'wb-ads-rotator-with-split-test' ),
				'description'         => __( 'List all available ad format types (image, code, rich content, email capture, adsense) with their supported fields.', 'wb-ads-rotator-with-split-test' ),
				'category'            => 'wbam-ads',
				'input_schema'        => array(
					'type'        => 'object',
					'properties'  => array(),
					'description' => __( 'No input required.', 'wb-ads-rotator-with-split-test' ),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'     => array( 'type' => 'string' ),
							'label'  => array( 'type' => 'string' ),
							'fields' => array( 'type' => 'array' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'execute_list_ad_types' ),
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Analytics abilities (2)
	// -------------------------------------------------------------------------

	/**
	 * Register analytics abilities.
	 */
	private function register_analytics_abilities() {

		// wbam/get-analytics-overview
		wp_register_ability(
			'wbam/get-analytics-overview',
			array(
				'label'               => __( 'Analytics Overview', 'wb-ads-rotator-with-split-test' ),
				'description'         => __( 'Get summary analytics including total impressions, clicks, CTR, and top performing ads for a date range.', 'wb-ads-rotator-with-split-test' ),
				'category'            => 'wbam-analytics',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'start_date' => array(
							'type'        => 'string',
							'description' => __( 'Start date in Y-m-d format.', 'wb-ads-rotator-with-split-test' ),
						),
						'end_date'   => array(
							'type'        => 'string',
							'description' => __( 'End date in Y-m-d format.', 'wb-ads-rotator-with-split-test' ),
						),
						'limit'      => array(
							'type'        => 'integer',
							'default'     => 10,
							'description' => __( 'Number of top ads to return.', 'wb-ads-rotator-with-split-test' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'total_impressions' => array( 'type' => 'integer' ),
						'total_clicks'      => array( 'type' => 'integer' ),
						'ctr'               => array( 'type' => 'number' ),
						'top_ads'           => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_get_analytics_overview' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
				),
			)
		);

		// wbam/get-ad-stats
		wp_register_ability(
			'wbam/get-ad-stats',
			array(
				'label'               => __( 'Get Ad Performance Stats', 'wb-ads-rotator-with-split-test' ),
				'description'         => __( 'Get detailed performance statistics for a specific ad including impressions, clicks, CTR, and placement breakdown.', 'wb-ads-rotator-with-split-test' ),
				'category'            => 'wbam-analytics',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id'         => array(
							'type'        => 'integer',
							'description' => __( 'Ad post ID.', 'wb-ads-rotator-with-split-test' ),
						),
						'start_date' => array(
							'type'        => 'string',
							'description' => __( 'Start date in Y-m-d format.', 'wb-ads-rotator-with-split-test' ),
						),
						'end_date'   => array(
							'type'        => 'string',
							'description' => __( 'End date in Y-m-d format.', 'wb-ads-rotator-with-split-test' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'impressions'  => array( 'type' => 'integer' ),
						'clicks'       => array( 'type' => 'integer' ),
						'ctr'          => array( 'type' => 'number' ),
						'by_placement' => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_get_ad_stats' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Link Management abilities (5)
	// -------------------------------------------------------------------------

	/**
	 * Register link management abilities.
	 */
	private function register_link_abilities() {

		// wbam/list-links
		wp_register_ability(
			'wbam/list-links',
			array(
				'label'               => __( 'List Links', 'wb-ads-rotator-with-split-test' ),
				'description'         => __( 'List all managed/tracked links with click statistics.', 'wb-ads-rotator-with-split-test' ),
				'category'            => 'wbam-links',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'category_id' => array(
							'type'        => 'integer',
							'description' => __( 'Filter by link category ID.', 'wb-ads-rotator-with-split-test' ),
						),
						'status'      => array(
							'type'        => 'string',
							'description' => __( 'Filter by link status (active, inactive, expired).', 'wb-ads-rotator-with-split-test' ),
						),
						'per_page'    => array(
							'type'        => 'integer',
							'default'     => 20,
							'description' => __( 'Number of links per page.', 'wb-ads-rotator-with-split-test' ),
						),
						'page'        => array(
							'type'        => 'integer',
							'default'     => 1,
							'description' => __( 'Page number for pagination.', 'wb-ads-rotator-with-split-test' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'items' => array( 'type' => 'array' ),
						'total' => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_list_links' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
				),
			)
		);

		// wbam/create-link
		wp_register_ability(
			'wbam/create-link',
			array(
				'label'               => __( 'Create Link', 'wb-ads-rotator-with-split-test' ),
				'description'         => __( 'Create a new tracked/cloaked affiliate link with optional category assignment.', 'wb-ads-rotator-with-split-test' ),
				'category'            => 'wbam-links',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'name', 'url' ),
					'properties' => array(
						'name'        => array(
							'type'        => 'string',
							'description' => __( 'Link display name.', 'wb-ads-rotator-with-split-test' ),
						),
						'url'         => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => __( 'Destination URL.', 'wb-ads-rotator-with-split-test' ),
						),
						'category_id' => array(
							'type'        => 'integer',
							'description' => __( 'Category ID to assign the link to.', 'wb-ads-rotator-with-split-test' ),
						),
						'nofollow'    => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'Add rel="nofollow" to the link.', 'wb-ads-rotator-with-split-test' ),
						),
						'new_tab'     => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'Open link in a new tab.', 'wb-ads-rotator-with-split-test' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array( 'type' => 'integer' ),
						'name'        => array( 'type' => 'string' ),
						'cloaked_url' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_create_link' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array( 'destructive' => true ),
				),
			)
		);

		// wbam/update-link
		wp_register_ability(
			'wbam/update-link',
			array(
				'label'               => __( 'Update Link', 'wb-ads-rotator-with-split-test' ),
				'description'         => __( "Update a managed link's URL, name, category, or tracking settings.", 'wb-ads-rotator-with-split-test' ),
				'category'            => 'wbam-links',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id'          => array(
							'type'        => 'integer',
							'description' => __( 'Link ID to update.', 'wb-ads-rotator-with-split-test' ),
						),
						'name'        => array(
							'type'        => 'string',
							'description' => __( 'New link display name.', 'wb-ads-rotator-with-split-test' ),
						),
						'url'         => array(
							'type'        => 'string',
							'description' => __( 'New destination URL.', 'wb-ads-rotator-with-split-test' ),
						),
						'category_id' => array(
							'type'        => 'integer',
							'description' => __( 'New category ID.', 'wb-ads-rotator-with-split-test' ),
						),
						'nofollow'    => array(
							'type'        => 'boolean',
							'description' => __( 'Add rel="nofollow" to the link.', 'wb-ads-rotator-with-split-test' ),
						),
						'new_tab'     => array(
							'type'        => 'boolean',
							'description' => __( 'Open link in a new tab.', 'wb-ads-rotator-with-split-test' ),
						),
						'status'      => array(
							'type'        => 'string',
							'enum'        => array( 'active', 'inactive', 'expired' ),
							'description' => __( 'Link status.', 'wb-ads-rotator-with-split-test' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer' ),
						'updated' => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_update_link' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array( 'destructive' => true ),
				),
			)
		);

		// wbam/delete-link
		wp_register_ability(
			'wbam/delete-link',
			array(
				'label'               => __( 'Delete Link', 'wb-ads-rotator-with-split-test' ),
				'description'         => __( 'Delete a managed link and its click tracking history.', 'wb-ads-rotator-with-split-test' ),
				'category'            => 'wbam-links',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'Link ID to delete.', 'wb-ads-rotator-with-split-test' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'deleted' => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_delete_link' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array( 'destructive' => true ),
				),
			)
		);

		// wbam/get-link-stats
		wp_register_ability(
			'wbam/get-link-stats',
			array(
				'label'               => __( 'Get Link Click Stats', 'wb-ads-rotator-with-split-test' ),
				'description'         => __( 'Get click statistics for a specific link including total clicks, unique clicks, and referrer breakdown.', 'wb-ads-rotator-with-split-test' ),
				'category'            => 'wbam-links',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id'         => array(
							'type'        => 'integer',
							'description' => __( 'Link ID.', 'wb-ads-rotator-with-split-test' ),
						),
						'start_date' => array(
							'type'        => 'string',
							'description' => __( 'Start date in Y-m-d format.', 'wb-ads-rotator-with-split-test' ),
						),
						'end_date'   => array(
							'type'        => 'string',
							'description' => __( 'End date in Y-m-d format.', 'wb-ads-rotator-with-split-test' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'total_clicks'  => array( 'type' => 'integer' ),
						'unique_clicks' => array( 'type' => 'integer' ),
						'by_date'       => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_get_link_stats' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Execute callbacks — Ad Management
	// -------------------------------------------------------------------------

	/**
	 * Execute wbam/list-ads.
	 *
	 * @since 2.8.0
	 *
	 * @param array $input Ability input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_list_ads( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$status   = isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'publish';
		$per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 20;
		$page     = isset( $input['page'] ) ? absint( $input['page'] ) : 1;

		// Map 'any' to WordPress post_status 'any'.
		$post_status = in_array( $status, array( 'publish', 'draft', 'any' ), true ) ? $status : 'publish';

		$query_args = array(
			'post_type'              => 'wbam-ad',
			'post_status'            => $post_status,
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		);

		// Optional placement filter.
		if ( ! empty( $input['placement'] ) ) {
			$placement                = sanitize_text_field( $input['placement'] );
			$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_wbam_placements',
					'value'   => $placement,
					'compare' => 'LIKE',
				),
			);
		}

		$query = new \WP_Query( $query_args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$ad_data    = get_post_meta( $post->ID, '_wbam_ad_data', true );
			$placements = get_post_meta( $post->ID, '_wbam_placements', true );
			$items[]    = array(
				'id'         => $post->ID,
				'title'      => $post->post_title,
				'type'       => isset( $ad_data['type'] ) ? sanitize_text_field( $ad_data['type'] ) : '',
				'enabled'    => (bool) get_post_meta( $post->ID, '_wbam_enabled', true ),
				'placements' => is_array( $placements ) ? $placements : array(),
				'status'     => $post->post_status,
			);
		}

		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * Execute wbam/get-ad.
	 *
	 * @since 2.8.0
	 *
	 * @param array $input Ability input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_get_ad( $input ) {
		$input = is_array( $input ) ? $input : array();

		if ( empty( $input['id'] ) ) {
			return new \WP_Error( 'missing_id', __( 'Ad ID is required.', 'wb-ads-rotator-with-split-test' ) );
		}

		$id   = absint( $input['id'] );
		$post = get_post( $id );

		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return new \WP_Error( 'ad_not_found', __( 'Ad not found.', 'wb-ads-rotator-with-split-test' ) );
		}

		$ad_data    = get_post_meta( $id, '_wbam_ad_data', true );
		$placements = get_post_meta( $id, '_wbam_placements', true );

		return array(
			'id'         => $id,
			'title'      => $post->post_title,
			'type'       => isset( $ad_data['type'] ) ? sanitize_text_field( $ad_data['type'] ) : '',
			'content'    => is_array( $ad_data ) ? $ad_data : array(),
			'placements' => is_array( $placements ) ? $placements : array(),
			'targeting'  => array(
				'priority' => (int) get_post_meta( $id, '_wbam_priority', true ),
			),
			'enabled'    => (bool) get_post_meta( $id, '_wbam_enabled', true ),
			'status'     => $post->post_status,
			'created'    => $post->post_date,
			'modified'   => $post->post_modified,
		);
	}

	/**
	 * Execute wbam/create-ad.
	 *
	 * @since 2.8.0
	 *
	 * @param array $input Ability input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_create_ad( $input ) {
		$input = is_array( $input ) ? $input : array();

		if ( empty( $input['title'] ) ) {
			return new \WP_Error( 'missing_title', __( 'Ad title is required.', 'wb-ads-rotator-with-split-test' ) );
		}
		if ( empty( $input['type'] ) ) {
			return new \WP_Error( 'missing_type', __( 'Ad type is required.', 'wb-ads-rotator-with-split-test' ) );
		}
		if ( empty( $input['content'] ) || ! is_array( $input['content'] ) ) {
			return new \WP_Error( 'missing_content', __( 'Ad content is required.', 'wb-ads-rotator-with-split-test' ) );
		}

		$title   = sanitize_text_field( $input['title'] );
		$enabled = isset( $input['enabled'] ) ? (bool) $input['enabled'] : true;

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return new \WP_Error( 'create_failed', __( 'Could not create ad.', 'wb-ads-rotator-with-split-test' ) );
		}

		// Build ad_data: merge 'type' into the content array.
		$ad_data         = $input['content'];
		$ad_data['type'] = sanitize_text_field( $input['type'] );
		update_post_meta( $post_id, '_wbam_ad_data', $ad_data );
		update_post_meta( $post_id, '_wbam_enabled', $enabled ? '1' : '0' );

		if ( ! empty( $input['placements'] ) && is_array( $input['placements'] ) ) {
			$placements = array_map( 'sanitize_text_field', $input['placements'] );
			update_post_meta( $post_id, '_wbam_placements', $placements );
		}

		do_action( 'wbam_save_ad_meta', $post_id );

		return array(
			'id'       => $post_id,
			'title'    => $title,
			'edit_url' => get_edit_post_link( $post_id, 'raw' ),
		);
	}

	/**
	 * Execute wbam/update-ad.
	 *
	 * @since 2.8.0
	 *
	 * @param array $input Ability input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_update_ad( $input ) {
		$input = is_array( $input ) ? $input : array();

		if ( empty( $input['id'] ) ) {
			return new \WP_Error( 'missing_id', __( 'Ad ID is required.', 'wb-ads-rotator-with-split-test' ) );
		}

		$id   = absint( $input['id'] );
		$post = get_post( $id );

		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return new \WP_Error( 'ad_not_found', __( 'Ad not found.', 'wb-ads-rotator-with-split-test' ) );
		}

		$update_data = array( 'ID' => $id );
		if ( isset( $input['title'] ) ) {
			$update_data['post_title'] = sanitize_text_field( $input['title'] );
		}

		wp_update_post( $update_data );

		if ( isset( $input['content'] ) && is_array( $input['content'] ) ) {
			$existing = get_post_meta( $id, '_wbam_ad_data', true );
			$merged   = is_array( $existing ) ? array_merge( $existing, $input['content'] ) : $input['content'];
			update_post_meta( $id, '_wbam_ad_data', $merged );
		}

		if ( isset( $input['enabled'] ) ) {
			update_post_meta( $id, '_wbam_enabled', (bool) $input['enabled'] ? '1' : '0' );
		}

		if ( isset( $input['placements'] ) && is_array( $input['placements'] ) ) {
			$placements = array_map( 'sanitize_text_field', $input['placements'] );
			update_post_meta( $id, '_wbam_placements', $placements );
		}

		do_action( 'wbam_save_ad_meta', $id );

		return array(
			'id'      => $id,
			'updated' => true,
		);
	}

	/**
	 * Execute wbam/delete-ad.
	 *
	 * @since 2.8.0
	 *
	 * @param array $input Ability input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_delete_ad( $input ) {
		$input = is_array( $input ) ? $input : array();

		if ( empty( $input['id'] ) ) {
			return new \WP_Error( 'missing_id', __( 'Ad ID is required.', 'wb-ads-rotator-with-split-test' ) );
		}

		$id   = absint( $input['id'] );
		$post = get_post( $id );

		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return new \WP_Error( 'ad_not_found', __( 'Ad not found.', 'wb-ads-rotator-with-split-test' ) );
		}

		$result = wp_delete_post( $id, true );

		if ( ! $result ) {
			return new \WP_Error( 'delete_failed', __( 'Could not delete ad.', 'wb-ads-rotator-with-split-test' ) );
		}

		return array( 'deleted' => true );
	}

	/**
	 * Execute wbam/serve-ad.
	 *
	 * @since 2.8.0
	 *
	 * @param array $input Ability input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_serve_ad( $input ) {
		$input = is_array( $input ) ? $input : array();

		if ( empty( $input['placement'] ) ) {
			return new \WP_Error( 'missing_placement', __( 'Placement is required.', 'wb-ads-rotator-with-split-test' ) );
		}

		$placement = sanitize_text_field( $input['placement'] );
		$post_id   = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

		if ( $post_id > 0 ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentionally setting post context for targeting.
			$GLOBALS['post'] = get_post( $post_id );
			setup_postdata( $GLOBALS['post'] );
		}

		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();
		$ad_ids = $engine->get_ads_for_placement( $placement );

		if ( $post_id > 0 ) {
			wp_reset_postdata();
		}

		if ( empty( $ad_ids ) ) {
			return array(
				'html'      => '',
				'ad_id'     => 0,
				'placement' => $placement,
			);
		}

		// Return the first served ad with its rendered HTML.
		$first_ad_id = $ad_ids[0];
		$html        = $engine->render_ad(
			$first_ad_id,
			array(
				'placement' => $placement,
				'context'   => 'abilities',
			)
		);

		return array(
			'html'      => (string) $html,
			'ad_id'     => $first_ad_id,
			'placement' => $placement,
		);
	}

	/**
	 * Execute wbam/list-placements.
	 *
	 * @since 2.8.0
	 *
	 * @param array $input Ability input parameters (unused; required by Abilities contract).
	 * @return array
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature required by Abilities contract.
	public function execute_list_placements( $input ) {
		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();
		// This ability is registered with permission_callback __return_true,
		// so it is public. Listing closed slots here would leak the site's
		// full inventory to anonymous callers.
		$placements = $engine->get_selectable_placements();

		$items = array();
		foreach ( $placements as $placement ) {
			$items[] = array(
				'id'          => $placement->get_id(),
				// Placement_Interface exposes get_name(), not get_label() —
				// the call to a non-existent method fataled every invocation
				// of this ability before this fix.
				'label'       => $placement->get_name(),
				'description' => method_exists( $placement, 'get_description' ) ? $placement->get_description() : '',
			);
		}

		return $items;
	}

	/**
	 * Execute wbam/list-ad-types.
	 *
	 * @since 2.8.0
	 *
	 * @param array $input Ability input parameters (unused; required by Abilities contract).
	 * @return array
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature required by Abilities contract.
	public function execute_list_ad_types( $input ) {
		$engine   = \WBAM\Modules\Placements\Placement_Engine::get_instance();
		$ad_types = $engine->get_ad_types();

		$items = array();
		foreach ( $ad_types as $ad_type ) {
			$items[] = array(
				'id'     => $ad_type->get_id(),
				'label'  => $ad_type->get_label(),
				'fields' => method_exists( $ad_type, 'get_fields' ) ? $ad_type->get_fields() : array(),
			);
		}

		return $items;
	}

	// -------------------------------------------------------------------------
	// Execute callbacks — Analytics
	// -------------------------------------------------------------------------

	/**
	 * Execute wbam/get-analytics-overview.
	 *
	 * Raw events plus rolled-up daily totals, through Analytics_Rollup.
	 *
	 * @since 2.8.0
	 *
	 * @param array $input Ability input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_get_analytics_overview( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$overview = Analytics_Rollup::overview(
			sanitize_text_field( $input['start_date'] ?? '' ),
			sanitize_text_field( $input['end_date'] ?? '' ),
			isset( $input['limit'] ) ? absint( $input['limit'] ) : 10
		);

		return array(
			'total_impressions' => $overview['impressions'],
			'total_clicks'      => $overview['clicks'],
			'ctr'               => $overview['ctr'],
			'top_ads'           => $overview['top_ads'],
		);
	}

	/**
	 * Execute wbam/get-ad-stats.
	 *
	 * @since 2.8.0
	 *
	 * @param array $input Ability input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_get_ad_stats( $input ) {
		$input = is_array( $input ) ? $input : array();

		if ( empty( $input['id'] ) ) {
			return new \WP_Error( 'missing_id', __( 'Ad ID is required.', 'wb-ads-rotator-with-split-test' ) );
		}

		$id   = absint( $input['id'] );
		$post = get_post( $id );

		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return new \WP_Error( 'ad_not_found', __( 'Ad not found.', 'wb-ads-rotator-with-split-test' ) );
		}

		return Analytics_Rollup::ad_stats( $id, sanitize_text_field( $input['start_date'] ?? '' ), sanitize_text_field( $input['end_date'] ?? '' ) );
	}

	// -------------------------------------------------------------------------
	// Execute callbacks — Link Management
	// -------------------------------------------------------------------------

	/**
	 * Execute wbam/list-links.
	 *
	 * @since 2.8.0
	 *
	 * @param array $input Ability input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_list_links( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 20;
		$page     = isset( $input['page'] ) ? absint( $input['page'] ) : 1;

		$manager = \WBAM\Modules\Links\Link_Manager::get_instance();

		$args = array(
			'status'      => isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : '',
			'category_id' => isset( $input['category_id'] ) ? absint( $input['category_id'] ) : 0,
			'limit'       => $per_page,
			'offset'      => ( $page - 1 ) * $per_page,
		);

		$links = $manager->get_links( $args );
		$total = $manager->count_links( $args );

		$items = array();
		foreach ( $links as $link ) {
			$items[] = array(
				'id'          => (int) $link->id,
				'name'        => $link->name,
				'url'         => $link->destination_url,
				'slug'        => $link->slug,
				'link_type'   => $link->link_type,
				'status'      => $link->status,
				'category_id' => (int) $link->category_id,
				'click_count' => (int) $link->click_count,
			);
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Execute wbam/create-link.
	 *
	 * @since 2.8.0
	 *
	 * @param array $input Ability input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_create_link( $input ) {
		$input = is_array( $input ) ? $input : array();

		if ( empty( $input['name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'Link name is required.', 'wb-ads-rotator-with-split-test' ) );
		}
		if ( empty( $input['url'] ) ) {
			return new \WP_Error( 'missing_url', __( 'Destination URL is required.', 'wb-ads-rotator-with-split-test' ) );
		}

		$manager = \WBAM\Modules\Links\Link_Manager::get_instance();

		$data = array(
			'name'            => sanitize_text_field( $input['name'] ),
			'destination_url' => esc_url_raw( $input['url'] ),
		);

		if ( isset( $input['category_id'] ) ) {
			$data['category_id'] = absint( $input['category_id'] );
		}
		if ( isset( $input['nofollow'] ) ) {
			$data['nofollow'] = (bool) $input['nofollow'] ? 1 : 0;
		}
		if ( isset( $input['new_tab'] ) ) {
			$data['new_tab'] = (bool) $input['new_tab'] ? 1 : 0;
		}

		$link_id = $manager->create( $data );

		if ( ! $link_id ) {
			return new \WP_Error( 'create_failed', __( 'Could not create link.', 'wb-ads-rotator-with-split-test' ) );
		}

		$link = $manager->get( $link_id );

		// Build the cloaked URL from the link slug if available.
		$cloaked_url = $link && ! empty( $link->slug )
			? home_url( '/go/' . $link->slug . '/' )
			: '';

		return array(
			'id'          => $link_id,
			'name'        => $data['name'],
			'cloaked_url' => $cloaked_url,
		);
	}

	/**
	 * Execute wbam/update-link.
	 *
	 * @since 2.8.0
	 *
	 * @param array $input Ability input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_update_link( $input ) {
		$input = is_array( $input ) ? $input : array();

		if ( empty( $input['id'] ) ) {
			return new \WP_Error( 'missing_id', __( 'Link ID is required.', 'wb-ads-rotator-with-split-test' ) );
		}

		$id      = absint( $input['id'] );
		$manager = \WBAM\Modules\Links\Link_Manager::get_instance();
		$link    = $manager->get( $id );

		if ( ! $link ) {
			return new \WP_Error( 'link_not_found', __( 'Link not found.', 'wb-ads-rotator-with-split-test' ) );
		}

		$data = array();

		if ( isset( $input['name'] ) ) {
			$data['name'] = sanitize_text_field( $input['name'] );
		}
		if ( isset( $input['url'] ) ) {
			$data['destination_url'] = esc_url_raw( $input['url'] );
		}
		if ( isset( $input['category_id'] ) ) {
			$data['category_id'] = absint( $input['category_id'] );
		}
		if ( isset( $input['nofollow'] ) ) {
			$data['nofollow'] = (bool) $input['nofollow'] ? 1 : 0;
		}
		if ( isset( $input['new_tab'] ) ) {
			$data['new_tab'] = (bool) $input['new_tab'] ? 1 : 0;
		}
		if ( isset( $input['status'] ) ) {
			$data['status'] = sanitize_text_field( $input['status'] );
		}

		$result = $manager->update( $id, $data );

		return array(
			'id'      => $id,
			'updated' => (bool) $result,
		);
	}

	/**
	 * Execute wbam/delete-link.
	 *
	 * @since 2.8.0
	 *
	 * @param array $input Ability input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_delete_link( $input ) {
		$input = is_array( $input ) ? $input : array();

		if ( empty( $input['id'] ) ) {
			return new \WP_Error( 'missing_id', __( 'Link ID is required.', 'wb-ads-rotator-with-split-test' ) );
		}

		$id      = absint( $input['id'] );
		$manager = \WBAM\Modules\Links\Link_Manager::get_instance();
		$link    = $manager->get( $id );

		if ( ! $link ) {
			return new \WP_Error( 'link_not_found', __( 'Link not found.', 'wb-ads-rotator-with-split-test' ) );
		}

		$result = $manager->delete( $id );

		if ( ! $result ) {
			return new \WP_Error( 'delete_failed', __( 'Could not delete link.', 'wb-ads-rotator-with-split-test' ) );
		}

		return array( 'deleted' => true );
	}

	/**
	 * Execute wbam/get-link-stats.
	 *
	 * @since 2.8.0
	 *
	 * @param array $input Ability input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_get_link_stats( $input ) {
		$input = is_array( $input ) ? $input : array();

		if ( empty( $input['id'] ) ) {
			return new \WP_Error( 'missing_id', __( 'Link ID is required.', 'wb-ads-rotator-with-split-test' ) );
		}

		$id      = absint( $input['id'] );
		$manager = \WBAM\Modules\Links\Link_Manager::get_instance();
		$link    = $manager->get( $id );

		if ( ! $link ) {
			return new \WP_Error( 'link_not_found', __( 'Link not found.', 'wb-ads-rotator-with-split-test' ) );
		}

		global $wpdb;
		$clicks_table = $wpdb->prefix . 'wbam_link_clicks';

		$where  = array( 'link_id = %d' );
		$values = array( $id );

		if ( ! empty( $input['start_date'] ) ) {
			$where[]  = 'clicked_at >= %s';
			$values[] = sanitize_text_field( $input['start_date'] ) . ' 00:00:00';
		}

		if ( ! empty( $input['end_date'] ) ) {
			$where[]  = 'clicked_at <= %s';
			$values[] = sanitize_text_field( $input['end_date'] ) . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- WBAM custom table name from $wpdb->prefix, not user input.
		$total_clicks = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders interpolated via  /  fragments; safe.
				"SELECT COUNT(*) FROM {$clicks_table} WHERE {$where_sql}",
				$values
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- WBAM custom table name from $wpdb->prefix, not user input.
		$unique_clicks = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders interpolated via  /  fragments; safe.
				"SELECT COUNT(DISTINCT visitor_hash) FROM {$clicks_table} WHERE {$where_sql}",
				$values
			)
		);

		// Daily breakdown.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- WBAM custom table name from $wpdb->prefix, not user input.
		$by_date_raw = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders interpolated via  /  fragments; safe.
				"SELECT DATE(clicked_at) as day, COUNT(*) as clicks, COUNT(DISTINCT visitor_hash) as unique_clicks FROM {$clicks_table} WHERE {$where_sql} GROUP BY day ORDER BY day ASC",
				$values
			)
		);

		$by_date = array();
		foreach ( $by_date_raw as $row ) {
			$by_date[] = array(
				'date'          => $row->day,
				'clicks'        => (int) $row->clicks,
				'unique_clicks' => (int) $row->unique_clicks,
			);
		}

		return array(
			'total_clicks'  => $total_clicks,
			'unique_clicks' => $unique_clicks,
			'by_date'       => $by_date,
		);
	}
}
