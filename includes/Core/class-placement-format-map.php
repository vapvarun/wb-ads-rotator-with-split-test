<?php
/**
 * Placement -> accepted-formats map.
 *
 * Phase B of the format-aware placement matching plan. Rather than
 * editing 15+ placement classes and adding a method to each, we keep
 * one mapping file here and attach accepted_formats to the existing
 * wbam_get_placements registry via a filter at priority 20 (the ad
 * submission producer runs at default priority 10).
 *
 * Adding a new built-in placement? Add one row. Third-party placements
 * get an empty accepted_formats unless their authors populate via the
 * same filter — which means the matching layer treats them as
 * permissive (accept anything) until the author opts in.
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
 * Attach accepted_formats to each registered placement.
 */
class Placement_Format_Map {

	/**
	 * Wire the filters. Called from Plugin::init().
	 *
	 * We register two callbacks on wbam_get_placements:
	 *  - Priority 5: seed the registry from the free plugin's own
	 *    Placement_Engine. This guarantees the registry is populated
	 *    in every context (admin, REST, wp-cli, frontend) without
	 *    depending on the pro plugin being active.
	 *  - Priority 20: attach accepted_formats via the canonical map.
	 *
	 * Pro's Ad_Submission_Shortcodes::get_available_placements stays at
	 * default priority 10 and remains a no-op when the free plugin has
	 * already seeded the entry (same idempotent shape: name, description,
	 * group). Priority 20 never overwrites non-empty accepted_formats,
	 * so third-party placements that declare their own formats stay
	 * authoritative.
	 */
	public static function register() {
		add_filter( 'wbam_get_placements', array( __CLASS__, 'seed_from_engine' ), 5 );
		add_filter( 'wbam_get_placements', array( __CLASS__, 'apply' ), 20 );
	}

	/**
	 * Populate the registry directly from Placement_Engine so it is
	 * available without the pro plugin.
	 *
	 * @param array $registry Existing registry.
	 * @return array
	 */
	public static function seed_from_engine( $registry ) {
		if ( ! is_array( $registry ) ) {
			$registry = array();
		}

		if ( ! class_exists( '\\WBAM\\Modules\\Placements\\Placement_Engine' ) ) {
			return $registry;
		}

		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();

		// Single source of truth — get_selectable_placements() already
		// applies is_available(), show_in_selector() and the site gate.
		// Duplicating those checks here is what let this registry drift
		// from the ad edit metabox.
		if ( ! is_object( $engine ) || ! method_exists( $engine, 'get_selectable_placements' ) ) {
			return $registry;
		}

		foreach ( $engine->get_selectable_placements() as $slug => $placement ) {
			// Don't overwrite an entry a higher-priority filter already
			// provided (mu-plugin, third-party customization, test mock).
			if ( isset( $registry[ $slug ] ) && is_array( $registry[ $slug ] ) ) {
				continue;
			}

			$registry[ $slug ] = array(
				'name'        => $placement->get_name(),
				'description' => $placement->get_description(),
				'group'       => $placement->get_group(),
			);
		}

		return $registry;
	}

	/**
	 * The canonical map. Placement slug => list of format slugs.
	 *
	 * Every entry should include Ad_Formats::RESPONSIVE so responsive
	 * ads can always render — a responsive creative by definition fits
	 * any slot.
	 *
	 * @return array<string, string[]>
	 */
	public static function map() {
		static $map = null;

		if ( null !== $map ) {
			return $map;
		}

		$r = Ad_Formats::RESPONSIVE;

		$map = array(
			// Core placements (free).
			'header'                       => array( 'leaderboard', 'large-leaderboard', 'banner', $r ),
			'footer'                       => array( 'leaderboard', $r ),
			// Keys must be real placement ids: 'content' (before/after the post)
			// and 'after_paragraph'. The old before_content / after_content /
			// paragraph / comment keys matched nothing, so those slots accepted
			// any size.
			'content'                      => array( 'leaderboard', 'medium-rectangle', 'large-rectangle', $r ),
			'after_paragraph'              => array( 'medium-rectangle', 'large-rectangle', $r ),
			'widget'                       => array( 'medium-rectangle', 'skyscraper', 'wide-skyscraper', 'square', $r ),
			'before_archive'               => array( 'leaderboard', $r ),
			'after_archive'                => array( 'leaderboard', $r ),
			'sticky'                       => array( 'mobile-banner', 'mobile-large-banner', $r ),
			'popup'                        => array( 'medium-rectangle', 'large-rectangle', $r ),
			'comments'                     => array( 'medium-rectangle', $r ),
			'shortcode'                    => array( $r ), // inline, author controls the container.

			// BuddyPress.
			'bp_activity'                  => array( 'medium-rectangle', $r ),
			'bp_before_members'            => array( 'leaderboard', 'medium-rectangle', $r ),
			'bp_after_members'             => array( 'leaderboard', 'medium-rectangle', $r ),
			'bp_before_groups'             => array( 'leaderboard', 'medium-rectangle', $r ),
			'bp_after_groups'              => array( 'leaderboard', 'medium-rectangle', $r ),

			// bbPress.
			'bbpress'                      => array( 'leaderboard', 'medium-rectangle', $r ),

			// Jetonomy.
			'jetonomy_sidebar_before'      => array( 'medium-rectangle', 'wide-skyscraper', 'skyscraper', $r ),
			'jetonomy_sidebar_after_about' => array( 'medium-rectangle', 'wide-skyscraper', 'skyscraper', $r ),
			'jetonomy_sidebar_after'       => array( 'medium-rectangle', 'wide-skyscraper', 'skyscraper', $r ),
			'jetonomy_after_post_article'  => array( 'leaderboard', 'medium-rectangle', $r ),
			'jetonomy_before_replies'      => array( 'leaderboard', $r ),
			'jetonomy_between_replies'     => array( 'leaderboard', 'medium-rectangle', $r ),
			'jetonomy_after_replies'       => array( 'leaderboard', $r ),
		);

		/**
		 * Filter the placement -> accepted-formats map.
		 *
		 * Third-party placement authors should hook this filter to
		 * register their slug. Unregistered placements are treated as
		 * permissive (empty accepted_formats = accept anything).
		 *
		 * @since 2.8.1
		 * @param array<string, string[]> $map
		 */
		$map = apply_filters( 'wbam_placement_format_map', $map );

		return $map;
	}

	/**
	 * Apply the map to the wbam_get_placements registry.
	 *
	 * @param array $registry Placement registry (slug => metadata).
	 * @return array Registry with accepted_formats merged in.
	 */
	public static function apply( $registry ) {
		if ( ! is_array( $registry ) ) {
			return $registry;
		}

		$map = self::map();

		foreach ( $registry as $slug => $meta ) {
			if ( ! is_array( $meta ) ) {
				continue;
			}

			// Never overwrite a placement that already declared its own
			// formats (e.g. via Placement_Interface::get_accepted_formats
			// when we later add that method, or via a third-party hook
			// at an earlier priority).
			if ( ! empty( $meta['accepted_formats'] ) ) {
				continue;
			}

			$registry[ $slug ]['accepted_formats'] = isset( $map[ $slug ] ) ? $map[ $slug ] : array();
		}

		return $registry;
	}
}
