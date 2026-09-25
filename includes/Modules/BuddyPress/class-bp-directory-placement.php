<?php
/**
 * BuddyPress Directory Placements.
 *
 * Exposes one placement per BuddyPress directory hook so each position
 * is independently selectable in the ad edit UI. Replaces the previous
 * single "BuddyPress Directories" checkbox that hid six positions
 * behind a post-save dropdown.
 *
 * All six directory-position classes live here because they share the
 * same BP-directory dependency and are trivially small (~10 lines each).
 *
 * phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Related directory-position classes share this file.
 *
 * @package WB_Ad_Manager
 * @since   1.1.0
 */

namespace WBAM\Modules\BuddyPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WBAM\Modules\Placements\Placement_Interface;
use WBAM\Modules\Placements\Placement_Engine;

/**
 * Registers all BP directory placements in one call. Kept under the
 * original class name so the rest of the plugin (BP_Module::init())
 * can keep referencing it unchanged.
 */
class BP_Directory_Placement {

	/**
	 * Definition of every directory placement this module registers.
	 *
	 * @return array<int,array{id:string,name:string,description:string,hook:string,style:string,loop?:string}>
	 */
	public static function placements() {
		return array(
			array(
				'id'          => 'bp_before_members',
				'name'        => __( 'Before Members List', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Display ads above the BuddyPress members directory loop.', 'wb-ads-rotator-with-split-test' ),
				'hook'        => 'bp_before_members_loop',
			),
			array(
				'id'          => 'bp_after_members',
				'name'        => __( 'After Members List', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Display ads below the BuddyPress members directory loop.', 'wb-ads-rotator-with-split-test' ),
				'hook'        => 'bp_after_members_loop',
			),
			array(
				'id'          => 'bp_before_groups',
				'name'        => __( 'Before Groups List', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Display ads above the BuddyPress groups directory loop.', 'wb-ads-rotator-with-split-test' ),
				'hook'        => 'bp_before_groups_loop',
			),
			array(
				'id'          => 'bp_after_groups',
				'name'        => __( 'After Groups List', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Display ads below the BuddyPress groups directory loop.', 'wb-ads-rotator-with-split-test' ),
				'hook'        => 'bp_after_groups_loop',
			),
		);
	}

	/**
	 * Register every directory placement with the engine. Deferred to
	 * the `init` hook because placements() contains __() calls, and
	 * WP 6.7+ warns if translations load before init.
	 */
	public function register_all() {
		add_action( 'init', array( $this, 'do_register' ), 5 );
	}

	/**
	 * Actually register the placements. Fires on init.
	 */
	public function do_register() {
		$engine = Placement_Engine::get_instance();
		foreach ( self::placements() as $spec ) {
			$engine->register_placement( new BP_Directory_Position( $spec ) );
		}
	}
}

/**
 * Placement for a single BuddyPress directory position.
 *
 * Note: Between-member and between-group placements are intentionally
 * not supported. BuddyPress fires `bp_directory_members_item` /
 * `bp_directory_groups_item` inside each `<li>`, not between them —
 * no safe hook exists to inject markup between loop items without
 * theme-fragile markup splicing.
 */
class BP_Directory_Position implements Placement_Interface {

	/**
	 * @var array
	 */
	private $spec;

	public function __construct( array $spec ) {
		$this->spec = $spec;
	}

	public function get_id() {
		return $this->spec['id'];
	}

	public function get_name() {
		return $this->spec['name'];
	}

	public function get_description() {
		return $this->spec['description'];
	}

	public function get_group() {
		return 'buddypress';
	}

	public function is_available() {
		return class_exists( 'BuddyPress' );
	}

	public function show_in_selector() {
		return true;
	}

	public function register() {
		add_action( $this->spec['hook'], array( $this, 'render' ) );
	}

	public function render() {
		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		foreach ( $ads as $ad_id ) {
			$html = $engine->render_ad( $ad_id, array( 'placement' => $this->get_id() ) );
			if ( empty( $html ) ) {
				continue;
			}

			echo '<div class="wbam-bp-directory-ad wbam-bp-' . esc_attr( $this->get_id() ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	public function render_options( $ad_id, $data ) {
		// No extra options for before/after placements.
	}

	public function save_options( $ad_id, $data ) {
		return array();
	}
}
