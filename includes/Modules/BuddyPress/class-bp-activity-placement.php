<?php
/**
 * BuddyPress Activity Placement
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\BuddyPress;

use WBAM\Modules\Placements\Placement_Interface;
use WBAM\Modules\Placements\Placement_Engine;

/**
 * BP_Activity_Placement class.
 */
class BP_Activity_Placement implements Placement_Interface {

	/**
	 * Ads already displayed (non-repeating).
	 *
	 * @var array
	 */
	private $displayed_ads = array();

	public function get_id() {
		return 'bp_activity';
	}

	public function get_name() {
		return __( 'BuddyPress Activity', 'wb-ads-rotator-with-split-test' );
	}

	public function get_description() {
		return __( 'Display ads in the BuddyPress activity stream.', 'wb-ads-rotator-with-split-test' );
	}

	public function get_group() {
		return 'buddypress';
	}

	public function is_available() {
		return class_exists( 'BuddyPress' );
	}

	/**
	 * Show in placement selector.
	 *
	 * @return bool
	 */
	public function show_in_selector() {
		return true;
	}

	public function register() {
		add_action( 'bp_after_activity_entry', array( $this, 'after_activity' ) );

		// Reset displayed ads on activity loop start.
		add_action( 'bp_before_activity_loop', array( $this, 'reset_displayed' ) );
	}

	/**
	 * Reset displayed ads tracker.
	 */
	public function reset_displayed() {
		$this->displayed_ads = array();
	}

	/**
	 * After activity entry - inject ads using did_action() for accurate counting.
	 */
	public function after_activity() {
		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		// Use did_action() to get accurate activity count (like BP Ads plugin).
		$activity_count = did_action( 'bp_after_activity_entry' );

		foreach ( $ads as $ad_id ) {
			// Skip if already displayed (non-repeating ads).
			if ( in_array( $ad_id, $this->displayed_ads, true ) ) {
				continue;
			}

			$data           = get_post_meta( $ad_id, '_wbam_ad_data', true );
			$after_activity = isset( $data['after_activity'] ) ? absint( $data['after_activity'] ) : 3;
			$repeat         = isset( $data['activity_repeat'] ) && $data['activity_repeat'];

			// Check if we should show ad at this position.
			if ( $repeat ) {
				// Repeating: show every N activities.
				if ( $activity_count >= $after_activity && ( $activity_count % $after_activity ) === 0 ) {
					$this->render_ad( $ad_id, $engine );
				}
			} else {
				// Non-repeating: show only once at position N.
				if ( $activity_count === $after_activity ) {
					$this->render_ad( $ad_id, $engine );
					$this->displayed_ads[] = $ad_id;
				}
			}
		}
	}

	/**
	 * Render ad in activity stream.
	 *
	 * @param int              $ad_id  Ad ID.
	 * @param Placement_Engine $engine Engine.
	 */
	private function render_ad( $ad_id, $engine ) {
		echo '<li class="wbam-activity-ad activity-item">';
		echo '<div class="wbam-placement wbam-placement-bp-activity">';
		echo $engine->render_ad( $ad_id, array( 'placement' => $this->get_id() ) ); // phpcs:ignore
		echo '</div>';
		echo '</li>';
	}
}
