<?php
/**
 * Geo Engine
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\GeoTargeting;

use WBAM\Core\Settings_Helper;
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
	 * Available providers.
	 *
	 * @var array
	 */
	private $providers = array(
		'ip-api'   => array(
			'name'     => 'ip-api.com',
			'requires_key' => false,
			'limit'    => '45 requests/minute',
		),
		'ipinfo'   => array(
			'name'     => 'ipinfo.io',
			'requires_key' => true,
			'limit'    => '50K requests/month',
		),
		'ipapi-co' => array(
			'name'     => 'ipapi.co',
			'requires_key' => false,
			'limit'    => '1K requests/day',
		),
	);

	/**
	 * Get available providers.
	 *
	 * @return array
	 */
	public function get_providers() {
		return $this->providers;
	}

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

		// Fall back to IP geolocation with provider fallback.
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
	 * Get location by IP with provider fallback.
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
			'provider'     => '',
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

		// Get provider order from settings.
		$primary        = Settings_Helper::get( 'geo_primary_provider', 'ip-api' );
		$provider_order = $this->get_provider_order( $primary );
		$settings       = Settings_Helper::get();

		$location = null;

		// Try each provider in order.
		foreach ( $provider_order as $provider ) {
			$location = $this->query_provider( $provider, $ip, $settings );

			if ( ! empty( $location['country_code'] ) ) {
				$location['provider'] = $provider;
				break;
			}
		}

		if ( empty( $location ) || empty( $location['country_code'] ) ) {
			return $default;
		}

		// Cache the result.
		set_transient( $cache_key, $location, self::CACHE_EXPIRATION );

		return $location;
	}

	/**
	 * Get provider order with primary first.
	 *
	 * @param string $primary Primary provider.
	 * @return array
	 */
	private function get_provider_order( $primary ) {
		$all = array_keys( $this->providers );

		// Move primary to front.
		$order = array( $primary );
		foreach ( $all as $provider ) {
			if ( $provider !== $primary ) {
				$order[] = $provider;
			}
		}

		return $order;
	}

	/**
	 * Query a specific provider.
	 *
	 * @param string $provider Provider ID.
	 * @param string $ip       IP address.
	 * @param array  $settings Plugin settings.
	 * @return array|null
	 */
	private function query_provider( $provider, $ip, $settings ) {
		switch ( $provider ) {
			case 'ip-api':
				return $this->query_ip_api( $ip );

			case 'ipinfo':
				$api_key = isset( $settings['geo_ipinfo_key'] ) ? $settings['geo_ipinfo_key'] : '';
				return $this->query_ipinfo( $ip, $api_key );

			case 'ipapi-co':
				return $this->query_ipapi_co( $ip );

			default:
				return null;
		}
	}

	/**
	 * Query ip-api.com.
	 *
	 * @param string $ip IP address.
	 * @return array
	 */
	private function query_ip_api( $ip ) {
		$response = wp_remote_get(
			"http://ip-api.com/json/{$ip}?fields=status,country,countryCode,regionName,city",
			array(
				'timeout' => 5,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) || 'success' !== ( $data['status'] ?? '' ) ) {
			return array();
		}

		return array(
			'country'      => $data['country'] ?? '',
			'country_code' => strtoupper( $data['countryCode'] ?? '' ),
			'region'       => $data['regionName'] ?? '',
			'city'         => $data['city'] ?? '',
			'source'       => 'ip',
		);
	}

	/**
	 * Query ipinfo.io.
	 *
	 * @param string $ip      IP address.
	 * @param string $api_key API key.
	 * @return array
	 */
	private function query_ipinfo( $ip, $api_key = '' ) {
		$url = "https://ipinfo.io/{$ip}/json";

		if ( ! empty( $api_key ) ) {
			$url .= "?token={$api_key}";
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 5,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) || isset( $data['error'] ) ) {
			return array();
		}

		return array(
			'country'      => $this->get_country_name( $data['country'] ?? '' ),
			'country_code' => strtoupper( $data['country'] ?? '' ),
			'region'       => $data['region'] ?? '',
			'city'         => $data['city'] ?? '',
			'source'       => 'ip',
		);
	}

	/**
	 * Query ipapi.co.
	 *
	 * @param string $ip IP address.
	 * @return array
	 */
	private function query_ipapi_co( $ip ) {
		$response = wp_remote_get(
			"https://ipapi.co/{$ip}/json/",
			array(
				'timeout' => 5,
				'headers' => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'Mozilla/5.0',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) || isset( $data['error'] ) ) {
			return array();
		}

		return array(
			'country'      => $data['country_name'] ?? '',
			'country_code' => strtoupper( $data['country_code'] ?? '' ),
			'region'       => $data['region'] ?? '',
			'city'         => $data['city'] ?? '',
			'source'       => 'ip',
		);
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
	 * Get country name from code.
	 *
	 * @param string $code Country code.
	 * @return string
	 */
	private function get_country_name( $code ) {
		$countries = $this->get_countries_list();
		$code      = strtoupper( $code );

		return isset( $countries[ $code ] ) ? $countries[ $code ] : $code;
	}

	/**
	 * Get list of countries.
	 *
	 * @return array
	 */
	public function get_countries_list() {
		return array(
			'AF' => __( 'Afghanistan', 'wb-ads-rotator-with-split-test' ),
			'AL' => __( 'Albania', 'wb-ads-rotator-with-split-test' ),
			'DZ' => __( 'Algeria', 'wb-ads-rotator-with-split-test' ),
			'AD' => __( 'Andorra', 'wb-ads-rotator-with-split-test' ),
			'AO' => __( 'Angola', 'wb-ads-rotator-with-split-test' ),
			'AG' => __( 'Antigua and Barbuda', 'wb-ads-rotator-with-split-test' ),
			'AR' => __( 'Argentina', 'wb-ads-rotator-with-split-test' ),
			'AM' => __( 'Armenia', 'wb-ads-rotator-with-split-test' ),
			'AU' => __( 'Australia', 'wb-ads-rotator-with-split-test' ),
			'AT' => __( 'Austria', 'wb-ads-rotator-with-split-test' ),
			'AZ' => __( 'Azerbaijan', 'wb-ads-rotator-with-split-test' ),
			'BS' => __( 'Bahamas', 'wb-ads-rotator-with-split-test' ),
			'BH' => __( 'Bahrain', 'wb-ads-rotator-with-split-test' ),
			'BD' => __( 'Bangladesh', 'wb-ads-rotator-with-split-test' ),
			'BB' => __( 'Barbados', 'wb-ads-rotator-with-split-test' ),
			'BY' => __( 'Belarus', 'wb-ads-rotator-with-split-test' ),
			'BE' => __( 'Belgium', 'wb-ads-rotator-with-split-test' ),
			'BZ' => __( 'Belize', 'wb-ads-rotator-with-split-test' ),
			'BJ' => __( 'Benin', 'wb-ads-rotator-with-split-test' ),
			'BT' => __( 'Bhutan', 'wb-ads-rotator-with-split-test' ),
			'BO' => __( 'Bolivia', 'wb-ads-rotator-with-split-test' ),
			'BA' => __( 'Bosnia and Herzegovina', 'wb-ads-rotator-with-split-test' ),
			'BW' => __( 'Botswana', 'wb-ads-rotator-with-split-test' ),
			'BR' => __( 'Brazil', 'wb-ads-rotator-with-split-test' ),
			'BN' => __( 'Brunei', 'wb-ads-rotator-with-split-test' ),
			'BG' => __( 'Bulgaria', 'wb-ads-rotator-with-split-test' ),
			'BF' => __( 'Burkina Faso', 'wb-ads-rotator-with-split-test' ),
			'BI' => __( 'Burundi', 'wb-ads-rotator-with-split-test' ),
			'KH' => __( 'Cambodia', 'wb-ads-rotator-with-split-test' ),
			'CM' => __( 'Cameroon', 'wb-ads-rotator-with-split-test' ),
			'CA' => __( 'Canada', 'wb-ads-rotator-with-split-test' ),
			'CV' => __( 'Cape Verde', 'wb-ads-rotator-with-split-test' ),
			'CF' => __( 'Central African Republic', 'wb-ads-rotator-with-split-test' ),
			'TD' => __( 'Chad', 'wb-ads-rotator-with-split-test' ),
			'CL' => __( 'Chile', 'wb-ads-rotator-with-split-test' ),
			'CN' => __( 'China', 'wb-ads-rotator-with-split-test' ),
			'CO' => __( 'Colombia', 'wb-ads-rotator-with-split-test' ),
			'KM' => __( 'Comoros', 'wb-ads-rotator-with-split-test' ),
			'CG' => __( 'Congo', 'wb-ads-rotator-with-split-test' ),
			'CD' => __( 'Congo (DRC)', 'wb-ads-rotator-with-split-test' ),
			'CR' => __( 'Costa Rica', 'wb-ads-rotator-with-split-test' ),
			'CI' => __( 'Côte d\'Ivoire', 'wb-ads-rotator-with-split-test' ),
			'HR' => __( 'Croatia', 'wb-ads-rotator-with-split-test' ),
			'CU' => __( 'Cuba', 'wb-ads-rotator-with-split-test' ),
			'CY' => __( 'Cyprus', 'wb-ads-rotator-with-split-test' ),
			'CZ' => __( 'Czech Republic', 'wb-ads-rotator-with-split-test' ),
			'DK' => __( 'Denmark', 'wb-ads-rotator-with-split-test' ),
			'DJ' => __( 'Djibouti', 'wb-ads-rotator-with-split-test' ),
			'DM' => __( 'Dominica', 'wb-ads-rotator-with-split-test' ),
			'DO' => __( 'Dominican Republic', 'wb-ads-rotator-with-split-test' ),
			'EC' => __( 'Ecuador', 'wb-ads-rotator-with-split-test' ),
			'EG' => __( 'Egypt', 'wb-ads-rotator-with-split-test' ),
			'SV' => __( 'El Salvador', 'wb-ads-rotator-with-split-test' ),
			'GQ' => __( 'Equatorial Guinea', 'wb-ads-rotator-with-split-test' ),
			'ER' => __( 'Eritrea', 'wb-ads-rotator-with-split-test' ),
			'EE' => __( 'Estonia', 'wb-ads-rotator-with-split-test' ),
			'ET' => __( 'Ethiopia', 'wb-ads-rotator-with-split-test' ),
			'FJ' => __( 'Fiji', 'wb-ads-rotator-with-split-test' ),
			'FI' => __( 'Finland', 'wb-ads-rotator-with-split-test' ),
			'FR' => __( 'France', 'wb-ads-rotator-with-split-test' ),
			'GA' => __( 'Gabon', 'wb-ads-rotator-with-split-test' ),
			'GM' => __( 'Gambia', 'wb-ads-rotator-with-split-test' ),
			'GE' => __( 'Georgia', 'wb-ads-rotator-with-split-test' ),
			'DE' => __( 'Germany', 'wb-ads-rotator-with-split-test' ),
			'GH' => __( 'Ghana', 'wb-ads-rotator-with-split-test' ),
			'GR' => __( 'Greece', 'wb-ads-rotator-with-split-test' ),
			'GD' => __( 'Grenada', 'wb-ads-rotator-with-split-test' ),
			'GT' => __( 'Guatemala', 'wb-ads-rotator-with-split-test' ),
			'GN' => __( 'Guinea', 'wb-ads-rotator-with-split-test' ),
			'GW' => __( 'Guinea-Bissau', 'wb-ads-rotator-with-split-test' ),
			'GY' => __( 'Guyana', 'wb-ads-rotator-with-split-test' ),
			'HT' => __( 'Haiti', 'wb-ads-rotator-with-split-test' ),
			'HN' => __( 'Honduras', 'wb-ads-rotator-with-split-test' ),
			'HK' => __( 'Hong Kong', 'wb-ads-rotator-with-split-test' ),
			'HU' => __( 'Hungary', 'wb-ads-rotator-with-split-test' ),
			'IS' => __( 'Iceland', 'wb-ads-rotator-with-split-test' ),
			'IN' => __( 'India', 'wb-ads-rotator-with-split-test' ),
			'ID' => __( 'Indonesia', 'wb-ads-rotator-with-split-test' ),
			'IR' => __( 'Iran', 'wb-ads-rotator-with-split-test' ),
			'IQ' => __( 'Iraq', 'wb-ads-rotator-with-split-test' ),
			'IE' => __( 'Ireland', 'wb-ads-rotator-with-split-test' ),
			'IL' => __( 'Israel', 'wb-ads-rotator-with-split-test' ),
			'IT' => __( 'Italy', 'wb-ads-rotator-with-split-test' ),
			'JM' => __( 'Jamaica', 'wb-ads-rotator-with-split-test' ),
			'JP' => __( 'Japan', 'wb-ads-rotator-with-split-test' ),
			'JO' => __( 'Jordan', 'wb-ads-rotator-with-split-test' ),
			'KZ' => __( 'Kazakhstan', 'wb-ads-rotator-with-split-test' ),
			'KE' => __( 'Kenya', 'wb-ads-rotator-with-split-test' ),
			'KI' => __( 'Kiribati', 'wb-ads-rotator-with-split-test' ),
			'KP' => __( 'North Korea', 'wb-ads-rotator-with-split-test' ),
			'KR' => __( 'South Korea', 'wb-ads-rotator-with-split-test' ),
			'KW' => __( 'Kuwait', 'wb-ads-rotator-with-split-test' ),
			'KG' => __( 'Kyrgyzstan', 'wb-ads-rotator-with-split-test' ),
			'LA' => __( 'Laos', 'wb-ads-rotator-with-split-test' ),
			'LV' => __( 'Latvia', 'wb-ads-rotator-with-split-test' ),
			'LB' => __( 'Lebanon', 'wb-ads-rotator-with-split-test' ),
			'LS' => __( 'Lesotho', 'wb-ads-rotator-with-split-test' ),
			'LR' => __( 'Liberia', 'wb-ads-rotator-with-split-test' ),
			'LY' => __( 'Libya', 'wb-ads-rotator-with-split-test' ),
			'LI' => __( 'Liechtenstein', 'wb-ads-rotator-with-split-test' ),
			'LT' => __( 'Lithuania', 'wb-ads-rotator-with-split-test' ),
			'LU' => __( 'Luxembourg', 'wb-ads-rotator-with-split-test' ),
			'MO' => __( 'Macau', 'wb-ads-rotator-with-split-test' ),
			'MK' => __( 'North Macedonia', 'wb-ads-rotator-with-split-test' ),
			'MG' => __( 'Madagascar', 'wb-ads-rotator-with-split-test' ),
			'MW' => __( 'Malawi', 'wb-ads-rotator-with-split-test' ),
			'MY' => __( 'Malaysia', 'wb-ads-rotator-with-split-test' ),
			'MV' => __( 'Maldives', 'wb-ads-rotator-with-split-test' ),
			'ML' => __( 'Mali', 'wb-ads-rotator-with-split-test' ),
			'MT' => __( 'Malta', 'wb-ads-rotator-with-split-test' ),
			'MH' => __( 'Marshall Islands', 'wb-ads-rotator-with-split-test' ),
			'MR' => __( 'Mauritania', 'wb-ads-rotator-with-split-test' ),
			'MU' => __( 'Mauritius', 'wb-ads-rotator-with-split-test' ),
			'MX' => __( 'Mexico', 'wb-ads-rotator-with-split-test' ),
			'FM' => __( 'Micronesia', 'wb-ads-rotator-with-split-test' ),
			'MD' => __( 'Moldova', 'wb-ads-rotator-with-split-test' ),
			'MC' => __( 'Monaco', 'wb-ads-rotator-with-split-test' ),
			'MN' => __( 'Mongolia', 'wb-ads-rotator-with-split-test' ),
			'ME' => __( 'Montenegro', 'wb-ads-rotator-with-split-test' ),
			'MA' => __( 'Morocco', 'wb-ads-rotator-with-split-test' ),
			'MZ' => __( 'Mozambique', 'wb-ads-rotator-with-split-test' ),
			'MM' => __( 'Myanmar', 'wb-ads-rotator-with-split-test' ),
			'NA' => __( 'Namibia', 'wb-ads-rotator-with-split-test' ),
			'NR' => __( 'Nauru', 'wb-ads-rotator-with-split-test' ),
			'NP' => __( 'Nepal', 'wb-ads-rotator-with-split-test' ),
			'NL' => __( 'Netherlands', 'wb-ads-rotator-with-split-test' ),
			'NZ' => __( 'New Zealand', 'wb-ads-rotator-with-split-test' ),
			'NI' => __( 'Nicaragua', 'wb-ads-rotator-with-split-test' ),
			'NE' => __( 'Niger', 'wb-ads-rotator-with-split-test' ),
			'NG' => __( 'Nigeria', 'wb-ads-rotator-with-split-test' ),
			'NO' => __( 'Norway', 'wb-ads-rotator-with-split-test' ),
			'OM' => __( 'Oman', 'wb-ads-rotator-with-split-test' ),
			'PK' => __( 'Pakistan', 'wb-ads-rotator-with-split-test' ),
			'PW' => __( 'Palau', 'wb-ads-rotator-with-split-test' ),
			'PS' => __( 'Palestine', 'wb-ads-rotator-with-split-test' ),
			'PA' => __( 'Panama', 'wb-ads-rotator-with-split-test' ),
			'PG' => __( 'Papua New Guinea', 'wb-ads-rotator-with-split-test' ),
			'PY' => __( 'Paraguay', 'wb-ads-rotator-with-split-test' ),
			'PE' => __( 'Peru', 'wb-ads-rotator-with-split-test' ),
			'PH' => __( 'Philippines', 'wb-ads-rotator-with-split-test' ),
			'PL' => __( 'Poland', 'wb-ads-rotator-with-split-test' ),
			'PT' => __( 'Portugal', 'wb-ads-rotator-with-split-test' ),
			'PR' => __( 'Puerto Rico', 'wb-ads-rotator-with-split-test' ),
			'QA' => __( 'Qatar', 'wb-ads-rotator-with-split-test' ),
			'RO' => __( 'Romania', 'wb-ads-rotator-with-split-test' ),
			'RU' => __( 'Russia', 'wb-ads-rotator-with-split-test' ),
			'RW' => __( 'Rwanda', 'wb-ads-rotator-with-split-test' ),
			'KN' => __( 'Saint Kitts and Nevis', 'wb-ads-rotator-with-split-test' ),
			'LC' => __( 'Saint Lucia', 'wb-ads-rotator-with-split-test' ),
			'VC' => __( 'Saint Vincent and the Grenadines', 'wb-ads-rotator-with-split-test' ),
			'WS' => __( 'Samoa', 'wb-ads-rotator-with-split-test' ),
			'SM' => __( 'San Marino', 'wb-ads-rotator-with-split-test' ),
			'ST' => __( 'São Tomé and Príncipe', 'wb-ads-rotator-with-split-test' ),
			'SA' => __( 'Saudi Arabia', 'wb-ads-rotator-with-split-test' ),
			'SN' => __( 'Senegal', 'wb-ads-rotator-with-split-test' ),
			'RS' => __( 'Serbia', 'wb-ads-rotator-with-split-test' ),
			'SC' => __( 'Seychelles', 'wb-ads-rotator-with-split-test' ),
			'SL' => __( 'Sierra Leone', 'wb-ads-rotator-with-split-test' ),
			'SG' => __( 'Singapore', 'wb-ads-rotator-with-split-test' ),
			'SK' => __( 'Slovakia', 'wb-ads-rotator-with-split-test' ),
			'SI' => __( 'Slovenia', 'wb-ads-rotator-with-split-test' ),
			'SB' => __( 'Solomon Islands', 'wb-ads-rotator-with-split-test' ),
			'SO' => __( 'Somalia', 'wb-ads-rotator-with-split-test' ),
			'ZA' => __( 'South Africa', 'wb-ads-rotator-with-split-test' ),
			'SS' => __( 'South Sudan', 'wb-ads-rotator-with-split-test' ),
			'ES' => __( 'Spain', 'wb-ads-rotator-with-split-test' ),
			'LK' => __( 'Sri Lanka', 'wb-ads-rotator-with-split-test' ),
			'SD' => __( 'Sudan', 'wb-ads-rotator-with-split-test' ),
			'SR' => __( 'Suriname', 'wb-ads-rotator-with-split-test' ),
			'SZ' => __( 'Eswatini', 'wb-ads-rotator-with-split-test' ),
			'SE' => __( 'Sweden', 'wb-ads-rotator-with-split-test' ),
			'CH' => __( 'Switzerland', 'wb-ads-rotator-with-split-test' ),
			'SY' => __( 'Syria', 'wb-ads-rotator-with-split-test' ),
			'TW' => __( 'Taiwan', 'wb-ads-rotator-with-split-test' ),
			'TJ' => __( 'Tajikistan', 'wb-ads-rotator-with-split-test' ),
			'TZ' => __( 'Tanzania', 'wb-ads-rotator-with-split-test' ),
			'TH' => __( 'Thailand', 'wb-ads-rotator-with-split-test' ),
			'TL' => __( 'Timor-Leste', 'wb-ads-rotator-with-split-test' ),
			'TG' => __( 'Togo', 'wb-ads-rotator-with-split-test' ),
			'TO' => __( 'Tonga', 'wb-ads-rotator-with-split-test' ),
			'TT' => __( 'Trinidad and Tobago', 'wb-ads-rotator-with-split-test' ),
			'TN' => __( 'Tunisia', 'wb-ads-rotator-with-split-test' ),
			'TR' => __( 'Turkey', 'wb-ads-rotator-with-split-test' ),
			'TM' => __( 'Turkmenistan', 'wb-ads-rotator-with-split-test' ),
			'TV' => __( 'Tuvalu', 'wb-ads-rotator-with-split-test' ),
			'UG' => __( 'Uganda', 'wb-ads-rotator-with-split-test' ),
			'UA' => __( 'Ukraine', 'wb-ads-rotator-with-split-test' ),
			'AE' => __( 'United Arab Emirates', 'wb-ads-rotator-with-split-test' ),
			'GB' => __( 'United Kingdom', 'wb-ads-rotator-with-split-test' ),
			'US' => __( 'United States', 'wb-ads-rotator-with-split-test' ),
			'UY' => __( 'Uruguay', 'wb-ads-rotator-with-split-test' ),
			'UZ' => __( 'Uzbekistan', 'wb-ads-rotator-with-split-test' ),
			'VU' => __( 'Vanuatu', 'wb-ads-rotator-with-split-test' ),
			'VA' => __( 'Vatican City', 'wb-ads-rotator-with-split-test' ),
			'VE' => __( 'Venezuela', 'wb-ads-rotator-with-split-test' ),
			'VN' => __( 'Vietnam', 'wb-ads-rotator-with-split-test' ),
			'YE' => __( 'Yemen', 'wb-ads-rotator-with-split-test' ),
			'ZM' => __( 'Zambia', 'wb-ads-rotator-with-split-test' ),
			'ZW' => __( 'Zimbabwe', 'wb-ads-rotator-with-split-test' ),
		);
	}

	/**
	 * Clear geo cache for an IP.
	 *
	 * @param string $ip IP address.
	 */
	public function clear_cache( $ip = '' ) {
		if ( empty( $ip ) ) {
			$ip = $this->get_visitor_ip();
		}

		$cache_key = self::CACHE_PREFIX . md5( $ip );
		delete_transient( $cache_key );
		$this->current_location = null;
	}

	/**
	 * Test a specific provider.
	 *
	 * @param string $provider Provider ID.
	 * @param string $ip       Test IP (optional).
	 * @return array
	 */
	public function test_provider( $provider, $ip = '' ) {
		if ( empty( $ip ) ) {
			$ip = '8.8.8.8'; // Google DNS as test IP.
		}

		return $this->query_provider( $provider, $ip, Settings_Helper::get() );
	}
}
