<?php
/**
 * Frontend Class
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Frontend;

use WBAM\Core\Singleton;

/**
 * Frontend class.
 */
class Frontend {

	use Singleton;

	/**
	 * Initialize.
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wbam_track_click', array( $this, 'handle_click_tracking' ) );
		add_action( 'wp_ajax_nopriv_wbam_track_click', array( $this, 'handle_click_tracking' ) );
		add_action( 'wp_ajax_wbam_email_capture', array( $this, 'handle_email_capture' ) );
		add_action( 'wp_ajax_nopriv_wbam_email_capture', array( $this, 'handle_email_capture' ) );
		add_action( 'wp_head', array( $this, 'maybe_add_adsense_auto_ads' ) );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_assets() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'wbam-frontend',
			WBAM_URL . 'assets/css/frontend' . $suffix . '.css',
			array(),
			WBAM_VERSION
		);

		wp_enqueue_script(
			'wbam-frontend',
			WBAM_URL . 'assets/js/frontend' . $suffix . '.js',
			array(),
			WBAM_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script(
			'wbam-frontend',
			'wbamFrontend',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wbam_frontend' ),
			)
		);
	}

	/**
	 * Add AdSense Auto Ads script to head if enabled.
	 */
	public function maybe_add_adsense_auto_ads() {
		$settings = get_option( 'wbam_settings', array() );

		// Check if Auto Ads is enabled.
		if ( empty( $settings['adsense_auto_ads'] ) ) {
			return;
		}

		// Get Publisher ID.
		$publisher_id = isset( $settings['adsense_publisher_id'] ) ? $settings['adsense_publisher_id'] : '';
		if ( empty( $publisher_id ) ) {
			return;
		}

		printf(
			'<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=%s" crossorigin="anonymous"></script>',
			esc_attr( $publisher_id )
		);
	}

	/**
	 * Handle click tracking AJAX request.
	 */
	public function handle_click_tracking() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wbam_frontend' ) ) {
			wp_die( 'Invalid nonce', '', array( 'response' => 403 ) );
		}

		$ad_id     = isset( $_POST['ad_id'] ) ? absint( wp_unslash( $_POST['ad_id'] ) ) : 0;
		$placement = isset( $_POST['placement'] ) ? sanitize_text_field( wp_unslash( $_POST['placement'] ) ) : '';

		if ( $ad_id > 0 ) {
			/**
			 * Action fired when an ad is clicked.
			 *
			 * @since 1.0.0
			 * @param int    $ad_id     Ad ID.
			 * @param string $placement Placement ID.
			 */
			do_action( 'wbam_ad_clicked', $ad_id, $placement );
		}

		wp_send_json_success();
	}

	/**
	 * Handle email capture AJAX request.
	 */
	public function handle_email_capture() {
		$ad_id = isset( $_POST['ad_id'] ) ? absint( wp_unslash( $_POST['ad_id'] ) ) : 0;
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		// Verify nonce.
		if ( ! wp_verify_nonce( $nonce, 'wbam_email_capture_' . $ad_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wb-ad-manager' ) ) );
		}

		$email = isset( $_POST['subscriber_email'] ) ? sanitize_email( wp_unslash( $_POST['subscriber_email'] ) ) : '';
		$name  = isset( $_POST['subscriber_name'] ) ? sanitize_text_field( wp_unslash( $_POST['subscriber_name'] ) ) : '';

		// Validate email.
		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'wb-ad-manager' ) ) );
		}

		/**
		 * Action fired when an email is captured.
		 *
		 * Use this hook to integrate with email services like Mailchimp, ConvertKit, etc.
		 *
		 * @since 2.2.0
		 * @param string $email  Subscriber email.
		 * @param string $name   Subscriber name (may be empty).
		 * @param int    $ad_id  Ad ID.
		 */
		do_action( 'wbam_email_captured', $email, $name, $ad_id );

		// Store submission in options for basic tracking.
		$submissions = get_option( 'wbam_email_submissions', array() );
		$submissions[] = array(
			'email'   => $email,
			'name'    => $name,
			'ad_id'   => $ad_id,
			'date'    => current_time( 'mysql' ),
			'ip'      => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
		);

		// Keep only last 1000 submissions.
		if ( count( $submissions ) > 1000 ) {
			$submissions = array_slice( $submissions, -1000 );
		}

		update_option( 'wbam_email_submissions', $submissions );

		// Get redirect URL if set.
		$data = get_post_meta( $ad_id, '_wbam_ad_data', true );
		$redirect_url = isset( $data['redirect_url'] ) ? $data['redirect_url'] : '';

		wp_send_json_success(
			array(
				'message'  => __( 'Thank you for subscribing!', 'wb-ad-manager' ),
				'redirect' => $redirect_url,
			)
		);
	}
}
