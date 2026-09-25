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

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings_Helper class.
 */
class Settings_Helper {

	/**
	 * Explicit "no placements" sentinel.
	 *
	 * ---------------------------------------------------------------------
	 * PLACEMENT GATE ENCODING — read this before touching either gate.
	 * ---------------------------------------------------------------------
	 *
	 * Both gates (`enabled_placements`, `advertiser_placements`) are stored
	 * as a list of placement IDs, but the value has THREE meanings and a
	 * plain list can only express one of them:
	 *
	 *   array()                 ALL placements. This is the upgrade-safety
	 *                           default: an install that never opens the
	 *                           Placements screen behaves exactly as it did
	 *                           before the gates existed, and a placement
	 *                           registered LATER (a companion plugin, a Pro
	 *                           custom slot) is open too. NEVER reinterpret
	 *                           an empty array as "none".
	 *   array( GATE_NONE )      NONE. Explicitly closed by an admin.
	 *                           GATE_NONE is not, and must never be, a real
	 *                           placement ID — so every `in_array()`
	 *                           membership test simply fails and the gate
	 *                           shuts. No consumer needs a special case.
	 *   array( 'header', ... )  Exactly those placements.
	 *
	 * An HTML form cannot express that on its own: an unticked checkbox
	 * posts nothing, so "the admin unticked every box" and "the matrix was
	 * not part of this request at all" both arrive as a missing key.
	 * `Placement_Settings::render_table()` therefore emits two
	 * transport-only hidden fields — `placement_gates_submitted` (the matrix
	 * WAS on this request) and `placement_gates_offered` (the exact rows it
	 * drew) — and `Settings::sanitize_settings()` uses them to choose
	 * between the three states. Neither field is ever stored.
	 *
	 * @since 2.11.0
	 * @var string
	 */
	const GATE_NONE = '__wbam_none__';

	/**
	 * Get settings value.
	 *
	 * @param string $key           Optional. Specific setting key to retrieve.
	 * @param mixed  $default_value Optional. Default value if key not found.
	 * @return mixed Settings array or specific value.
	 */
	public static function get( $key = null, $default_value = null ) {
		$settings = get_option( 'wbam_settings', array() );

		if ( null === $key ) {
			return $settings;
		}

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default_value;
	}

	/**
	 * Whether format-aware placement matching is on (Ad Display > Placements).
	 *
	 * Stored in `wbam_settings[format_matching]` since 3.2.0. Before that it
	 * was the standalone `wbam_format_matching_enabled` option, which stays
	 * the fallback until the owner saves the setting.
	 *
	 * @since 3.2.0
	 * @return bool
	 */
	public static function format_matching_enabled() {
		return (bool) self::get( 'format_matching', (bool) get_option( 'wbam_format_matching_enabled', false ) );
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

	/**
	 * Optional feature areas that a site owner can switch off.
	 *
	 * Each entry ships enabled. A site that does not use a feature can turn it
	 * off to remove its admin menu; a site that does use it is unaffected by
	 * the module system existing. Defaults are deliberately ON so that adding
	 * a module here never removes a menu from an existing install on update.
	 *
	 * @since 2.9.2
	 * @return array<string,bool> Module slug => default enabled state.
	 */
	public static function module_defaults() {
		/**
		 * Filter the optional module list and their default states.
		 *
		 * @since 2.9.2
		 * @param array<string,bool> $defaults Module slug => default enabled.
		 */
		return (array) apply_filters(
			'wbam_module_defaults',
			array(
				'links' => true,
			)
		);
	}

	/**
	 * Whether an optional module is switched on.
	 *
	 * Unknown slugs return true, so a module that has not been added to
	 * module_defaults() yet is never hidden by accident.
	 *
	 * @since 2.9.2
	 * @param string $slug Module slug.
	 * @return bool
	 */
	public static function is_module_enabled( $slug ) {
		$defaults = self::module_defaults();
		$default  = array_key_exists( $slug, $defaults ) ? (bool) $defaults[ $slug ] : true;
		$modules  = (array) self::get( 'modules', array() );
		$enabled  = array_key_exists( $slug, $modules ) ? (bool) $modules[ $slug ] : $default;

		/**
		 * Filter whether a single module is enabled.
		 *
		 * PRO reads this so a module switched off in FREE also removes the
		 * submenus PRO contributes to it.
		 *
		 * @since 2.9.2
		 * @param bool   $enabled Whether the module is on.
		 * @param string $slug    Module slug.
		 */
		return (bool) apply_filters( 'wbam_is_module_enabled', $enabled, $slug );
	}

	/**
	 * Normalize a raw stored gate value into sanitized placement IDs.
	 *
	 * `strlen` rather than the default `array_filter()` callback, so a slug
	 * that sanitizes to "0" survives. Built-in placements cannot produce
	 * one, but `wbam_register_placements` is a public extension point.
	 *
	 * @since 2.11.0
	 * @param mixed $ids Raw value from the option or a filter.
	 * @return string[]
	 */
	private static function normalize_ids( $ids ) {
		$out = array();

		foreach ( (array) $ids as $id ) {
			if ( ! is_scalar( $id ) ) {
				continue;
			}

			$id = sanitize_key( (string) $id );
			if ( '' === $id ) {
				continue;
			}

			$out[] = $id;
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Placement IDs usable on this site.
	 *
	 * An empty array means "all placements" — that is what keeps existing
	 * installs behaving identically after upgrade. It must never be
	 * reinterpreted as "none"; an explicit "none" is `array( GATE_NONE )`.
	 * See the GATE_NONE docblock for the full encoding.
	 *
	 * @since 2.11.0
	 * @return string[] Placement IDs, empty for all, or array( GATE_NONE ) for none.
	 */
	public static function enabled_placements() {
		$settings = get_option( 'wbam_settings', array() );
		$ids      = isset( $settings['enabled_placements'] ) && is_array( $settings['enabled_placements'] )
			? $settings['enabled_placements']
			: array();

		$ids = self::normalize_ids( $ids );

		// "None" is absolute — anything alongside the sentinel is noise.
		if ( in_array( self::GATE_NONE, $ids, true ) ) {
			$ids = array( self::GATE_NONE );
		}

		/**
		 * Filter the placements usable on this site.
		 *
		 * @since 2.11.0
		 * @param string[] $ids Placement IDs. Empty array means all.
		 */
		return (array) apply_filters( 'wbam_enabled_placements', $ids );
	}

	/**
	 * Whether the site gate lets a placement deliver ads.
	 *
	 * The single expression of the site gate. It was previously inlined in
	 * both Placement_Engine::get_selectable_placements() and
	 * ::get_ads_for_placement(), where the two copies were free to drift.
	 *
	 * @since 2.11.0
	 * @param string $id Placement ID.
	 * @return bool
	 */
	public static function is_placement_open( $id ) {
		$id = (string) $id;

		if ( '' === $id || self::GATE_NONE === $id ) {
			return false;
		}

		$allowed = self::enabled_placements();

		// Empty means ALL. array( GATE_NONE ) means none, and needs no
		// special case here: no real placement ID can ever match it.
		return empty( $allowed ) || in_array( $id, $allowed, true );
	}

	/**
	 * Placement IDs that may be sold to advertisers.
	 *
	 * Always a subset of enabled_placements(): a slot the site has closed
	 * can never be sellable, whatever the stored value says. An empty
	 * stored value falls back to the site gate rather than to "none", so
	 * an admin who never opens this screen keeps today's behaviour. An
	 * admin who DID open it and unticked every box gets GATE_NONE, which
	 * short-circuits that fallback — otherwise the screen would warn about
	 * closing the column and then silently open all of it.
	 *
	 * @since 2.11.0
	 * @return string[] Placement IDs, empty for all, or array( GATE_NONE ) for none.
	 */
	public static function advertiser_placements() {
		$settings = get_option( 'wbam_settings', array() );
		$ids      = isset( $settings['advertiser_placements'] ) && is_array( $settings['advertiser_placements'] )
			? $settings['advertiser_placements']
			: array();

		$ids  = self::normalize_ids( $ids );
		$site = self::enabled_placements();

		if ( in_array( self::GATE_NONE, $ids, true ) ) {
			// Explicitly closed for sale. Never fall back to the site list.
			$ids = array( self::GATE_NONE );
		} elseif ( empty( $ids ) ) {
			// "All" here means "everything the site allows", which IS the
			// site gate — including when that is itself GATE_NONE.
			$ids = $site;
		} elseif ( ! empty( $site ) ) {
			$ids = array_values( array_intersect( $ids, $site ) );

			// The intersection emptied the list. That is "none"; returning
			// array() here would invert the gate into "all".
			if ( empty( $ids ) ) {
				$ids = array( self::GATE_NONE );
			}
		}

		/**
		 * Filter the placements sellable to advertisers.
		 *
		 * @since 2.11.0
		 * @param string[] $ids Placement IDs. Empty array means all.
		 */
		return (array) apply_filters( 'wbam_advertiser_placements', $ids );
	}
}
