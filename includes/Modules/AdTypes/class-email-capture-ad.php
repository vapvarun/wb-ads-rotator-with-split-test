<?php
/**
 * Email Capture Ad Type
 *
 * Newsletter signup popup/form for email list building.
 *
 * @package WB_Ad_Manager
 * @since   2.2.0
 */

namespace WBAM\Modules\AdTypes;

/**
 * Email_Capture_Ad class.
 */
class Email_Capture_Ad implements Ad_Type_Interface {

	/**
	 * Get ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'email_capture';
	}

	/**
	 * Get name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Email Capture', 'wb-ad-manager' );
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Newsletter signup form to grow your email list.', 'wb-ad-manager' );
	}

	/**
	 * Get icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'dashicons-email-alt';
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

		$headline      = isset( $data['headline'] ) ? $data['headline'] : __( 'Subscribe to our Newsletter', 'wb-ad-manager' );
		$description   = isset( $data['description'] ) ? $data['description'] : '';
		$button_text   = isset( $data['button_text'] ) ? $data['button_text'] : __( 'Subscribe', 'wb-ad-manager' );
		$success_msg   = isset( $data['success_message'] ) ? $data['success_message'] : __( 'Thank you for subscribing!', 'wb-ad-manager' );
		$show_name     = ! empty( $data['show_name_field'] );
		$cookie_days   = isset( $data['cookie_days'] ) ? absint( $data['cookie_days'] ) : 7;
		$redirect_url  = isset( $data['redirect_url'] ) ? $data['redirect_url'] : '';
		$privacy_text  = isset( $data['privacy_text'] ) ? $data['privacy_text'] : '';
		$bg_color      = isset( $data['bg_color'] ) ? $data['bg_color'] : '#ffffff';
		$text_color    = isset( $data['text_color'] ) ? $data['text_color'] : '#1d2327';
		$button_color  = isset( $data['button_color'] ) ? $data['button_color'] : '#2271b1';

		// Check cookie for dismiss.
		$cookie_name = 'wbam_email_dismiss_' . $ad_id;
		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			return '';
		}

		$classes = array( 'wbam-ad', 'wbam-ad-email-capture' );
		if ( ! empty( $options['class'] ) ) {
			$classes[] = sanitize_html_class( $options['class'] );
		}

		$form_id = 'wbam-email-form-' . $ad_id;
		$nonce   = wp_create_nonce( 'wbam_email_capture_' . $ad_id );

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			 data-ad-id="<?php echo esc_attr( $ad_id ); ?>"
			 data-cookie-days="<?php echo esc_attr( $cookie_days ); ?>"
			 style="--wbam-email-bg: <?php echo esc_attr( $bg_color ); ?>; --wbam-email-text: <?php echo esc_attr( $text_color ); ?>; --wbam-email-btn: <?php echo esc_attr( $button_color ); ?>;">

			<button type="button" class="wbam-email-close" aria-label="<?php esc_attr_e( 'Close', 'wb-ad-manager' ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>

			<div class="wbam-email-content">
				<?php if ( ! empty( $headline ) ) : ?>
					<h3 class="wbam-email-headline"><?php echo esc_html( $headline ); ?></h3>
				<?php endif; ?>

				<?php if ( ! empty( $description ) ) : ?>
					<p class="wbam-email-description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>

				<form id="<?php echo esc_attr( $form_id ); ?>" class="wbam-email-form" method="post">
					<input type="hidden" name="action" value="wbam_email_capture">
					<input type="hidden" name="ad_id" value="<?php echo esc_attr( $ad_id ); ?>">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">
					<input type="hidden" name="redirect_url" value="<?php echo esc_url( $redirect_url ); ?>">

					<div class="wbam-email-fields">
						<?php if ( $show_name ) : ?>
							<div class="wbam-email-field">
								<input type="text" name="subscriber_name" placeholder="<?php esc_attr_e( 'Your Name', 'wb-ad-manager' ); ?>" class="wbam-email-input">
							</div>
						<?php endif; ?>

						<div class="wbam-email-field">
							<input type="email" name="subscriber_email" placeholder="<?php esc_attr_e( 'Your Email', 'wb-ad-manager' ); ?>" class="wbam-email-input" required>
						</div>

						<div class="wbam-email-submit">
							<button type="submit" class="wbam-email-button"><?php echo esc_html( $button_text ); ?></button>
						</div>
					</div>

					<?php if ( ! empty( $privacy_text ) ) : ?>
						<p class="wbam-email-privacy"><?php echo esc_html( $privacy_text ); ?></p>
					<?php endif; ?>
				</form>

				<div class="wbam-email-success" style="display: none;">
					<div class="wbam-email-success-icon">
						<span class="dashicons dashicons-yes-alt"></span>
					</div>
					<p class="wbam-email-success-message"><?php echo esc_html( $success_msg ); ?></p>
				</div>

				<div class="wbam-email-error" style="display: none;">
					<p class="wbam-email-error-message"></p>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render metabox.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Data.
	 */
	public function render_metabox( $ad_id, $data ) {
		$headline       = isset( $data['headline'] ) ? $data['headline'] : __( 'Subscribe to our Newsletter', 'wb-ad-manager' );
		$description    = isset( $data['description'] ) ? $data['description'] : '';
		$button_text    = isset( $data['button_text'] ) ? $data['button_text'] : __( 'Subscribe', 'wb-ad-manager' );
		$success_message = isset( $data['success_message'] ) ? $data['success_message'] : __( 'Thank you for subscribing!', 'wb-ad-manager' );
		$show_name      = ! empty( $data['show_name_field'] );
		$cookie_days    = isset( $data['cookie_days'] ) ? absint( $data['cookie_days'] ) : 7;
		$redirect_url   = isset( $data['redirect_url'] ) ? $data['redirect_url'] : '';
		$privacy_text   = isset( $data['privacy_text'] ) ? $data['privacy_text'] : '';
		$bg_color       = isset( $data['bg_color'] ) ? $data['bg_color'] : '#ffffff';
		$text_color     = isset( $data['text_color'] ) ? $data['text_color'] : '#1d2327';
		$button_color   = isset( $data['button_color'] ) ? $data['button_color'] : '#2271b1';
		?>
		<div class="wbam-field">
			<label for="wbam_email_headline"><?php esc_html_e( 'Headline', 'wb-ad-manager' ); ?></label>
			<div class="wbam-field-input">
				<input type="text" id="wbam_email_headline" name="wbam_data[headline]" value="<?php echo esc_attr( $headline ); ?>" class="large-text">
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_email_description"><?php esc_html_e( 'Description', 'wb-ad-manager' ); ?></label>
			<div class="wbam-field-input">
				<textarea id="wbam_email_description" name="wbam_data[description]" rows="2" class="large-text"><?php echo esc_textarea( $description ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Optional text below the headline.', 'wb-ad-manager' ); ?></p>
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_email_button_text"><?php esc_html_e( 'Button Text', 'wb-ad-manager' ); ?></label>
			<div class="wbam-field-input">
				<input type="text" id="wbam_email_button_text" name="wbam_data[button_text]" value="<?php echo esc_attr( $button_text ); ?>" class="regular-text">
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_email_success_message"><?php esc_html_e( 'Success Message', 'wb-ad-manager' ); ?></label>
			<div class="wbam-field-input">
				<input type="text" id="wbam_email_success_message" name="wbam_data[success_message]" value="<?php echo esc_attr( $success_message ); ?>" class="large-text">
				<p class="description"><?php esc_html_e( 'Message shown after successful subscription.', 'wb-ad-manager' ); ?></p>
			</div>
		</div>

		<div class="wbam-field">
			<label><?php esc_html_e( 'Form Fields', 'wb-ad-manager' ); ?></label>
			<div class="wbam-field-input">
				<label>
					<input type="checkbox" name="wbam_data[show_name_field]" value="1" <?php checked( $show_name ); ?>>
					<?php esc_html_e( 'Show Name Field', 'wb-ad-manager' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Email field is always shown.', 'wb-ad-manager' ); ?></p>
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_email_redirect_url"><?php esc_html_e( 'Redirect URL (Optional)', 'wb-ad-manager' ); ?></label>
			<div class="wbam-field-input">
				<input type="url" id="wbam_email_redirect_url" name="wbam_data[redirect_url]" value="<?php echo esc_url( $redirect_url ); ?>" class="regular-text" placeholder="https://">
				<p class="description"><?php esc_html_e( 'Redirect to this URL after subscription. Leave empty to show success message.', 'wb-ad-manager' ); ?></p>
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_email_cookie_days"><?php esc_html_e( 'Dismiss Duration (Days)', 'wb-ad-manager' ); ?></label>
			<div class="wbam-field-input">
				<input type="number" id="wbam_email_cookie_days" name="wbam_data[cookie_days]" value="<?php echo esc_attr( $cookie_days ); ?>" min="0" max="365" class="small-text">
				<p class="description"><?php esc_html_e( 'Days to hide after user closes. 0 = show every visit.', 'wb-ad-manager' ); ?></p>
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_email_privacy_text"><?php esc_html_e( 'Privacy Text', 'wb-ad-manager' ); ?></label>
			<div class="wbam-field-input">
				<input type="text" id="wbam_email_privacy_text" name="wbam_data[privacy_text]" value="<?php echo esc_attr( $privacy_text ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'We respect your privacy. Unsubscribe anytime.', 'wb-ad-manager' ); ?>">
			</div>
		</div>

		<h4><?php esc_html_e( 'Colors', 'wb-ad-manager' ); ?></h4>

		<div class="wbam-field wbam-field-inline">
			<label for="wbam_email_bg_color"><?php esc_html_e( 'Background', 'wb-ad-manager' ); ?></label>
			<input type="color" id="wbam_email_bg_color" name="wbam_data[bg_color]" value="<?php echo esc_attr( $bg_color ); ?>">

			<label for="wbam_email_text_color"><?php esc_html_e( 'Text', 'wb-ad-manager' ); ?></label>
			<input type="color" id="wbam_email_text_color" name="wbam_data[text_color]" value="<?php echo esc_attr( $text_color ); ?>">

			<label for="wbam_email_button_color"><?php esc_html_e( 'Button', 'wb-ad-manager' ); ?></label>
			<input type="color" id="wbam_email_button_color" name="wbam_data[button_color]" value="<?php echo esc_attr( $button_color ); ?>">
		</div>

		<div class="wbam-field">
			<h4><?php esc_html_e( 'Integration', 'wb-ad-manager' ); ?></h4>
			<p class="description">
				<?php
				printf(
					/* translators: %s: hook name */
					esc_html__( 'Developers can integrate email services using the %s action hook.', 'wb-ad-manager' ),
					'<code>wbam_email_captured</code>'
				);
				?>
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
		return array(
			'headline'        => isset( $data['headline'] ) ? sanitize_text_field( $data['headline'] ) : '',
			'description'     => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'button_text'     => isset( $data['button_text'] ) ? sanitize_text_field( $data['button_text'] ) : __( 'Subscribe', 'wb-ad-manager' ),
			'success_message' => isset( $data['success_message'] ) ? sanitize_text_field( $data['success_message'] ) : '',
			'show_name_field' => ! empty( $data['show_name_field'] ),
			'cookie_days'     => isset( $data['cookie_days'] ) ? absint( $data['cookie_days'] ) : 7,
			'redirect_url'    => isset( $data['redirect_url'] ) ? esc_url_raw( $data['redirect_url'] ) : '',
			'privacy_text'    => isset( $data['privacy_text'] ) ? sanitize_text_field( $data['privacy_text'] ) : '',
			'bg_color'        => isset( $data['bg_color'] ) ? sanitize_hex_color( $data['bg_color'] ) : '#ffffff',
			'text_color'      => isset( $data['text_color'] ) ? sanitize_hex_color( $data['text_color'] ) : '#1d2327',
			'button_color'    => isset( $data['button_color'] ) ? sanitize_hex_color( $data['button_color'] ) : '#2271b1',
		);
	}
}
