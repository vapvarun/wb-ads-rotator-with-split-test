<?php
/**
 * Formatter Helper
 *
 * Centralized formatting utilities for WB Ad Manager.
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formatter class.
 */
class Formatter {

	/**
	 * Default currency symbols.
	 *
	 * @var array
	 */
	private static $currency_symbols = array(
		'USD' => '$',
		'EUR' => '€',
		'GBP' => '£',
		'INR' => '₹',
		'AUD' => 'A$',
		'CAD' => 'C$',
		'JPY' => '¥',
		'CNY' => '¥',
	);

	/**
	 * Format currency amount.
	 *
	 * @param float  $amount   Amount to format.
	 * @param string $currency Currency code (default: USD).
	 * @return string Formatted currency string.
	 */
	public static function currency( $amount, $currency = 'USD' ) {
		$symbol   = self::get_currency_symbol( $currency );
		$decimals = self::get_currency_decimals( $currency );

		return $symbol . number_format( (float) $amount, $decimals );
	}

	/**
	 * Get currency symbol.
	 *
	 * Uses filter hook so PRO plugin can override with settings.
	 *
	 * @param string $currency Currency code.
	 * @return string Currency symbol.
	 */
	public static function get_currency_symbol( $currency = 'USD' ) {
		$default_symbol = isset( self::$currency_symbols[ $currency ] )
			? self::$currency_symbols[ $currency ]
			: $currency . ' ';

		/**
		 * Filter the currency symbol.
		 *
		 * @param string $symbol   Default currency symbol.
		 * @param string $currency Currency code.
		 */
		return apply_filters( 'wbam_currency_symbol', $default_symbol, $currency );
	}

	/**
	 * Get decimal places for currency.
	 *
	 * @param string $currency Currency code.
	 * @return int Number of decimal places.
	 */
	public static function get_currency_decimals( $currency = 'USD' ) {
		// JPY doesn't use decimal places.
		$no_decimals = array( 'JPY' );

		return in_array( $currency, $no_decimals, true ) ? 0 : 2;
	}

	/**
	 * Format a number with thousands separator.
	 *
	 * @param int|float $number   Number to format.
	 * @param int       $decimals Decimal places (default: 0).
	 * @return string Formatted number.
	 */
	public static function number( $number, $decimals = 0 ) {
		return number_format( (float) $number, $decimals );
	}

	/**
	 * Format a percentage.
	 *
	 * @param float $value    Value to format as percentage.
	 * @param int   $decimals Decimal places (default: 2).
	 * @return string Formatted percentage.
	 */
	public static function percentage( $value, $decimals = 2 ) {
		return number_format( (float) $value, $decimals ) . '%';
	}

	/**
	 * Format a date.
	 *
	 * @param string|int $date   A site-calendar 'Y-m-d', a stored UTC moment, or a Unix timestamp.
	 * @param string     $format Date format (default: WordPress date format).
	 * @return string Formatted date.
	 */
	public static function date( $date, $format = '' ) {
		if ( empty( $format ) ) {
			$format = get_option( 'date_format' );
		}

		if ( is_numeric( $date ) ) {
			return (string) wp_date( $format, (int) $date );
		}

		// A bare 'Y-m-d' is a site-calendar day; anything longer is a stored UTC moment.
		return 10 === strlen( trim( (string) $date ) ) ? wbam_format_day( $date, $format ) : wbam_format_datetime( $date, $format );
	}

	/**
	 * Format a datetime.
	 *
	 * @param string|int $datetime A stored UTC moment or a Unix timestamp.
	 * @param string     $format   DateTime format (default: WordPress datetime format).
	 * @return string Formatted datetime.
	 */
	public static function datetime( $datetime, $format = '' ) {
		if ( empty( $format ) ) {
			$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}

		return is_numeric( $datetime ) ? (string) wp_date( $format, (int) $datetime ) : wbam_format_datetime( $datetime, $format );
	}

	/**
	 * Format bytes to human readable size.
	 *
	 * @param int $bytes     Bytes to format.
	 * @param int $precision Decimal places (default: 2).
	 * @return string Formatted size.
	 */
	public static function bytes( $bytes, $precision = 2 ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );

		$bytes = max( $bytes, 0 );
		$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow   = min( $pow, count( $units ) - 1 );

		$bytes /= pow( 1024, $pow );

		return round( $bytes, $precision ) . ' ' . $units[ $pow ];
	}
}
