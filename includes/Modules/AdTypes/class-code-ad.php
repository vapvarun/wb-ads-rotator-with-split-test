<?php
/**
 * Code Ad Type
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\AdTypes;

/**
 * Code Ad class.
 */
class Code_Ad implements Ad_Type_Interface {

	/**
	 * Get ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'code';
	}

	/**
	 * Get name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'HTML/JS Code', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Paste ad network code (AdSense, etc.).', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'dashicons-editor-code';
	}

	/**
	 * Render ad.
	 *
	 * @param int   $ad_id   Ad ID.
	 * @param array $options Options.
	 * @return string
	 */
	public function render( $ad_id, $options = array() ) {
		$data = get_post_meta( $ad_id, '_wbam_ad_data', true );
		$code = isset( $data['code'] ) ? $data['code'] : '';

		if ( empty( $code ) ) {
			return '';
		}

		$classes = array( 'wbam-ad', 'wbam-ad-code' );
		if ( ! empty( $options['class'] ) ) {
			$classes[] = sanitize_html_class( $options['class'] );
		}

		$placement = isset( $options['placement'] ) ? $options['placement'] : '';

		$html  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-ad-id="' . esc_attr( $ad_id ) . '" data-placement="' . esc_attr( $placement ) . '">';
		$html .= $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
		$code = isset( $data['code'] ) ? $data['code'] : '';
		?>
		<div class="wbam-field wbam-field-full">
			<label for="wbam_code"><?php esc_html_e( 'Ad Code', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<p class="description"><?php esc_html_e( 'Paste your ad network code (AdSense, Media.net, etc.) or custom HTML/JavaScript.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<textarea id="wbam_code" name="wbam_data[code]" rows="12" class="large-text code"><?php echo esc_textarea( $code ); ?></textarea>
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
		$code = isset( $data['code'] ) ? $data['code'] : '';

		// Allow unfiltered HTML for admins.
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$code = wp_kses_post( $code );
		}

		return array(
			'code' => $code,
		);
	}
}
