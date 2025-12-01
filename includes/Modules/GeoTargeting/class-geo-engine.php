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
			'AF' => __( 'Afghanistan', 'wb-ad-manager' ),
			'AL' => __( 'Albania', 'wb-ad-manager' ),
			'DZ' => __( 'Algeria', 'wb-ad-manager' ),
			'AD' => __( 'Andorra', 'wb-ad-manager' ),
			'AO' => __( 'Angola', 'wb-ad-manager' ),
			'AG' => __( 'Antigua and Barbuda', 'wb-ad-manager' ),
			'AR' => __( 'Argentina', 'wb-ad-manager' ),
			'AM' => __( 'Armenia', 'wb-ad-manager' ),
			'AU' => __( 'Australia', 'wb-ad-manager' ),
			'AT' => __( 'Austria', 'wb-ad-manager' ),
			'AZ' => __( 'Azerbaijan', 'wb-ad-manager' ),
			'BS' => __( 'Bahamas', 'wb-ad-manager' ),
			'BH' => __( 'Bahrain', 'wb-ad-manager' ),
			'BD' => __( 'Bangladesh', 'wb-ad-manager' ),
			'BB' => __( 'Barbados', 'wb-ad-manager' ),
			'BY' => __( 'Belarus', 'wb-ad-manager' ),
			'BE' => __( 'Belgium', 'wb-ad-manager' ),
			'BZ' => __( 'Belize', 'wb-ad-manager' ),
			'BJ' => __( 'Benin', 'wb-ad-manager' ),
			'BT' => __( 'Bhutan', 'wb-ad-manager' ),
			'BO' => __( 'Bolivia', 'wb-ad-manager' ),
			'BA' => __( 'Bosnia and Herzegovina', 'wb-ad-manager' ),
			'BW' => __( 'Botswana', 'wb-ad-manager' ),
			'BR' => __( 'Brazil', 'wb-ad-manager' ),
			'BN' => __( 'Brunei', 'wb-ad-manager' ),
			'BG' => __( 'Bulgaria', 'wb-ad-manager' ),
			'BF' => __( 'Burkina Faso', 'wb-ad-manager' ),
			'BI' => __( 'Burundi', 'wb-ad-manager' ),
			'KH' => __( 'Cambodia', 'wb-ad-manager' ),
			'CM' => __( 'Cameroon', 'wb-ad-manager' ),
			'CA' => __( 'Canada', 'wb-ad-manager' ),
			'CV' => __( 'Cape Verde', 'wb-ad-manager' ),
			'CF' => __( 'Central African Republic', 'wb-ad-manager' ),
			'TD' => __( 'Chad', 'wb-ad-manager' ),
			'CL' => __( 'Chile', 'wb-ad-manager' ),
			'CN' => __( 'China', 'wb-ad-manager' ),
			'CO' => __( 'Colombia', 'wb-ad-manager' ),
			'KM' => __( 'Comoros', 'wb-ad-manager' ),
			'CG' => __( 'Congo', 'wb-ad-manager' ),
			'CD' => __( 'Congo (DRC)', 'wb-ad-manager' ),
			'CR' => __( 'Costa Rica', 'wb-ad-manager' ),
			'CI' => __( 'Côte d\'Ivoire', 'wb-ad-manager' ),
			'HR' => __( 'Croatia', 'wb-ad-manager' ),
			'CU' => __( 'Cuba', 'wb-ad-manager' ),
			'CY' => __( 'Cyprus', 'wb-ad-manager' ),
			'CZ' => __( 'Czech Republic', 'wb-ad-manager' ),
			'DK' => __( 'Denmark', 'wb-ad-manager' ),
			'DJ' => __( 'Djibouti', 'wb-ad-manager' ),
			'DM' => __( 'Dominica', 'wb-ad-manager' ),
			'DO' => __( 'Dominican Republic', 'wb-ad-manager' ),
			'EC' => __( 'Ecuador', 'wb-ad-manager' ),
			'EG' => __( 'Egypt', 'wb-ad-manager' ),
			'SV' => __( 'El Salvador', 'wb-ad-manager' ),
			'GQ' => __( 'Equatorial Guinea', 'wb-ad-manager' ),
			'ER' => __( 'Eritrea', 'wb-ad-manager' ),
			'EE' => __( 'Estonia', 'wb-ad-manager' ),
			'ET' => __( 'Ethiopia', 'wb-ad-manager' ),
			'FJ' => __( 'Fiji', 'wb-ad-manager' ),
			'FI' => __( 'Finland', 'wb-ad-manager' ),
			'FR' => __( 'France', 'wb-ad-manager' ),
			'GA' => __( 'Gabon', 'wb-ad-manager' ),
			'GM' => __( 'Gambia', 'wb-ad-manager' ),
			'GE' => __( 'Georgia', 'wb-ad-manager' ),
			'DE' => __( 'Germany', 'wb-ad-manager' ),
			'GH' => __( 'Ghana', 'wb-ad-manager' ),
			'GR' => __( 'Greece', 'wb-ad-manager' ),
			'GD' => __( 'Grenada', 'wb-ad-manager' ),
			'GT' => __( 'Guatemala', 'wb-ad-manager' ),
			'GN' => __( 'Guinea', 'wb-ad-manager' ),
			'GW' => __( 'Guinea-Bissau', 'wb-ad-manager' ),
			'GY' => __( 'Guyana', 'wb-ad-manager' ),
			'HT' => __( 'Haiti', 'wb-ad-manager' ),
			'HN' => __( 'Honduras', 'wb-ad-manager' ),
			'HK' => __( 'Hong Kong', 'wb-ad-manager' ),
			'HU' => __( 'Hungary', 'wb-ad-manager' ),
			'IS' => __( 'Iceland', 'wb-ad-manager' ),
			'IN' => __( 'India', 'wb-ad-manager' ),
			'ID' => __( 'Indonesia', 'wb-ad-manager' ),
			'IR' => __( 'Iran', 'wb-ad-manager' ),
			'IQ' => __( 'Iraq', 'wb-ad-manager' ),
			'IE' => __( 'Ireland', 'wb-ad-manager' ),
			'IL' => __( 'Israel', 'wb-ad-manager' ),
			'IT' => __( 'Italy', 'wb-ad-manager' ),
			'JM' => __( 'Jamaica', 'wb-ad-manager' ),
			'JP' => __( 'Japan', 'wb-ad-manager' ),
			'JO' => __( 'Jordan', 'wb-ad-manager' ),
			'KZ' => __( 'Kazakhstan', 'wb-ad-manager' ),
			'KE' => __( 'Kenya', 'wb-ad-manager' ),
			'KI' => __( 'Kiribati', 'wb-ad-manager' ),
			'KP' => __( 'North Korea', 'wb-ad-manager' ),
			'KR' => __( 'South Korea', 'wb-ad-manager' ),
			'KW' => __( 'Kuwait', 'wb-ad-manager' ),
			'KG' => __( 'Kyrgyzstan', 'wb-ad-manager' ),
			'LA' => __( 'Laos', 'wb-ad-manager' ),
			'LV' => __( 'Latvia', 'wb-ad-manager' ),
			'LB' => __( 'Lebanon', 'wb-ad-manager' ),
			'LS' => __( 'Lesotho', 'wb-ad-manager' ),
			'LR' => __( 'Liberia', 'wb-ad-manager' ),
			'LY' => __( 'Libya', 'wb-ad-manager' ),
			'LI' => __( 'Liechtenstein', 'wb-ad-manager' ),
			'LT' => __( 'Lithuania', 'wb-ad-manager' ),
			'LU' => __( 'Luxembourg', 'wb-ad-manager' ),
			'MO' => __( 'Macau', 'wb-ad-manager' ),
			'MK' => __( 'North Macedonia', 'wb-ad-manager' ),
			'MG' => __( 'Madagascar', 'wb-ad-manager' ),
			'MW' => __( 'Malawi', 'wb-ad-manager' ),
			'MY' => __( 'Malaysia', 'wb-ad-manager' ),
			'MV' => __( 'Maldives', 'wb-ad-manager' ),
			'ML' => __( 'Mali', 'wb-ad-manager' ),
			'MT' => __( 'Malta', 'wb-ad-manager' ),
			'MH' => __( 'Marshall Islands', 'wb-ad-manager' ),
			'MR' => __( 'Mauritania', 'wb-ad-manager' ),
			'MU' => __( 'Mauritius', 'wb-ad-manager' ),
			'MX' => __( 'Mexico', 'wb-ad-manager' ),
			'FM' => __( 'Micronesia', 'wb-ad-manager' ),
			'MD' => __( 'Moldova', 'wb-ad-manager' ),
			'MC' => __( 'Monaco', 'wb-ad-manager' ),
			'MN' => __( 'Mongolia', 'wb-ad-manager' ),
			'ME' => __( 'Montenegro', 'wb-ad-manager' ),
			'MA' => __( 'Morocco', 'wb-ad-manager' ),
			'MZ' => __( 'Mozambique', 'wb-ad-manager' ),
			'MM' => __( 'Myanmar', 'wb-ad-manager' ),
			'NA' => __( 'Namibia', 'wb-ad-manager' ),
			'NR' => __( 'Nauru', 'wb-ad-manager' ),
			'NP' => __( 'Nepal', 'wb-ad-manager' ),
			'NL' => __( 'Netherlands', 'wb-ad-manager' ),
			'NZ' => __( 'New Zealand', 'wb-ad-manager' ),
			'NI' => __( 'Nicaragua', 'wb-ad-manager' ),
			'NE' => __( 'Niger', 'wb-ad-manager' ),
			'NG' => __( 'Nigeria', 'wb-ad-manager' ),
			'NO' => __( 'Norway', 'wb-ad-manager' ),
			'OM' => __( 'Oman', 'wb-ad-manager' ),
			'PK' => __( 'Pakistan', 'wb-ad-manager' ),
			'PW' => __( 'Palau', 'wb-ad-manager' ),
			'PS' => __( 'Palestine', 'wb-ad-manager' ),
			'PA' => __( 'Panama', 'wb-ad-manager' ),
			'PG' => __( 'Papua New Guinea', 'wb-ad-manager' ),
			'PY' => __( 'Paraguay', 'wb-ad-manager' ),
			'PE' => __( 'Peru', 'wb-ad-manager' ),
			'PH' => __( 'Philippines', 'wb-ad-manager' ),
			'PL' => __( 'Poland', 'wb-ad-manager' ),
			'PT' => __( 'Portugal', 'wb-ad-manager' ),
			'PR' => __( 'Puerto Rico', 'wb-ad-manager' ),
			'QA' => __( 'Qatar', 'wb-ad-manager' ),
			'RO' => __( 'Romania', 'wb-ad-manager' ),
			'RU' => __( 'Russia', 'wb-ad-manager' ),
			'RW' => __( 'Rwanda', 'wb-ad-manager' ),
			'KN' => __( 'Saint Kitts and Nevis', 'wb-ad-manager' ),
			'LC' => __( 'Saint Lucia', 'wb-ad-manager' ),
			'VC' => __( 'Saint Vincent and the Grenadines', 'wb-ad-manager' ),
			'WS' => __( 'Samoa', 'wb-ad-manager' ),
			'SM' => __( 'San Marino', 'wb-ad-manager' ),
			'ST' => __( 'São Tomé and Príncipe', 'wb-ad-manager' ),
			'SA' => __( 'Saudi Arabia', 'wb-ad-manager' ),
			'SN' => __( 'Senegal', 'wb-ad-manager' ),
			'RS' => __( 'Serbia', 'wb-ad-manager' ),
			'SC' => __( 'Seychelles', 'wb-ad-manager' ),
			'SL' => __( 'Sierra Leone', 'wb-ad-manager' ),
			'SG' => __( 'Singapore', 'wb-ad-manager' ),
			'SK' => __( 'Slovakia', 'wb-ad-manager' ),
			'SI' => __( 'Slovenia', 'wb-ad-manager' ),
			'SB' => __( 'Solomon Islands', 'wb-ad-manager' ),
			'SO' => __( 'Somalia', 'wb-ad-manager' ),
			'ZA' => __( 'South Africa', 'wb-ad-manager' ),
			'SS' => __( 'South Sudan', 'wb-ad-manager' ),
			'ES' => __( 'Spain', 'wb-ad-manager' ),
			'LK' => __( 'Sri Lanka', 'wb-ad-manager' ),
			'SD' => __( 'Sudan', 'wb-ad-manager' ),
			'SR' => __( 'Suriname', 'wb-ad-manager' ),
			'SZ' => __( 'Eswatini', 'wb-ad-manager' ),
			'SE' => __( 'Sweden', 'wb-ad-manager' ),
			'CH' => __( 'Switzerland', 'wb-ad-manager' ),
			'SY' => __( 'Syria', 'wb-ad-manager' ),
			'TW' => __( 'Taiwan', 'wb-ad-manager' ),
			'TJ' => __( 'Tajikistan', 'wb-ad-manager' ),
			'TZ' => __( 'Tanzania', 'wb-ad-manager' ),
			'TH' => __( 'Thailand', 'wb-ad-manager' ),
			'TL' => __( 'Timor-Leste', 'wb-ad-manager' ),
			'TG' => __( 'Togo', 'wb-ad-manager' ),
			'TO' => __( 'Tonga', 'wb-ad-manager' ),
			'TT' => __( 'Trinidad and Tobago', 'wb-ad-manager' ),
			'TN' => __( 'Tunisia', 'wb-ad-manager' ),
			'TR' => __( 'Turkey', 'wb-ad-manager' ),
			'TM' => __( 'Turkmenistan', 'wb-ad-manager' ),
			'TV' => __( 'Tuvalu', 'wb-ad-manager' ),
			'UG' => __( 'Uganda', 'wb-ad-manager' ),
			'UA' => __( 'Ukraine', 'wb-ad-manager' ),
			'AE' => __( 'United Arab Emirates', 'wb-ad-manager' ),
			'GB' => __( 'United Kingdom', 'wb-ad-manager' ),
			'US' => __( 'United States', 'wb-ad-manager' ),
			'UY' => __( 'Uruguay', 'wb-ad-manager' ),
			'UZ' => __( 'Uzbekistan', 'wb-ad-manager' ),
			'VU' => __( 'Vanuatu', 'wb-ad-manager' ),
			'VA' => __( 'Vatican City', 'wb-ad-manager' ),
			'VE' => __( 'Venezuela', 'wb-ad-manager' ),
			'VN' => __( 'Vietnam', 'wb-ad-manager' ),
			'YE' => __( 'Yemen', 'wb-ad-manager' ),
			'ZM' => __( 'Zambia', 'wb-ad-manager' ),
			'ZW' => __( 'Zimbabwe', 'wb-ad-manager' ),
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
