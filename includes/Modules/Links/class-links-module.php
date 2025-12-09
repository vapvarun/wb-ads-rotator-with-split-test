<?php
/**
 * Links Module Class
 *
 * Main entry point for the Links feature.
 *
 * @package WB_Ad_Manager
 * @since   2.1.0
 */

namespace WBAM\Modules\Links;

use WBAM\Core\Singleton;

/**
 * Links Module class.
 */
class Links_Module {

	use Singleton;

	/**
	 * Link Manager instance.
	 *
	 * @var Link_Manager
	 */
	private $manager;

	/**
	 * Link Cloaker instance.
	 *
	 * @var Link_Cloaker
	 */
	private $cloaker;

	/**
	 * Shortcodes instance.
	 *
	 * @var Link_Shortcodes
	 */
	private $shortcodes;

	/**
	 * Admin instance.
	 *
	 * @var Links_Admin
	 */
	private $admin;

	/**
	 * Partnership Form instance.
	 *
	 * @var Partnership_Form
	 */
	private $partnership_form;

	/**
	 * Partnership Admin instance.
	 *
	 * @var Partnership_Admin
	 */
	private $partnership_admin;

	/**
	 * Partnership Emails instance.
	 *
	 * @var Partnership_Emails
	 */
	private $partnership_emails;

	/**
	 * Initialize the module.
	 */
	public function init() {
		// Initialize manager (available everywhere).
		$this->manager = Link_Manager::get_instance();

		// Initialize cloaker for frontend redirects.
		$this->cloaker = Link_Cloaker::get_instance();
		$this->cloaker->init();

		// Initialize shortcodes.
		$this->shortcodes = Link_Shortcodes::get_instance();
		$this->shortcodes->init();

		// Initialize partnership form (frontend shortcode).
		$this->partnership_form = Partnership_Form::get_instance();
		$this->partnership_form->init();

		// Initialize partnership email notifications.
		$this->partnership_emails = Partnership_Emails::get_instance();
		$this->partnership_emails->init();

		// Initialize admin.
		if ( is_admin() ) {
			$this->admin = Links_Admin::get_instance();
			$this->admin->init();

			// Initialize partnership admin.
			$this->partnership_admin = Partnership_Admin::get_instance();
			$this->partnership_admin->init();
		}

		// Frontend scripts for click tracking.
		if ( ! is_admin() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		}

		// AJAX handlers for click tracking.
		add_action( 'wp_ajax_wbam_track_link_click', array( $this, 'ajax_track_click' ) );
		add_action( 'wp_ajax_nopriv_wbam_track_link_click', array( $this, 'ajax_track_click' ) );

		// Register settings.
		add_filter( 'wbam_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_filter( 'wbam_settings_fields', array( $this, 'add_settings_fields' ) );

		// Allow PRO extensions.
		do_action( 'wbam_links_module_init', $this );
	}

	/**
	 * Enqueue frontend scripts for click tracking.
	 */
	public function enqueue_frontend_scripts() {
		// Only load if we have links to track.
		if ( ! apply_filters( 'wbam_load_link_tracking_js', true ) ) {
			return;
		}

		wp_enqueue_script(
			'wbam-links-frontend',
			WBAM_URL . 'assets/js/links-frontend.js',
			array(),
			WBAM_VERSION,
			true
		);

		wp_localize_script(
			'wbam-links-frontend',
			'wbamLinksConfig',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wbam_track_click' ),
			)
		);
	}

	/**
	 * AJAX handler for click tracking.
	 */
	public function ajax_track_click() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wbam_track_click' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		$link_id = isset( $_POST['link_id'] ) ? absint( $_POST['link_id'] ) : 0;

		if ( ! $link_id ) {
			wp_send_json_error( 'Invalid link ID' );
		}

		// Track the click.
		$this->manager->increment_clicks( $link_id );

		// Allow PRO to add detailed tracking.
		do_action( 'wbam_link_click_tracked', $link_id, $_POST );

		wp_send_json_success();
	}

	/**
	 * Add Links settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['links'] = __( 'Links', 'wb-ads-rotator-with-split-test' );
		return $tabs;
	}

	/**
	 * Add Links settings fields.
	 *
	 * @param array $fields Existing fields.
	 * @return array
	 */
	public function add_settings_fields( $fields ) {
		$fields['links'] = array(
			array(
				'id'          => 'link_cloak_prefix',
				'title'       => __( 'Cloak Prefix', 'wb-ads-rotator-with-split-test' ),
				'type'        => 'text',
				'default'     => 'go',
				'description' => sprintf(
					/* translators: %s: example URL */
					__( 'The URL prefix for cloaked links. Example: %s', 'wb-ads-rotator-with-split-test' ),
					'<code>' . home_url( '/go/your-link' ) . '</code>'
				),
			),
			array(
				'id'          => 'link_default_nofollow',
				'title'       => __( 'Default Nofollow', 'wb-ads-rotator-with-split-test' ),
				'type'        => 'checkbox',
				'default'     => 1,
				'description' => __( 'Add nofollow attribute to links by default.', 'wb-ads-rotator-with-split-test' ),
			),
			array(
				'id'          => 'link_default_sponsored',
				'title'       => __( 'Default Sponsored', 'wb-ads-rotator-with-split-test' ),
				'type'        => 'checkbox',
				'default'     => 0,
				'description' => __( 'Add sponsored attribute to links by default.', 'wb-ads-rotator-with-split-test' ),
			),
			array(
				'id'          => 'link_default_new_tab',
				'title'       => __( 'Open in New Tab', 'wb-ads-rotator-with-split-test' ),
				'type'        => 'checkbox',
				'default'     => 1,
				'description' => __( 'Open links in new tab by default.', 'wb-ads-rotator-with-split-test' ),
			),
			array(
				'id'          => 'link_default_redirect',
				'title'       => __( 'Default Redirect Type', 'wb-ads-rotator-with-split-test' ),
				'type'        => 'select',
				'options'     => Link::get_redirect_types(),
				'default'     => 307,
				'description' => __( 'Default redirect type for cloaked links.', 'wb-ads-rotator-with-split-test' ),
			),
			array(
				'id'          => 'link_inactive_action',
				'title'       => __( 'Inactive Link Action', 'wb-ads-rotator-with-split-test' ),
				'type'        => 'select',
				'options'     => array(
					'404'    => __( 'Show 404 page', 'wb-ads-rotator-with-split-test' ),
					'home'   => __( 'Redirect to homepage', 'wb-ads-rotator-with-split-test' ),
					'custom' => __( 'Redirect to custom URL', 'wb-ads-rotator-with-split-test' ),
				),
				'default'     => '404',
				'description' => __( 'What to do when an inactive or expired link is accessed.', 'wb-ads-rotator-with-split-test' ),
			),
			array(
				'id'          => 'link_inactive_url',
				'title'       => __( 'Inactive Link URL', 'wb-ads-rotator-with-split-test' ),
				'type'        => 'url',
				'default'     => '',
				'description' => __( 'Custom URL to redirect to when inactive link action is set to custom.', 'wb-ads-rotator-with-split-test' ),
			),
		);

		return $fields;
	}

	/**
	 * Get Link Manager.
	 *
	 * @return Link_Manager
	 */
	public function manager() {
		return $this->manager;
	}

	/**
	 * Get Link Cloaker.
	 *
	 * @return Link_Cloaker
	 */
	public function cloaker() {
		return $this->cloaker;
	}

	/**
	 * Get shortcodes handler.
	 *
	 * @return Link_Shortcodes
	 */
	public function shortcodes() {
		return $this->shortcodes;
	}

	/**
	 * Get admin handler.
	 *
	 * @return Links_Admin|null
	 */
	public function admin() {
		return $this->admin;
	}
}
