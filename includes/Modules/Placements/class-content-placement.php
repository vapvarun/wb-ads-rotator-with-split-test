<?php
/**
 * Content Placement
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\Placements;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Content Placement class.
 */
class Content_Placement implements Placement_Interface {

	public function get_id() {
		return 'content';
	}

	public function get_name() {
		return __( 'Before/After Content', 'wb-ads-rotator-with-split-test' );
	}

	public function get_description() {
		return __( 'Display ads before or after post content.', 'wb-ads-rotator-with-split-test' );
	}

	public function get_group() {
		return 'WordPress';
	}

	public function is_available() {
		return true;
	}

	public function show_in_selector() {
		return true;
	}

	public function register() {
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
	}

	public function filter_content( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		/**
		 * Filter whether to skip in-content ad injection (before/after content
		 * and after-paragraph) on the current page. Application pages - an
		 * account dashboard, a posting form, a message thread - render their
		 * UI through the_content, and ads injected there land inside forms.
		 *
		 * @since 3.2.0
		 *
		 * @param bool   $skip    Whether to skip. Default false.
		 * @param string $content The content being filtered.
		 */
		if ( apply_filters( 'wbam_skip_content_injection', false, $content ) ) {
			return $content;
		}

		$engine = Placement_Engine::get_instance();

		// One creative per page: it renders where its Position option puts
		// it. The engine's page cap made the old second copy (after the
		// content) an empty wrapper on every page.
		$before = '';
		$after  = '';
		foreach ( $engine->get_ads_for_placement( 'content' ) as $ad_id ) {
			$html = $engine->render_ad( $ad_id, array( 'placement' => 'content' ) );
			if ( '' === $html ) {
				continue;
			}

			$position = $this->save_options( $ad_id, (array) get_post_meta( $ad_id, '_wbam_ad_data', true ) )['content_position'];
			if ( 'after' === $position ) {
				$after .= $html;
			} else {
				$before .= $html;
			}
		}

		if ( '' !== $before ) {
			$before = '<div class="wbam-placement wbam-placement-before-content">' . $before . '</div>';
		}
		if ( '' !== $after ) {
			$after = '<div class="wbam-placement wbam-placement-after-content">' . $after . '</div>';
		}

		return $before . $content . $after;
	}

	/**
	 * Render placement options.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Ad data.
	 */
	public function render_options( $ad_id, $data ) {
		$position = $this->save_options( $ad_id, (array) $data )['content_position'];
		?>
		<div class="wbam-placement-extra">
			<label for="wbam_content_position"><?php esc_html_e( 'Position', 'wb-ads-rotator-with-split-test' ); ?></label>
			<select id="wbam_content_position" name="wbam_data[content_position]">
				<option value="before" <?php selected( $position, 'before' ); ?>><?php esc_html_e( 'Before the content', 'wb-ads-rotator-with-split-test' ); ?></option>
				<option value="after" <?php selected( $position, 'after' ); ?>><?php esc_html_e( 'After the content', 'wb-ads-rotator-with-split-test' ); ?></option>
			</select>
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
		return array(
			'content_position' => isset( $data['content_position'] ) && 'after' === $data['content_position'] ? 'after' : 'before',
		);
	}
}
