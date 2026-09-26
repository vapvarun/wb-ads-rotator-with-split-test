<?php
/**
 * Placement Engine
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\Placements;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use WBAM\Core\Singleton;
use WBAM\Modules\AdTypes\Ad_Type_Interface;
use WBAM\Modules\AdTypes\Image_Ad;
use WBAM\Modules\AdTypes\Rich_Content_Ad;
use WBAM\Modules\AdTypes\Code_Ad;
use WBAM\Modules\AdTypes\AdSense_Ad;
use WBAM\Modules\AdTypes\Email_Capture_Ad;
use WBAM\Modules\Targeting\Targeting_Engine;
use WBAM\Modules\Targeting\Frequency_Manager;

/**
 * Placement Engine class.
 */
class Placement_Engine {

	use Singleton;

	/**
	 * Delivery tiers. A slot goes to the highest tier that has an eligible,
	 * renderable ad; lower tiers only fill slots nothing above can serve.
	 * Pro marks advertiser and campaign ads as paid. Filter
	 * `wbam_ad_delivery_tier` to change an ad's tier.
	 */
	const TIER_SAMPLE = 0;
	const TIER_HOUSE  = 10;
	const TIER_PAID   = 20;

	/**
	 * Registered placements.
	 *
	 * @var array
	 */
	private $placements = array();

	/**
	 * Registered ad types.
	 *
	 * @var array
	 */
	private $ad_types = array();

	/**
	 * Ad IDs already rendered on the current request.
	 *
	 * Used by render_ad() to prevent the same creative firing in
	 * multiple placements on a single page load. Scoped to one request;
	 * resets naturally on the next page view.
	 *
	 * @var array<int,bool>
	 */
	private $rendered_ad_ids = array();

	/**
	 * Initialize.
	 */
	public function init() {
		$this->register_ad_types();
		$this->register_placements();

		foreach ( $this->placements as $placement ) {
			if ( $placement->is_available() ) {
				$placement->register();
			}
		}

		// Clear placement cache when ads are saved/updated/deleted.
		add_action( 'wbam_save_ad_meta', array( $this, 'clear_placement_cache' ) );
		add_action( 'delete_post', array( $this, 'maybe_clear_cache_on_delete' ) );
		add_action( 'trashed_post', array( $this, 'maybe_clear_cache_on_delete' ) );
		// Restoring from trash must invalidate too, or the restored ad stays
		// out of rotation for up to the 5-minute cache TTL. Mirrors the same
		// hook on the ad-count cache (Plugin::init_hooks()).
		add_action( 'untrashed_post', array( $this, 'maybe_clear_cache_on_delete' ) );

		do_action( 'wbam_placements_init', $this );
	}

	/**
	 * Register ad types.
	 */
	private function register_ad_types() {
		$this->register_ad_type( new Image_Ad() );
		$this->register_ad_type( new Rich_Content_Ad() );
		$this->register_ad_type( new Code_Ad() );
		$this->register_ad_type( new AdSense_Ad() );
		$this->register_ad_type( new Email_Capture_Ad() );

		do_action( 'wbam_register_ad_types', $this );
	}

	/**
	 * Register placements.
	 */
	private function register_placements() {
		$this->register_placement( new Header_Placement() );
		$this->register_placement( new Footer_Placement() );
		$this->register_placement( new Content_Placement() );
		$this->register_placement( new Shortcode_Placement() );
		$this->register_placement( new Paragraph_Placement() );
		$this->register_placement( new Widget_Placement() );
		$this->register_placement( new Before_Archive_Placement() );
		$this->register_placement( new After_Archive_Placement() );
		$this->register_placement( new Sticky_Placement() );
		$this->register_placement( new Popup_Placement() );
		$this->register_placement( new Comment_Placement() );

		do_action( 'wbam_register_placements', $this );
	}

	/**
	 * Register an ad type.
	 *
	 * @param Ad_Type_Interface $ad_type Ad type.
	 */
	public function register_ad_type( Ad_Type_Interface $ad_type ) {
		$this->ad_types[ $ad_type->get_id() ] = $ad_type;
	}

	/**
	 * Register a placement.
	 *
	 * @param Placement_Interface $placement Placement.
	 */
	public function register_placement( Placement_Interface $placement ) {
		$this->placements[ $placement->get_id() ] = $placement;

		// If init already ran, register the placement now.
		if ( did_action( 'wbam_placements_init' ) && $placement->is_available() ) {
			$placement->register();
		}
	}

	/**
	 * Get ad type.
	 *
	 * @param string $id Ad type ID.
	 * @return Ad_Type_Interface|null
	 */
	public function get_ad_type( $id ) {
		// Normalize legacy stored spellings to the canonical registered id.
		// The Rich Content handler registers as 'rich-content' but the setup
		// wizard, admin preview, and abilities API all wrote 'rich_content'
		// for years - so every such ad silently rendered as an empty string
		// (the whole creative type was dead, including the sample ad every
		// fresh install ships). PRO's installer migrates stored values, but
		// FREE-only sites never run it, so the lookup itself has to accept
		// the legacy forms. Same alias set as PRO's migration.
		if ( ! isset( $this->ad_types[ $id ] ) && in_array( $id, array( 'rich_content', 'rich', 'content' ), true ) ) {
			$id = 'rich-content';
		}

		return isset( $this->ad_types[ $id ] ) ? $this->ad_types[ $id ] : null;
	}

	/**
	 * Get all ad types.
	 *
	 * @return array
	 */
	public function get_ad_types() {
		return $this->ad_types;
	}

	/**
	 * Get placement.
	 *
	 * @param string $id Placement ID.
	 * @return Placement_Interface|null
	 */
	public function get_placement( $id ) {
		return isset( $this->placements[ $id ] ) ? $this->placements[ $id ] : null;
	}

	/**
	 * Get all placements.
	 *
	 * @return array
	 */
	public function get_placements() {
		return $this->placements;
	}

	/**
	 * Get placements grouped.
	 *
	 * @return array
	 */
	public function get_placements_grouped() {
		$grouped = array();

		foreach ( $this->placements as $placement ) {
			$group = $placement->get_group();
			if ( ! isset( $grouped[ $group ] ) ) {
				$grouped[ $group ] = array();
			}
			$grouped[ $group ][ $placement->get_id() ] = $placement;
		}

		return $grouped;
	}

	/**
	 * Placements an ad may be assigned to on this site.
	 *
	 * Applies, in order: is_available(), show_in_selector(), and the site
	 * allowlist. This is the ONLY method admin UI and the portal registry
	 * may use to build a placement list. Reading $this->placements
	 * directly reintroduces the drift this method exists to remove — the
	 * ad edit metabox and the advertiser portal previously read two
	 * different lists, so filtering one silently missed the other.
	 *
	 * @since 2.11.0
	 * @return Placement_Interface[] Keyed by placement ID.
	 */
	public function get_selectable_placements() {
		$out = array();

		foreach ( $this->placements as $id => $placement ) {
			if ( ! $placement->is_available() || ! $placement->show_in_selector() ) {
				continue;
			}

			if ( ! \WBAM\Core\Settings_Helper::is_placement_open( $id ) ) {
				continue;
			}

			$out[ $id ] = $placement;
		}

		return $out;
	}

	/**
	 * get_selectable_placements() grouped by Placement_Interface::get_group().
	 *
	 * @since 2.11.0
	 * @return array<string, Placement_Interface[]>
	 */
	public function get_selectable_placements_grouped() {
		$grouped = array();

		foreach ( $this->get_selectable_placements() as $id => $placement ) {
			$grouped[ $placement->get_group() ][ $id ] = $placement;
		}

		return $grouped;
	}

	/**
	 * Get ads for a placement.
	 *
	 * Uses object caching to avoid repeated LIKE queries on serialized meta.
	 * Cache is invalidated when ads are saved (via wbam_save_ad_meta action).
	 *
	 * @param string $placement_id Placement ID.
	 * @return array
	 */
	public function get_ads_for_placement( $placement_id ) {
		// Site gate. A slot the admin has closed delivers nothing, so
		// unticking it in Settings actually stops the ads rather than
		// only hiding the checkbox. Checked before the cache lookup so a
		// warm cache cannot serve a closed slot.
		//
		// The advertiser gate is deliberately NOT applied here: closing a
		// slot for sale must never dark-drop a creative an advertiser has
		// already paid for. See plan/ad-slot-control.md §2.
		if ( ! \WBAM\Core\Settings_Helper::is_placement_open( $placement_id ) ) {
			return array();
		}

		// Try to get cached ad IDs for this placement.
		$cache_key = 'wbam_placement_ads_' . sanitize_key( $placement_id );
		$ad_ids    = wp_cache_get( $cache_key, 'wbam' );

		if ( false === $ad_ids ) {
			// Use a more precise LIKE pattern for serialized data.
			// Format: s:X:"placement_id"; where X is the string length.
			$serialized_pattern = sprintf( 's:%d:"%s"', strlen( $placement_id ), $placement_id );

			$args = array(
				'post_type'      => 'wbam-ad',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Cached to mitigate performance impact.
					'relation' => 'AND',
					array(
						'key'     => '_wbam_enabled',
						'value'   => '1',
						'compare' => '=',
					),
					array(
						'key'     => '_wbam_placements',
						'value'   => $serialized_pattern,
						'compare' => 'LIKE',
					),
				),
			);

			$ad_ids = get_posts( $args );

			// Cache for 5 minutes. Invalidated on ad save via clear_placement_cache().
			wp_cache_set( $cache_key, $ad_ids, 'wbam', 5 * MINUTE_IN_SECONDS );
		}

		// One meta query for every candidate instead of one per ad below.
		update_postmeta_cache( $ad_ids );

		/**
		 * Fires with a placement's candidate ads before each one is checked,
		 * so an extension can batch-load what its `wbam_should_display_ad`
		 * callback needs in one query instead of one per ad.
		 *
		 * @since 3.2.0
		 * @param int[]  $ad_ids       Candidate ad IDs.
		 * @param string $placement_id Placement ID.
		 */
		do_action( 'wbam_placement_candidates', $ad_ids, $placement_id );

		// Filter through targeting engine and verify exact placement match.
		$targeting = Targeting_Engine::get_instance();
		$filtered  = array();

		// Phase D of the format-aware matching plan: drop ads whose
		// declared format doesn't match this placement's accepted list.
		// Feature-flagged via Ad Display > Placements > Format matching
		// so sites opt in after they've had a chance to review the
		// backfilled formats on their existing ads. Filterable for
		// A/B testing and per-env control.
		$enforce_format = (bool) apply_filters(
			'wbam_enforce_format_matching',
			\WBAM\Core\Settings_Helper::format_matching_enabled(),
			$placement_id
		);

		foreach ( $ad_ids as $ad_id ) {
			// Some ad types are not served through placements at all - a video
			// ad is played in-stream by the host plugin, never painted into a
			// header or sidebar. The ad edit screen has said so since 2.11.1
			// ("ticking boxes below has no effect"), but this loop never asked,
			// so a video ad with default placements saved against it rendered
			// as a standalone <video> banner on every page (card 10235667764).
			if ( function_exists( 'wbam_ad_uses_placements' ) && ! wbam_ad_uses_placements( $ad_id ) ) {
				continue;
			}

			// Double-check that the placement is actually in the array (prevents false LIKE matches).
			$placements = get_post_meta( $ad_id, '_wbam_placements', true );
			if ( ! is_array( $placements ) || ! in_array( $placement_id, $placements, true ) ) {
				continue;
			}

			if ( $enforce_format && function_exists( 'wbam_ad_fits_placement' ) ) {
				if ( ! wbam_ad_fits_placement( $ad_id, $placement_id ) ) {
					continue;
				}
			}

			if ( $targeting->should_display( $ad_id ) ) {
				$filtered[] = $ad_id;
			}
		}

		/**
		 * Filter the ads eligible for a placement, before a winner is picked.
		 *
		 * Eligibility rules that depend on the placement belong here, not on
		 * `wbam_ads_for_placement`: dropping the winner after the draw blanks
		 * the slot instead of letting the next eligible ad fill it.
		 *
		 * @since 3.2.0
		 * @param int[]  $filtered     Ad IDs that passed targeting.
		 * @param string $placement_id Placement ID.
		 */
		$filtered = array_values( (array) apply_filters( 'wbam_ads_eligible_for_placement', $filtered, $placement_id ) );

		// Delivery tier first (paid, then house, then sample), then priority.
		$tiers = array();
		foreach ( $filtered as $ad_id ) {
			$tiers[ $ad_id ] = $this->get_delivery_tier( $ad_id, $placement_id );
		}

		usort(
			$filtered,
			function ( $a, $b ) use ( $tiers ) {
				if ( $tiers[ $a ] !== $tiers[ $b ] ) {
					return $tiers[ $b ] - $tiers[ $a ];
				}

				// Default priority is 5 if not set. Higher priority first.
				$priority_a = (int) get_post_meta( $a, '_wbam_priority', true );
				$priority_b = (int) get_post_meta( $b, '_wbam_priority', true );

				return ( $priority_b ? $priority_b : 5 ) - ( $priority_a ? $priority_a : 5 );
			}
		);

		// Slot policy: fixed-inventory placements render AT MOST ONE ad
		// per hook invocation. When multiple advertisers target the same
		// slot, we pick one winner rather than stacking every creative
		// (which would give one advertiser visibility and the other a pixel
		// below them - not what either of them paid for).
		//
		// Placements that legitimately render multiple ads per page
		// (widget areas, between_replies with frequency counters) can
		// opt out per-placement via the wbam_placement_render_mode filter
		// returning 'stack' for their slug.
		$render_mode = apply_filters( 'wbam_placement_render_mode', 'rotate', $placement_id );

		if ( count( $filtered ) > 1 ) {
			// The highest tier with a renderable ad wins the slot; a lower
			// tier only fills it when nothing above can serve. Rotate picks
			// one winner from that tier, stack keeps all of its ads.
			$pools = array();
			foreach ( $filtered as $ad_id ) {
				$pools[ $tiers[ $ad_id ] ][] = (int) $ad_id;
			}
			krsort( $pools );

			$winners = array();
			foreach ( $pools as $tier => $pool ) {
				$pool    = array_values( array_unique( $pool ) );
				$winners = 'rotate' === $render_mode
					? array_filter( array( $this->pick_winner( $pool, $placement_id, (int) $tier ) ) )
					: array_values( array_filter( $pool, array( $this, 'ad_is_renderable' ) ) );
				if ( $winners ) {
					break;
				}
			}

			$filtered = array_values( $winners );
		}

		/**
		 * Filter the ads returned for a placement.
		 *
		 * @since 2.3.0
		 * @param array  $filtered     Array of ad IDs that passed targeting.
		 * @param string $placement_id Placement ID.
		 * @param array  $ad_ids       Original array of ad IDs before targeting.
		 */
		return apply_filters( 'wbam_ads_for_placement', $filtered, $placement_id, $ad_ids );
	}

	/**
	 * Delivery tier of an ad in a placement.
	 *
	 * Plugin sample ads never outrank the owner's own ads; everything else
	 * is a house ad until an extension (Pro) marks it paid.
	 *
	 * @since 3.2.0
	 * @param int    $ad_id        Ad ID.
	 * @param string $placement_id Placement ID.
	 * @return int One of the TIER_* constants, or any int from the filter.
	 */
	public function get_delivery_tier( $ad_id, $placement_id ) {
		$is_sample = get_post_meta( $ad_id, '_wbam_is_demo', true ) || get_post_meta( $ad_id, '_wbam_sample_ad', true );

		/**
		 * Filter an ad's delivery tier. Higher tiers win the slot first.
		 *
		 * @since 3.2.0
		 * @param int    $tier         Placement_Engine::TIER_SAMPLE, TIER_HOUSE or TIER_PAID.
		 * @param int    $ad_id        Ad ID.
		 * @param string $placement_id Placement ID.
		 */
		return (int) apply_filters( 'wbam_ad_delivery_tier', $is_sample ? self::TIER_SAMPLE : self::TIER_HOUSE, $ad_id, $placement_id );
	}

	/**
	 * The rel attribute for an ad's click-through link.
	 *
	 * Paid ads carry rel="sponsored" (Google requires paid links to be
	 * qualified); the owner's house and sample ads do not.
	 *
	 * @since 3.2.0
	 * @param int $ad_id Ad ID.
	 * @return string
	 */
	public function get_ad_link_rel( $ad_id ) {
		$rel = $this->get_delivery_tier( $ad_id, '' ) >= self::TIER_PAID ? 'sponsored noopener' : 'noopener';

		/**
		 * Filter the rel attribute of an ad's click-through link. Return
		 * 'sponsored noopener' to mark a house ad as paid.
		 *
		 * @since 3.2.0
		 * @param string $rel   Space-separated rel tokens.
		 * @param int    $ad_id Ad ID.
		 */
		return (string) apply_filters( 'wbam_ad_link_rel', $rel, $ad_id );
	}

	/**
	 * Pick one renderable ad from a pool of same-tier ads.
	 *
	 * Fill-fallback: when the pick cannot render right now (already shown
	 * elsewhere on this page, broken creative) it leaves the pool and the
	 * next pick is tried, so the slot stays full while any option remains.
	 *
	 * @param int[]  $pool         Ad IDs of one tier.
	 * @param string $placement_id Placement ID.
	 * @param int    $tier         The pool's tier.
	 * @return int|null
	 */
	private function pick_winner( array $pool, $placement_id, $tier ) {
		$frequency = Frequency_Manager::get_instance();

		while ( ! empty( $pool ) ) {
			/**
			 * Choose the winner from a pool of same-tier ads. Return an ID
			 * from the pool, or null for the default priority-weighted draw.
			 *
			 * @since 3.2.0
			 * @param int|null $pick         Chosen ad ID.
			 * @param int[]    $pool         Candidate ad IDs.
			 * @param string   $placement_id Placement ID.
			 * @param int      $tier         The pool's delivery tier.
			 */
			$candidate = apply_filters( 'wbam_rotation_pick', null, $pool, $placement_id, $tier );
			if ( ! in_array( (int) $candidate, $pool, true ) ) {
				$candidate = $frequency->get_weighted_random( $pool );
			}
			if ( null === $candidate ) {
				return null;
			}

			if ( $this->ad_is_renderable( (int) $candidate ) ) {
				return (int) $candidate;
			}

			$pool = array_values( array_diff( $pool, array( (int) $candidate ) ) );
		}

		return null;
	}

	/**
	 * Render an ad.
	 *
	 * @param int   $ad_id   Ad ID.
	 * @param array $options Options.
	 * @return string
	 */
	public function render_ad( $ad_id, $options = array() ) {
		$ad_id = (int) $ad_id;

		$enabled = get_post_meta( $ad_id, '_wbam_enabled', true );
		if ( ! $enabled ) {
			return '';
		}

		/**
		 * Per-creative page cap: an ad renders at most once per request
		 * regardless of how many placements it targets. Prevents the
		 * "same ad 6 times on one page" experience when an advertiser
		 * enables every compatible placement on a single creative.
		 *
		 * Bypass by setting $options['allow_duplicate'] = true (reserved
		 * for surfaces that legitimately need the same ad twice, e.g.
		 * preview screens).
		 *
		 * The cap is also filterable so site owners can opt out per
		 * placement (e.g. sticky bar that must always show).
		 *
		 * @since 2.8.0
		 */
		$allow_duplicate = ! empty( $options['allow_duplicate'] );
		$enforce_cap     = apply_filters( 'wbam_enforce_page_cap', ! $allow_duplicate, $ad_id, $options );

		if ( $enforce_cap && isset( $this->rendered_ad_ids[ $ad_id ] ) ) {
			return '';
		}

		// Full delivery gate on EVERY render path. Placement selection
		// already filters through Targeting_Engine::should_display(), but
		// by-id surfaces (the [wbam_ad] / [wbam_ads] shortcodes) used to
		// reach here directly - honouring _wbam_enabled above while
		// silently ignoring schedule windows, impression caps, session
		// limits, geo, and the wbam_should_display_ad filter. An expired
		// seasonal creative dropped into a post with a shortcode kept
		// serving years past its end date. Admin/preview surfaces are
		// exempt (an owner must be able to preview a scheduled ad), and
		// callers that already gated can pass skip_targeting to avoid the
		// re-check.
		if ( empty( $options['skip_targeting'] ) && ! is_admin() ) {
			$targeting = \WBAM\Modules\Targeting\Targeting_Engine::get_instance();
			if ( ! $targeting->should_display( $ad_id ) ) {
				return '';
			}
		}

		$data    = get_post_meta( $ad_id, '_wbam_ad_data', true );
		$ad_type = isset( $data['type'] ) ? $data['type'] : '';

		$handler = $this->get_ad_type( $ad_type );
		if ( ! $handler ) {
			return '';
		}

		$output    = $handler->render( $ad_id, $options );
		$placement = isset( $options['placement'] ) ? $options['placement'] : '';

		// Track only renders that actually produced output — an empty
		// string (disabled handler, missing image URL) should not count
		// as "shown" and block the ad from appearing elsewhere.
		if ( $enforce_cap && '' !== $output ) {
			$this->rendered_ad_ids[ $ad_id ] = true;
		}

		// Wrap every rendered ad in a standard container carrying BOTH
		// the .wbam-ad-slot class (responsive rules: max-width:100%,
		// aspect-ratio preservation) and the canonical .wbam-ad class,
		// which the frontend contract depends on: frontend.js attaches
		// the click-tracking listener to .wbam-ad[data-ad-id], and
		// frontend.css applies the base/print/focus rules to .wbam-ad.
		// No ad-type handler emits .wbam-ad itself, so this wrapper is
		// the single source of that class.
		if ( '' !== $output ) {
			$is_responsive = '1' === (string) get_post_meta( $ad_id, '_wbam_is_responsive', true );
			$classes       = 'wbam-ad wbam-ad-slot';
			if ( $is_responsive ) {
				$classes .= ' wbam-ad-slot--responsive';
			}
			/**
			 * Filter the extra CSS class added to every rendered ad's
			 * container wrapper.
			 *
			 * Plug and play (owner decision, card 10343726590): no Settings
			 * UI field any more. A site's already-stored `container_class`
			 * is this filter's default, so nothing changes silently; a
			 * developer who wants a class without a field to click uses
			 * this filter instead.
			 *
			 * @since 3.2.0
			 * @param string $container_class Extra class, or '' for none.
			 */
			$container_class = sanitize_html_class( (string) apply_filters( 'wbam_ad_container_class', \WBAM\Core\Settings_Helper::get( 'container_class', '' ) ) );
			if ( '' !== $container_class ) {
				$classes .= ' ' . $container_class;
			}

			// Ad-disclosure label. The `ad_label` / `ad_label_position` settings
			// and the .wbam-ad-label-{above,below} CSS shipped, but nothing ever
			// emitted the markup — so the disclosure a site owner configured
			// never appeared. Render it here (the single wrapping point) when a
			// non-empty label is set.
			// Default to a disclosure until the owner saves one; a label they
			// clear on purpose is stored as '' and still wins.
			$label_text = trim( (string) \WBAM\Core\Settings_Helper::get( 'ad_label', __( 'Advertisement', 'wb-ads-rotator-with-split-test' ) ) );
			$label_pos  = 'below' === \WBAM\Core\Settings_Helper::get( 'ad_label_position', 'above' ) ? 'below' : 'above';
			$label_html = '';
			if ( '' !== $label_text ) {
				$label_html = sprintf(
					'<span class="wbam-ad-label wbam-ad-label-%1$s">%2$s</span>',
					esc_attr( $label_pos ),
					esc_html( $label_text )
				);
			}

			$inner = 'below' === $label_pos
				? $output . $label_html
				: $label_html . $output;

			// Viewable impressions: the beacon frontend.js sends once the ad
			// has been seen, in place of the render-time count.
			$viewable = \WBAM\Frontend\Frontend::defers_impression( $ad_id, $placement )
				? ' data-wbam-viewable="' . esc_url( \WBAM\Frontend\Frontend::viewable_beacon_url( $ad_id, $placement ) ) . '"'
				: '';

			$output = sprintf(
				'<div class="%1$s" data-ad-id="%2$d" data-responsive="%3$s"%4$s>%5$s</div>',
				esc_attr( $classes ),
				$ad_id,
				$is_responsive ? '1' : '0',
				( $placement ? ' data-placement="' . esc_attr( $placement ) . '"' : '' ) . $viewable,
				$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ad rendered upstream by ad type handler; label escaped above.
			);
		}

		/**
		 * Filter the ad output HTML.
		 *
		 * @since 1.0.0
		 * @param string $output    Ad output HTML.
		 * @param int    $ad_id     Ad ID.
		 * @param string $placement Placement ID.
		 */
		return apply_filters( 'wbam_ad_output', $output, $ad_id, $placement );
	}

	/**
	 * Render every ad assigned to a placement, wrapped in the standard
	 * `.wbam-placement` container.
	 *
	 * Single source for "render a whole placement slot" — used by
	 * Before/After Archive Placement (both the classic-theme hook and the
	 * block-safe `render_block_core/query` filter) and the `wb-ads/placement`
	 * block, so all three can't drift into different markup.
	 *
	 * @since 3.2.0
	 * @param string $placement_id Placement ID.
	 * @return string HTML, or '' if the placement has nothing to show.
	 */
	public function render_placement( $placement_id ) {
		$ads = $this->get_ads_for_placement( $placement_id );

		if ( empty( $ads ) ) {
			return '';
		}

		// CSS class suffix uses hyphens (matches every existing placement's
		// hand-written wrapper - e.g. `before_archive` -> `wbam-placement-before-archive`),
		// while $placement_id itself (data attrs, get_ads_for_placement()) keeps underscores.
		$css_suffix = sanitize_html_class( str_replace( '_', '-', $placement_id ) );

		$html = '<div class="wbam-placement wbam-placement-' . esc_attr( $css_suffix ) . '">';
		foreach ( $ads as $ad_id ) {
			$html .= $this->render_ad( $ad_id, array( 'placement' => $placement_id ) );
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Clear placement cache when an ad is saved.
	 *
	 * Clears cache for all placements the ad uses, ensuring fresh
	 * query results after ad configuration changes.
	 *
	 * @since 2.3.1
	 *
	 * @param int $ad_id Ad post ID.
	 */
	public function clear_placement_cache( $ad_id ) {
		$placements = get_post_meta( $ad_id, '_wbam_placements', true );

		if ( ! empty( $placements ) && is_array( $placements ) ) {
			foreach ( $placements as $placement_id ) {
				$cache_key = 'wbam_placement_ads_' . sanitize_key( $placement_id );
				wp_cache_delete( $cache_key, 'wbam' );
			}
		}

		// Also clear cache for all registered placements in case ad was removed from some.
		foreach ( array_keys( $this->placements ) as $placement_id ) {
			$cache_key = 'wbam_placement_ads_' . sanitize_key( $placement_id );
			wp_cache_delete( $cache_key, 'wbam' );
		}
	}

	/**
	 * Clear placement cache when an ad is deleted or trashed.
	 *
	 * @since 2.3.1
	 *
	 * @param int $post_id Post ID.
	 */
	public function maybe_clear_cache_on_delete( $post_id ) {
		if ( 'wbam-ad' === get_post_type( $post_id ) ) {
			$this->clear_placement_cache( $post_id );
		}
	}

	/**
	 * Cheap renderability probe used by Phase I.1 fill-fallback.
	 *
	 * Returns false when render_ad() would short-circuit on this
	 * request — currently that's the per-page dedup case (the ad
	 * already rendered in another slot during this page load) and
	 * the disabled-ad case. Format / session / package gates were
	 * already applied by the caller, so we don't re-check them here.
	 *
	 * Pure read; no side effects on the page-cap registry.
	 *
	 * @since 2.8.1
	 * @param int $ad_id Ad post ID.
	 * @return bool
	 */
	public function ad_is_renderable( $ad_id ) {
		$ad_id = (int) $ad_id;
		if ( $ad_id <= 0 ) {
			return false;
		}

		if ( isset( $this->rendered_ad_ids[ $ad_id ] ) ) {
			return false;
		}

		$enabled = get_post_meta( $ad_id, '_wbam_enabled', true );
		if ( ! $enabled ) {
			return false;
		}

		// Creative-health probe: an enabled ad whose creative cannot render
		// (image deleted from the media library, required field empty) must
		// lose the slot to a healthy competitor, not blank it. Kept cheap by
		// design - types opt in via has_creative(), which checks required
		// fields without rendering; types without the method are assumed
		// healthy, exactly as before.
		$data    = get_post_meta( $ad_id, '_wbam_ad_data', true );
		$type_id = isset( $data['type'] ) ? $data['type'] : '';
		$handler = $this->get_ad_type( $type_id );
		if ( $handler && method_exists( $handler, 'has_creative' ) && ! $handler->has_creative( $ad_id ) ) {
			return false;
		}

		return true;
	}
}
