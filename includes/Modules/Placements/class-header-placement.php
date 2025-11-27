<?php
/**
 * Header Placement
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\Placements;

/**
 * Header Placement class.
 */
class Header_Placement implements Placement_Interface {

	public function get_id() {
		return 'header';
	}

	public function get_name() {
		return __( 'Header', 'wb-ad-manager' );
	}

	public function get_description() {
		return __( 'Display ads in the site header.', 'wb-ad-manager' );
	}

	public function get_group() {
		return 'wordpress';
	}

	public function is_available() {
		return true;
	}

	public function register() {
		add_action( 'wp_head', array( $this, 'display' ), 100 );
	}

	public function display() {
		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		echo '<div class="wbam-placement wbam-placement-header">';
		foreach ( $ads as $ad_id ) {
			echo $engine->render_ad( $ad_id, array( 'placement' => $this->get_id() ) ); // phpcs:ignore
		}
		echo '</div>';
	}
}
