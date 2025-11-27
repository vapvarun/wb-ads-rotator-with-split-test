<?php
/**
 * Mock WordPress Functions for Standalone Testing
 *
 * @package WB_Ad_Manager
 */

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * Mock wp_parse_args.
	 *
	 * @param array $args     Arguments.
	 * @param array $defaults Defaults.
	 * @return array
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		} elseif ( is_string( $args ) ) {
			parse_str( $args, $args );
		}
		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Mock sanitize_text_field.
	 *
	 * @param string $str String to sanitize.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Mock sanitize_key.
	 *
	 * @param string $key Key to sanitize.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Mock absint.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Mock wp_strip_all_tags.
	 *
	 * @param string $string String.
	 * @param bool   $remove_breaks Remove breaks.
	 * @return string
	 */
	function wp_strip_all_tags( $string, $remove_breaks = false ) {
		$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );
		$string = strip_tags( $string );

		if ( $remove_breaks ) {
			$string = preg_replace( '/[\r\n\t ]+/', ' ', $string );
		}

		return trim( $string );
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Mock __ (translation).
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Mock esc_html__.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Mock esc_attr.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Mock esc_html.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Mock get_option.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_option( $option, $default = false ) {
		global $wbam_mock_options;
		if ( isset( $wbam_mock_options[ $option ] ) ) {
			return $wbam_mock_options[ $option ];
		}
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Mock update_option.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 * @return bool
	 */
	function update_option( $option, $value ) {
		global $wbam_mock_options;
		$wbam_mock_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * Mock get_post_meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Single value.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		global $wbam_mock_post_meta;
		if ( isset( $wbam_mock_post_meta[ $post_id ][ $key ] ) ) {
			return $single ? $wbam_mock_post_meta[ $post_id ][ $key ] : array( $wbam_mock_post_meta[ $post_id ][ $key ] );
		}
		return $single ? '' : array();
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Mock get_transient.
	 *
	 * @param string $transient Transient name.
	 * @return mixed
	 */
	function get_transient( $transient ) {
		global $wbam_mock_transients;
		if ( isset( $wbam_mock_transients[ $transient ] ) ) {
			return $wbam_mock_transients[ $transient ];
		}
		return false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Mock set_transient.
	 *
	 * @param string $transient  Transient name.
	 * @param mixed  $value      Value.
	 * @param int    $expiration Expiration.
	 * @return bool
	 */
	function set_transient( $transient, $value, $expiration = 0 ) {
		global $wbam_mock_transients;
		$wbam_mock_transients[ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * Mock delete_transient.
	 *
	 * @param string $transient Transient name.
	 * @return bool
	 */
	function delete_transient( $transient ) {
		global $wbam_mock_transients;
		unset( $wbam_mock_transients[ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Mock add_action.
	 *
	 * @param string   $tag      Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Arguments.
	 * @return bool
	 */
	function add_action( $tag, $callback, $priority = 10, $args = 1 ) {
		global $wbam_mock_actions;
		$wbam_mock_actions[ $tag ][] = array(
			'callback' => $callback,
			'priority' => $priority,
		);
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Mock add_filter.
	 *
	 * @param string   $tag      Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Arguments.
	 * @return bool
	 */
	function add_filter( $tag, $callback, $priority = 10, $args = 1 ) {
		global $wbam_mock_filters;
		$wbam_mock_filters[ $tag ][] = array(
			'callback' => $callback,
			'priority' => $priority,
		);
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Mock do_action.
	 *
	 * @param string $tag Hook name.
	 */
	function do_action( $tag ) {
		// No-op for testing.
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Mock apply_filters.
	 *
	 * @param string $tag   Hook name.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $tag, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * Mock is_admin.
	 *
	 * @return bool
	 */
	function is_admin() {
		return defined( 'WBAM_MOCK_IS_ADMIN' ) ? WBAM_MOCK_IS_ADMIN : false;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	/**
	 * Mock current_time.
	 *
	 * @param string $type Type.
	 * @return string|int
	 */
	function current_time( $type ) {
		if ( 'mysql' === $type ) {
			return date( 'Y-m-d H:i:s' );
		}
		if ( 'timestamp' === $type ) {
			return time();
		}
		return date( $type );
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	/**
	 * Mock is_user_logged_in.
	 *
	 * @return bool
	 */
	function is_user_logged_in() {
		return defined( 'WBAM_MOCK_LOGGED_IN' ) ? WBAM_MOCK_LOGGED_IN : false;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	/**
	 * Mock get_current_user_id.
	 *
	 * @return int
	 */
	function get_current_user_id() {
		return defined( 'WBAM_MOCK_USER_ID' ) ? WBAM_MOCK_USER_ID : 0;
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * Mock wp_remote_get.
	 *
	 * @param string $url  URL.
	 * @param array  $args Arguments.
	 * @return array|WP_Error
	 */
	function wp_remote_get( $url, $args = array() ) {
		global $wbam_mock_remote_responses;
		if ( isset( $wbam_mock_remote_responses[ $url ] ) ) {
			return $wbam_mock_remote_responses[ $url ];
		}
		// Return mock error for unmocked URLs.
		return new WP_Error( 'mock_error', 'No mock response configured' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * Mock wp_remote_retrieve_body.
	 *
	 * @param array $response Response.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) {
		if ( is_wp_error( $response ) ) {
			return '';
		}
		return isset( $response['body'] ) ? $response['body'] : '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * Mock wp_remote_retrieve_response_code.
	 *
	 * @param array $response Response.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) {
		if ( is_wp_error( $response ) ) {
			return 0;
		}
		return isset( $response['response']['code'] ) ? $response['response']['code'] : 200;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Mock is_wp_error.
	 *
	 * @param mixed $thing Thing to check.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Mock WP_Error class.
	 */
	class WP_Error {
		/**
		 * Errors.
		 *
		 * @var array
		 */
		public $errors = array();

		/**
		 * Error data.
		 *
		 * @var array
		 */
		public $error_data = array();

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Error data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( ! empty( $code ) ) {
				$this->errors[ $code ][] = $message;
				if ( ! empty( $data ) ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		/**
		 * Get error message.
		 *
		 * @return string
		 */
		public function get_error_message() {
			$all_messages = array();
			foreach ( $this->errors as $messages ) {
				$all_messages = array_merge( $all_messages, $messages );
			}
			return reset( $all_messages );
		}
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Mock wp_unslash.
	 *
	 * @param string|array $value Value.
	 * @return string|array
	 */
	function wp_unslash( $value ) {
		return stripslashes_deep( $value );
	}
}

if ( ! function_exists( 'stripslashes_deep' ) ) {
	/**
	 * Mock stripslashes_deep.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function stripslashes_deep( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'stripslashes_deep', $value );
		}
		return stripslashes( $value );
	}
}

// Initialize mock globals.
$wbam_mock_options          = array();
$wbam_mock_post_meta        = array();
$wbam_mock_transients       = array();
$wbam_mock_actions          = array();
$wbam_mock_filters          = array();
$wbam_mock_remote_responses = array();
