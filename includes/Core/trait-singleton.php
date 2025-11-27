<?php
/**
 * Singleton Trait
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Core;

/**
 * Singleton trait.
 */
trait Singleton {

	/**
	 * Instance.
	 *
	 * @var static
	 */
	protected static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return static
	 */
	public static function get_instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Prevent cloning.
	 */
	protected function __clone() {}

	/**
	 * Prevent unserializing.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
