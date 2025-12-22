<?php
/**
 * Privacy Helper
 *
 * GDPR-compliant privacy utilities for WB Ad Manager.
 * Provides consent checking and IP anonymization.
 *
 * @package WB_Ad_Manager
 * @since   1.0.1
 */

namespace WBAM\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Privacy_Helper class.
 */
class Privacy_Helper {

	/**
	 * Check if user has given consent for third-party scripts.
	 *
	 * Integrates with popular consent management plugins:
	 * - Cookie Notice by dFactory
	 * - CookieYes (Cookie Law Info)
	 * - Complianz GDPR
	 * - GDPR Cookie Consent (WebToffee)
	 * - Borlabs Cookie
	 * - Real Cookie Banner
	 *
	 * @param string $consent_type Type of consent to check (statistics, marketing, preferences).
	 * @return bool True if consent given or not required, false if consent required but not given.
	 */
	public static function has_consent( $consent_type = 'marketing' ) {
		// Check if consent requirement is enabled in plugin settings.
		if ( ! Settings_Helper::is_enabled( 'require_consent_adsense' ) ) {
			return true; // Consent not required by settings.
		}

		// Allow developers to override consent check.
		$has_consent = apply_filters( 'wbam_has_consent', null, $consent_type );
		if ( null !== $has_consent ) {
			return (bool) $has_consent;
		}

		// Cookie Notice by dFactory.
		if ( function_exists( 'cn_cookies_accepted' ) ) {
			return cn_cookies_accepted();
		}

		// CookieYes / Cookie Law Info.
		if ( isset( $_COOKIE['cookieyes-consent'] ) ) {
			$consent = sanitize_text_field( wp_unslash( $_COOKIE['cookieyes-consent'] ) );
			// Check for marketing or advertisement consent.
			if ( strpos( $consent, 'advertisement:yes' ) !== false || strpos( $consent, 'analytics:yes' ) !== false ) {
				return true;
			}
			return false;
		}

		// Complianz GDPR.
		if ( function_exists( 'cmplz_has_consent' ) ) {
			return cmplz_has_consent( $consent_type );
		}

		// GDPR Cookie Consent by WebToffee.
		if ( isset( $_COOKIE['viewed_cookie_policy'] ) && 'yes' === $_COOKIE['viewed_cookie_policy'] ) {
			return true;
		}

		// Borlabs Cookie.
		if ( function_exists( 'BorlabsCookieHelper' ) ) {
			return BorlabsCookieHelper()->giveConsent( 'statistics' ) || BorlabsCookieHelper()->giveConsent( 'marketing' );
		}

		// Real Cookie Banner.
		if ( function_exists( 'wp_rcb_consent_given' ) ) {
			return wp_rcb_consent_given( 'http' );
		}

		// Generic consent cookie check (fallback).
		$consent_cookies = array(
			'cookie_notice_accepted',
			'moove_gdpr_popup',
			'cookieconsent_status',
			'eu-cookie',
		);

		foreach ( $consent_cookies as $cookie ) {
			if ( isset( $_COOKIE[ $cookie ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie ] ) );
				if ( in_array( $value, array( 'true', '1', 'yes', 'allow', 'allowed', 'accepted' ), true ) ) {
					return true;
				}
			}
		}

		// No consent mechanism detected - default to requiring consent.
		// This is the safest approach for GDPR compliance.
		return false;
	}

	/**
	 * Get anonymized IP address using hash with daily rotating salt.
	 *
	 * This provides a GDPR-compliant way to track unique visitors without
	 * storing personally identifiable information.
	 *
	 * @param string $ip Optional. IP address to anonymize. Defaults to current visitor IP.
	 * @return string Hashed IP (64 char hex string) or empty string if no IP.
	 */
	public static function get_anonymized_ip( $ip = '' ) {
		if ( empty( $ip ) ) {
			$ip = self::get_raw_ip();
		}

		if ( empty( $ip ) ) {
			return '';
		}

		// Use daily rotating salt to prevent long-term tracking.
		$daily_salt = wp_hash( gmdate( 'Y-m-d' ) );

		return hash( 'sha256', $ip . $daily_salt );
	}

	/**
	 * Get the raw client IP address.
	 *
	 * Note: This should only be used for rate limiting or security purposes.
	 * For storage, always use get_anonymized_ip() instead.
	 *
	 * @return string IP address or empty string.
	 */
	public static function get_raw_ip() {
		// Check proxy headers (in order of trust).
		$headers = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_FORWARDED_FOR',  // Standard proxy header.
			'HTTP_X_REAL_IP',        // Nginx.
			'REMOTE_ADDR',           // Direct connection.
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

				// X-Forwarded-For may contain multiple IPs, take the first.
				if ( 'HTTP_X_FORWARDED_FOR' === $header && strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip  = trim( $ips[0] );
				}

				// Validate IP format.
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '';
	}

	/**
	 * Generate a visitor hash for anonymous tracking.
	 *
	 * Combines IP, User-Agent, and daily salt for unique but anonymous visitor identification.
	 *
	 * @return string 64 character hex hash.
	 */
	public static function get_visitor_hash() {
		$ip             = self::get_raw_ip();
		$user_agent_raw = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$daily_salt     = wp_hash( gmdate( 'Y-m-d' ) );

		return hash( 'sha256', $ip . $user_agent_raw . $daily_salt );
	}

	/**
	 * Check if IP anonymization is enabled in settings.
	 *
	 * @return bool True if IP should be anonymized (default), false if raw IP can be stored.
	 */
	public static function should_anonymize_ip() {
		// Default to true for privacy by default.
		return Settings_Helper::get( 'anonymize_ip', true );
	}

	/**
	 * Get IP address for storage (anonymized if enabled, raw otherwise).
	 *
	 * @return string IP address (anonymized hash or raw based on settings).
	 */
	public static function get_storage_ip() {
		if ( self::should_anonymize_ip() ) {
			return self::get_anonymized_ip();
		}

		return self::get_raw_ip();
	}
}
