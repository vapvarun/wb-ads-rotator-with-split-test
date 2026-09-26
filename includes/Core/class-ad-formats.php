<?php
/**
 * Canonical ad format taxonomy + compatibility helpers.
 *
 * Single source of truth for ad sizing. Every ad declares one format
 * slug; every placement declares which formats it accepts. The
 * compatibility check lives here so there is exactly one matching
 * function across the render engine, admin UI, ad submission form,
 * and package editor.
 *
 * See docs/superpowers/plans/2026-04-15-format-aware-placement-matching.md
 *
 * @package WB_Ad_Manager
 * @since   2.8.1
 */

namespace WBAM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ad format taxonomy + matching.
 */
class Ad_Formats {

	/**
	 * Special slug: ad fills any container width, fits every placement.
	 */
	const RESPONSIVE = 'responsive';

	/**
	 * Special slug: ad declares its own W x H that doesn't map to a
	 * named size in the taxonomy. Fits a placement only when W x H
	 * matches one of the placement's accepted_formats.
	 */
	const CUSTOM = 'custom';

	/**
	 * Canonical format registry.
	 *
	 * Keys are the slugs stored in post meta and passed between layers.
	 * Do not rename a slug in place — existing ads/packages reference
	 * it by string. Add new formats by appending rows.
	 *
	 * @return array<string, array{label:string, width:int, height:int, responsive:bool}>
	 */
	public static function all() {
		static $formats = null;

		if ( null !== $formats ) {
			return $formats;
		}

		$formats = array(
			'leaderboard'         => array(
				'label'      => __( 'Leaderboard (728x90)', 'wb-ads-rotator-with-split-test' ),
				'width'      => 728,
				'height'     => 90,
				'responsive' => false,
			),
			'large-leaderboard'   => array(
				'label'      => __( 'Large Leaderboard (970x90)', 'wb-ads-rotator-with-split-test' ),
				'width'      => 970,
				'height'     => 90,
				'responsive' => false,
			),
			'billboard'           => array(
				'label'      => __( 'Billboard (970x250)', 'wb-ads-rotator-with-split-test' ),
				'width'      => 970,
				'height'     => 250,
				'responsive' => false,
			),
			'banner'              => array(
				'label'      => __( 'Banner (468x60)', 'wb-ads-rotator-with-split-test' ),
				'width'      => 468,
				'height'     => 60,
				'responsive' => false,
			),
			'mobile-banner'       => array(
				'label'      => __( 'Mobile Banner (320x50)', 'wb-ads-rotator-with-split-test' ),
				'width'      => 320,
				'height'     => 50,
				'responsive' => false,
			),
			'mobile-large-banner' => array(
				'label'      => __( 'Mobile Large Banner (320x100)', 'wb-ads-rotator-with-split-test' ),
				'width'      => 320,
				'height'     => 100,
				'responsive' => false,
			),
			'medium-rectangle'    => array(
				'label'      => __( 'Medium Rectangle (300x250)', 'wb-ads-rotator-with-split-test' ),
				'width'      => 300,
				'height'     => 250,
				'responsive' => false,
			),
			'large-rectangle'     => array(
				'label'      => __( 'Large Rectangle (336x280)', 'wb-ads-rotator-with-split-test' ),
				'width'      => 336,
				'height'     => 280,
				'responsive' => false,
			),
			'skyscraper'          => array(
				'label'      => __( 'Skyscraper (160x600)', 'wb-ads-rotator-with-split-test' ),
				'width'      => 160,
				'height'     => 600,
				'responsive' => false,
			),
			'wide-skyscraper'     => array(
				'label'      => __( 'Wide Skyscraper / Half-Page (300x600)', 'wb-ads-rotator-with-split-test' ),
				'width'      => 300,
				'height'     => 600,
				'responsive' => false,
			),
			'square'              => array(
				'label'      => __( 'Square (250x250)', 'wb-ads-rotator-with-split-test' ),
				'width'      => 250,
				'height'     => 250,
				'responsive' => false,
			),
			self::RESPONSIVE      => array(
				'label'      => __( 'Responsive (fills any slot)', 'wb-ads-rotator-with-split-test' ),
				'width'      => 0,
				'height'     => 0,
				'responsive' => true,
			),
			self::CUSTOM          => array(
				'label'      => __( 'Custom size', 'wb-ads-rotator-with-split-test' ),
				'width'      => 0,
				'height'     => 0,
				'responsive' => false,
			),
		);

		/**
		 * Filter the canonical ad format taxonomy.
		 *
		 * Downstream consumers may append site-specific formats here,
		 * but should not remove or rename the built-in entries — ads
		 * and packages reference them by slug.
		 *
		 * @since 2.8.1
		 * @param array $formats Format slug => metadata map.
		 */
		$formats = apply_filters( 'wbam_ad_formats', $formats );

		return $formats;
	}

	/**
	 * Look up a single format by slug.
	 *
	 * @param string $slug Format slug.
	 * @return array|null Format metadata, or null if unknown.
	 */
	public static function get( $slug ) {
		$all = self::all();
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}

	/**
	 * Return true if the slug is a known format.
	 *
	 * @param string $slug Format slug.
	 * @return bool
	 */
	public static function is_known( $slug ) {
		return null !== self::get( (string) $slug );
	}

	/**
	 * Infer a format slug from pixel dimensions.
	 *
	 * Returns the first taxonomy slug whose W x H matches exactly.
	 * Returns self::CUSTOM when no taxonomy entry matches — the caller
	 * is expected to persist the actual width/height alongside the
	 * custom slug so matching can still work downstream.
	 *
	 * Tolerance is exact by default; filter `wbam_format_fit_tolerance`
	 * to allow fuzzy matching on sites that want it.
	 *
	 * @param int $width  Pixel width.
	 * @param int $height Pixel height.
	 * @return string Format slug (one of the taxonomy keys, or CUSTOM).
	 */
	public static function detect_by_dimensions( $width, $height ) {
		$width  = (int) $width;
		$height = (int) $height;

		if ( $width <= 0 || $height <= 0 ) {
			return self::CUSTOM;
		}

		/**
		 * Filter the pixel tolerance when matching dimensions to
		 * the taxonomy. 0 = exact match required (default).
		 *
		 * @since 2.8.1
		 * @param int $tolerance Pixels of allowed deviation on each axis.
		 */
		$tolerance = (int) apply_filters( 'wbam_format_fit_tolerance', 0 );

		foreach ( self::all() as $slug => $meta ) {
			if ( empty( $meta['width'] ) || empty( $meta['height'] ) ) {
				continue; // responsive / custom have no fixed dims.
			}

			if (
				abs( $meta['width'] - $width ) <= $tolerance
				&& abs( $meta['height'] - $height ) <= $tolerance
			) {
				return $slug;
			}
		}

		return self::CUSTOM;
	}

	/**
	 * Resolve the format slug currently stored on an ad.
	 *
	 * Reads `_wbam_ad_format` meta; falls back to `_wbam_is_responsive`
	 * so ads created before the format UI shipped are treated as
	 * responsive (permissive default — they keep rendering everywhere
	 * they already do).
	 *
	 * @param int $ad_id Ad post ID.
	 * @return string Format slug.
	 */
	public static function get_ad_format( $ad_id ) {
		$ad_id = (int) $ad_id;

		$format = (string) get_post_meta( $ad_id, '_wbam_ad_format', true );

		if ( '' !== $format && self::is_known( $format ) ) {
			return $format;
		}

		// Backcompat: ads without a format but with the responsive flag
		// ticked are treated as responsive. Everything else defaults to
		// responsive too (safe permissive default — the render-time
		// filter is feature-flagged anyway).
		$is_responsive = '1' === (string) get_post_meta( $ad_id, '_wbam_is_responsive', true );

		return $is_responsive ? self::RESPONSIVE : self::RESPONSIVE;
	}

	/**
	 * Resolve an ad's declared pixel dimensions, if any.
	 *
	 * @param int $ad_id Ad post ID.
	 * @return array{width:int, height:int} Zeros when unknown.
	 */
	public static function get_ad_dimensions( $ad_id ) {
		$ad_id  = (int) $ad_id;
		$width  = (int) get_post_meta( $ad_id, '_wbam_ad_width', true );
		$height = (int) get_post_meta( $ad_id, '_wbam_ad_height', true );

		return array(
			'width'  => max( 0, $width ),
			'height' => max( 0, $height ),
		);
	}

	/**
	 * Whether an ad fits a given placement.
	 *
	 * Rules (see plan doc, section "The canonical format taxonomy"):
	 * - `responsive` ads fit every placement.
	 * - A fixed-format ad fits a placement whose accepted_formats list
	 *   includes that format OR the literal `responsive` slug.
	 * - A `custom` ad fits a placement only if its W x H matches
	 *   (within tolerance) one of the placement's accepted formats.
	 *
	 * Placement lookup is delegated to wbam_get_placements() which the
	 * free plugin populates via the 'wbam_get_placements' filter (same
	 * hook the pro plugin already uses). This keeps Ad_Formats free of
	 * a hard dependency on Placement_Engine internals.
	 *
	 * @param int    $ad_id          Ad post ID.
	 * @param string $placement_slug Placement slug.
	 * @return bool
	 */
	public static function fits( $ad_id, $placement_slug ) {
		$ad_id          = (int) $ad_id;
		$placement_slug = (string) $placement_slug;

		if ( $ad_id <= 0 || '' === $placement_slug ) {
			return false;
		}

		$ad_format = self::get_ad_format( $ad_id );

		// Responsive ads always fit — by definition they fill the slot.
		if ( self::RESPONSIVE === $ad_format ) {
			return true;
		}

		$placement_formats = self::get_placement_accepted_formats( $placement_slug );

		// Placement didn't declare formats (or is unknown) => permissive.
		// Feature flag on the render side governs whether we should even
		// be here; at this helper level, empty = accept all to avoid
		// breaking surfaces that call fits() before placements migrate.
		if ( empty( $placement_formats ) ) {
			return true;
		}

		if ( self::CUSTOM === $ad_format ) {
			// Translate the ad's persisted dimensions into a taxonomy
			// slug, then check against the placement's accepted list.
			// A custom ad with unknown dimensions can't be matched.
			$dims = self::get_ad_dimensions( $ad_id );
			if ( $dims['width'] <= 0 || $dims['height'] <= 0 ) {
				return false;
			}

			$matched_slug = self::detect_by_dimensions( $dims['width'], $dims['height'] );
			if ( self::CUSTOM === $matched_slug ) {
				return false;
			}

			return in_array( $matched_slug, $placement_formats, true );
		}

		// Fixed-format ad fits only when the placement explicitly lists
		// the ad's format. Note: a placement listing `responsive` in its
		// accepted_formats means "accepts responsive creatives", NOT
		// "accepts anything" — a wide-skyscraper ad does not belong in
		// a header slot that only lists [leaderboard, responsive].
		return in_array( $ad_format, $placement_formats, true );
	}

	/**
	 * Resolve the accepted_formats array for a placement slug.
	 *
	 * Reads from the wbam_get_placements() registry. Returns an empty
	 * array when the placement is unknown or didn't declare any
	 * formats — callers should treat empty as "permissive".
	 *
	 * @param string $placement_slug Placement slug.
	 * @return string[]
	 */
	public static function get_placement_accepted_formats( $placement_slug ) {
		$registry = apply_filters( 'wbam_get_placements', array() );

		if ( ! is_array( $registry ) || ! isset( $registry[ $placement_slug ] ) ) {
			return array();
		}

		$entry = $registry[ $placement_slug ];
		if ( ! is_array( $entry ) || empty( $entry['accepted_formats'] ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'strval', (array) $entry['accepted_formats'] ) ) );
	}

	/**
	 * Intersect a placement registry's format-compatible slugs with a
	 * specific list of "selected" (ticked) placement slugs.
	 *
	 * Backs the ad-edit screen's sizing "compatibility" summary (A1 fix:
	 * that summary used to answer "which placements accept this format"
	 * while claiming "Will render in:" — misleading when the admin had
	 * only ticked a subset of placements in the metabox below, or none at
	 * all). Pure computation — no WP calls, no DB reads — so it is
	 * unit-testable in isolation from both `render_status_metabox()`
	 * (initial server-rendered value) and the client-side live recompute
	 * (`collect_format_js_data()` payload consumed by the inline script).
	 *
	 * Matching rule: a placement matches when $format is 'responsive' OR
	 * appears in that placement's accepted_formats list. An empty
	 * accepted_formats list does NOT match a non-responsive format here —
	 * deliberately not Ad_Formats::fits()'s permissive-on-empty behaviour,
	 * to keep this summary's output unchanged for placements that have
	 * not declared formats yet (same restrictive rule the summary already
	 * used before this fix).
	 *
	 * @since 2.11.1
	 * @param array<string,array{name?:string,accepted_formats?:array<int,string>}> $registry Placement registry (already site-gated, e.g. via the wbam_get_placements filter).
	 * @param string   $format   Resolved format slug for the ad being edited.
	 * @param string[] $selected Placement slugs currently ticked in the Placements metabox.
	 * @return array{compatible:string[], match:string[], mismatch:string[]} `compatible` is every
	 *         registry slug whose accepted formats include $format; `match` is the subset of
	 *         $selected that is also in `compatible`; `mismatch` is the subset of $selected that
	 *         is not. Selected slugs absent from $registry are ignored — no checkbox can exist
	 *         for a placement the registry doesn't expose (get_selectable_placements() is the
	 *         only source for those checkboxes).
	 */
	public static function summarize_placement_compat( array $registry, $format, array $selected ) {
		$format     = (string) $format;
		$compatible = array();

		foreach ( $registry as $slug => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$accepted = isset( $entry['accepted_formats'] ) ? array_map( 'strval', (array) $entry['accepted_formats'] ) : array();

			if ( self::RESPONSIVE === $format || in_array( $format, $accepted, true ) ) {
				$compatible[] = (string) $slug;
			}
		}

		$match    = array();
		$mismatch = array();

		foreach ( $selected as $slug ) {
			$slug = (string) $slug;

			if ( ! isset( $registry[ $slug ] ) ) {
				continue;
			}

			if ( in_array( $slug, $compatible, true ) ) {
				$match[] = $slug;
			} else {
				$mismatch[] = $slug;
			}
		}

		return array(
			'compatible' => $compatible,
			'match'      => $match,
			'mismatch'   => $mismatch,
		);
	}
}
