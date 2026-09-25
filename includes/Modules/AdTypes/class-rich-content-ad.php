<?php
/**
 * Rich Content Ad Type
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\AdTypes;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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

		$placement = isset( $options['placement'] ) ? $options['placement'] : '';

		$body     = wp_kses_post( wpautop( $content ) );
		$link_url = isset( $data['link_url'] ) ? $data['link_url'] : '';

		// Make the creative clickable through its click URL with a full-cover
		// overlay link beside it, never around it: block content inside an <a>
		// let paragraph injection place other ads inside this link. Content
		// that already carries its own links keeps them and gets no overlay,
		// which would sit on top of them.
		$overlay = '';
		if ( '' !== $link_url && false === stripos( $body, '<a ' ) ) {
			$target = isset( $data['target'] ) ? $data['target'] : '_blank';
			$host   = wp_parse_url( $link_url, PHP_URL_HOST );
			/* translators: %s: advertiser's website host name. */
			$label   = sprintf( __( 'Visit %s', 'wb-ads-rotator-with-split-test' ), $host ? $host : $link_url );
			$overlay = '<a class="wbam-ad-rich-content__link" href="' . esc_url( $link_url ) . '" target="' . esc_attr( $target ) . '" rel="noopener noreferrer" aria-label="' . esc_attr( $label ) . '"></a>';

			$classes[] = 'wbam-ad-rich-content--linked';
		}

		$html  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-ad-id="' . esc_attr( $ad_id ) . '" data-placement="' . esc_attr( $placement ) . '">';
		$html .= $body . $overlay;
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
		$content  = isset( $data['content'] ) ? $data['content'] : '';
		$link_url = isset( $data['link_url'] ) ? $data['link_url'] : '';
		$target   = isset( $data['target'] ) ? $data['target'] : '_blank';
		?>
		<div class="wbam-field wbam-field-full">
			<label for="wbam_rich_content"><?php esc_html_e( 'Ad Content', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<p class="description"><?php esc_html_e( 'Enter HTML content for your ad. Basic HTML tags are supported.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<textarea id="wbam_rich_content" name="wbam_data[content]" rows="10" class="large-text"><?php echo esc_textarea( $content ); ?></textarea>
			</div>
		</div>

		<?php
		// Field names carry a rich_ prefix: every type's panel posts in the
		// same form, and the image panel already owns wbam_data[link_url].
		?>
		<div class="wbam-field">
			<label for="wbam_rich_link_url"><?php esc_html_e( 'Link URL', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<input type="url" id="wbam_rich_link_url" name="wbam_data[rich_link_url]" value="<?php echo esc_url( $link_url ); ?>" class="regular-text" placeholder="https://" />
				<p class="description"><?php esc_html_e( 'Where users go when clicking the ad. Leave empty if the content has its own links.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_rich_target"><?php esc_html_e( 'Link Target', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<select id="wbam_rich_target" name="wbam_data[rich_target]">
					<option value="_blank" <?php selected( $target, '_blank' ); ?>><?php esc_html_e( 'New Tab', 'wb-ads-rotator-with-split-test' ); ?></option>
					<option value="_self" <?php selected( $target, '_self' ); ?>><?php esc_html_e( 'Same Tab', 'wb-ads-rotator-with-split-test' ); ?></option>
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
			'content'  => isset( $data['content'] ) ? wp_kses_post( $data['content'] ) : '',
			'link_url' => isset( $data['rich_link_url'] ) ? esc_url_raw( $data['rich_link_url'] ) : '',
			'target'   => isset( $data['rich_target'] ) && '_self' === $data['rich_target'] ? '_self' : '_blank',
		);
	}
}
