<?php
/**
 * Frontend Class
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Frontend;

use WBAM\Core\Settings_Helper;
use WBAM\Core\Singleton;

/**
 * Frontend class.
 */
class Frontend {

	use Singleton;

	/**
	 * Cache for table existence checks.
	 *
	 * @var array
	 */
	private static $table_cache = array();

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

		// Track impressions when ads are rendered.
		add_filter( 'wbam_ad_output', array( $this, 'maybe_track_impression' ), 10, 3 );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_assets() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'wbam-frontend',
			WBAM_URL . 'assets/css/frontend' . $suffix . '.css',
			array( 'dashicons' ),
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
		// Check if Auto Ads is enabled.
		if ( ! Settings_Helper::is_enabled( 'adsense_auto_ads' ) ) {
			return;
		}

		// Get Publisher ID.
		$publisher_id = Settings_Helper::get( 'adsense_publisher_id', '' );
		if ( empty( $publisher_id ) ) {
			return;
		}

		// Enqueue AdSense script properly with async and crossorigin attributes.
		$adsense_url = add_query_arg( 'client', $publisher_id, 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js' );
		wp_enqueue_script( 'wbam-adsense', $adsense_url, array(), null, false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion

		// Add async and crossorigin attributes via filter.
		add_filter( 'script_loader_tag', array( $this, 'add_adsense_script_attributes' ), 10, 2 );
	}

	/**
	 * Add async and crossorigin attributes to AdSense script tag.
	 *
	 * @param string $tag    Script tag HTML.
	 * @param string $handle Script handle.
	 * @return string Modified script tag.
	 */
	public function add_adsense_script_attributes( $tag, $handle ) {
		if ( 'wbam-adsense' !== $handle ) {
			return $tag;
		}

		// Add async and crossorigin attributes.
		$tag = str_replace( ' src=', ' async crossorigin="anonymous" src=', $tag );

		return $tag;
	}

	/**
	 * Handle click tracking AJAX request.
	 */
	public function handle_click_tracking() {
		// Rate limiting: 30 requests per minute per IP.
		if ( ! $this->check_rate_limit( 'click_tracking', 30, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'wb-ads-rotator-with-split-test' ) ), 429 );
		}

		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wbam_frontend' ) ) {
			wp_die( 'Invalid nonce', '', array( 'response' => 403 ) );
		}

		$ad_id     = isset( $_POST['ad_id'] ) ? absint( wp_unslash( $_POST['ad_id'] ) ) : 0;
		$placement = isset( $_POST['placement'] ) ? sanitize_text_field( wp_unslash( $_POST['placement'] ) ) : '';

		if ( $ad_id > 0 ) {
			// Record click in analytics table.
			$this->record_analytics( $ad_id, 'click', $placement );

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
	 * Record an analytics event.
	 *
	 * @param int    $ad_id     Ad ID.
	 * @param string $event_type Event type (impression, click).
	 * @param string $placement  Placement ID.
	 * @return bool|int False on failure, number of rows inserted on success.
	 */
	public function record_analytics( $ad_id, $event_type, $placement = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wbam_analytics';

		// Check if table exists (cached to avoid repeated queries).
		if ( ! $this->table_exists( $table_name ) ) {
			return false;
		}

		// Generate visitor hash for GDPR-compliant anonymized tracking.
		// Uses daily rotating salt to prevent long-term tracking while still detecting unique visitors.
		$visitor_hash = '';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip_address     = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			$user_agent_raw = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
			$daily_salt     = wp_hash( gmdate( 'Y-m-d' ) );
			$visitor_hash   = hash( 'sha256', $ip_address . $user_agent_raw . $daily_salt );
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$referer    = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';

		// GDPR: Store empty string for IP address - use visitor_hash for unique visitor detection.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->insert(
			$table_name,
			array(
				'ad_id'        => $ad_id,
				'event_type'   => $event_type,
				'placement'    => $placement,
				'visitor_hash' => $visitor_hash,
				'ip_address'   => '', // GDPR: Never store raw IP addresses.
				'user_agent'   => $user_agent,
				'referer'      => $referer,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Record an impression for an ad.
	 *
	 * @param int    $ad_id     Ad ID.
	 * @param string $placement Placement ID.
	 */
	public function record_impression( $ad_id, $placement = '' ) {
		$this->record_analytics( $ad_id, 'impression', $placement );

		/**
		 * Action fired when an ad impression is recorded.
		 *
		 * @since 2.3.0
		 * @param int    $ad_id     Ad ID.
		 * @param string $placement Placement ID.
		 */
		do_action( 'wbam_ad_impression', $ad_id, $placement );
	}

	/**
	 * Track impression via wbam_ad_output filter.
	 *
	 * Only tracks if PRO plugin is not active (PRO handles its own tracking).
	 *
	 * @since 2.3.1
	 *
	 * @param string $output    Ad output HTML.
	 * @param int    $ad_id     Ad ID.
	 * @param string $placement Placement ID.
	 * @return string Unmodified output.
	 */
	public function maybe_track_impression( $output, $ad_id, $placement ) {
		// Skip if PRO plugin is handling analytics.
		if ( defined( 'WBAM_PRO_VERSION' ) ) {
			return $output;
		}

		// Skip if output is empty (no ad rendered).
		if ( empty( $output ) ) {
			return $output;
		}

		$this->record_impression( $ad_id, $placement );

		return $output;
	}

	/**
	 * Handle email capture AJAX request.
	 */
	public function handle_email_capture() {
		// Rate limiting: 10 email submissions per minute per IP.
		if ( ! $this->check_rate_limit( 'email_capture', 10, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'wb-ads-rotator-with-split-test' ) ), 429 );
		}

		$ad_id = isset( $_POST['ad_id'] ) ? absint( wp_unslash( $_POST['ad_id'] ) ) : 0;
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		// Verify nonce.
		if ( ! wp_verify_nonce( $nonce, 'wbam_email_capture_' . $ad_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wb-ads-rotator-with-split-test' ) ), 403 );
		}

		$email = isset( $_POST['subscriber_email'] ) ? sanitize_email( wp_unslash( $_POST['subscriber_email'] ) ) : '';
		$name  = isset( $_POST['subscriber_name'] ) ? sanitize_text_field( wp_unslash( $_POST['subscriber_name'] ) ) : '';

		// Build sanitized form data array for hooks (instead of raw $_POST).
		$form_data = array(
			'subscriber_email' => $email,
			'subscriber_name'  => $name,
			'ad_id'            => $ad_id,
		);

		/**
		 * Fires before processing email capture submission.
		 *
		 * @since 2.2.0
		 * @param string $email     Subscriber email.
		 * @param string $name      Subscriber name.
		 * @param int    $ad_id     Ad ID.
		 * @param array  $form_data Sanitized form data.
		 */
		do_action( 'wbam_email_form_submission_before', $email, $name, $ad_id, $form_data );

		// Validate email.
		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'wb-ads-rotator-with-split-test' ) ) );
		}

		/**
		 * Custom validation filter for email capture.
		 *
		 * Return a WP_Error to fail validation with a custom message.
		 *
		 * @since 2.2.0
		 * @param true|WP_Error $valid     True if valid, WP_Error to fail.
		 * @param string        $email     Subscriber email.
		 * @param string        $name      Subscriber name.
		 * @param int           $ad_id     Ad ID.
		 * @param array         $form_data Sanitized form data.
		 */
		$validation = apply_filters( 'wbam_email_form_validation', true, $email, $name, $ad_id, $form_data );

		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
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

		// Store submission in database table.
		$this->save_email_submission( $ad_id, $email, $name );

		/**
		 * Fires after successful email capture submission.
		 *
		 * @since 2.2.0
		 * @param string $email Subscriber email.
		 * @param string $name  Subscriber name.
		 * @param int    $ad_id Ad ID.
		 */
		do_action( 'wbam_email_form_submission_after', $email, $name, $ad_id );

		// Get redirect URL if set (with validation).
		$data         = get_post_meta( $ad_id, '_wbam_ad_data', true );
		$redirect_url = isset( $data['redirect_url'] ) ? esc_url( $data['redirect_url'] ) : '';

		$success_message = __( 'Thank you for subscribing!', 'wb-ads-rotator-with-split-test' );

		/**
		 * Filter the success message for email capture.
		 *
		 * @since 2.2.0
		 * @param string $success_message Success message.
		 * @param string $email           Subscriber email.
		 * @param int    $ad_id           Ad ID.
		 */
		$success_message = apply_filters( 'wbam_email_capture_success_message', $success_message, $email, $ad_id );

		wp_send_json_success(
			array(
				'message'  => $success_message,
				'redirect' => $redirect_url,
			)
		);
	}

	/**
	 * Save email submission to database.
	 *
	 * @param int    $ad_id Ad ID.
	 * @param string $email Subscriber email.
	 * @param string $name  Subscriber name.
	 * @return bool|int False on failure, number of rows inserted on success.
	 */
	private function save_email_submission( $ad_id, $email, $name ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wbam_email_submissions';

		// Check if table exists (cached to avoid repeated queries).
		if ( ! $this->table_exists( $table_name ) ) {
			// Fallback to options for backward compatibility.
			$submissions   = get_option( 'wbam_email_submissions', array() );
			$submissions[] = array(
				'email' => $email,
				'name'  => $name,
				'ad_id' => $ad_id,
				'date'  => current_time( 'mysql' ),
				'ip'    => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			);

			// Keep only last 1000 submissions.
			if ( count( $submissions ) > 1000 ) {
				$submissions = array_slice( $submissions, -1000 );
			}

			update_option( 'wbam_email_submissions', $submissions );
			return true;
		}

		$ip_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->insert(
			$table_name,
			array(
				'ad_id'      => $ad_id,
				'email'      => $email,
				'name'       => $name,
				'ip_address' => $ip_address,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Check rate limit for an action.
	 *
	 * Uses database-level locking to prevent TOCTOU race conditions.
	 * Falls back to transients if database locking is not available.
	 *
	 * @param string $action    Action identifier.
	 * @param int    $limit     Maximum requests allowed.
	 * @param int    $window    Time window in seconds.
	 * @return bool True if within limit, false if exceeded.
	 */
	public function check_rate_limit( $action, $limit, $window ) {
		// Check user-based rate limit for logged-in users.
		if ( is_user_logged_in() ) {
			$user_key = 'wbam_rl_' . $action . '_u' . get_current_user_id();
			if ( ! $this->increment_rate_limit_atomic( $user_key, $limit, $window ) ) {
				return false;
			}
		}

		// Check IP-based rate limit.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( empty( $ip ) ) {
			return true; // Can't rate limit by IP without IP.
		}

		$key = 'wbam_rl_' . $action . '_' . md5( $ip );
		return $this->increment_rate_limit_atomic( $key, $limit, $window );
	}

	/**
	 * Atomically increment rate limit counter using database locking.
	 *
	 * Prevents TOCTOU race conditions by using MySQL INSERT ... ON DUPLICATE KEY UPDATE
	 * for atomic increment operation.
	 *
	 * @since 2.3.2
	 *
	 * @param string $key    Rate limit key.
	 * @param int    $limit  Maximum requests allowed.
	 * @param int    $window Time window in seconds.
	 * @return bool True if within limit, false if exceeded.
	 */
	private function increment_rate_limit_atomic( $key, $limit, $window ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wbam_rate_limits';

		// Check if rate limits table exists.
		if ( ! $this->table_exists( $table_name ) ) {
			// Fallback to transient-based rate limiting (non-atomic).
			return $this->increment_rate_limit_transient( $key, $limit, $window );
		}

		$expiration = time() + $window;

		// Atomic increment using INSERT ... ON DUPLICATE KEY UPDATE.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table_name} (`key`, `count`, `expires`)
				VALUES (%s, 1, %d)
				ON DUPLICATE KEY UPDATE
					`count` = IF(expires < %d, 1, `count` + 1),
					`expires` = IF(expires < %d, %d, `expires`)",
				$key,
				$expiration,
				time(),
				time(),
				$expiration
			)
		);

		// Get updated count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `count` FROM {$table_name} WHERE `key` = %s",
				$key
			)
		);

		return ( (int) $count ) <= $limit;
	}

	/**
	 * Fallback non-atomic rate limit increment using transients.
	 *
	 * Used when rate limits table doesn't exist.
	 *
	 * @since 2.3.2
	 *
	 * @param string $key    Rate limit key.
	 * @param int    $limit  Maximum requests allowed.
	 * @param int    $window Time window in seconds.
	 * @return bool True if within limit, false if exceeded.
	 */
	private function increment_rate_limit_transient( $key, $limit, $window ) {
		$count = get_transient( $key );

		if ( false === $count ) {
			set_transient( $key, 1, $window );
			return true;
		}

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, $window );
		return true;
	}

	/**
	 * Check if a database table exists (with static caching).
	 *
	 * Caches the result to avoid repeated SHOW TABLES queries during
	 * the same request. Tables are created during plugin activation,
	 * so they should always exist at runtime.
	 *
	 * @since 2.3.1
	 *
	 * @param string $table_name Full table name including prefix.
	 * @return bool True if table exists, false otherwise.
	 */
	private function table_exists( $table_name ) {
		// Return cached result if available.
		if ( isset( self::$table_cache[ $table_name ] ) ) {
			return self::$table_cache[ $table_name ];
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		// Cache the result for subsequent calls.
		self::$table_cache[ $table_name ] = ! empty( $exists );

		return self::$table_cache[ $table_name ];
	}
}
