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
	 * Per-request memoization for shapes()/map() — see reset_cache().
	 *
	 * @var array<string, string[]>|null
	 */
	private static $shapes_cache = null;

	/**
	 * @var array<string, string[]>|null
	 */
	private static $map_cache = null;

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
	 * Shape taxonomy (owner decision 13, card 10343726460, 3.2.0).
	 *
	 * Groups the pixel-format taxonomy into the shapes an advertiser
	 * actually thinks in: a slim strip (Banner), a tall strip's oversize
	 * sibling (Billboard), a squarish box (Box), or a tall column (Tower).
	 * `map()` below composes each placement's accepted_formats from one or
	 * more of these lists instead of hand-picking format slugs per
	 * placement, so every Banner placement gets every Banner size (the
	 * header used to accept only 3 of the 6 IAB banner sizes).
	 *
	 * Billboard (970x250) is its OWN shape, split out from Banner on a
	 * later owner decision: reserving a fixed 250px box for every Banner
	 * placement (to fit a 970x250) left ~160px of dead space around a
	 * 728x90 header ad. Banner now reserves only 90px (its own tallest
	 * size); Billboard is sold only where a taller box is already
	 * reserved anyway — the between-content placements (see map()).
	 *
	 * 'square' (250x250) rides along with Box: the QA proposal's Box row
	 * lists it alongside 300x250/336x280, it is a standard IAB/AdSense
	 * size, and the plugin's own demo data already sells one in a Box
	 * placement — narrowing to exactly two sizes would just break that
	 * demo ad. Override via the `wbam_placement_shapes` filter if a site
	 * wants the stricter two-size list.
	 *
	 * @since 3.2.0
	 * @return array<string, string[]> Shape name => format slugs.
	 */
	public static function shapes() {
		if ( null !== self::$shapes_cache ) {
			return self::$shapes_cache;
		}

		$shapes = array(
			'banner'    => array( 'leaderboard', 'large-leaderboard', 'banner', 'mobile-banner', 'mobile-large-banner' ),
			'billboard' => array( 'billboard' ),
			'box'       => array( 'medium-rectangle', 'large-rectangle', 'square' ),
			'tower'     => array( 'skyscraper', 'wide-skyscraper' ),
		);

		/**
		 * Filter the shape -> format-slugs map.
		 *
		 * @since 3.2.0
		 * @param array<string, string[]> $shapes
		 */
		$shapes = apply_filters( 'wbam_placement_shapes', $shapes );

		self::$shapes_cache = $shapes;

		return $shapes;
	}

	/**
	 * Resolve the format slugs for one or more shape names.
	 *
	 * @since 3.2.0
	 * @param string[] $shape_names One or more of 'banner', 'box', 'tower'.
	 * @return string[] De-duplicated format slugs. Unknown shape names are ignored.
	 */
	private static function shape_formats( array $shape_names ) {
		$shapes = self::shapes();
		$out    = array();

		foreach ( $shape_names as $name ) {
			if ( isset( $shapes[ $name ] ) && is_array( $shapes[ $name ] ) ) {
				$out = array_merge( $out, $shapes[ $name ] );
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * The canonical map. Placement slug => list of format slugs.
	 *
	 * Every entry should include Ad_Formats::RESPONSIVE so responsive
	 * ads can always render — a responsive creative by definition fits
	 * any slot.
	 *
	 * Header, footer, before/after archive and sticky take the Banner
	 * shape only; the sidebar takes Box or Tower (owner decision 13).
	 * Billboard (970x250) is sold only through the between-content
	 * placements — 'content' (before/after the post) and
	 * 'after_paragraph' — alongside whatever they already accepted
	 * (Banner+Box for 'content', Box for 'after_paragraph'), because those
	 * are the only slots where a taller reserved box (see frontend.css)
	 * doesn't leave dead space around a strip-shaped Banner ad. Composed
	 * from shape_formats() rather than hand-picked slugs so a shape's size
	 * list only needs updating in one place (shapes(), above).
	 *
	 * @return array<string, string[]>
	 */
	public static function map() {
		if ( null !== self::$map_cache ) {
			return self::$map_cache;
		}

		$r                    = Ad_Formats::RESPONSIVE;
		$banner               = array_merge( self::shape_formats( array( 'banner' ) ), array( $r ) );
		$box                  = array_merge( self::shape_formats( array( 'box' ) ), array( $r ) );
		$box_tower            = array_merge( self::shape_formats( array( 'box', 'tower' ) ), array( $r ) );
		$banner_box           = array_merge( self::shape_formats( array( 'banner', 'box' ) ), array( $r ) );
		$banner_box_billboard = array_merge( self::shape_formats( array( 'banner', 'box', 'billboard' ) ), array( $r ) );
		$box_billboard        = array_merge( self::shape_formats( array( 'box', 'billboard' ) ), array( $r ) );

		$map = array(
			// Core placements (free).
			'header'                       => $banner,
			'footer'                       => $banner,
			// Keys must be real placement ids: 'content' (before/after the post)
			// and 'after_paragraph'. The old before_content / after_content /
			// paragraph / comment keys matched nothing, so those slots accepted
			// any size. 'content' takes Banner, Box AND Billboard — a
			// before/after-post strip can run full-width (Banner or
			// Billboard) or as an in-flow rectangle (Box).
			'content'                      => $banner_box_billboard,
			'after_paragraph'              => $box_billboard,
			'widget'                       => $box_tower, // Sidebar: Box or Tower.
			'before_archive'               => $banner,
			'after_archive'                => $banner,
			'sticky'                       => $banner,
			'popup'                        => $box,
			'comments'                     => $box,
			'shortcode'                    => array( $r ), // inline, author controls the container.

			// BuddyPress.
			'bp_activity'                  => $box,
			'bp_before_members'            => $banner,
			'bp_after_members'             => $banner,
			'bp_before_groups'             => $banner,
			'bp_after_groups'              => $banner,

			// bbPress. Not a "between-content" slot in the owner's sense
			// (a single list-position strip, not a taller reserved box),
			// so no Billboard here.
			'bbpress'                      => $banner_box,

			// Jetonomy.
			'jetonomy_sidebar_before'      => $box_tower,
			'jetonomy_sidebar_after_about' => $box_tower,
			'jetonomy_sidebar_after'       => $box_tower,
			'jetonomy_after_post_article'  => $banner_box,
			'jetonomy_before_replies'      => $banner,
			'jetonomy_between_replies'     => $box,
			'jetonomy_after_replies'       => $banner,
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

		self::$map_cache = $map;

		return $map;
	}

	/**
	 * Clear the per-request memoization of shapes()/map(). Production code
	 * never needs this — filters are registered once, before either method
	 * is first called, so the cache is correct for the life of a request.
	 * It exists for tests that add/remove `wbam_placement_shapes` or
	 * `wbam_placement_format_map` filters mid-run and need the next call
	 * to recompute rather than reuse an earlier test's cached result.
	 *
	 * @since 3.2.0
	 */
	public static function reset_cache() {
		self::$shapes_cache = null;
		self::$map_cache    = null;
	}

	/**
	 * List existing ads that don't fit at least one of their assigned
	 * placements under the shape map, for the "existing sites" opt-in
	 * notice (owner decision 13). Read-only — never changes an ad or its
	 * placements.
	 *
	 * A responsive ad never mismatches (Ad_Formats::fits() short-circuits
	 * true for it), so this only ever lists ads with a resolved fixed
	 * shape/size.
	 *
	 * @since 3.2.0
	 * @param int $limit Maximum number of ad titles to return. 0 = count only.
	 * @return array{count:int, titles:string[]} Total mismatched ads and up to $limit titles.
	 */
	public static function get_mismatched_ads( $limit = 20 ) {
		$ads = get_posts(
			array(
				'post_type'              => 'wbam-ad',
				'post_status'            => array( 'publish', 'draft' ),
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$count  = 0;
		$titles = array();

		foreach ( (array) $ads as $ad_id ) {
			$placements = get_post_meta( $ad_id, '_wbam_placements', true );
			if ( ! is_array( $placements ) || empty( $placements ) ) {
				continue;
			}

			$mismatched = false;
			foreach ( $placements as $placement_id ) {
				if ( ! Ad_Formats::fits( $ad_id, (string) $placement_id ) ) {
					$mismatched = true;
					break;
				}
			}

			if ( $mismatched ) {
				++$count;
				if ( count( $titles ) < $limit ) {
					$titles[] = get_the_title( $ad_id );
				}
			}
		}

		return array(
			'count'  => $count,
			'titles' => $titles,
		);
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
