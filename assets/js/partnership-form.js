/**
 * Partnership Form JavaScript
 *
 * @package WB_Ad_Manager
 * @since   2.2.0
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		var $form = $('#wbam-partnership-form');
		var $submitBtn = $form.find('.wbam-submit-btn');
		var $message = $form.find('.wbam-form-message');
		var $budgetFields = $('#wbam-budget-fields');
		var originalBtnText = $submitBtn.text();

		// Show/hide budget fields based on partnership type
		$('#wbam_partnership_type').on('change', function() {
			var type = $(this).val();
			if (type === 'exchange') {
				$budgetFields.slideUp();
			} else {
				$budgetFields.slideDown();
			}
		}).trigger('change');

		// Form submission
		$form.on('submit', function(e) {
			e.preventDefault();

			// Clear previous messages
			$message.removeClass('wbam-success wbam-error').hide();

			// Validate form
			if (!validateForm()) {
				return;
			}

			// Disable submit button
			$submitBtn.prop('disabled', true).text(wbamPartnership.i18n.submitting);

			// Prepare form data
			var formData = {
				action: 'wbam_submit_partnership',
				nonce: wbamPartnership.nonce,
				name: $('#wbam_partner_name').val(),
				email: $('#wbam_partner_email').val(),
				website_url: $('#wbam_partner_website').val(),
				partnership_type: $('#wbam_partnership_type').val(),
				target_post_id: $('#wbam_target_page').val() || '',
				anchor_text: $('#wbam_anchor_text').val(),
				budget_min: $('#wbam_budget_min').val(),
				budget_max: $('#wbam_budget_max').val(),
				message: $('#wbam_message').val()
			};

			// Submit via AJAX
			$.ajax({
				url: wbamPartnership.ajaxUrl,
				type: 'POST',
				data: formData,
				success: function(response) {
					if (response.success) {
						showMessage(response.data.message, 'success');
						$form[0].reset();
						// Hide the form fields
						$form.find('.wbam-form-row').not('.wbam-form-actions').slideUp();
					} else {
						showMessage(response.data.message || wbamPartnership.i18n.error, 'error');
					}
				},
				error: function() {
					showMessage(wbamPartnership.i18n.error, 'error');
				},
				complete: function() {
					$submitBtn.prop('disabled', false).text(originalBtnText);
				}
			});
		});

		/**
		 * Validate the form
		 */
		function validateForm() {
			var name = $('#wbam_partner_name').val().trim();
			var email = $('#wbam_partner_email').val().trim();
			var website = $('#wbam_partner_website').val().trim();

			// Required fields
			if (!name || !email || !website) {
				showMessage(wbamPartnership.i18n.required, 'error');
				return false;
			}

			// Email validation
			var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			if (!emailRegex.test(email)) {
				showMessage(wbamPartnership.i18n.invalidEmail, 'error');
				return false;
			}

			// URL validation
			var urlRegex = /^https?:\/\/.+/;
			if (!urlRegex.test(website)) {
				showMessage(wbamPartnership.i18n.invalidUrl, 'error');
				return false;
			}

			return true;
		}

		/**
		 * Show message
		 */
		function showMessage(text, type) {
			$message
				.removeClass('wbam-success wbam-error')
				.addClass('wbam-' + type)
				.html(text)
				.slideDown();

			// Scroll to message
			$('html, body').animate({
				scrollTop: $message.offset().top - 100
			}, 300);
		}
	});

})(jQuery);
