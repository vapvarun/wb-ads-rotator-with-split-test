<?php
/**
 * Main Plugin Class
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Core;

use WBAM\Modules\Placements\Placement_Engine;
use WBAM\Modules\Targeting\Targeting_Engine;
use WBAM\Modules\Targeting\Frequency_Manager;
use WBAM\Admin\Admin;
use WBAM\Admin\Settings;
use WBAM\Admin\Display_Options;
use WBAM\Admin\Setup_Wizard;
use WBAM\Frontend\Frontend;

/**
 * Plugin class.
 */
class Plugin {

	use Singleton;

	/**
	 * Placement engine instance.
	 *
	 * @var Placement_Engine
	 */
	private $placements;

	/**
	 * Admin instance.
	 *
	 * @var Admin
	 */
	private $admin;

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Frontend instance.
	 *
	 * @var Frontend
	 */
	private $frontend;

	/**
	 * Setup wizard instance.
	 *
	 * @var Setup_Wizard
	 */
	private $setup_wizard;

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		// Register post type on init hook (rewrite rules need this).
		add_action( 'init', array( $this, 'register_post_type' ), 5 );

		$this->init_components();
		$this->setup_hooks();

		do_action( 'wbam_init' );
	}

	/**
	 * Initialize components.
	 */
	private function init_components() {
		// Placements engine.
		$this->placements = Placement_Engine::get_instance();
		$this->placements->init();

		// Frequency manager.
		$frequency = Frequency_Manager::get_instance();
		$frequency->init();

		// Admin.
		if ( is_admin() ) {
			$this->admin = Admin::get_instance();
			$this->admin->init();

			$this->settings = Settings::get_instance();
			$this->settings->init();

			$display_options = Display_Options::get_instance();
			$display_options->init();

			// Setup wizard.
			$this->setup_wizard = new Setup_Wizard();
			$this->setup_wizard->init();
		}

		// Frontend.
		$this->frontend = Frontend::get_instance();
		$this->frontend->init();

		// BuddyPress module.
		if ( class_exists( 'BuddyPress' ) ) {
			$bp = new \WBAM\Modules\BuddyPress\BP_Module();
			$bp->init();
		}

		// bbPress module.
		if ( class_exists( 'bbPress' ) ) {
			$bbpress = new \WBAM\Modules\bbPress\bbPress_Module();
			$bbpress->init();
		}
	}

	/**
	 * Setup hooks.
	 */
	private function setup_hooks() {
		add_action( 'admin_init', array( $this, 'activation_redirect' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Register custom post type.
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Ads', 'Post Type General Name', 'wb-ad-manager' ),
			'singular_name'      => _x( 'Ad', 'Post Type Singular Name', 'wb-ad-manager' ),
			'menu_name'          => __( 'WB Ad Manager', 'wb-ad-manager' ),
			'all_items'          => __( 'All Ads', 'wb-ad-manager' ),
			'add_new_item'       => __( 'Add New Ad', 'wb-ad-manager' ),
			'add_new'            => __( 'Add New', 'wb-ad-manager' ),
			'new_item'           => __( 'New Ad', 'wb-ad-manager' ),
			'edit_item'          => __( 'Edit Ad', 'wb-ad-manager' ),
			'update_item'        => __( 'Update Ad', 'wb-ad-manager' ),
			'view_item'          => __( 'View Ad', 'wb-ad-manager' ),
			'search_items'       => __( 'Search Ads', 'wb-ad-manager' ),
			'not_found'          => __( 'Not found', 'wb-ad-manager' ),
			'not_found_in_trash' => __( 'Not found in Trash', 'wb-ad-manager' ),
		);

		$args = array(
			'label'               => __( 'Ad', 'wb-ad-manager' ),
			'labels'              => $labels,
			'supports'            => array( 'title' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 25,
			'menu_icon'           => 'dashicons-megaphone',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
			'show_in_rest'        => false,
		);

		register_post_type( 'wbam-ad', $args );
	}

	/**
	 * Activation redirect.
	 */
	public function activation_redirect() {
		if ( get_transient( '_wbam_activation_redirect' ) ) {
			delete_transient( '_wbam_activation_redirect' );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No form data processed, just checking activation type.
			if ( ! isset( $_GET['activate-multi'] ) ) {
				// Redirect to setup wizard if not completed.
				if ( ! get_option( 'wbam_setup_complete' ) && ! get_option( 'wbam_setup_dismissed' ) ) {
					wp_safe_redirect( admin_url( 'index.php?page=wbam-setup' ) );
				} else {
					wp_safe_redirect( admin_url( 'edit.php?post_type=wbam-ad' ) );
				}
				exit;
			}
		}
	}

	/**
	 * Admin notices.
	 */
	public function admin_notices() {
		$screen = get_current_screen();
		if ( ! $screen || 'wbam-ad' !== $screen->post_type ) {
			return;
		}

		if ( ! class_exists( 'BuddyPress' ) ) {
			echo '<div class="notice notice-info"><p>';
			esc_html_e( 'BuddyPress is not active. BuddyPress activity placements are disabled.', 'wb-ad-manager' );
			echo '</p></div>';
		}
	}

	/**
	 * Get placements engine.
	 *
	 * @return Placement_Engine
	 */
	public function placements() {
		return $this->placements;
	}

	/**
	 * Get admin instance.
	 *
	 * @return Admin
	 */
	public function admin() {
		return $this->admin;
	}

	/**
	 * Get frontend instance.
	 *
	 * @return Frontend
	 */
	public function frontend() {
		return $this->frontend;
	}

	/**
	 * Get settings instance.
	 *
	 * @return Settings
	 */
	public function settings() {
		return $this->settings;
	}
}
