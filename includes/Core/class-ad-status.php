<?php
/**
 * Whether an ad can show right now, and one plain reason when it can't.
 *
 * One source for All Ads, the ad editor, the WB Ad block and the admin-only
 * hint an empty shortcode or widget prints (owner decision 5, card
 * 10344382789). Pro adds its own reasons (no live campaign, paid ads fill
 * the placement first) through the `wbam_ad_status` filter.
 *
 * @package WB_Ad_Manager
 * @since   3.2.0
 */

namespace WBAM\Core;

use WBAM\Modules\Placements\Placement_Engine;
use WBAM\Modules\Targeting\Frequency_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ad_Status class.
 */
final class Ad_Status {

	const LIVE        = 'live';
	const SCHEDULED   = 'scheduled';
	const ENDED       = 'ended';
	const NOT_SHOWING = 'not_showing';
	const OFF         = 'off';
	const DRAFT       = 'draft';
	const PENDING     = 'pending';

	/**
	 * Load what the checks read for a page of ads in one go, so a list of
	 * 2,000 ads costs a query per page, not per row. Pro batch-loads the
	 * ads' campaigns on the action.
	 *
	 * @param int[] $ad_ids Ad IDs.
	 * @return void
	 */
	public static function prime( array $ad_ids ) {
		$ad_ids = array_values( array_filter( array_map( 'absint', $ad_ids ) ) );
		if ( ! $ad_ids ) {
			return;
		}

		update_postmeta_cache( $ad_ids );

		/**
		 * Batch-load anything a `wbam_ad_status` callback reads per ad.
		 *
		 * @since 3.2.0
		 * @param int[] $ad_ids Ad IDs about to be checked.
		 */
		do_action( 'wbam_ad_status_prime', $ad_ids );
	}

	/**
	 * The ad's state and the one reason an owner needs.
	 *
	 * @param int $ad_id Ad ID.
	 * @return array{state: string, label: string, reason: string}
	 */
	public static function get( $ad_id ) {
		$ad_id  = absint( $ad_id );
		$status = self::base( $ad_id );

		/**
		 * Filter an ad's state and reason. Pro adds campaign reasons.
		 *
		 * @since 3.2.0
		 * @param array{state: string, label: string, reason: string} $status State, badge label and reason.
		 * @param int                                                   $ad_id  Ad ID.
		 */
		$status = (array) apply_filters( 'wbam_ad_status', $status, $ad_id );

		return array(
			'state'  => (string) ( $status['state'] ?? self::LIVE ),
			'label'  => (string) ( $status['label'] ?? self::label( self::LIVE ) ),
			'reason' => (string) ( $status['reason'] ?? '' ),
		);
	}

	/**
	 * Badge text for a state.
	 *
	 * @param string $state One of the class constants.
	 * @return string
	 */
	public static function label( $state ) {
		$labels = array(
			self::LIVE        => __( 'Live', 'wb-ads-rotator-with-split-test' ),
			self::SCHEDULED   => __( 'Scheduled', 'wb-ads-rotator-with-split-test' ),
			self::ENDED       => __( 'Ended', 'wb-ads-rotator-with-split-test' ),
			self::NOT_SHOWING => __( 'Not showing', 'wb-ads-rotator-with-split-test' ),
			self::OFF         => __( 'Off', 'wb-ads-rotator-with-split-test' ),
			self::DRAFT       => __( 'Draft', 'wb-ads-rotator-with-split-test' ),
			self::PENDING     => __( 'Pending review', 'wb-ads-rotator-with-split-test' ),
		);

		return $labels[ $state ] ?? $labels[ self::LIVE ];
	}

	/**
	 * A state array.
	 *
	 * @param string $state  State.
	 * @param string $reason Reason.
	 * @return array{state: string, label: string, reason: string}
	 */
	public static function make( $state, $reason = '' ) {
		return array(
			'state'  => $state,
			'label'  => self::label( $state ),
			'reason' => $reason,
		);
	}

	/**
	 * Why an ad printed nothing here, for people who can edit ads only; an
	 * empty string for everyone else. Used where a shortcode, widget or
	 * block would otherwise leave a blank gap with no explanation.
	 *
	 * @param int $ad_id Ad ID.
	 * @return string Note HTML, escaped.
	 */
	public static function editor_hint( $ad_id ) {
		if ( ! current_user_can( 'edit_post', (int) $ad_id ) ) {
			return '';
		}

		$status = self::get( $ad_id );
		$reason = self::LIVE === $status['state']
			? __( 'It is live, but its display rules keep it off this page.', 'wb-ads-rotator-with-split-test' )
			: $status['reason'];

		return self::note(
			sprintf(
				/* translators: 1: ad title, 2: reason */
				__( 'Ad "%1$s" is not showing here: %2$s', 'wb-ads-rotator-with-split-test' ),
				get_the_title( $ad_id ),
				$reason
			)
		);
	}

	/**
	 * An editors-only note in place of an empty ad.
	 *
	 * @param string $message Plain text.
	 * @return string
	 */
	public static function note( $message ) {
		return '<p class="wbam-editor-hint" role="note">' . esc_html(
			sprintf(
				/* translators: %s: explanation */
				__( 'WB Ad Manager (only people who can edit ads see this): %s', 'wb-ads-rotator-with-split-test' ),
				$message
			)
		) . '</p>';
	}

	/**
	 * Free's checks, in the order an owner would fix them.
	 *
	 * @param int $ad_id Ad ID.
	 * @return array{state: string, label: string, reason: string}
	 */
	private static function base( $ad_id ) {
		$post_status = get_post_status( $ad_id );
		if ( 'pending' === $post_status ) {
			return self::make( self::PENDING, __( 'Waiting for review.', 'wb-ads-rotator-with-split-test' ) );
		}
		if ( 'draft' === $post_status || 'auto-draft' === $post_status ) {
			return self::make( self::DRAFT, __( 'Publish it to start showing.', 'wb-ads-rotator-with-split-test' ) );
		}
		if ( '0' === (string) get_post_meta( $ad_id, '_wbam_enabled', true ) ) {
			return self::make( self::OFF, __( 'Turned off in Ad Status.', 'wb-ads-rotator-with-split-test' ) );
		}

		$dates = self::dates( $ad_id );
		$today = wp_date( 'Y-m-d' );
		if ( $dates['start'] && $dates['start'] > $today ) {
			/* translators: %s: start date */
			return self::make( self::SCHEDULED, sprintf( __( 'Starts on %s.', 'wb-ads-rotator-with-split-test' ), wbam_format_day( $dates['start'] ) ) );
		}
		if ( $dates['end'] && $dates['end'] < $today ) {
			/* translators: %s: end date */
			return self::make( self::ENDED, sprintf( __( 'Ended on %s.', 'wb-ads-rotator-with-split-test' ), wbam_format_day( $dates['end'] ) ) );
		}

		$ad_data = get_post_meta( $ad_id, '_wbam_ad_data', true );
		$handler = Placement_Engine::get_instance()->get_ad_type( is_array( $ad_data ) && isset( $ad_data['type'] ) ? $ad_data['type'] : '' );
		if ( $handler && method_exists( $handler, 'has_creative' ) && ! $handler->has_creative( $ad_id ) ) {
			$missing = method_exists( $handler, 'get_missing_label' ) ? $handler->get_missing_label( $ad_id ) : __( 'Image missing', 'wb-ads-rotator-with-split-test' );
			return self::make( self::NOT_SHOWING, $missing . '.' );
		}

		// Display rules that can never match (card 10344383905).
		$rules = get_post_meta( $ad_id, '_wbam_display_rules', true );
		if ( is_array( $rules ) && 'specific' === ( $rules['display_on'] ?? '' ) && ! array_filter( array_intersect_key( $rules, array_flip( array( 'posts', 'post_types', 'categories', 'tags', 'page_types' ) ) ) ) ) {
			return self::make( self::NOT_SHOWING, __( 'Display Rules say "Specific pages" but nothing is picked.', 'wb-ads-rotator-with-split-test' ) );
		}
		$visitors = get_post_meta( $ad_id, '_wbam_visitor_conditions', true );
		if ( is_array( $visitors ) && 'logged_in' === ( $visitors['user_status'] ?? '' ) && Settings_Helper::get( 'disable_ads_logged_in' ) ) {
			return self::make( self::NOT_SHOWING, __( 'It is for logged-in visitors only, but Settings hides ads from logged-in users.', 'wb-ads-rotator-with-split-test' ) );
		}

		$frequency = Frequency_Manager::get_instance();
		if ( $frequency->cap_reached( $ad_id ) ) {
			/* translators: %s: impression cap */
			return self::make( self::ENDED, sprintf( __( 'Reached its cap of %s impressions.', 'wb-ads-rotator-with-split-test' ), number_format_i18n( $frequency->get_cap( $ad_id ) ) ) );
		}

		// A shortcode, WB Ad block or widget with this ad chosen shows it
		// whatever its placements, so these are Live with a reason, not
		// Not showing.
		$placements = array_values( array_filter( (array) get_post_meta( $ad_id, '_wbam_placements', true ) ) );
		$split      = $placements && function_exists( 'wbam_split_placements_by_fit' ) ? wbam_split_placements_by_fit( $ad_id, $placements ) : array( 'kept' => $placements );
		if ( ! $placements ) {
			return self::make( self::LIVE, __( 'No placement ticked: it shows only where its shortcode, block or widget is used.', 'wb-ads-rotator-with-split-test' ) );
		}
		if ( empty( $split['kept'] ) ) {
			return self::make( self::LIVE, __( 'Its size fits none of its placements: it shows only where its shortcode, block or widget is used.', 'wb-ads-rotator-with-split-test' ) );
		}
		if ( array( 'widget' ) === array_values( $split['kept'] ) && ! self::widget_placement_in_use() ) {
			return self::make( self::NOT_SHOWING, __( 'Its only placement is Widget, and no WB Ad Manager widget is in a widget area.', 'wb-ads-rotator-with-split-test' ) );
		}

		return self::make( self::LIVE );
	}

	/**
	 * Whether any widget area shows the Widget placement: the classic
	 * WB Ad Manager widget, or a block widget area holding the WB Ad
	 * placement block set to Widget. Both read autoloaded options, so
	 * this is cheap per call.
	 *
	 * @return bool
	 */
	private static function widget_placement_in_use() {
		$in_use = (bool) is_active_widget( false, false, 'wbam_ad_widget', true );
		if ( ! $in_use ) {
			$blocks = (array) get_option( 'widget_block', array() );
			foreach ( wbam_sidebars_widgets() as $sidebar => $widget_ids ) {
				if ( 'wp_inactive_widgets' === $sidebar ) {
					continue;
				}
				foreach ( (array) $widget_ids as $widget_id ) {
					$number  = (int) str_replace( 'block-', '', (string) $widget_id );
					$content = isset( $blocks[ $number ]['content'] ) ? (string) $blocks[ $number ]['content'] : '';
					if ( 0 === strpos( (string) $widget_id, 'block-' ) && false !== strpos( $content, 'wp:wb-ads/placement' ) && false !== strpos( $content, '"placementId":"widget"' ) ) {
						$in_use = true;
						break 2;
					}
				}
			}
		}

		return $in_use;
	}

	/**
	 * The ad's start and end days (site calendar, Y-m-d), from the one date
	 * store with the pre-3.2.0 schedule array as the fallback.
	 *
	 * @param int $ad_id Ad ID.
	 * @return array{start: string, end: string}
	 */
	private static function dates( $ad_id ) {
		$schedule = get_post_meta( $ad_id, '_wbam_schedule', true );
		$schedule = is_array( $schedule ) ? $schedule : array();
		$start    = (string) get_post_meta( $ad_id, '_wbam_start_date', true );
		$end      = (string) get_post_meta( $ad_id, '_wbam_end_date', true );

		return array(
			'start' => substr( '' !== $start ? $start : (string) ( $schedule['start_date'] ?? '' ), 0, 10 ),
			'end'   => substr( '' !== $end ? $end : (string) ( $schedule['end_date'] ?? '' ), 0, 10 ),
		);
	}
}
