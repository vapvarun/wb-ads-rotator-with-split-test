<?php
/**
 * Email Capture Ad Type
 *
 * Newsletter signup popup/form for email list building.
 *
 * @package WB_Ad_Manager
 * @since   2.2.0
 *
 * ## Available Hooks
 *
 * ### Actions:
 * - `wbam_email_form_before` - Before form renders (passes $ad_id, $data)
 * - `wbam_email_form_after` - After form renders
 * - `wbam_email_form_before_fields` - Before form fields
 * - `wbam_email_form_after_fields` - After form fields (before submit button)
 * - `wbam_email_form_after_name` - After name field (if shown)
 * - `wbam_email_form_after_email` - After email field
 *
 * ### Filters:
 * - `wbam_email_form_data` - Modify form configuration data
 * - `wbam_email_form_classes` - Modify wrapper CSS classes
 * - `wbam_email_form_fields` - Modify/add form fields
 * - `wbam_email_form_button_text` - Modify button text
 * - `wbam_email_form_success_message` - Modify success message
 * - `wbam_email_form_privacy_text` - Modify privacy text
 * - `wbam_email_form_placeholders` - Modify field placeholders
 * - `wbam_email_form_show_cookie_check` - Control cookie check (return false to always show)
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
		return __( 'Email Capture', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Newsletter signup form to grow your email list.', 'wb-ads-rotator-with-split-test' );
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

		/**
		 * Filter the form configuration data.
		 *
		 * @since 2.2.0
		 * @param array $data  Form configuration.
		 * @param int   $ad_id Ad ID.
		 */
		$data = apply_filters( 'wbam_email_form_data', $data, $ad_id );

		$headline      = isset( $data['headline'] ) ? $data['headline'] : __( 'Subscribe to our Newsletter', 'wb-ads-rotator-with-split-test' );
		$description   = isset( $data['description'] ) ? $data['description'] : '';
		$button_text   = isset( $data['button_text'] ) ? $data['button_text'] : __( 'Subscribe', 'wb-ads-rotator-with-split-test' );
		$success_msg   = isset( $data['success_message'] ) ? $data['success_message'] : __( 'Thank you for subscribing!', 'wb-ads-rotator-with-split-test' );
		$show_name     = ! empty( $data['show_name_field'] );
		$cookie_days   = isset( $data['cookie_days'] ) ? absint( $data['cookie_days'] ) : 7;
		$redirect_url  = isset( $data['redirect_url'] ) ? $data['redirect_url'] : '';
		$privacy_text  = isset( $data['privacy_text'] ) ? $data['privacy_text'] : '';
		$bg_color      = isset( $data['bg_color'] ) ? $data['bg_color'] : '#ffffff';
		$text_color    = isset( $data['text_color'] ) ? $data['text_color'] : '#1d2327';
		$button_color  = isset( $data['button_color'] ) ? $data['button_color'] : '#2271b1';

		/**
		 * Filter the button text.
		 *
		 * @since 2.2.0
		 * @param string $button_text Button text.
		 * @param int    $ad_id       Ad ID.
		 * @param array  $data        Form data.
		 */
		$button_text = apply_filters( 'wbam_email_form_button_text', $button_text, $ad_id, $data );

		/**
		 * Filter the success message.
		 *
		 * @since 2.2.0
		 * @param string $success_msg Success message.
		 * @param int    $ad_id       Ad ID.
		 * @param array  $data        Form data.
		 */
		$success_msg = apply_filters( 'wbam_email_form_success_message', $success_msg, $ad_id, $data );

		/**
		 * Filter the privacy text.
		 *
		 * @since 2.2.0
		 * @param string $privacy_text Privacy text.
		 * @param int    $ad_id        Ad ID.
		 * @param array  $data         Form data.
		 */
		$privacy_text = apply_filters( 'wbam_email_form_privacy_text', $privacy_text, $ad_id, $data );

		// Check cookie for dismiss.
		$cookie_name = 'wbam_email_dismiss_' . $ad_id;

		/**
		 * Filter whether to check the dismiss cookie.
		 *
		 * Return false to always show the form regardless of cookie.
		 *
		 * @since 2.2.0
		 * @param bool $check_cookie Whether to check cookie.
		 * @param int  $ad_id        Ad ID.
		 */
		$check_cookie = apply_filters( 'wbam_email_form_show_cookie_check', true, $ad_id );

		if ( $check_cookie && isset( $_COOKIE[ $cookie_name ] ) ) {
			return '';
		}

		$classes = array( 'wbam-ad', 'wbam-ad-email-capture' );
		if ( ! empty( $options['class'] ) ) {
			$classes[] = sanitize_html_class( $options['class'] );
		}

		/**
		 * Filter the wrapper CSS classes.
		 *
		 * @since 2.2.0
		 * @param array $classes CSS classes.
		 * @param int   $ad_id   Ad ID.
		 * @param array $options Render options.
		 */
		$classes = apply_filters( 'wbam_email_form_classes', $classes, $ad_id, $options );

		$form_id = 'wbam-email-form-' . $ad_id;
		$nonce   = wp_create_nonce( 'wbam_email_capture_' . $ad_id );

		// Placeholders.
		$placeholders = array(
			'name'  => __( 'Your Name', 'wb-ads-rotator-with-split-test' ),
			'email' => __( 'Your Email', 'wb-ads-rotator-with-split-test' ),
		);

		/**
		 * Filter the field placeholders.
		 *
		 * @since 2.2.0
		 * @param array $placeholders Placeholder texts.
		 * @param int   $ad_id        Ad ID.
		 */
		$placeholders = apply_filters( 'wbam_email_form_placeholders', $placeholders, $ad_id );

		ob_start();

		/**
		 * Fires before the email capture form.
		 *
		 * @since 2.2.0
		 * @param int   $ad_id Ad ID.
		 * @param array $data  Form data.
		 */
		do_action( 'wbam_email_form_before', $ad_id, $data );
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			 data-ad-id="<?php echo esc_attr( $ad_id ); ?>"
			 data-cookie-days="<?php echo esc_attr( $cookie_days ); ?>"
			 style="--wbam-email-bg: <?php echo esc_attr( $bg_color ); ?>; --wbam-email-text: <?php echo esc_attr( $text_color ); ?>; --wbam-email-btn: <?php echo esc_attr( $button_color ); ?>;">

			<button type="button" class="wbam-email-close" aria-label="<?php esc_attr_e( 'Close', 'wb-ads-rotator-with-split-test' ); ?>">
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

					<?php
					/**
					 * Fires before the form fields.
					 *
					 * @since 2.2.0
					 * @param int   $ad_id Ad ID.
					 * @param array $data  Form data.
					 */
					do_action( 'wbam_email_form_before_fields', $ad_id, $data );
					?>

					<div class="wbam-email-fields">
						<?php if ( $show_name ) : ?>
							<div class="wbam-email-field wbam-email-field-name">
								<input type="text" name="subscriber_name" placeholder="<?php echo esc_attr( $placeholders['name'] ); ?>" class="wbam-email-input">
							</div>
							<?php
							/**
							 * Fires after the name field.
							 *
							 * @since 2.2.0
							 * @param int   $ad_id Ad ID.
							 * @param array $data  Form data.
							 */
							do_action( 'wbam_email_form_after_name', $ad_id, $data );
							?>
						<?php endif; ?>

						<div class="wbam-email-field wbam-email-field-email">
							<input type="email" name="subscriber_email" placeholder="<?php echo esc_attr( $placeholders['email'] ); ?>" class="wbam-email-input" required>
						</div>

						<?php
						/**
						 * Fires after the email field.
						 *
						 * @since 2.2.0
						 * @param int   $ad_id Ad ID.
						 * @param array $data  Form data.
						 */
						do_action( 'wbam_email_form_after_email', $ad_id, $data );
						?>

						<?php
						/**
						 * Fires after all fields, before the submit button.
						 *
						 * Use this to add custom fields like phone, company, etc.
						 *
						 * @since 2.2.0
						 * @param int   $ad_id Ad ID.
						 * @param array $data  Form data.
						 */
						do_action( 'wbam_email_form_after_fields', $ad_id, $data );
						?>

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
		/**
		 * Fires after the email capture form.
		 *
		 * @since 2.2.0
		 * @param int   $ad_id Ad ID.
		 * @param array $data  Form data.
		 */
		do_action( 'wbam_email_form_after', $ad_id, $data );

		return ob_get_clean();
	}

	/**
	 * Render metabox.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Data.
	 */
	public function render_metabox( $ad_id, $data ) {
		$headline       = isset( $data['headline'] ) ? $data['headline'] : __( 'Subscribe to our Newsletter', 'wb-ads-rotator-with-split-test' );
		$description    = isset( $data['description'] ) ? $data['description'] : '';
		$button_text    = isset( $data['button_text'] ) ? $data['button_text'] : __( 'Subscribe', 'wb-ads-rotator-with-split-test' );
		$success_message = isset( $data['success_message'] ) ? $data['success_message'] : __( 'Thank you for subscribing!', 'wb-ads-rotator-with-split-test' );
		$show_name      = ! empty( $data['show_name_field'] );
		$cookie_days    = isset( $data['cookie_days'] ) ? absint( $data['cookie_days'] ) : 7;
		$redirect_url   = isset( $data['redirect_url'] ) ? $data['redirect_url'] : '';
		$privacy_text   = isset( $data['privacy_text'] ) ? $data['privacy_text'] : '';
		$bg_color       = isset( $data['bg_color'] ) ? $data['bg_color'] : '#ffffff';
		$text_color     = isset( $data['text_color'] ) ? $data['text_color'] : '#1d2327';
		$button_color   = isset( $data['button_color'] ) ? $data['button_color'] : '#2271b1';
		?>
		<div class="wbam-field">
			<label for="wbam_email_headline"><?php esc_html_e( 'Headline', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<input type="text" id="wbam_email_headline" name="wbam_data[headline]" value="<?php echo esc_attr( $headline ); ?>" class="large-text">
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_email_description"><?php esc_html_e( 'Description', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<textarea id="wbam_email_description" name="wbam_data[description]" rows="2" class="large-text"><?php echo esc_textarea( $description ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Optional text below the headline.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_email_button_text"><?php esc_html_e( 'Button Text', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<input type="text" id="wbam_email_button_text" name="wbam_data[button_text]" value="<?php echo esc_attr( $button_text ); ?>" class="regular-text">
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_email_success_message"><?php esc_html_e( 'Success Message', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<input type="text" id="wbam_email_success_message" name="wbam_data[success_message]" value="<?php echo esc_attr( $success_message ); ?>" class="large-text">
				<p class="description"><?php esc_html_e( 'Message shown after successful subscription.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>
		</div>

		<div class="wbam-field">
			<label><?php esc_html_e( 'Form Fields', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<label>
					<input type="checkbox" name="wbam_data[show_name_field]" value="1" <?php checked( $show_name ); ?>>
					<?php esc_html_e( 'Show Name Field', 'wb-ads-rotator-with-split-test' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Email field is always shown.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_email_redirect_url"><?php esc_html_e( 'Redirect URL (Optional)', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<input type="url" id="wbam_email_redirect_url" name="wbam_data[redirect_url]" value="<?php echo esc_url( $redirect_url ); ?>" class="regular-text" placeholder="https://">
				<p class="description"><?php esc_html_e( 'Redirect to this URL after subscription. Leave empty to show success message.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_email_cookie_days"><?php esc_html_e( 'Dismiss Duration (Days)', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<input type="number" id="wbam_email_cookie_days" name="wbam_data[cookie_days]" value="<?php echo esc_attr( $cookie_days ); ?>" min="0" max="365" class="small-text">
				<p class="description"><?php esc_html_e( 'Days to hide after user closes. 0 = show every visit.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_email_privacy_text"><?php esc_html_e( 'Privacy Text', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<input type="text" id="wbam_email_privacy_text" name="wbam_data[privacy_text]" value="<?php echo esc_attr( $privacy_text ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'We respect your privacy. Unsubscribe anytime.', 'wb-ads-rotator-with-split-test' ); ?>">
			</div>
		</div>

		<h4><?php esc_html_e( 'Colors', 'wb-ads-rotator-with-split-test' ); ?></h4>

		<div class="wbam-field wbam-field-inline">
			<label for="wbam_email_bg_color"><?php esc_html_e( 'Background', 'wb-ads-rotator-with-split-test' ); ?></label>
			<input type="color" id="wbam_email_bg_color" name="wbam_data[bg_color]" value="<?php echo esc_attr( $bg_color ); ?>">

			<label for="wbam_email_text_color"><?php esc_html_e( 'Text', 'wb-ads-rotator-with-split-test' ); ?></label>
			<input type="color" id="wbam_email_text_color" name="wbam_data[text_color]" value="<?php echo esc_attr( $text_color ); ?>">

			<label for="wbam_email_button_color"><?php esc_html_e( 'Button', 'wb-ads-rotator-with-split-test' ); ?></label>
			<input type="color" id="wbam_email_button_color" name="wbam_data[button_color]" value="<?php echo esc_attr( $button_color ); ?>">
		</div>

		<div class="wbam-field">
			<h4><?php esc_html_e( 'Developer Hooks', 'wb-ads-rotator-with-split-test' ); ?></h4>
			<p class="description">
				<?php esc_html_e( 'Available hooks for developers:', 'wb-ads-rotator-with-split-test' ); ?>
			</p>
			<ul class="wbam-hooks-list" style="margin-left: 20px; font-size: 12px;">
				<li><code>wbam_email_captured</code> - <?php esc_html_e( 'After email is submitted (integrate with Mailchimp, etc.)', 'wb-ads-rotator-with-split-test' ); ?></li>
				<li><code>wbam_email_form_before</code> / <code>after</code> - <?php esc_html_e( 'Before/after form renders', 'wb-ads-rotator-with-split-test' ); ?></li>
				<li><code>wbam_email_form_after_fields</code> - <?php esc_html_e( 'Add custom fields', 'wb-ads-rotator-with-split-test' ); ?></li>
				<li><code>wbam_email_form_validation</code> - <?php esc_html_e( 'Custom validation', 'wb-ads-rotator-with-split-test' ); ?></li>
			</ul>
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
			'button_text'     => isset( $data['button_text'] ) ? sanitize_text_field( $data['button_text'] ) : __( 'Subscribe', 'wb-ads-rotator-with-split-test' ),
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
