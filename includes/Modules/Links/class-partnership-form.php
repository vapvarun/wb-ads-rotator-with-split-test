<?php
/**
 * Partnership Form Class
 *
 * Handles the partnership inquiry form shortcode and submission.
 *
 * @package WB_Ad_Manager
 * @since   2.2.0
 */

namespace WBAM\Modules\Links;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use WBAM\Core\Singleton;

/**
 * Partnership Form class.
 *
 * ## Available Hooks
 *
 * ### Actions:
 * - `wbam_partnership_form_before` - Before form renders
 * - `wbam_partnership_form_after` - After form renders
 * - `wbam_partnership_form_before_fields` - Before form fields (inside form tag)
 * - `wbam_partnership_form_after_fields` - After form fields (before submit button)
 * - `wbam_partnership_form_after_name_email` - After name/email row
 * - `wbam_partnership_form_after_website` - After website field
 * - `wbam_partnership_form_after_type` - After partnership type field
 * - `wbam_partnership_form_after_anchor` - After anchor text field
 * - `wbam_partnership_form_after_budget` - After budget fields
 * - `wbam_partnership_form_after_message` - After message field
 * - `wbam_partnership_form_submission_before` - Before processing submission
 * - `wbam_partnership_form_submission_after` - After successful submission
 *
 * ### Filters:
 * - `wbam_partnership_form_attributes` - Modify shortcode attributes
 * - `wbam_partnership_form_fields` - Modify/add form fields array
 * - `wbam_partnership_form_types` - Modify available partnership types
 * - `wbam_partnership_form_scripts` - Modify localized script data
 * - `wbam_partnership_form_validation` - Custom validation (return WP_Error to fail)
 * - `wbam_partnership_form_data` - Modify submission data before save
 * - `wbam_partnership_form_success_message` - Modify success message
 * - `wbam_partnership_form_error_messages` - Modify error messages
 * - `wbam_partnership_form_styles` - Modify/disable the form's CSS
 * - `wbam_partnership_form_wrapper_class` - Modify wrapper CSS classes
 * - `wbam_partnership_form_button_class` - Modify submit button CSS classes
 * - `wbam_partnership_form_duplicate_hours` - Change duplicate check timeframe (default: 24)
 */
class Partnership_Form {

	use Singleton;

	/**
	 * Manager instance.
	 *
	 * @var Partnership_Manager
	 */
	private $manager;

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->manager = Partnership_Manager::get_instance();
	}

	/**
	 * Initialize the form.
	 */
	public function init() {
		add_shortcode( 'wbam_partnership_inquiry', array( $this, 'render_form' ) );
		add_action( 'wp_ajax_wbam_submit_partnership', array( $this, 'handle_ajax_submission' ) );
		add_action( 'wp_ajax_nopriv_wbam_submit_partnership', array( $this, 'handle_ajax_submission' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts for the form.
	 */
	public function enqueue_scripts() {
		global $post;

		// Pages whose content holds the shortcode load it in the head; forms
		// rendered any other way (the Pro dashboard's do_shortcode()) load it
		// from render_form().
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'wbam_partnership_inquiry' ) ) {
			$this->enqueue_form_assets();
		}
	}

	/**
	 * Enqueue the form's stylesheet and localized script once per request.
	 */
	private function enqueue_form_assets() {
		if ( wp_script_is( 'wbam-partnership-form', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style( 'wbam-partnership-form', WBAM_URL . 'assets/css/partnership-form.css', array(), WBAM_VERSION );
		$this->apply_styles_filter();

		wp_enqueue_script(
			'wbam-partnership-form',
			WBAM_URL . 'assets/js/partnership-form.js',
			array( 'jquery' ),
			WBAM_VERSION,
			true
		);

		$script_data = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wbam_partnership_form' ),
			'i18n'    => $this->get_i18n_strings(),
		);

		/**
		 * Filter the localized script data.
		 *
		 * @since 2.2.0
		 * @param array $script_data Script data.
		 */
		$script_data = apply_filters( 'wbam_partnership_form_scripts', $script_data );

		wp_localize_script( 'wbam-partnership-form', 'wbamPartnership', $script_data );
	}

	/**
	 * Get i18n strings for JavaScript.
	 *
	 * @return array
	 */
	private function get_i18n_strings() {
		$strings = array(
			'submitting'   => __( 'Submitting...', 'wb-ads-rotator-with-split-test' ),
			'success'      => __( 'Thank you! Your inquiry has been submitted successfully. We will review it and get back to you soon.', 'wb-ads-rotator-with-split-test' ),
			'error'        => __( 'An error occurred. Please try again.', 'wb-ads-rotator-with-split-test' ),
			'duplicate'    => __( 'You have already submitted an inquiry recently. Please wait before submitting again.', 'wb-ads-rotator-with-split-test' ),
			'required'     => __( 'Please fill in all required fields.', 'wb-ads-rotator-with-split-test' ),
			'invalidEmail' => __( 'Please enter a valid email address.', 'wb-ads-rotator-with-split-test' ),
			'invalidUrl'   => __( 'Please enter a valid website URL.', 'wb-ads-rotator-with-split-test' ),
		);

		/**
		 * Filter the error messages for the form.
		 *
		 * @since 2.2.0
		 * @param array $strings Error message strings.
		 */
		return apply_filters( 'wbam_partnership_form_error_messages', $strings );
	}

	/**
	 * Render the partnership inquiry form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_form( $atts ) {
		$this->enqueue_form_assets();

		$atts = shortcode_atts(
			array(
				'title'            => __( 'Link Partnership Inquiry', 'wb-ads-rotator-with-split-test' ),
				'description'      => __( 'Interested in a link partnership? Fill out the form below and we will get back to you.', 'wb-ads-rotator-with-split-test' ),
				'show_budget'      => 'yes',
				'show_target_page' => 'no',
				'show_anchor'      => 'yes',
				'show_message'     => 'yes',
				'button_text'      => __( 'Submit Inquiry', 'wb-ads-rotator-with-split-test' ),
				'class'            => '',
			),
			$atts,
			'wbam_partnership_inquiry'
		);

		/**
		 * Filter the shortcode attributes.
		 *
		 * @since 2.2.0
		 * @param array $atts Shortcode attributes.
		 */
		$atts = apply_filters( 'wbam_partnership_form_attributes', $atts );

		// Build wrapper classes.
		$wrapper_classes = array( 'wbam-partnership-form-wrap' );
		if ( ! empty( $atts['class'] ) ) {
			$wrapper_classes[] = sanitize_html_class( $atts['class'] );
		}

		/**
		 * Filter the wrapper CSS classes.
		 *
		 * @since 2.2.0
		 * @param array $wrapper_classes CSS classes.
		 * @param array $atts            Shortcode attributes.
		 */
		$wrapper_classes = apply_filters( 'wbam_partnership_form_wrapper_class', $wrapper_classes, $atts );

		// Get partnership types.
		$partnership_types = Partnership::get_partnership_types();

		/**
		 * Filter the available partnership types.
		 *
		 * @since 2.2.0
		 * @param array $partnership_types Partnership types.
		 */
		$partnership_types = apply_filters( 'wbam_partnership_form_types', $partnership_types );

		ob_start();

		/**
		 * Fires before the partnership form renders.
		 *
		 * @since 2.2.0
		 * @param array $atts Shortcode attributes.
		 */
		do_action( 'wbam_partnership_form_before', $atts );
		?>
		<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<h3 class="wbam-partnership-title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( ! empty( $atts['description'] ) ) : ?>
				<p class="wbam-partnership-description"><?php echo esc_html( $atts['description'] ); ?></p>
			<?php endif; ?>

			<form id="wbam-partnership-form" class="wbam-form" method="post">
				<?php wp_nonce_field( 'wbam_partnership_form', 'wbam_partnership_nonce' ); ?>

				<?php
				/**
				 * Fires at the beginning of the form, before any fields.
				 *
				 * @since 2.2.0
				 * @param array $atts Shortcode attributes.
				 */
				do_action( 'wbam_partnership_form_before_fields', $atts );
				?>

				<!-- Name and Email -->
				<div class="wbam-form-row">
					<div class="wbam-form-field wbam-field-half">
						<label for="wbam_partner_name"><?php esc_html_e( 'Your Name', 'wb-ads-rotator-with-split-test' ); ?> <span class="required">*</span></label>
						<input type="text" id="wbam_partner_name" name="name" required>
					</div>
					<div class="wbam-form-field wbam-field-half">
						<label for="wbam_partner_email"><?php esc_html_e( 'Email Address', 'wb-ads-rotator-with-split-test' ); ?> <span class="required">*</span></label>
						<input type="email" id="wbam_partner_email" name="email" required>
					</div>
				</div>

				<?php
				/**
				 * Fires after the name/email fields row.
				 *
				 * @since 2.2.0
				 * @param array $atts Shortcode attributes.
				 */
				do_action( 'wbam_partnership_form_after_name_email', $atts );
				?>

				<!-- Website URL -->
				<div class="wbam-form-row">
					<div class="wbam-form-field">
						<label for="wbam_partner_website"><?php esc_html_e( 'Your Website URL', 'wb-ads-rotator-with-split-test' ); ?> <span class="required">*</span></label>
						<input type="url" id="wbam_partner_website" name="website_url" placeholder="https://" required>
					</div>
				</div>

				<?php
				/**
				 * Fires after the website field.
				 *
				 * @since 2.2.0
				 * @param array $atts Shortcode attributes.
				 */
				do_action( 'wbam_partnership_form_after_website', $atts );
				?>

				<!-- Partnership Type -->
				<div class="wbam-form-row">
					<div class="wbam-form-field">
						<label for="wbam_partnership_type"><?php esc_html_e( 'Partnership Type', 'wb-ads-rotator-with-split-test' ); ?> <span class="required">*</span></label>
						<select id="wbam_partnership_type" name="partnership_type" required>
							<?php foreach ( $partnership_types as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<?php
				/**
				 * Fires after the partnership type field.
				 *
				 * @since 2.2.0
				 * @param array $atts Shortcode attributes.
				 */
				do_action( 'wbam_partnership_form_after_type', $atts );
				?>

				<?php if ( 'yes' === $atts['show_target_page'] ) : ?>
				<!-- Target Page -->
				<div class="wbam-form-row">
					<div class="wbam-form-field">
						<label for="wbam_target_page"><?php esc_html_e( 'Target Page (Optional)', 'wb-ads-rotator-with-split-test' ); ?></label>
						<select id="wbam_target_page" name="target_post_id">
							<option value=""><?php esc_html_e( 'Any page', 'wb-ads-rotator-with-split-test' ); ?></option>
							<?php
							$pages = get_pages( array( 'post_status' => 'publish' ) );
							// get_pages() returns false on DB error; default to empty list.
							$pages = is_array( $pages ) ? $pages : array();
							foreach ( $pages as $page ) :
								?>
								<option value="<?php echo esc_attr( $page->ID ); ?>"><?php echo esc_html( $page->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
						<small class="wbam-field-help"><?php esc_html_e( 'Select a specific page where you would like the link to appear.', 'wb-ads-rotator-with-split-test' ); ?></small>
					</div>
				</div>
				<?php endif; ?>

				<?php if ( 'yes' === $atts['show_anchor'] ) : ?>
				<!-- Anchor Text -->
				<div class="wbam-form-row">
					<div class="wbam-form-field">
						<label for="wbam_anchor_text"><?php esc_html_e( 'Proposed Anchor Text', 'wb-ads-rotator-with-split-test' ); ?></label>
						<input type="text" id="wbam_anchor_text" name="anchor_text" placeholder="<?php esc_attr_e( 'e.g., best hosting provider', 'wb-ads-rotator-with-split-test' ); ?>">
						<small class="wbam-field-help"><?php esc_html_e( 'The text you would like us to use for the link.', 'wb-ads-rotator-with-split-test' ); ?></small>
					</div>
				</div>

					<?php
					/**
					 * Fires after the anchor text field.
					 *
					 * @since 2.2.0
					 * @param array $atts Shortcode attributes.
					 */
					do_action( 'wbam_partnership_form_after_anchor', $atts );
					?>
				<?php endif; ?>

				<?php if ( 'yes' === $atts['show_budget'] ) : ?>
				<!-- Budget Range -->
				<div class="wbam-form-row wbam-budget-row" id="wbam-budget-fields">
					<div class="wbam-form-field wbam-field-half">
						<label for="wbam_budget_min"><?php esc_html_e( 'Budget Range (Min)', 'wb-ads-rotator-with-split-test' ); ?></label>
						<input type="number" id="wbam_budget_min" name="budget_min" min="0" step="1" placeholder="50">
					</div>
					<div class="wbam-form-field wbam-field-half">
						<label for="wbam_budget_max"><?php esc_html_e( 'Budget Range (Max)', 'wb-ads-rotator-with-split-test' ); ?></label>
						<input type="number" id="wbam_budget_max" name="budget_max" min="0" step="1" placeholder="200">
					</div>
					<small class="wbam-field-help wbam-full-width"><?php esc_html_e( 'For paid link partnerships, let us know your budget range.', 'wb-ads-rotator-with-split-test' ); ?></small>
				</div>

					<?php
					/**
					 * Fires after the budget fields.
					 *
					 * @since 2.2.0
					 * @param array $atts Shortcode attributes.
					 */
					do_action( 'wbam_partnership_form_after_budget', $atts );
					?>
				<?php endif; ?>

				<?php if ( 'yes' === $atts['show_message'] ) : ?>
				<!-- Message -->
				<div class="wbam-form-row">
					<div class="wbam-form-field">
						<label for="wbam_message"><?php esc_html_e( 'Additional Message', 'wb-ads-rotator-with-split-test' ); ?></label>
						<textarea id="wbam_message" name="message" rows="4" placeholder="<?php esc_attr_e( 'Tell us more about your partnership proposal...', 'wb-ads-rotator-with-split-test' ); ?>"></textarea>
					</div>
				</div>

					<?php
					/**
					 * Fires after the message field.
					 *
					 * @since 2.2.0
					 * @param array $atts Shortcode attributes.
					 */
					do_action( 'wbam_partnership_form_after_message', $atts );
					?>
				<?php endif; ?>

				<?php
				/**
				 * Fires after all standard fields, before the submit button.
				 *
				 * Use this hook to add custom fields to the form.
				 *
				 * @since 2.2.0
				 * @param array $atts Shortcode attributes.
				 */
				do_action( 'wbam_partnership_form_after_fields', $atts );
				?>

				<!-- Submit Button -->
				<div class="wbam-form-row wbam-form-actions">
					<?php
					$button_classes = array( 'wbam-btn', 'wbam-btn-primary', 'wbam-submit-btn' );

					/**
					 * Filter the submit button CSS classes.
					 *
					 * @since 2.2.0
					 * @param array $button_classes CSS classes.
					 * @param array $atts           Shortcode attributes.
					 */
					$button_classes = apply_filters( 'wbam_partnership_form_button_class', $button_classes, $atts );
					?>
					<button type="submit" class="<?php echo esc_attr( implode( ' ', $button_classes ) ); ?>">
						<?php echo esc_html( $atts['button_text'] ); ?>
					</button>
				</div>

				<div class="wbam-form-message" style="display: none;"></div>
			</form>
		</div>

		<?php
		/**
		 * Fires after the partnership form.
		 *
		 * @since 2.2.0
		 * @param array $atts Shortcode attributes.
		 */
		do_action( 'wbam_partnership_form_after', $atts );

		return ob_get_clean();
	}

	/**
	 * Honour the wbam_partnership_form_styles filter.
	 *
	 * Since 2.2.0 the filter received and returned the form's CSS, which was
	 * printed inline. The CSS now ships as partnership-form.css; a site that
	 * filters it still gets its result: an empty string drops the stylesheet,
	 * any other change replaces it with the filtered CSS.
	 */
	private function apply_styles_filter() {
		if ( ! has_filter( 'wbam_partnership_form_styles' ) ) {
			return;
		}

		$default = (string) file_get_contents( WBAM_PATH . 'assets/css/partnership-form.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file, not a remote URL.

		/**
		 * Filter the partnership form CSS.
		 *
		 * Return an empty string to drop the form's styles completely.
		 *
		 * @since 2.2.0
		 * @param string $styles CSS styles.
		 */
		$styles = (string) apply_filters( 'wbam_partnership_form_styles', $default );

		if ( $styles === $default ) {
			return;
		}

		wp_dequeue_style( 'wbam-partnership-form' );

		if ( '' !== trim( $styles ) ) {
			wp_register_style( 'wbam-partnership-form-custom', false, array(), WBAM_VERSION );
			wp_enqueue_style( 'wbam-partnership-form-custom' );
			wp_add_inline_style( 'wbam-partnership-form-custom', $styles );
		}
	}

	/**
	 * Handle AJAX form submission.
	 */
	public function handle_ajax_submission() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wbam_partnership_form' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wb-ads-rotator-with-split-test' ) ) );
		}

		/**
		 * Fires before processing the form submission.
		 *
		 * @since 2.2.0
		 * @param array $_POST The submitted form data.
		 */
		do_action( 'wbam_partnership_form_submission_before', $_POST );

		// Get and sanitize data.
		$data = array(
			'name'             => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'email'            => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'website_url'      => isset( $_POST['website_url'] ) ? esc_url_raw( wp_unslash( $_POST['website_url'] ) ) : '',
			'partnership_type' => isset( $_POST['partnership_type'] ) ? sanitize_text_field( wp_unslash( $_POST['partnership_type'] ) ) : 'paid_link',
			'target_post_id'   => isset( $_POST['target_post_id'] ) && ! empty( $_POST['target_post_id'] ) ? absint( $_POST['target_post_id'] ) : null,
			'anchor_text'      => isset( $_POST['anchor_text'] ) ? sanitize_text_field( wp_unslash( $_POST['anchor_text'] ) ) : '',
			'message'          => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
			'budget_min'       => isset( $_POST['budget_min'] ) && '' !== $_POST['budget_min'] ? (float) $_POST['budget_min'] : null,
			'budget_max'       => isset( $_POST['budget_max'] ) && '' !== $_POST['budget_max'] ? (float) $_POST['budget_max'] : null,
		);

		/**
		 * Filter the submission data before validation.
		 *
		 * Use this to add custom fields to the data array.
		 *
		 * @since 2.2.0
		 * @param array $data   Sanitized form data.
		 * @param array $_POST  Raw POST data.
		 */
		$data = apply_filters( 'wbam_partnership_form_data', $data, $_POST );

		// Validate required fields.
		if ( empty( $data['name'] ) || empty( $data['email'] ) || empty( $data['website_url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all required fields.', 'wb-ads-rotator-with-split-test' ) ) );
		}

		// Validate email.
		if ( ! is_email( $data['email'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'wb-ads-rotator-with-split-test' ) ) );
		}

		// Validate URL.
		if ( ! wp_http_validate_url( $data['website_url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid website URL.', 'wb-ads-rotator-with-split-test' ) ) );
		}

		/**
		 * Custom validation filter.
		 *
		 * Return a WP_Error object to fail validation with custom message.
		 *
		 * @since 2.2.0
		 * @param true|WP_Error $valid True if valid, WP_Error to fail.
		 * @param array         $data  Form data.
		 * @param array         $_POST Raw POST data.
		 */
		$validation = apply_filters( 'wbam_partnership_form_validation', true, $data, $_POST );

		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
		}

		// Check for duplicate submissions.
		$duplicate_hours = apply_filters( 'wbam_partnership_form_duplicate_hours', 24 );

		if ( $duplicate_hours > 0 && $this->manager->has_recent_submission( $data['email'], $data['website_url'], $duplicate_hours ) ) {
			wp_send_json_error( array( 'message' => __( 'You have already submitted an inquiry recently. Please wait before submitting again.', 'wb-ads-rotator-with-split-test' ) ) );
		}

		// Create the partnership.
		$partnership = $this->manager->create( $data );

		if ( ! $partnership ) {
			wp_send_json_error( array( 'message' => __( 'An error occurred. Please try again.', 'wb-ads-rotator-with-split-test' ) ) );
		}

		/**
		 * Fires after successful form submission.
		 *
		 * @since 2.2.0
		 * @param Partnership $partnership The created partnership object.
		 * @param array       $data        The submitted data.
		 */
		do_action( 'wbam_partnership_form_submission_after', $partnership, $data );

		$success_message = __( 'Thank you! Your inquiry has been submitted successfully. We will review it and get back to you soon.', 'wb-ads-rotator-with-split-test' );

		/**
		 * Filter the success message.
		 *
		 * @since 2.2.0
		 * @param string      $success_message Success message.
		 * @param Partnership $partnership     The created partnership.
		 * @param array       $data            The submitted data.
		 */
		$success_message = apply_filters( 'wbam_partnership_form_success_message', $success_message, $partnership, $data );

		wp_send_json_success( array( 'message' => $success_message ) );
	}
}
