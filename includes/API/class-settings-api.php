<?php
/**
 * Settings REST API
 *
 * Handles REST API routes for plugin settings stored in wbam_settings option.
 *
 * @package WB_Ad_Manager
 * @since   2.8.0
 */

namespace WBAM\API;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings API class.
 */
class Settings_API {

	/**
	 * REST namespace.
	 *
	 * @var non-falsy-string
	 */
	private const REST_NAMESPACE = 'wbam/v1';

	/**
	 * Settings keys that belong to the display options group.
	 *
	 * @var array
	 */
	private $display_keys = array(
		// Only keys the runtime actually consumes. The rest of the original
		// list (disable_frontend_css, lazy_load_ads, ad_label_text,
		// ad_container_class, responsive_ads, hide_on_mobile, hide_on_tablet)
		// had zero consumers in Free or Pro — the endpoint accepted and echoed
		// them but nothing rendered them. ad_label / ad_label_position are the
		// live disclosure-label settings (rendered by Placement_Engine).
		'ad_label',
		'ad_label_position',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		// Admin: get / update all settings.
		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'settings' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
			)
		);

		// Admin: get / update display options subset.
		register_rest_route(
			self::REST_NAMESPACE,
			'/settings/display',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_display_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_display_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'settings' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Check admin permission.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_admin_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to perform this action.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * GET /settings — Get all settings (admin).
	 *
	 * @param \WP_REST_Request $request Request object (unused; required by REST callback contract).
	 * @return \WP_REST_Response
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature required by REST callback contract.
	public function get_settings( $request ) {
		$settings = \WBAM\Core\Settings_Helper::get();

		return rest_ensure_response(
			array(
				'settings' => $this->sanitize_settings_for_output( $settings ),
			)
		);
	}

	/**
	 * PUT /settings — Update settings (admin).
	 *
	 * Merges the provided settings with existing, then saves.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_settings( $request ) {
		$incoming = $request['settings'];

		if ( ! is_array( $incoming ) ) {
			return new \WP_Error(
				'invalid_settings',
				__( 'Settings must be a valid object.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 400 )
			);
		}

		// The admin form's sanitizer, run explicitly: register_setting() hooks
		// it on admin_init only, which a REST request never fires. It keeps
		// every stored key this write does not carry and drops unknown keys.
		$merged = \WBAM\Admin\Settings::get_instance()->sanitize_settings( $incoming );

		$result = update_option( 'wbam_settings', $merged );

		if ( false === $result ) {
			// update_option returns false when value is unchanged — treat as success.
			// Only return error on genuine failure (i.e., when we can't read back the value).
			$verify = get_option( 'wbam_settings' );
			if ( false === $verify ) {
				return new \WP_Error(
					'update_failed',
					__( 'Could not save settings.', 'wb-ads-rotator-with-split-test' ),
					array( 'status' => 500 )
				);
			}
		}

		return rest_ensure_response(
			array(
				'updated'  => true,
				'settings' => $this->sanitize_settings_for_output( $merged ),
			)
		);
	}

	/**
	 * GET /settings/display — Get display options (admin).
	 *
	 * @param \WP_REST_Request $request Request object (unused; required by REST callback contract).
	 * @return \WP_REST_Response
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature required by REST callback contract.
	public function get_display_settings( $request ) {
		$all     = \WBAM\Core\Settings_Helper::get();
		$display = array();

		foreach ( $this->display_keys as $key ) {
			if ( array_key_exists( $key, $all ) ) {
				$display[ $key ] = $all[ $key ];
			}
		}

		return rest_ensure_response( array( 'settings' => $display ) );
	}

	/**
	 * PUT /settings/display — Update display options (admin).
	 *
	 * Only keys in $this->display_keys are accepted and merged.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_display_settings( $request ) {
		$incoming = $request['settings'];

		if ( ! is_array( $incoming ) ) {
			return new \WP_Error(
				'invalid_settings',
				__( 'Settings must be a valid object.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 400 )
			);
		}

		// Filter to display-only keys.
		$filtered = array();
		foreach ( $this->display_keys as $key ) {
			if ( array_key_exists( $key, $incoming ) ) {
				$filtered[ $key ] = $incoming[ $key ];
			}
		}

		if ( empty( $filtered ) ) {
			return new \WP_Error(
				'no_valid_keys',
				__( 'No valid display setting keys provided.', 'wb-ads-rotator-with-split-test' ),
				array( 'status' => 400 )
			);
		}

		$merged = \WBAM\Admin\Settings::get_instance()->sanitize_settings( $filtered );

		update_option( 'wbam_settings', $merged );

		// Return only the display slice.
		$display = array();
		foreach ( $this->display_keys as $key ) {
			if ( array_key_exists( $key, $merged ) ) {
				$display[ $key ] = $merged[ $key ];
			}
		}

		return rest_ensure_response(
			array(
				'updated'  => true,
				'settings' => $display,
			)
		);
	}

	/**
	 * Sanitize settings array for output (escape strings).
	 *
	 * @param array $settings Settings array.
	 * @return array
	 */
	private function sanitize_settings_for_output( $settings ) {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		$safe = array();
		foreach ( $settings as $key => $value ) {
			if ( is_string( $value ) ) {
				$safe[ $key ] = esc_html( $value );
			} else {
				$safe[ $key ] = $value;
			}
		}

		return $safe;
	}
}
