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

	/**
	 * Flag to track if header ads have been displayed.
	 *
	 * @var bool
	 */
	private $displayed = false;

	public function get_id() {
		return 'header';
	}

	public function get_name() {
		return __( 'Header', 'wb-ads-rotator-with-split-test' );
	}

	public function get_description() {
		return __( 'Display ads in the site header.', 'wb-ads-rotator-with-split-test' );
	}

	public function get_group() {
		return 'wordpress';
	}

	public function is_available() {
		return true;
	}

	public function register() {
		// Primary: Use wp_body_open for visible header ads (after <body> tag).
		add_action( 'wp_body_open', array( $this, 'display' ), 10 );

		// Theme-specific hooks for better compatibility.
		add_action( 'buddyx_body_top', array( $this, 'display' ), 10 );  // BuddyX theme.
		add_action( 'reign_body_top', array( $this, 'display' ), 10 );   // Reign theme.
		add_action( 'genesis_before_header', array( $this, 'display' ), 10 ); // Genesis.
		add_action( 'theme_body_top', array( $this, 'display' ), 10 );   // Generic.
	}

	public function display() {
		// Prevent duplicate display if multiple hooks fire.
		if ( $this->displayed ) {
			return;
		}

		// Only display on frontend.
		if ( is_admin() ) {
			return;
		}

		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		$this->displayed = true;

		echo '<div class="wbam-placement wbam-placement-header">';
		foreach ( $ads as $ad_id ) {
			echo $engine->render_ad( $ad_id, array( 'placement' => $this->get_id() ) ); // phpcs:ignore
		}
		echo '</div>';
	}
}
