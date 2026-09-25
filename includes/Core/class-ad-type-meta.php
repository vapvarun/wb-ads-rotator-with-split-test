<?php
/**
 * Queryable ad type mirror.
 *
 * @package WB_Ad_Manager
 * @since   3.2.0
 */

namespace WBAM\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps `_wbam_ad_type`, a flat copy of `_wbam_ad_data['type']`, on every ad.
 *
 * The creative type otherwise lives only inside the serialised
 * `_wbam_ad_data` blob, which a meta_query cannot filter or sort on. The All
 * Ads type filter and sort, and PRO's video lookups, read this key. It moved
 * here from PRO in 3.2.0 so a FREE-only site has it too.
 */
class Ad_Type_Meta {

	/**
	 * Meta key.
	 */
	const KEY = '_wbam_ad_type';

	/**
	 * Option set once every existing ad carries the key.
	 */
	const BACKFILL_OPTION = 'wbam_ad_type_backfilled';

	/**
	 * Hook the sync and the one-time backfill.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wbam_save_ad_meta', array( __CLASS__, 'sync' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_backfill' ) );
	}

	/**
	 * Mirror one ad's creative type. Hooked on `wbam_save_ad_meta`, which
	 * fires after `_wbam_ad_data` is written.
	 *
	 * @param int $post_id Ad post ID.
	 * @return void
	 */
	public static function sync( $post_id ) {
		$data = get_post_meta( (int) $post_id, '_wbam_ad_data', true );
		$type = is_array( $data ) && ! empty( $data['type'] ) ? sanitize_key( $data['type'] ) : '';

		// An explicit empty value, not a missing key, so the backfill below
		// never picks the same ad up again.
		update_post_meta( (int) $post_id, self::KEY, $type );
	}

	/**
	 * Write the key on ads saved before it existed, 200 at a time.
	 *
	 * Each synced ad drops out of the NOT EXISTS set, so the next pass reads
	 * the following batch. The pass cap bounds one request; any remainder is
	 * finished on a later one because the option stays unset.
	 *
	 * @return void
	 */
	public static function maybe_backfill() {
		if ( get_option( self::BACKFILL_OPTION ) ) {
			return;
		}

		$batch  = 200;
		$passes = 0;

		do {
			$ids = get_posts(
				array(
					'post_type'      => 'wbam-ad',
					'post_status'    => 'any',
					'posts_per_page' => $batch,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one-time backfill.
						array(
							'key'     => self::KEY,
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);

			foreach ( $ids as $id ) {
				self::sync( (int) $id );
			}

			$found = count( $ids );
			++$passes;
		} while ( $found === $batch && $passes < 100 );

		if ( $found < $batch ) {
			update_option( self::BACKFILL_OPTION, 1, false );
		}
	}
}
