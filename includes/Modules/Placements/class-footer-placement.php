<?php
/**
 * Footer Placement
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\Placements;

/**
 * Footer Placement class.
 */
class Footer_Placement implements Placement_Interface {

	public function get_id() {
		return 'footer';
	}

	public function get_name() {
		return __( 'Footer', 'wb-ads-rotator-with-split-test' );
	}

	public function get_description() {
		return __( 'Display ads in the site footer.', 'wb-ads-rotator-with-split-test' );
	}

	public function get_group() {
		return 'wordpress';
	}

	public function is_available() {
		return true;
	}

	public function show_in_selector() {
		return true;
	}

	public function register() {
		add_action( 'wp_footer', array( $this, 'display' ), 10 );
	}

	public function display() {
		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		echo '<div class="wbam-placement wbam-placement-footer">';
		foreach ( $ads as $ad_id ) {
			echo $engine->render_ad( $ad_id, array( 'placement' => $this->get_id() ) ); // phpcs:ignore
		}
		echo '</div>';
	}
}
