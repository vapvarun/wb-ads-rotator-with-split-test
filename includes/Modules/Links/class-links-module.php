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

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
		// Links already published keep working whatever the Link Manager
		// switch says: cloaked /go/ redirects, the link shortcodes and click
		// counting. Turning it off used to unload all of this, so every
		// existing cloaked link fell through to the homepage (card
		// 10344382999, owner decision 7a).
		$this->manager = Link_Manager::get_instance();

		$this->cloaker = Link_Cloaker::get_instance();
		$this->cloaker->init();

		$this->shortcodes = Link_Shortcodes::get_instance();
		$this->shortcodes->init();

		if ( ! is_admin() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		}
		add_action( 'wp_ajax_wbam_track_link_click', array( $this, 'ajax_track_click' ) );
		add_action( 'wp_ajax_nopriv_wbam_track_link_click', array( $this, 'ajax_track_click' ) );

		// Off hides the feature itself: the admin screens, Settings > Links,
		// new partnership inquiries and Pro's link tools. A published
		// partnership form prints nothing rather than its raw shortcode.
		if ( ! \WBAM\Core\Settings_Helper::is_module_enabled( 'links' ) ) {
			add_shortcode( 'wbam_partnership_inquiry', '__return_empty_string' );
			return;
		}

		$this->partnership_form = Partnership_Form::get_instance();
		$this->partnership_form->init();

		$this->partnership_emails = Partnership_Emails::get_instance();
		$this->partnership_emails->init();

		if ( is_admin() ) {
			$this->admin = Links_Admin::get_instance();
			$this->admin->init();

			$this->partnership_admin = Partnership_Admin::get_instance();
			$this->partnership_admin->init();
		}

		/**
		 * Fires after the Links module has wired its own hooks, so an
		 * extension (Pro's Links_Pro_Module) can add its own AJAX handlers
		 * and filters on top.
		 *
		 * @since 2.0.0
		 * @param Links_Module $module This module instance.
		 */
		do_action( 'wbam_links_module_init', $this );
	}

	/**
	 * Enqueue frontend scripts for click tracking.
	 */
	public function enqueue_frontend_scripts() {
		/**
		 * Filter whether the frontend click-tracking script is enqueued.
		 *
		 * @since 2.0.0
		 * @param bool $load Whether to enqueue the tracking script. Default true.
		 */
		if ( ! apply_filters( 'wbam_load_link_tracking_js', true ) ) {
			return;
		}

		wp_enqueue_script(
			'wbam-links-frontend',
			wbam_asset_url( 'js/links-frontend.js' ),
			array(),
			WBAM_VERSION,
			true
		);

		wp_localize_script(
			'wbam-links-frontend',
			'wbamLinks',
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
		// Rate limiting: 60 requests per minute.
		$frontend = \WBAM\Frontend\Frontend::get_instance();
		if ( ! $frontend->check_rate_limit( 'link_click', 60, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'wb-ads-rotator-with-split-test' ) ), 429 );
		}

		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wbam_track_click' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wb-ads-rotator-with-split-test' ) ), 403 );
		}

		$link_id = isset( $_POST['link_id'] ) ? absint( $_POST['link_id'] ) : 0;

		if ( ! $link_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid link ID.', 'wb-ads-rotator-with-split-test' ) ), 400 );
		}

		// Track the click.
		$this->manager->increment_clicks( $link_id );

		/**
		 * Fires after a link click AJAX request has incremented the click
		 * count, so an extension (Pro's Link_Tracker) can record richer
		 * per-click data (referrer, geo, device) from the same request.
		 *
		 * @since 2.0.0
		 * @param int   $link_id Link ID that was clicked.
		 * @param array $_post   Raw, unsanitized $_POST data from the AJAX request.
		 */
		do_action( 'wbam_link_click_tracked', $link_id, $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- raw request passed through for listeners to sanitize per their own needs; this handler itself does not read $_POST.

		wp_send_json_success();
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
