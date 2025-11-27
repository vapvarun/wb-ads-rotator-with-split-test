<?php
/**
 * BuddyPress Module
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\BuddyPress;

use WBAM\Modules\Placements\Placement_Engine;

/**
 * BP_Module class.
 */
class BP_Module {

	/**
	 * Initialize.
	 */
	public function init() {
		$this->register_placements();
		$this->setup_hooks();
		BP_Widgets::init();
	}

	/**
	 * Register BuddyPress placements.
	 */
	private function register_placements() {
		$engine = Placement_Engine::get_instance();

		$engine->register_placement( new BP_Activity_Placement() );
		$engine->register_placement( new BP_Directory_Placement() );
	}

	/**
	 * Setup hooks.
	 */
	private function setup_hooks() {
		// Additional BuddyPress-specific hooks can be added here.
	}
}
