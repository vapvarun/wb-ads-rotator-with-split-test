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

		/**
		 * Filter code ad content before rendering.
		 *
		 * Allows developers to apply custom sanitization or processing
		 * to code ads for additional security measures.
		 *
		 * @since 1.2.0
		 *
		 * @param string $code    The raw ad code.
		 * @param int    $ad_id   The ad post ID.
		 * @param array  $options Rendering options.
		 */
		$code = apply_filters( 'wbam_code_ad_content', $code, $ad_id, $options );

		$classes = array( 'wbam-ad', 'wbam-ad-code' );
		if ( ! empty( $options['class'] ) ) {
			$classes[] = sanitize_html_class( $options['class'] );
		}

		$placement = isset( $options['placement'] ) ? $options['placement'] : '';

		// Check if sandbox mode is enabled for this ad.
		$use_sandbox = get_post_meta( $ad_id, '_wbam_code_sandbox', true );

		/**
		 * Filter whether to use iframe sandbox for this code ad.
		 *
		 * When enabled, the ad code will be rendered in a sandboxed iframe
		 * for additional security isolation.
		 *
		 * @since 1.2.0
		 *
		 * @param bool   $use_sandbox Whether to use sandbox mode.
		 * @param int    $ad_id       The ad post ID.
		 * @param string $code        The ad code.
		 */
		$use_sandbox = apply_filters( 'wbam_code_ad_use_sandbox', (bool) $use_sandbox, $ad_id, $code );

		$html = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-ad-id="' . esc_attr( $ad_id ) . '" data-placement="' . esc_attr( $placement ) . '">';

		if ( $use_sandbox ) {
			// Render in sandboxed iframe for security isolation.
			$html .= $this->render_sandboxed( $code, $ad_id );
		} else {
			// Direct output for maximum compatibility with ad networks.
			$html .= $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentional: Ad code must be output unmodified. Access restricted via unfiltered_html capability check in save().
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render code in a sandboxed iframe for security isolation.
	 *
	 * @since 1.2.0
	 *
	 * @param string $code  The ad code.
	 * @param int    $ad_id The ad post ID.
	 * @return string
	 */
	private function render_sandboxed( $code, $ad_id ) {
		// Generate unique ID for this iframe.
		$iframe_id = 'wbam-sandbox-' . $ad_id . '-' . wp_rand( 1000, 9999 );

		// Encode the code for safe embedding.
		$encoded_code = rawurlencode( $code );

		// Build srcdoc content with proper HTML structure.
		$srcdoc = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{margin:0;padding:0;overflow:hidden;}</style></head><body>' . $code . '</body></html>';

		// Sandbox attributes allow scripts and same-origin for ad functionality.
		$sandbox_attrs = 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox';

		/**
		 * Filter the sandbox attributes for code ad iframes.
		 *
		 * @since 1.2.0
		 *
		 * @param string $sandbox_attrs The sandbox attribute values.
		 * @param int    $ad_id         The ad post ID.
		 */
		$sandbox_attrs = apply_filters( 'wbam_code_ad_sandbox_attrs', $sandbox_attrs, $ad_id );

		$html  = '<iframe id="' . esc_attr( $iframe_id ) . '" ';
		$html .= 'class="wbam-code-sandbox" ';
		$html .= 'sandbox="' . esc_attr( $sandbox_attrs ) . '" ';
		$html .= 'srcdoc="' . esc_attr( $srcdoc ) . '" ';
		$html .= 'style="width:100%;border:none;overflow:hidden;" ';
		$html .= 'scrolling="no" ';
		$html .= 'loading="lazy">';
		$html .= '</iframe>';

		// Add auto-resize script.
		$html .= '<script>';
		$html .= '(function(){';
		$html .= 'var f=document.getElementById("' . esc_js( $iframe_id ) . '");';
		$html .= 'if(f){f.onload=function(){try{f.style.height=f.contentWindow.document.body.scrollHeight+"px";}catch(e){f.style.height="250px";}};}';
		$html .= '})();';
		$html .= '</script>';

		return $html;
	}

	/**
	 * Render metabox.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Data.
	 */
	public function render_metabox( $ad_id, $data ) {
		$code        = isset( $data['code'] ) ? $data['code'] : '';
		$use_sandbox = get_post_meta( $ad_id, '_wbam_code_sandbox', true );
		?>
		<div class="wbam-field wbam-field-full">
			<label for="wbam_code"><?php esc_html_e( 'Ad Code', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<p class="description"><?php esc_html_e( 'Paste your ad network code (AdSense, Media.net, etc.) or custom HTML/JavaScript.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<textarea id="wbam_code" name="wbam_data[code]" rows="12" class="large-text code"><?php echo esc_textarea( $code ); ?></textarea>
			</div>
		</div>

		<div class="wbam-field">
			<label>
				<input type="checkbox" name="wbam_code_sandbox" value="1" <?php checked( $use_sandbox, '1' ); ?>>
				<?php esc_html_e( 'Enable Sandbox Mode (Security Isolation)', 'wb-ads-rotator-with-split-test' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'Renders the ad code in a sandboxed iframe for additional security isolation. Note: Some ad networks may not work properly in sandbox mode.', 'wb-ads-rotator-with-split-test' ); ?>
			</p>
		</div>

		<div class="wbam-security-notice" style="background: #fff8e5; border-left: 4px solid #ffb900; padding: 12px; margin-top: 15px;">
			<p style="margin: 0;">
				<strong><span class="dashicons dashicons-shield" style="color: #ffb900;"></span> <?php esc_html_e( 'Security Notice', 'wb-ads-rotator-with-split-test' ); ?></strong>
			</p>
			<p style="margin: 8px 0 0;">
				<?php esc_html_e( 'Code ads execute JavaScript directly on your site. Only paste code from trusted ad networks. Administrators with "unfiltered_html" capability can save any code.', 'wb-ads-rotator-with-split-test' ); ?>
			</p>
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

		// Save sandbox mode setting.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the parent save handler.
		$use_sandbox = isset( $_POST['wbam_code_sandbox'] ) ? '1' : '';
		update_post_meta( $ad_id, '_wbam_code_sandbox', $use_sandbox );

		return array(
			'code' => $code,
		);
	}
}
