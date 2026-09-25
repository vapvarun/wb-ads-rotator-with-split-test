<?php
/**
 * Frequency Manager
 *
 * @package WB_Ad_Manager
 * @since   1.1.0
 */

namespace WBAM\Modules\Targeting;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use WBAM\Core\Singleton;
use WBAM\Admin\Settings;

/**
 * Frequency Manager class.
 */
class Frequency_Manager {

	use Singleton;

	/**
	 * Cookie holding this visitor's views today, ad id => views.
	 *
	 * Only ads that some cap reads are counted, and the count lives only in
	 * this cookie: no per-view rows in wp_options.
	 */
	const COOKIE_NAME = 'wbam_ad_views';

	/**
	 * Total lifetime impressions this ad may be delivered. 0/empty = unlimited.
	 *
	 * Distinct from `_wbam_session_limit`, which caps how many times ONE visitor
	 * sees the ad per day. This caps the ad across the whole site and every
	 * visitor — "run this creative 5,000 times, then stop".
	 */
	const CAP_META = '_wbam_impression_cap';

	/**
	 * Impressions delivered so far against CAP_META.
	 *
	 * Deliberately its own counter rather than a read of the analytics tables.
	 * Analytics recording is conditional — it is skipped when analytics are
	 * disabled, when `track_logged_in` is off, for bots, and without GDPR
	 * consent — so on a members-only site an analytics-derived count can sit at
	 * near zero while the ad has in fact been delivered thousands of times. A
	 * cap that under-counts silently over-delivers, which is the one failure
	 * mode an advertiser will notice.
	 */
	const COUNT_META = '_wbam_impression_count';

	/**
	 * Ads shown on current page.
	 *
	 * @var array
	 */
	private $page_ads = array();

	/**
	 * Initialize.
	 */
	public function init() {
		// Hook to track impressions when ads are rendered.
		add_filter( 'wbam_ad_output', array( $this, 'on_ad_output' ), 5, 2 );

		// Set cookie in footer with all tracked impressions.
		add_action( 'wp_footer', array( $this, 'set_view_cookie' ), 999 );
	}

	/**
	 * Callback for wbam_ad_output filter to track frequency.
	 *
	 * @since 2.3.3
	 *
	 * @param string $output Ad HTML output.
	 * @param int    $ad_id  Ad ID.
	 * @return string Unchanged output.
	 */
	public function on_ad_output( $output, $ad_id ) {
		// Only track if ad actually has output (was rendered).
		if ( ! empty( $output ) && ! empty( $ad_id ) ) {
			$this->track_impression( $ad_id );
			$this->record_delivery( $ad_id );
		}
		return $output;
	}

	/**
	 * Count one delivered impression against the ad's total cap.
	 *
	 * Call this once per impression that actually reached a visitor. Surfaces
	 * that render server-side are handled by on_ad_output(); surfaces that
	 * decide client-side (in-stream video ads, for example) call this when the
	 * creative actually starts.
	 *
	 * No cap set means no write, so uncapped ads cost nothing extra.
	 *
	 * @param int $ad_id Ad ID.
	 * @return void
	 */
	public function record_delivery( $ad_id ) {
		global $wpdb;

		$ad_id = (int) $ad_id;
		$cap   = $this->get_cap( $ad_id );

		if ( $ad_id <= 0 || $cap <= 0 ) {
			return;
		}

		// Increment in SQL rather than read-modify-write.
		//
		// Ads are delivered concurrently, and `get + 1 then update` loses
		// writes when two impressions land at once: both read the same value
		// and both store the same increment. Measured on a seeded run, that
		// under-counted by roughly half, which means an advertiser would be
		// served about twice the impressions they paid for before the cap
		// noticed. A single UPDATE is atomic, so simultaneous impressions each
		// count exactly once.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic counter; an ORM read-modify-write is the bug being fixed.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s",
				$ad_id,
				self::COUNT_META
			)
		);

		// No row yet — seed it. add_post_meta() with $unique guards the race
		// where two requests both find it missing.
		if ( ! $updated ) {
			if ( ! add_post_meta( $ad_id, self::COUNT_META, 1, true ) ) {
				// Someone else created it first; apply our increment to theirs.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See above.
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s",
						$ad_id,
						self::COUNT_META
					)
				);
			}
		}

		wp_cache_delete( $ad_id, 'post_meta' );

		$delivered = $this->get_delivered( $ad_id );

		if ( $delivered >= $cap ) {
			/**
			 * Fired the moment an ad reaches its total impression cap.
			 *
			 * @param int $ad_id     Ad ID.
			 * @param int $delivered Impressions delivered.
			 */
			do_action( 'wbam_ad_cap_reached', $ad_id, $delivered );
		}
	}

	/**
	 * Claim one impression against the ad's total cap, atomically.
	 *
	 * The difference from record_delivery() is that this REFUSES when the cap
	 * is already spent, and says so. record_delivery() always counts, which is
	 * right for a surface that has already rendered the ad server-side — the
	 * visitor has seen it, so the only honest thing left is to count it.
	 *
	 * Surfaces that decide client-side need the opposite: they ask before
	 * playing, and skip when the answer is no. An in-stream video player picks
	 * its break plan at page render and then plays those breaks over the next
	 * several minutes, so "was it under cap at render time" is not the same
	 * question as "is it under cap now". Without a claim, one viewer sitting
	 * through four breaks delivers four impressions against a cap that may
	 * have been spent at the first one.
	 *
	 * The check and the increment are a single UPDATE so concurrent viewers
	 * cannot both pass a `get < cap` test and then both increment. Exactly one
	 * of them gets the last impression.
	 *
	 * @param int $ad_id Ad ID.
	 * @return bool True when the impression may be shown and has been counted.
	 */
	public function claim_delivery( $ad_id ) {
		global $wpdb;

		$ad_id = (int) $ad_id;

		if ( $ad_id <= 0 ) {
			return false;
		}

		$cap = $this->get_cap( $ad_id );

		// Uncapped ads are always claimable and keep no counter, matching
		// record_delivery(): an unlimited ad costs no writes.
		if ( $cap <= 0 ) {
			return true;
		}

		// The counter row must exist for the conditional UPDATE to match. When
		// a cap is set through the admin this has already happened via
		// seed_delivered(); this covers caps set by code or by import.
		if ( '' === (string) get_post_meta( $ad_id, self::COUNT_META, true ) ) {
			$this->seed_delivered( $ad_id );
		}

		// Increment only while still under cap. Rows affected tells us whether
		// we won the impression: 0 means the cap was already spent.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic check-and-increment; a read-then-write here is the race being closed.
		$granted = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = CAST( meta_value AS UNSIGNED ) + 1
				 WHERE post_id = %d AND meta_key = %s AND CAST( meta_value AS UNSIGNED ) < %d",
				$ad_id,
				self::COUNT_META,
				$cap
			)
		);

		wp_cache_delete( $ad_id, 'post_meta' );

		if ( $granted < 1 ) {
			return false;
		}

		if ( $this->get_delivered( $ad_id ) >= $cap ) {
			/** This action is documented in includes/Modules/Targeting/class-frequency-manager.php */
			do_action( 'wbam_ad_cap_reached', $ad_id, $this->get_delivered( $ad_id ) );
		}

		return true;
	}

	/**
	 * Count one per-visitor view of an ad without needing the page footer.
	 *
	 * set_view_cookie() runs on `wp_footer` and is how server-rendered ads
	 * record that this visitor has now seen them. An AJAX request has no
	 * footer, but its headers are still open, so it sets the cookie directly.
	 *
	 * @param int $ad_id Ad ID.
	 * @return void
	 */
	public function record_session_view( $ad_id ) {
		$ad_id = (int) $ad_id;

		if ( $ad_id <= 0 || headers_sent() || ! $this->counts_views( $ad_id ) ) {
			return;
		}

		$data           = $this->get_cookie_data();
		$data[ $ad_id ] = ( isset( $data[ $ad_id ] ) ? $data[ $ad_id ] : 0 ) + 1;
		$value          = (string) wp_json_encode( $data );

		setcookie( self::COOKIE_NAME, $value, $this->cookie_expiry(), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), false );
		$_COOKIE[ self::COOKIE_NAME ] = $value;
	}

	/**
	 * Whether this visitor's views of an ad need counting at all.
	 *
	 * Only when some cap reads the count: the ad's own daily limit, or
	 * anything hooked on the filter. Uncapped ads cost nothing.
	 *
	 * @param int $ad_id Ad ID.
	 * @return bool
	 */
	public function counts_views( $ad_id ) {
		$counts = (int) get_post_meta( (int) $ad_id, '_wbam_session_limit', true ) > 0;

		/**
		 * Filters whether visitor views of an ad are counted.
		 *
		 * Return true when a cap outside the ad's own daily limit reads
		 * get_ad_views() for this ad.
		 *
		 * @since 3.2.0
		 * @param bool $counts Whether the ad has its own daily limit.
		 * @param int  $ad_id  Ad ID.
		 */
		return (bool) apply_filters( 'wbam_count_visitor_views', $counts, (int) $ad_id );
	}

	/**
	 * When today's view counts expire: midnight in the site's timezone.
	 *
	 * @return int Unix timestamp.
	 */
	private function cookie_expiry() {
		return ( new \DateTimeImmutable( 'tomorrow', wp_timezone() ) )->getTimestamp();
	}

	/**
	 * Impressions this ad may still deliver, or null when uncapped.
	 *
	 * Lets a caller that is planning several impressions up front — an
	 * in-stream break plan, say — avoid scheduling more of one creative than
	 * its remaining allowance can cover.
	 *
	 * @param int $ad_id Ad ID.
	 * @return int|null Remaining impressions, or null for unlimited.
	 */
	public function remaining_cap( $ad_id ) {
		$cap = $this->get_cap( $ad_id );

		if ( $cap <= 0 ) {
			return null;
		}

		return max( 0, $cap - $this->get_delivered( $ad_id ) );
	}

	/**
	 * Total impression cap for an ad. 0 = unlimited.
	 *
	 * @param int $ad_id Ad ID.
	 * @return int
	 */
	public function get_cap( $ad_id ) {
		return max( 0, (int) get_post_meta( (int) $ad_id, self::CAP_META, true ) );
	}

	/**
	 * Impressions delivered against the cap.
	 *
	 * @param int $ad_id Ad ID.
	 * @return int
	 */
	public function get_delivered( $ad_id ) {
		return max( 0, (int) get_post_meta( (int) $ad_id, self::COUNT_META, true ) );
	}

	/**
	 * Impressions this ad already served before it had a cap.
	 *
	 * Ads are usually already running when someone decides to cap them. Starting
	 * the counter at zero would hand a creative that has served 3,000
	 * impressions a fresh allowance of 5,000 and deliver 8,000 in total, which
	 * is precisely the over-delivery the cap exists to prevent. So the first
	 * time a cap is set we seed the counter from recorded history.
	 *
	 * This is a lower bound, not a true total: analytics recording is skipped
	 * for bots, for logged-in visitors when `track_logged_in` is off, and
	 * without GDPR consent. Under-counting history is the safe direction - it
	 * can only make the ad run longer than its true remaining allowance, never
	 * cut it short below what the advertiser paid for.
	 *
	 * @param int $ad_id Ad ID.
	 * @return int
	 */
	public function historical_impressions( $ad_id ) {
		global $wpdb;

		$ad_id = (int) $ad_id;

		if ( $ad_id <= 0 ) {
			return 0;
		}

		$table = $wpdb->prefix . 'wbam_analytics';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- One-off read when a cap is first set; result is stored in meta. Table name is built from $wpdb->prefix; ad_id and event_type are bound.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE ad_id = %d AND event_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from $wpdb->prefix.
				$ad_id,
				'impression'
			)
		);

		return max( 0, (int) $count );
	}

	/**
	 * Start the cap counter for an ad, seeding it from recorded history.
	 *
	 * Idempotent: once the counter exists it is left alone, so re-saving an ad
	 * never rewinds or double-seeds it.
	 *
	 * @param int $ad_id Ad ID.
	 * @return int The delivered count now on record.
	 */
	public function seed_delivered( $ad_id ) {
		$ad_id = (int) $ad_id;

		$existing = get_post_meta( $ad_id, self::COUNT_META, true );

		if ( '' !== $existing && null !== $existing ) {
			return $this->get_delivered( $ad_id );
		}

		$seed = $this->historical_impressions( $ad_id );
		update_post_meta( $ad_id, self::COUNT_META, $seed );

		return $seed;
	}

	/**
	 * Whether the ad has used up its total impression cap.
	 *
	 * @param int $ad_id Ad ID.
	 * @return bool
	 */
	public function cap_reached( $ad_id ) {
		$cap = $this->get_cap( $ad_id );

		return $cap > 0 && $this->get_delivered( $ad_id ) >= $cap;
	}

	/**
	 * Track ad impression on current page.
	 *
	 * @param int $ad_id Ad ID.
	 */
	public function track_impression( $ad_id ) {
		$this->page_ads[] = $ad_id;
	}

	/**
	 * Get number of ads shown on current page.
	 *
	 * @return int
	 */
	public function get_page_ad_count() {
		return count( array_unique( $this->page_ads ) );
	}

	/**
	 * Check if page limit reached.
	 *
	 * @return bool
	 */
	public function page_limit_reached() {
		$settings = Settings::get_instance();
		$max_page = $settings->get( 'max_ads_per_page', 0 );

		if ( $max_page <= 0 ) {
			return false;
		}

		return $this->get_page_ad_count() >= $max_page;
	}

	/**
	 * Check if specific ad can be shown (page, total and per-visitor daily limits).
	 *
	 * @param int $ad_id Ad ID.
	 * @return bool
	 */
	public function can_show_ad( $ad_id ) {
		// Check page limit first.
		if ( $this->page_limit_reached() ) {
			return false;
		}

		// Total impression cap — the ad is finished for everyone, not just
		// this visitor, so it is checked before any per-session logic.
		if ( $this->cap_reached( $ad_id ) ) {
			return false;
		}

		// Per-visitor daily limit for this specific ad.
		$session_limit = get_post_meta( $ad_id, '_wbam_session_limit', true );

		if ( empty( $session_limit ) || $session_limit <= 0 ) {
			return true;
		}

		$views = $this->get_ad_views( $ad_id );
		return $views < $session_limit;
	}

	/**
	 * This visitor's views of an ad today, from the view cookie.
	 *
	 * A visitor who blocks cookies is not capped; that is the price of not
	 * writing a database row per view.
	 *
	 * @param int $ad_id Ad ID.
	 * @return int
	 */
	public function get_ad_views( $ad_id ) {
		$cookie_data = $this->get_cookie_data();

		return isset( $cookie_data[ (int) $ad_id ] ) ? $cookie_data[ (int) $ad_id ] : 0;
	}

	/**
	 * Get cookie data, reduced to ad id => views integers.
	 *
	 * @return array<int,int>
	 */
	private function get_cookie_data() {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return array();
		}

		$data  = json_decode( sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ), true );
		$clean = array();

		foreach ( is_array( $data ) ? $data : array() as $ad_id => $views ) {
			if ( (int) $ad_id > 0 ) {
				$clean[ (int) $ad_id ] = absint( $views );
			}
		}

		return $clean;
	}

	/**
	 * Count this page's views of capped ads in the view cookie.
	 *
	 * Runs on wp_footer, after output has started, so the cookie is set from
	 * a script rather than a header.
	 */
	public function set_view_cookie() {
		$cookie_data = $this->get_cookie_data();
		$counted     = false;

		foreach ( $this->page_ads as $ad_id ) {
			if ( ! $this->counts_views( $ad_id ) ) {
				continue;
			}

			$cookie_data[ $ad_id ] = ( isset( $cookie_data[ $ad_id ] ) ? $cookie_data[ $ad_id ] : 0 ) + 1;
			$counted               = true;
		}

		if ( ! $counted ) {
			return;
		}

		$cookie = self::COOKIE_NAME . '=' . rawurlencode( (string) wp_json_encode( $cookie_data ) )
			. '; expires=' . gmdate( 'D, d M Y H:i:s', $this->cookie_expiry() ) . ' GMT; path=' . COOKIEPATH
			. ( COOKIE_DOMAIN ? '; domain=' . COOKIE_DOMAIN : '' )
			. ( is_ssl() ? '; secure' : '' );

		wp_print_inline_script_tag( 'document.cookie=' . wp_json_encode( $cookie, JSON_HEX_TAG | JSON_HEX_AMP ) . ';' );
	}

	/**
	 * Get ads sorted by priority.
	 *
	 * @param array $ad_ids Array of ad IDs.
	 * @return array Sorted ad IDs.
	 */
	public function sort_by_priority( $ad_ids ) {
		if ( empty( $ad_ids ) ) {
			return array();
		}

		$ads_with_priority = array();

		foreach ( $ad_ids as $ad_id ) {
			$priority                    = get_post_meta( $ad_id, '_wbam_priority', true );
			$ads_with_priority[ $ad_id ] = ! empty( $priority ) ? (int) $priority : 5;
		}

		// Sort by priority (higher = first).
		arsort( $ads_with_priority );

		return array_keys( $ads_with_priority );
	}

	/**
	 * Get random ad from list with weight.
	 *
	 * @param array $ad_ids Array of ad IDs.
	 * @return int|null Selected ad ID or null.
	 */
	public function get_weighted_random( $ad_ids ) {
		if ( empty( $ad_ids ) ) {
			return null;
		}

		$weighted = array();

		foreach ( $ad_ids as $ad_id ) {
			$priority = get_post_meta( $ad_id, '_wbam_priority', true );
			$weight   = ! empty( $priority ) ? (int) $priority : 5;

			// Add ad to pool based on weight.
			for ( $i = 0; $i < $weight; $i++ ) {
				$weighted[] = $ad_id;
			}
		}

		if ( empty( $weighted ) ) {
			return $ad_ids[0];
		}

		return $weighted[ array_rand( $weighted ) ];
	}

	/**
	 * Filter ads by frequency rules.
	 *
	 * @param array $ad_ids Array of ad IDs.
	 * @return array Filtered ad IDs.
	 */
	public function filter_by_frequency( $ad_ids ) {
		$filtered = array();

		foreach ( $ad_ids as $ad_id ) {
			if ( $this->can_show_ad( $ad_id ) ) {
				$filtered[] = $ad_id;
			}
		}

		return $filtered;
	}
}
