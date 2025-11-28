<?php
/**
 * Placement Engine
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\Placements;

use WBAM\Core\Singleton;
use WBAM\Modules\AdTypes\Ad_Type_Interface;
use WBAM\Modules\AdTypes\Image_Ad;
use WBAM\Modules\AdTypes\Rich_Content_Ad;
use WBAM\Modules\AdTypes\Code_Ad;
use WBAM\Modules\Targeting\Targeting_Engine;

/**
 * Placement Engine class.
 */
class Placement_Engine {

	use Singleton;

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

		do_action( 'wbam_placements_init', $this );
	}

	/**
	 * Register ad types.
	 */
	private function register_ad_types() {
		$this->register_ad_type( new Image_Ad() );
		$this->register_ad_type( new Rich_Content_Ad() );
		$this->register_ad_type( new Code_Ad() );

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
		$this->register_placement( new Archive_Placement() );
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
	}

	/**
	 * Get ad type.
	 *
	 * @param string $id Ad type ID.
	 * @return Ad_Type_Interface|null
	 */
	public function get_ad_type( $id ) {
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
	 * Get ads for a placement.
	 *
	 * @param string $placement_id Placement ID.
	 * @return array
	 */
	public function get_ads_for_placement( $placement_id ) {
		$args = array(
			'post_type'      => 'wbam-ad',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_wbam_enabled',
					'value'   => '1',
					'compare' => '=',
				),
				array(
					'key'     => '_wbam_placements',
					'value'   => $placement_id,
					'compare' => 'LIKE',
				),
			),
		);

		$ad_ids = get_posts( $args );

		// Filter through targeting engine.
		$targeting = Targeting_Engine::get_instance();
		$filtered  = array();

		foreach ( $ad_ids as $ad_id ) {
			if ( $targeting->should_display( $ad_id ) ) {
				$filtered[] = $ad_id;
			}
		}

		return $filtered;
	}

	/**
	 * Render an ad.
	 *
	 * @param int   $ad_id   Ad ID.
	 * @param array $options Options.
	 * @return string
	 */
	public function render_ad( $ad_id, $options = array() ) {
		$enabled = get_post_meta( $ad_id, '_wbam_enabled', true );
		if ( ! $enabled ) {
			return '';
		}

		$data    = get_post_meta( $ad_id, '_wbam_ad_data', true );
		$ad_type = isset( $data['type'] ) ? $data['type'] : '';

		$handler = $this->get_ad_type( $ad_type );
		if ( ! $handler ) {
			return '';
		}

		$output    = $handler->render( $ad_id, $options );
		$placement = isset( $options['placement'] ) ? $options['placement'] : '';

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
}
