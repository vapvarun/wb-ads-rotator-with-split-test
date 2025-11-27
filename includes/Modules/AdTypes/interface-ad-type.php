<?php
/**
 * Ad Type Interface
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\AdTypes;

/**
 * Ad Type Interface.
 */
interface Ad_Type_Interface {

	/**
	 * Get the ad type ID.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Get the ad type name.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Get the ad type description.
	 *
	 * @return string
	 */
	public function get_description();

	/**
	 * Get the ad type icon.
	 *
	 * @return string
	 */
	public function get_icon();

	/**
	 * Render the ad.
	 *
	 * @param int   $ad_id   Ad ID.
	 * @param array $options Options.
	 * @return string
	 */
	public function render( $ad_id, $options = array() );

	/**
	 * Render the admin metabox.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Saved data.
	 */
	public function render_metabox( $ad_id, $data );

	/**
	 * Save ad data.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Posted data.
	 * @return array
	 */
	public function save( $ad_id, $data );
}
