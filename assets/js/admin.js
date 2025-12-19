/**
 * WB Ad Manager - Admin Scripts
 */
(function($) {
	'use strict';

	$(document).ready(function() {

		// Ad Type Tab Switching
		$('.wbam-adtype-tab').on('click', function(e) {
			e.preventDefault();
			var typeId = $(this).data('type');
			$('#wbam-adtype-' + typeId).prop('checked', true);
			$('.wbam-adtype-tab').removeClass('wbam-adtype-tab-active');
			$(this).addClass('wbam-adtype-tab-active');
			$('.wbam-adtype-content').hide();
			$('.wbam-adtype-content[data-type="' + typeId + '"]').show();
		});

		// Image Upload
		var wbamMediaFrame = null;
		$(document).on('click', '.wbam-upload-image', function(e) {
			e.preventDefault();

			// Check if wp.media is available.
			if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
				alert('Media library not loaded. Please refresh the page.');
				return;
			}

			var $button = $(this);
			var $field = $button.closest('.wbam-field');
			var $input = $field.find('input[type="url"]').first();
			var $preview = $field.find('.wbam-image-preview');
			var $removeBtn = $field.find('.wbam-remove-image');

			// Create frame if not exists or reuse.
			if (wbamMediaFrame) {
				wbamMediaFrame.off('select');
			}

			wbamMediaFrame = wp.media({
				title: typeof wbamAdmin !== 'undefined' ? wbamAdmin.i18n.selectImage : 'Select Image',
				button: { text: typeof wbamAdmin !== 'undefined' ? wbamAdmin.i18n.useImage : 'Use This Image' },
				library: { type: 'image' },
				multiple: false
			});

			wbamMediaFrame.on('select', function() {
				var attachment = wbamMediaFrame.state().get('selection').first().toJSON();
				var url = attachment.url;
				$input.val(url).trigger('change');
				$preview.html('<img src="' + url + '" alt="" />');
				$removeBtn.show();
			});

			wbamMediaFrame.open();
		});

		// Image Remove
		$(document).on('click', '.wbam-remove-image', function(e) {
			e.preventDefault();
			var $field = $(this).closest('.wbam-field');
			$field.find('input[type="url"]').first().val('').trigger('change');
			$field.find('.wbam-image-preview').empty();
			$(this).hide();
		});

		// Image URL manual input - update preview.
		$(document).on('input change', '#wbam_image_url', function() {
			var url = $(this).val().trim();
			var $field = $(this).closest('.wbam-field');
			var $preview = $field.find('.wbam-image-preview');
			var $removeBtn = $field.find('.wbam-remove-image');

			if (url && url.match(/\.(jpg|jpeg|png|gif|webp|svg)$/i)) {
				$preview.html('<img src="' + url + '" alt="" />');
				$removeBtn.show();
			} else if (!url) {
				$preview.empty();
				$removeBtn.hide();
			}
		});

		// Placement Settings Toggle
		$(document).on('change', 'input[name="wbam_placements[]"]', function() {
			$('.wbam-paragraph-settings').toggle($('input[value="after_paragraph"]').is(':checked'));
			$('.wbam-activity-settings').toggle($('input[value="bp_activity"]').is(':checked'));
			$('.wbam-archive-settings').toggle($('input[value="archive"]').is(':checked'));
		});
		// Trigger on page load to show/hide settings based on initial state
		$('input[name="wbam_placements[]"]').first().trigger('change');

		// Display Rules
		$(document).on('change', 'input[name="wbam_display_rules[display_on]"]', function() {
			$('.wbam-conditional-section').removeClass('wbam-visible');
			$('.wbam-conditional-section[data-show-when="' + $(this).val() + '"]').addClass('wbam-visible');
		});
		$('input[name="wbam_display_rules[display_on]"]:checked').trigger('change');

		// Device Options
		$('.wbam-device-option').each(function() {
			$(this).toggleClass('wbam-selected', $(this).find('input').is(':checked'));
		});
		$(document).on('change', '.wbam-device-option input', function() {
			$(this).closest('.wbam-device-option').toggleClass('wbam-selected', $(this).is(':checked'));
		});

		// Geo Toggle
		$(document).on('change', '.wbam-geo-enable', function() {
			$('.wbam-geo-options').toggle($(this).is(':checked'));
		});

		// Code Editor
		if (typeof wbamCodeEditor !== 'undefined' && $('#wbam_code').length) {
			wp.codeEditor.initialize($('#wbam_code'), wbamCodeEditor);
		}

		// Copy URL to Clipboard
		$(document).on('click', '.wbam-copy-btn, .wbam-copy-url', function(e) {
			e.preventDefault();
			var url = $(this).data('clipboard') || $(this).data('url');
			var $btn = $(this);
			var originalHtml = $btn.html();

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(function() {
					$btn.html('<span class="dashicons dashicons-yes"></span>');
					setTimeout(function() {
						$btn.html(originalHtml);
					}, 1500);
				}).catch(function() {
					fallbackCopy(url, $btn, originalHtml);
				});
			} else {
				fallbackCopy(url, $btn, originalHtml);
			}
		});

		function fallbackCopy(text, $btn, originalHtml) {
			var $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();
			try {
				document.execCommand('copy');
				$btn.html('<span class="dashicons dashicons-yes"></span>');
				setTimeout(function() {
					$btn.html(originalHtml);
				}, 1500);
			} catch (err) {
				alert('Copy failed. URL: ' + text);
			}
			$temp.remove();
		}

	});

})(jQuery);
