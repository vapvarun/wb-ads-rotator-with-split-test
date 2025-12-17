<?php
/**
 * BuddyPress Directory Placement
 *
 * @package WB_Ad_Manager
 * @since   1.1.0
 */

namespace WBAM\Modules\BuddyPress;

use WBAM\Modules\Placements\Placement_Interface;
use WBAM\Modules\Placements\Placement_Engine;

/**
 * BP_Directory_Placement class.
 */
class BP_Directory_Placement implements Placement_Interface {

	/**
	 * Get placement ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'bp_directory';
	}

	/**
	 * Get placement name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'BuddyPress Directories', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get placement description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Display ads in member or group directory listings.', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get placement group.
	 *
	 * @return string
	 */
	public function get_group() {
		return 'buddypress';
	}

	/**
	 * Check if placement is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return class_exists( 'BuddyPress' );
	}

	/**
	 * Show in placement selector.
	 *
	 * @return bool
	 */
	public function show_in_selector() {
		return true;
	}

	/**
	 * Register placement hooks.
	 */
	public function register() {
		// Member directory.
		add_action( 'bp_before_members_loop', array( $this, 'render_before_members' ) );
		add_action( 'bp_after_members_loop', array( $this, 'render_after_members' ) );

		// Group directory.
		add_action( 'bp_before_groups_loop', array( $this, 'render_before_groups' ) );
		add_action( 'bp_after_groups_loop', array( $this, 'render_after_groups' ) );

		// Inside loops (between items).
		add_action( 'bp_directory_members_item', array( $this, 'render_between_members' ), 999 );
		add_action( 'bp_directory_groups_item', array( $this, 'render_between_groups' ), 999 );
	}

	/**
	 * Counter for member loop.
	 *
	 * @var int
	 */
	private $member_count = 0;

	/**
	 * Counter for group loop.
	 *
	 * @var int
	 */
	private $group_count = 0;

	/**
	 * Render ads before members loop.
	 */
	public function render_before_members() {
		$this->member_count = 0;
		$this->render_directory_ads( 'before_members' );
	}

	/**
	 * Render ads after members loop.
	 */
	public function render_after_members() {
		$this->render_directory_ads( 'after_members' );
	}

	/**
	 * Render ads between members.
	 */
	public function render_between_members() {
		$this->member_count++;
		$this->render_between_items( 'members', $this->member_count );
	}

	/**
	 * Render ads before groups loop.
	 */
	public function render_before_groups() {
		$this->group_count = 0;
		$this->render_directory_ads( 'before_groups' );
	}

	/**
	 * Render ads after groups loop.
	 */
	public function render_after_groups() {
		$this->render_directory_ads( 'after_groups' );
	}

	/**
	 * Render ads between groups.
	 */
	public function render_between_groups() {
		$this->group_count++;
		$this->render_between_items( 'groups', $this->group_count );
	}

	/**
	 * Render directory ads.
	 *
	 * @param string $position Position key.
	 */
	private function render_directory_ads( $position ) {
		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		foreach ( $ads as $ad_id ) {
			$data         = get_post_meta( $ad_id, '_wbam_ad_data', true );
			$ad_position  = isset( $data['bp_directory_position'] ) ? $data['bp_directory_position'] : 'before_members';

			if ( $ad_position !== $position ) {
				continue;
			}

			$output = $engine->render_ad( $ad_id );

			if ( ! empty( $output ) ) {
				echo '<div class="wbam-bp-directory-ad wbam-bp-' . esc_attr( $position ) . '">' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * Render ads between items.
	 *
	 * @param string $type  Directory type (members/groups).
	 * @param int    $count Current item count.
	 */
	private function render_between_items( $type, $count ) {
		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		foreach ( $ads as $ad_id ) {
			$data        = get_post_meta( $ad_id, '_wbam_ad_data', true );
			$ad_position = isset( $data['bp_directory_position'] ) ? $data['bp_directory_position'] : '';
			$after_count = isset( $data['bp_directory_after'] ) ? absint( $data['bp_directory_after'] ) : 3;
			$repeat      = isset( $data['bp_directory_repeat'] ) ? $data['bp_directory_repeat'] : false;

			$expected_position = 'between_' . $type;
			if ( $ad_position !== $expected_position ) {
				continue;
			}

			$show = false;
			if ( $repeat ) {
				$show = ( $count % $after_count === 0 );
			} else {
				$show = ( $count === $after_count );
			}

			if ( ! $show ) {
				continue;
			}

			$output = $engine->render_ad( $ad_id );

			if ( ! empty( $output ) ) {
				echo '</li><li class="wbam-bp-between-item">' . $output . '</li><li style="display:none;">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * Render placement options.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Ad data.
	 */
	public function render_options( $ad_id, $data ) {
		$position    = isset( $data['bp_directory_position'] ) ? $data['bp_directory_position'] : 'before_members';
		$after_count = isset( $data['bp_directory_after'] ) ? absint( $data['bp_directory_after'] ) : 3;
		$repeat      = isset( $data['bp_directory_repeat'] ) ? $data['bp_directory_repeat'] : false;

		$positions = array(
			'before_members'  => __( 'Before Members List', 'wb-ads-rotator-with-split-test' ),
			'after_members'   => __( 'After Members List', 'wb-ads-rotator-with-split-test' ),
			'between_members' => __( 'Between Members', 'wb-ads-rotator-with-split-test' ),
			'before_groups'   => __( 'Before Groups List', 'wb-ads-rotator-with-split-test' ),
			'after_groups'    => __( 'After Groups List', 'wb-ads-rotator-with-split-test' ),
			'between_groups'  => __( 'Between Groups', 'wb-ads-rotator-with-split-test' ),
		);
		?>
		<div class="wbam-placement-extra">
			<label><?php esc_html_e( 'Position', 'wb-ads-rotator-with-split-test' ); ?></label>
			<select name="wbam_data[bp_directory_position]" class="wbam-bp-directory-select">
				<?php foreach ( $positions as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $position, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="wbam-placement-extra wbam-bp-between-options" <?php echo ( strpos( $position, 'between' ) === false ) ? 'style="display:none;"' : ''; ?>>
			<label><?php esc_html_e( 'Show after every', 'wb-ads-rotator-with-split-test' ); ?></label>
			<input type="number" name="wbam_data[bp_directory_after]" value="<?php echo esc_attr( $after_count ); ?>" min="1" max="20" style="width:60px;" />
			<?php esc_html_e( 'items', 'wb-ads-rotator-with-split-test' ); ?>
			<label style="margin-left:15px;">
				<input type="checkbox" name="wbam_data[bp_directory_repeat]" value="1" <?php checked( $repeat ); ?> />
				<?php esc_html_e( 'Repeat', 'wb-ads-rotator-with-split-test' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Save placement options.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Posted data.
	 * @return array
	 */
	public function save_options( $ad_id, $data ) {
		$valid_positions = array(
			'before_members',
			'after_members',
			'between_members',
			'before_groups',
			'after_groups',
			'between_groups',
		);

		$position = isset( $data['bp_directory_position'] ) ? sanitize_key( $data['bp_directory_position'] ) : 'before_members';

		return array(
			'bp_directory_position' => in_array( $position, $valid_positions, true ) ? $position : 'before_members',
			'bp_directory_after'    => isset( $data['bp_directory_after'] ) ? absint( $data['bp_directory_after'] ) : 3,
			'bp_directory_repeat'   => isset( $data['bp_directory_repeat'] ),
		);
	}
}
