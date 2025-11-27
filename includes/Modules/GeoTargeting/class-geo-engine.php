<?php
/**
 * Geo Engine
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\GeoTargeting;

use WBAM\Core\Singleton;

/**
 * Geo Engine class.
 */
class Geo_Engine {

	use Singleton;

	/**
	 * Cache key prefix.
	 */
	const CACHE_PREFIX = 'wbam_geo_';

	/**
	 * Cache expiration (24 hours).
	 */
	const CACHE_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Current location data.
	 *
	 * @var array|null
	 */
	private $current_location = null;

	/**
	 * Get visitor location.
	 *
	 * @return array
	 */
	public function get_visitor_location() {
		if ( null !== $this->current_location ) {
			return $this->current_location;
		}

		// Try BuddyPress profile location first.
		if ( is_user_logged_in() && function_exists( 'bp_is_active' ) ) {
			$location = $this->get_bp_location();
			if ( ! empty( $location['country'] ) ) {
				$this->current_location = $location;
				return $this->current_location;
			}
		}

		// Fall back to IP geolocation.
		$ip       = $this->get_visitor_ip();
		$location = $this->get_location_by_ip( $ip );

		$this->current_location = $location;
		return $this->current_location;
	}

	/**
	 * Get BuddyPress profile location.
	 *
	 * @return array
	 */
	private function get_bp_location() {
		$user_id  = get_current_user_id();
		$location = array(
			'country'      => '',
			'country_code' => '',
			'region'       => '',
			'city'         => '',
			'source'       => 'buddypress',
		);

		if ( ! $user_id || ! function_exists( 'xprofile_get_field_data' ) ) {
			return $location;
		}

		// Common BuddyPress field names for location.
		$country_fields = array( 'Country', 'country', 'Location Country' );
		$city_fields    = array( 'City', 'city', 'Location', 'location' );

		foreach ( $country_fields as $field ) {
			$value = xprofile_get_field_data( $field, $user_id );
			if ( ! empty( $value ) ) {
				$location['country'] = $value;
				break;
			}
		}

		foreach ( $city_fields as $field ) {
			$value = xprofile_get_field_data( $field, $user_id );
			if ( ! empty( $value ) ) {
				$location['city'] = $value;
				break;
			}
		}

		return $location;
	}

	/**
	 * Get visitor IP address.
	 *
	 * @return string
	 */
	private function get_visitor_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs.
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * Get location by IP.
	 *
	 * @param string $ip IP address.
	 * @return array
	 */
	private function get_location_by_ip( $ip ) {
		$default = array(
			'country'      => '',
			'country_code' => '',
			'region'       => '',
			'city'         => '',
			'source'       => 'ip',
		);

		if ( empty( $ip ) || in_array( $ip, array( '127.0.0.1', '::1' ), true ) ) {
			return $default;
		}

		// Check cache.
		$cache_key = self::CACHE_PREFIX . md5( $ip );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Query ip-api.com (free tier: 45 requests/minute).
		$response = wp_remote_get(
			"http://ip-api.com/json/{$ip}?fields=status,country,countryCode,regionName,city",
			array(
				'timeout' => 5,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $default;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) || 'success' !== ( $data['status'] ?? '' ) ) {
			return $default;
		}

		$location = array(
			'country'      => $data['country'] ?? '',
			'country_code' => strtoupper( $data['countryCode'] ?? '' ),
			'region'       => $data['regionName'] ?? '',
			'city'         => $data['city'] ?? '',
			'source'       => 'ip',
		);

		// Cache the result.
		set_transient( $cache_key, $location, self::CACHE_EXPIRATION );

		return $location;
	}

	/**
	 * Check if visitor matches geo targeting.
	 *
	 * @param int $ad_id Ad ID.
	 * @return bool
	 */
	public function matches_targeting( $ad_id ) {
		$geo_rules = get_post_meta( $ad_id, '_wbam_geo_targeting', true );

		if ( empty( $geo_rules ) || ! is_array( $geo_rules ) ) {
			return true; // No geo rules = show everywhere.
		}

		$geo_enabled = isset( $geo_rules['enabled'] ) ? $geo_rules['enabled'] : false;

		if ( ! $geo_enabled ) {
			return true;
		}

		$location = $this->get_visitor_location();

		if ( empty( $location['country_code'] ) && empty( $location['country'] ) ) {
			// Unknown location - check default behavior.
			$show_unknown = isset( $geo_rules['show_unknown'] ) ? $geo_rules['show_unknown'] : true;
			return $show_unknown;
		}

		$include_countries = isset( $geo_rules['include_countries'] ) ? (array) $geo_rules['include_countries'] : array();
		$exclude_countries = isset( $geo_rules['exclude_countries'] ) ? (array) $geo_rules['exclude_countries'] : array();

		// Check exclusions first.
		if ( ! empty( $exclude_countries ) ) {
			if ( in_array( $location['country_code'], $exclude_countries, true ) ) {
				return false;
			}
		}

		// Check inclusions.
		if ( ! empty( $include_countries ) ) {
			if ( ! in_array( $location['country_code'], $include_countries, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get list of countries.
	 *
	 * @return array
	 */
	public function get_countries_list() {
		return array(
			'AF' => __( 'Afghanistan', 'wb-ad-manager' ),
			'AL' => __( 'Albania', 'wb-ad-manager' ),
			'DZ' => __( 'Algeria', 'wb-ad-manager' ),
			'AR' => __( 'Argentina', 'wb-ad-manager' ),
			'AU' => __( 'Australia', 'wb-ad-manager' ),
			'AT' => __( 'Austria', 'wb-ad-manager' ),
			'BD' => __( 'Bangladesh', 'wb-ad-manager' ),
			'BE' => __( 'Belgium', 'wb-ad-manager' ),
			'BR' => __( 'Brazil', 'wb-ad-manager' ),
			'CA' => __( 'Canada', 'wb-ad-manager' ),
			'CL' => __( 'Chile', 'wb-ad-manager' ),
			'CN' => __( 'China', 'wb-ad-manager' ),
			'CO' => __( 'Colombia', 'wb-ad-manager' ),
			'CZ' => __( 'Czech Republic', 'wb-ad-manager' ),
			'DK' => __( 'Denmark', 'wb-ad-manager' ),
			'EG' => __( 'Egypt', 'wb-ad-manager' ),
			'FI' => __( 'Finland', 'wb-ad-manager' ),
			'FR' => __( 'France', 'wb-ad-manager' ),
			'DE' => __( 'Germany', 'wb-ad-manager' ),
			'GR' => __( 'Greece', 'wb-ad-manager' ),
			'HK' => __( 'Hong Kong', 'wb-ad-manager' ),
			'HU' => __( 'Hungary', 'wb-ad-manager' ),
			'IN' => __( 'India', 'wb-ad-manager' ),
			'ID' => __( 'Indonesia', 'wb-ad-manager' ),
			'IE' => __( 'Ireland', 'wb-ad-manager' ),
			'IL' => __( 'Israel', 'wb-ad-manager' ),
			'IT' => __( 'Italy', 'wb-ad-manager' ),
			'JP' => __( 'Japan', 'wb-ad-manager' ),
			'KR' => __( 'South Korea', 'wb-ad-manager' ),
			'MY' => __( 'Malaysia', 'wb-ad-manager' ),
			'MX' => __( 'Mexico', 'wb-ad-manager' ),
			'NL' => __( 'Netherlands', 'wb-ad-manager' ),
			'NZ' => __( 'New Zealand', 'wb-ad-manager' ),
			'NG' => __( 'Nigeria', 'wb-ad-manager' ),
			'NO' => __( 'Norway', 'wb-ad-manager' ),
			'PK' => __( 'Pakistan', 'wb-ad-manager' ),
			'PH' => __( 'Philippines', 'wb-ad-manager' ),
			'PL' => __( 'Poland', 'wb-ad-manager' ),
			'PT' => __( 'Portugal', 'wb-ad-manager' ),
			'RO' => __( 'Romania', 'wb-ad-manager' ),
			'RU' => __( 'Russia', 'wb-ad-manager' ),
			'SA' => __( 'Saudi Arabia', 'wb-ad-manager' ),
			'SG' => __( 'Singapore', 'wb-ad-manager' ),
			'ZA' => __( 'South Africa', 'wb-ad-manager' ),
			'ES' => __( 'Spain', 'wb-ad-manager' ),
			'SE' => __( 'Sweden', 'wb-ad-manager' ),
			'CH' => __( 'Switzerland', 'wb-ad-manager' ),
			'TW' => __( 'Taiwan', 'wb-ad-manager' ),
			'TH' => __( 'Thailand', 'wb-ad-manager' ),
			'TR' => __( 'Turkey', 'wb-ad-manager' ),
			'UA' => __( 'Ukraine', 'wb-ad-manager' ),
			'AE' => __( 'United Arab Emirates', 'wb-ad-manager' ),
			'GB' => __( 'United Kingdom', 'wb-ad-manager' ),
			'US' => __( 'United States', 'wb-ad-manager' ),
			'VN' => __( 'Vietnam', 'wb-ad-manager' ),
		);
	}
}
