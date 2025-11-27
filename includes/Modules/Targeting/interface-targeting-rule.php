<?php
/**
 * Targeting Rule Interface
 *
 * @package WB_Ad_Manager
 * @since   1.1.0
 */

namespace WBAM\Modules\Targeting;

/**
 * Interface for targeting rules.
 */
interface Targeting_Rule_Interface {

	/**
	 * Get rule ID.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Get rule name.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Check if rule passes.
	 *
	 * @param array $conditions Rule conditions.
	 * @param array $context    Current context (post, user, etc.).
	 * @return bool
	 */
	public function check( $conditions, $context );

	/**
	 * Render rule settings in admin.
	 *
	 * @param int   $ad_id      Ad post ID.
	 * @param array $conditions Saved conditions.
	 */
	public function render_settings( $ad_id, $conditions );

	/**
	 * Sanitize rule conditions.
	 *
	 * @param array $conditions Raw conditions.
	 * @return array
	 */
	public function sanitize( $conditions );
}
