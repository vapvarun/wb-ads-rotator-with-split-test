<?php
/**
 * WordPress personal data export and erase for the free plugin's tables.
 *
 * Tools > Export / Erase Personal Data look people up by email address. The
 * free plugin keeps two kinds of personal data, both purely the person's own,
 * so the eraser deletes them (owner decision on card 10345179396):
 *
 * - email sign-ups from Email Capture ads (wbam_email_submissions);
 * - partnership requests from [wbam_partnership_inquiry] (wbam_link_partnerships).
 *
 * Ad analytics hold no email and no raw IP (visitor hashes only), so there is
 * nothing to find for a person there. Pro registers its own exporters and
 * erasers for its tables.
 *
 * @package WBAM
 * @since 3.2.0
 */

namespace WBAM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and answers the free plugin's exporters and erasers.
 */
final class Privacy {

	/**
	 * Rows per call. WordPress calls again with the next page until `done`.
	 */
	const PER_PAGE = 100;

	/**
	 * Hook into the WordPress privacy tools.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
	}

	/**
	 * Add the exporters.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public static function exporters( $exporters ) {
		$exporters['wbam-email-signups']        = array(
			'exporter_friendly_name' => __( 'WB Ad Manager email sign-ups', 'wb-ads-rotator-with-split-test' ),
			'callback'               => array( __CLASS__, 'export_signups' ),
		);
		$exporters['wbam-partnership-requests'] = array(
			'exporter_friendly_name' => __( 'WB Ad Manager partnership requests', 'wb-ads-rotator-with-split-test' ),
			'callback'               => array( __CLASS__, 'export_partnerships' ),
		);
		return $exporters;
	}

	/**
	 * Add the erasers.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public static function erasers( $erasers ) {
		$erasers['wbam-email-signups']        = array(
			'eraser_friendly_name' => __( 'WB Ad Manager email sign-ups', 'wb-ads-rotator-with-split-test' ),
			'callback'             => array( __CLASS__, 'erase_signups' ),
		);
		$erasers['wbam-partnership-requests'] = array(
			'eraser_friendly_name' => __( 'WB Ad Manager partnership requests', 'wb-ads-rotator-with-split-test' ),
			'callback'             => array( __CLASS__, 'erase_partnerships' ),
		);
		return $erasers;
	}

	/**
	 * Export the email sign-ups made with this address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  1-based page.
	 * @return array{data: array, done: bool}
	 */
	public static function export_signups( $email, $page = 1 ) {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- privacy export, plugin table.
			$wpdb->prepare(
				"SELECT id, ad_id, email, name, created_at FROM {$wpdb->prefix}wbam_email_submissions WHERE email = %s ORDER BY id LIMIT %d OFFSET %d",
				$email,
				self::PER_PAGE,
				self::offset( $page )
			)
		);

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'group_id'    => 'wbam-email-signups',
				'group_label' => __( 'Ad email sign-ups', 'wb-ads-rotator-with-split-test' ),
				'item_id'     => 'wbam-signup-' . (int) $row->id,
				'data'        => self::fields(
					array(
						__( 'Email', 'wb-ads-rotator-with-split-test' )      => $row->email,
						__( 'Name', 'wb-ads-rotator-with-split-test' )       => $row->name,
						__( 'Signed up on', 'wb-ads-rotator-with-split-test' ) => get_the_title( (int) $row->ad_id ),
						__( 'Date', 'wb-ads-rotator-with-split-test' )       => wbam_format_datetime( $row->created_at ),
					)
				),
			);
		}

		return array(
			'data' => $items,
			'done' => count( $items ) < self::PER_PAGE,
		);
	}

	/**
	 * Export the partnership requests sent from this address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  1-based page.
	 * @return array{data: array, done: bool}
	 */
	public static function export_partnerships( $email, $page = 1 ) {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- privacy export, plugin table.
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wbam_link_partnerships WHERE email = %s ORDER BY id LIMIT %d OFFSET %d",
				$email,
				self::PER_PAGE,
				self::offset( $page )
			)
		);

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'group_id'    => 'wbam-partnership-requests',
				'group_label' => __( 'Partnership requests', 'wb-ads-rotator-with-split-test' ),
				'item_id'     => 'wbam-partnership-' . (int) $row->id,
				'data'        => self::fields(
					array(
						__( 'Name', 'wb-ads-rotator-with-split-test' )        => $row->name,
						__( 'Email', 'wb-ads-rotator-with-split-test' )       => $row->email,
						__( 'Website', 'wb-ads-rotator-with-split-test' )     => $row->website_url,
						__( 'Request type', 'wb-ads-rotator-with-split-test' ) => $row->partnership_type,
						__( 'Anchor text', 'wb-ads-rotator-with-split-test' ) => $row->anchor_text,
						__( 'Message', 'wb-ads-rotator-with-split-test' )     => $row->message,
						__( 'Status', 'wb-ads-rotator-with-split-test' )      => $row->status,
						__( 'IP address', 'wb-ads-rotator-with-split-test' )  => $row->ip_address,
						__( 'Date', 'wb-ads-rotator-with-split-test' )        => wbam_format_datetime( $row->created_at ),
					)
				),
			);
		}

		return array(
			'data' => $items,
			'done' => count( $items ) < self::PER_PAGE,
		);
	}

	/**
	 * Delete the email sign-ups made with this address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Unused: each call deletes the next batch.
	 * @return array
	 */
	public static function erase_signups( $email, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress eraser signature.
		global $wpdb;

		$deleted = (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- privacy erase, plugin table.
			$wpdb->prepare( "DELETE FROM {$wpdb->prefix}wbam_email_submissions WHERE email = %s LIMIT %d", $email, self::PER_PAGE )
		);

		return self::erased( $deleted );
	}

	/**
	 * Delete the partnership requests sent from this address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Unused: each call deletes the next batch.
	 * @return array
	 */
	public static function erase_partnerships( $email, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress eraser signature.
		global $wpdb;

		$deleted = (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- privacy erase, plugin table.
			$wpdb->prepare( "DELETE FROM {$wpdb->prefix}wbam_link_partnerships WHERE email = %s LIMIT %d", $email, self::PER_PAGE )
		);

		return self::erased( $deleted );
	}

	/**
	 * Name/value pairs for an export item, leaving out empty values.
	 *
	 * @param array<string,mixed> $pairs Label => value.
	 * @return array<int,array{name:string,value:string}>
	 */
	public static function fields( array $pairs ) {
		$out = array();
		foreach ( $pairs as $name => $value ) {
			if ( null !== $value && '' !== (string) $value ) {
				$out[] = array(
					'name'  => (string) $name,
					'value' => (string) $value,
				);
			}
		}
		return $out;
	}

	/**
	 * Eraser result for one delete batch.
	 *
	 * @param int $deleted Rows deleted by this call.
	 * @return array
	 */
	private static function erased( $deleted ) {
		return array(
			'items_removed'  => $deleted > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => $deleted < self::PER_PAGE,
		);
	}

	/**
	 * SQL offset for a 1-based page.
	 *
	 * @param int $page Page.
	 * @return int
	 */
	private static function offset( $page ) {
		return ( max( 1, (int) $page ) - 1 ) * self::PER_PAGE;
	}
}
