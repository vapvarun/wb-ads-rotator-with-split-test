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
	 * Activity counter.
	 *
	 * @var int
	 */
	private $activity_count = 0;

	/**
	 * Ads already displayed.
	 *
	 * @var array
	 */
	private $displayed_ads = array();

	public function get_id() {
		return 'bp_activity';
	}

	public function get_name() {
		return __( 'BuddyPress Activity', 'wb-ad-manager' );
	}

	public function get_description() {
		return __( 'Display ads in the BuddyPress activity stream.', 'wb-ad-manager' );
	}

	public function get_group() {
		return 'buddypress';
	}

	public function is_available() {
		return class_exists( 'BuddyPress' );
	}

	public function register() {
		add_action( 'bp_before_activity_entry', array( $this, 'before_activity' ) );
		add_action( 'bp_after_activity_entry', array( $this, 'after_activity' ) );

		// Reset counter on activity loop start.
		add_action( 'bp_before_activity_loop', array( $this, 'reset_counter' ) );
	}

	/**
	 * Reset activity counter.
	 */
	public function reset_counter() {
		$this->activity_count = 0;
		$this->displayed_ads  = array();
	}

	/**
	 * Before activity entry.
	 */
	public function before_activity() {
		$this->activity_count++;
	}

	/**
	 * After activity entry - inject ads.
	 */
	public function after_activity() {
		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		foreach ( $ads as $ad_id ) {
			// Skip if already displayed.
			if ( in_array( $ad_id, $this->displayed_ads, true ) ) {
				continue;
			}

			$data           = get_post_meta( $ad_id, '_wbam_ad_data', true );
			$after_activity = isset( $data['after_activity'] ) ? absint( $data['after_activity'] ) : 3;
			$repeat         = isset( $data['activity_repeat'] ) && $data['activity_repeat'];

			// Check if we should show ad at this position.
			if ( $repeat ) {
				if ( $this->activity_count >= $after_activity && ( $this->activity_count % $after_activity ) === 0 ) {
					$this->render_ad( $ad_id, $engine );
				}
			} else {
				if ( $this->activity_count === $after_activity ) {
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
