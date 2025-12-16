<?php
/**
 * Comment Placement
 *
 * @package WB_Ad_Manager
 * @since   1.1.0
 */

namespace WBAM\Modules\Placements;

/**
 * Comment Placement class.
 */
class Comment_Placement implements Placement_Interface {

	/**
	 * Comment counter.
	 *
	 * @var int
	 */
	private $comment_count = 0;

	/**
	 * Get placement ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'comments';
	}

	/**
	 * Get placement name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Comments', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get placement description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Display ads before, after, or between comments.', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get placement group.
	 *
	 * @return string
	 */
	public function get_group() {
		return 'content';
	}

	/**
	 * Check if placement is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * Check if placement should appear in the admin selector.
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
		add_action( 'comment_form_before', array( $this, 'render_before_form' ) );
		add_action( 'comment_form_after', array( $this, 'render_after_form' ) );
		add_filter( 'comments_array', array( $this, 'reset_counter' ), 5 );
		add_filter( 'get_comment_text', array( $this, 'render_between_comments' ), 99, 2 );
	}

	/**
	 * Reset comment counter.
	 *
	 * @param array $comments Comments array.
	 * @return array
	 */
	public function reset_counter( $comments ) {
		$this->comment_count = 0;
		return $comments;
	}

	/**
	 * Render ads before comment form.
	 */
	public function render_before_form() {
		$this->render_comment_ads( 'before_form' );
	}

	/**
	 * Render ads after comment form.
	 */
	public function render_after_form() {
		$this->render_comment_ads( 'after_form' );
	}

	/**
	 * Render ads between comments.
	 *
	 * @param string     $comment_text Comment text.
	 * @param WP_Comment $comment      Comment object.
	 * @return string
	 */
	public function render_between_comments( $comment_text, $comment ) {
		// Only for top-level comments.
		if ( 0 !== (int) $comment->comment_parent ) {
			return $comment_text;
		}

		$this->comment_count++;

		$ad_output = $this->get_between_ads( $this->comment_count );

		if ( ! empty( $ad_output ) ) {
			$comment_text .= $ad_output;
		}

		return $comment_text;
	}

	/**
	 * Render comment ads.
	 *
	 * @param string $position Position key.
	 */
	private function render_comment_ads( $position ) {
		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		foreach ( $ads as $ad_id ) {
			$data         = get_post_meta( $ad_id, '_wbam_ad_data', true );
			$ad_position  = isset( $data['comment_position'] ) ? $data['comment_position'] : 'before_form';

			if ( $ad_position !== $position ) {
				continue;
			}

			$output = $engine->render_ad( $ad_id );

			if ( ! empty( $output ) ) {
				echo '<div class="wbam-comment-ad wbam-comment-' . esc_attr( $position ) . '">' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * Get between ads output.
	 *
	 * @param int $count Current comment count.
	 * @return string
	 */
	private function get_between_ads( $count ) {
		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );
		$output = '';

		if ( empty( $ads ) ) {
			return $output;
		}

		foreach ( $ads as $ad_id ) {
			$data        = get_post_meta( $ad_id, '_wbam_ad_data', true );
			$ad_position = isset( $data['comment_position'] ) ? $data['comment_position'] : '';
			$after_count = isset( $data['comment_after'] ) ? absint( $data['comment_after'] ) : 3;
			$repeat      = isset( $data['comment_repeat'] ) ? $data['comment_repeat'] : false;

			if ( 'between_comments' !== $ad_position ) {
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

			$ad_output = $engine->render_ad( $ad_id );

			if ( ! empty( $ad_output ) ) {
				$output .= '<div class="wbam-comment-ad wbam-comment-between">' . $ad_output . '</div>';
			}
		}

		return $output;
	}

	/**
	 * Render placement options.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Ad data.
	 */
	public function render_options( $ad_id, $data ) {
		$position    = isset( $data['comment_position'] ) ? $data['comment_position'] : 'before_form';
		$after_count = isset( $data['comment_after'] ) ? absint( $data['comment_after'] ) : 3;
		$repeat      = isset( $data['comment_repeat'] ) ? $data['comment_repeat'] : false;

		$positions = array(
			'before_form'      => __( 'Before Comment Form', 'wb-ads-rotator-with-split-test' ),
			'after_form'       => __( 'After Comment Form', 'wb-ads-rotator-with-split-test' ),
			'between_comments' => __( 'Between Comments', 'wb-ads-rotator-with-split-test' ),
		);
		?>
		<div class="wbam-placement-extra">
			<label><?php esc_html_e( 'Position', 'wb-ads-rotator-with-split-test' ); ?></label>
			<select name="wbam_data[comment_position]" class="wbam-comment-position-select">
				<?php foreach ( $positions as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $position, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="wbam-placement-extra wbam-comment-between-options" <?php echo ( 'between_comments' !== $position ) ? 'style="display:none;"' : ''; ?>>
			<label><?php esc_html_e( 'Show after every', 'wb-ads-rotator-with-split-test' ); ?></label>
			<input type="number" name="wbam_data[comment_after]" value="<?php echo esc_attr( $after_count ); ?>" min="1" max="50" style="width:60px;" />
			<?php esc_html_e( 'comments', 'wb-ads-rotator-with-split-test' ); ?>
			<label style="margin-left:15px;">
				<input type="checkbox" name="wbam_data[comment_repeat]" value="1" <?php checked( $repeat ); ?> />
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
		$valid_positions = array( 'before_form', 'after_form', 'between_comments' );
		$position        = isset( $data['comment_position'] ) ? sanitize_key( $data['comment_position'] ) : 'before_form';

		return array(
			'comment_position' => in_array( $position, $valid_positions, true ) ? $position : 'before_form',
			'comment_after'    => isset( $data['comment_after'] ) ? absint( $data['comment_after'] ) : 3,
			'comment_repeat'   => isset( $data['comment_repeat'] ),
		);
	}
}
