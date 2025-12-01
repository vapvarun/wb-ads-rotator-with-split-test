<?php
/**
 * Settings Helper
 *
 * Centralized settings access for WB Ad Manager.
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings_Helper class.
 */
class Settings_Helper {

	/**
	 * Get settings value.
	 *
	 * @param string $key     Optional. Specific setting key to retrieve.
	 * @param mixed  $default Optional. Default value if key not found.
	 * @return mixed Settings array or specific value.
	 */
	public static function get( $key = null, $default = null ) {
		$settings = get_option( 'wbam_settings', array() );

		if ( null === $key ) {
			return $settings;
		}

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Update a specific setting.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool True on success, false on failure.
	 */
	public static function update( $key, $value ) {
		$settings         = self::get();
		$settings[ $key ] = $value;

		return update_option( 'wbam_settings', $settings );
	}

	/**
	 * Delete a specific setting.
	 *
	 * @param string $key Setting key.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $key ) {
		$settings = self::get();

		if ( isset( $settings[ $key ] ) ) {
			unset( $settings[ $key ] );
			return update_option( 'wbam_settings', $settings );
		}

		return false;
	}

	/**
	 * Check if a setting exists and is truthy.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public static function is_enabled( $key ) {
		return (bool) self::get( $key, false );
	}
}
