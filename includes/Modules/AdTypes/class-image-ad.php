<?php
/**
 * Image Ad Type
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\AdTypes;

/**
 * Image Ad class.
 */
class Image_Ad implements Ad_Type_Interface {

	/**
	 * Get ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'image';
	}

	/**
	 * Get name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Image Ad', 'wb-ad-manager' );
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Display an image with optional link.', 'wb-ad-manager' );
	}

	/**
	 * Get icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'dashicons-format-image';
	}

	/**
	 * Render ad.
	 *
	 * @param int   $ad_id   Ad ID.
	 * @param array $options Options.
	 * @return string
	 */
	public function render( $ad_id, $options = array() ) {
		$data      = get_post_meta( $ad_id, '_wbam_ad_data', true );
		$image_url = isset( $data['image_url'] ) ? $data['image_url'] : '';
		$link_url  = isset( $data['link_url'] ) ? $data['link_url'] : '';
		$alt_text  = isset( $data['alt_text'] ) ? $data['alt_text'] : get_the_title( $ad_id );
		$target    = isset( $data['target'] ) ? $data['target'] : '_blank';

		if ( empty( $image_url ) ) {
			return '';
		}

		$classes = array( 'wbam-ad', 'wbam-ad-image' );
		if ( ! empty( $options['class'] ) ) {
			$classes[] = sanitize_html_class( $options['class'] );
		}

		$html = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-ad-id="' . esc_attr( $ad_id ) . '">';

		if ( ! empty( $link_url ) ) {
			$html .= '<a href="' . esc_url( $link_url ) . '" target="' . esc_attr( $target ) . '" rel="noopener noreferrer">';
		}

		$html .= '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $alt_text ) . '" />';

		if ( ! empty( $link_url ) ) {
			$html .= '</a>';
		}

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
		$image_url = isset( $data['image_url'] ) ? $data['image_url'] : '';
		$link_url  = isset( $data['link_url'] ) ? $data['link_url'] : '';
		$alt_text  = isset( $data['alt_text'] ) ? $data['alt_text'] : '';
		$target    = isset( $data['target'] ) ? $data['target'] : '_blank';
		?>
		<div class="wbam-field">
			<label for="wbam_image_url"><?php esc_html_e( 'Image URL', 'wb-ad-manager' ); ?></label>
			<div class="wbam-field-input">
				<input type="url" id="wbam_image_url" name="wbam_data[image_url]" value="<?php echo esc_url( $image_url ); ?>" class="regular-text" />
				<button type="button" class="button wbam-upload-image"><?php esc_html_e( 'Upload', 'wb-ad-manager' ); ?></button>
				<button type="button" class="button wbam-remove-image" <?php echo empty( $image_url ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove', 'wb-ad-manager' ); ?></button>
				<div class="wbam-image-preview">
					<?php if ( ! empty( $image_url ) ) : ?>
						<img src="<?php echo esc_url( $image_url ); ?>" alt="" />
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_link_url"><?php esc_html_e( 'Link URL', 'wb-ad-manager' ); ?></label>
			<div class="wbam-field-input">
				<input type="url" id="wbam_link_url" name="wbam_data[link_url]" value="<?php echo esc_url( $link_url ); ?>" class="regular-text" placeholder="https://" />
				<p class="description"><?php esc_html_e( 'Where users go when clicking the ad.', 'wb-ad-manager' ); ?></p>
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_alt_text"><?php esc_html_e( 'Alt Text', 'wb-ad-manager' ); ?></label>
			<div class="wbam-field-input">
				<input type="text" id="wbam_alt_text" name="wbam_data[alt_text]" value="<?php echo esc_attr( $alt_text ); ?>" class="regular-text" />
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_target"><?php esc_html_e( 'Link Target', 'wb-ad-manager' ); ?></label>
			<div class="wbam-field-input">
				<select id="wbam_target" name="wbam_data[target]">
					<option value="_blank" <?php selected( $target, '_blank' ); ?>><?php esc_html_e( 'New Tab', 'wb-ad-manager' ); ?></option>
					<option value="_self" <?php selected( $target, '_self' ); ?>><?php esc_html_e( 'Same Tab', 'wb-ad-manager' ); ?></option>
				</select>
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
			'image_url' => isset( $data['image_url'] ) ? esc_url_raw( $data['image_url'] ) : '',
			'link_url'  => isset( $data['link_url'] ) ? esc_url_raw( $data['link_url'] ) : '',
			'alt_text'  => isset( $data['alt_text'] ) ? sanitize_text_field( $data['alt_text'] ) : '',
			'target'    => isset( $data['target'] ) ? sanitize_text_field( $data['target'] ) : '_blank',
		);
	}
}
