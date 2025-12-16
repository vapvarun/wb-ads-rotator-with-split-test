<?php
/**
 * Placement Interface
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\Placements;

/**
 * Placement Interface.
 */
interface Placement_Interface {

	/**
	 * Get placement ID.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Get placement name.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Get placement description.
	 *
	 * @return string
	 */
	public function get_description();

	/**
	 * Get placement group.
	 *
	 * @return string
	 */
	public function get_group();

	/**
	 * Check if placement is available.
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * Check if placement should appear in the admin selector.
	 *
	 * Placements like shortcode are used manually and shouldn't
	 * appear as selectable options in the placement metabox.
	 *
	 * @return bool
	 */
	public function show_in_selector();

	/**
	 * Register placement hooks.
	 */
	public function register();
}
