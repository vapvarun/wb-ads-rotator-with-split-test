<?php
/**
 * Rich Content Ad Type
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\AdTypes;

/**
 * Rich Content Ad class.
 */
class Rich_Content_Ad implements Ad_Type_Interface {

	/**
	 * Get ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'rich-content';
	}

	/**
	 * Get name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Rich Content', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Create ads with the WordPress editor.', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'dashicons-edit';
	}

	/**
	 * Render ad.
	 *
	 * @param int   $ad_id   Ad ID.
	 * @param array $options Options.
	 * @return string
	 */
	public function render( $ad_id, $options = array() ) {
		$data    = get_post_meta( $ad_id, '_wbam_ad_data', true );
		$content = isset( $data['content'] ) ? $data['content'] : '';

		if ( empty( $content ) ) {
			return '';
		}

		$classes = array( 'wbam-ad', 'wbam-ad-rich-content' );
		if ( ! empty( $options['class'] ) ) {
			$classes[] = sanitize_html_class( $options['class'] );
		}

		$html  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-ad-id="' . esc_attr( $ad_id ) . '">';
		$html .= wp_kses_post( wpautop( $content ) );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render metabox.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Data.
	 */
	public function render_metabox( $ad_id, $data ) {
		$content = isset( $data['content'] ) ? $data['content'] : '';
		?>
		<div class="wbam-field wbam-field-full">
			<label for="wbam_rich_content"><?php esc_html_e( 'Ad Content', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<p class="description"><?php esc_html_e( 'Enter HTML content for your ad. Basic HTML tags are supported.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<textarea id="wbam_rich_content" name="wbam_data[content]" rows="10" class="large-text"><?php echo esc_textarea( $content ); ?></textarea>
			</div>
		</div>
		<?php
	}

	/**
	 * Save data.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Data.
	 * @return array
	 */
	public function save( $ad_id, $data ) {
		return array(
			'content' => isset( $data['content'] ) ? wp_kses_post( $data['content'] ) : '',
		);
	}
}
