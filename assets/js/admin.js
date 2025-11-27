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
		$(document).on('click', '.wbam-upload-image', function(e) {
			e.preventDefault();
			var $field = $(this).closest('.wbam-field');
			var $input = $field.find('input[type="url"]');
			var $preview = $field.find('.wbam-image-preview');

			var frame = wp.media({
				title: wbamAdmin.i18n.selectImage,
				button: { text: wbamAdmin.i18n.useImage },
				multiple: false
			});

			frame.on('select', function() {
				var url = frame.state().get('selection').first().toJSON().url;
				$input.val(url);
				$preview.html('<img src="' + url + '" alt="" />');
				$field.find('.wbam-remove-image').show();
			});

			frame.open();
		});

		// Image Remove
		$(document).on('click', '.wbam-remove-image', function(e) {
			e.preventDefault();
			var $field = $(this).closest('.wbam-field');
			$field.find('input[type="url"]').val('');
			$field.find('.wbam-image-preview').empty();
			$(this).hide();
		});

		// Placement Settings Toggle
		$(document).on('change', 'input[name="wbam_placements[]"]', function() {
			$('.wbam-paragraph-settings').toggle($('input[value="after_paragraph"]').is(':checked'));
			$('.wbam-activity-settings').toggle($('input[value="bp_activity"]').is(':checked'));
			$('.wbam-archive-settings').toggle($('input[value="archive"]').is(':checked'));
		});

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

	});

})(jQuery);
