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
				window.wbamToast.error('Media library not loaded. Please refresh the page.');
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
			$('.wbam-placement-settings').each(function() {
				var slug = $(this).data('placement');
				$(this).prop('hidden', ! $('input[name="wbam_placements[]"]').filter(function() {
					return this.value === slug;
				}).is(':checked'));
			});
		});

		// Option rows that only apply to one choice of a select, e.g. the
		// popup delay: data-wbam-show-when="select_id:value|value".
		$(document).on('change', '.wbam-placement-settings select', function() {
			$('[data-wbam-show-when]').each(function() {
				var rule = String($(this).data('wbam-show-when')).split(':');
				$(this).prop('hidden', rule[1].split('|').indexOf($('#' + rule[0]).val()) === -1);
			});
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
				window.wbamToast.error('Copy failed. URL: ' + text);
			}
			$temp.remove();
		}

	});

})(jQuery);

/**
 * Searchable checkbox picker for multi-selects marked .wbam-select2.
 *
 * The markup always asked for Select2, which neither plugin ships, so the
 * pickers rendered as bare Ctrl-click lists. This keeps the original select
 * as the form field (same POST shape) and drives it from a filterable
 * checkbox list. A select with data-rest (wp/v2/pages, wp/v2/categories,
 * wp/v2/tags) also searches the REST API, so a site with thousands of pages
 * or tags only renders a first page.
 */
(function($) {
	'use strict';

	var cfg = window.wbamAdmin || {};
	var i18n = cfg.i18n || {};

	function addChoice($list, $select, value, label, checked) {
		var id = $select.attr('id') + '-opt-' + value;
		if ($list.find('#' + id).length) {
			return;
		}
		var $row = $('<label class="wbam-picker__item"></label>').attr('for', id);
		$('<input type="checkbox">').attr({ id: id, value: value }).prop('checked', !!checked).appendTo($row);
		$('<span></span>').text(label).appendTo($row);
		$list.append($row);
	}

	function enhance(select) {
		var $select = $(select);
		if ($select.data('wbamPicker')) {
			return;
		}
		$select.data('wbamPicker', true).addClass('wbam-picker__source').attr('aria-hidden', 'true').attr('tabindex', '-1');

		var $wrap = $('<div class="wbam-picker"></div>');
		var $search = $('<input type="search" class="wbam-picker__search">').attr({
			placeholder: $select.data('placeholder') || '',
			'aria-label': $select.data('placeholder') || ''
		});
		var $list = $('<div class="wbam-picker__list" role="group"></div>').attr('aria-label', $('label[for="' + select.id + '"]').text());
		var $empty = $('<p class="wbam-picker__empty"></p>').text(i18n.noMatches || 'No matches.').hide();

		// Selected first, so what is already chosen is visible without scrolling.
		$select.find('option:selected').each(function() {
			addChoice($list, $select, this.value, $(this).text().trim(), true);
		});
		$select.find('option:not(:selected)').each(function() {
			addChoice($list, $select, this.value, $(this).text().trim(), false);
		});

		$wrap.append($search, $list, $empty).insertAfter($select);

		$list.on('change', 'input', function() {
			var value = this.value;
			var $opt = $select.find('option').filter(function() { return this.value === value; });
			if (!$opt.length) {
				$opt = $('<option></option>').val(value).text($(this).next().text()).appendTo($select);
			}
			$opt.prop('selected', this.checked);
			$select.trigger('change');
		});

		function applyFilter() {
			var q = $.trim($search.val()).toLowerCase();
			var shown = 0;
			$list.children().each(function() {
				var match = !q || $(this).text().toLowerCase().indexOf(q) !== -1;
				$(this).toggle(match);
				shown += match ? 1 : 0;
			});
			$empty.toggle(0 === shown);
			return q;
		}

		var timer = null;
		$search.on('input', function() {
			var q = applyFilter();

			var route = $select.data('rest');
			if (!route || q.length < 2 || !cfg.restUrl) {
				return;
			}
			clearTimeout(timer);
			timer = setTimeout(function() {
				$.ajax({
					url: cfg.restUrl + route,
					data: { search: q, per_page: 20, _fields: 'id,title,name' },
					headers: { 'X-WP-Nonce': cfg.restNonce }
				}).done(function(items) {
					$.each(items || [], function(_, item) {
						// Posts carry title.rendered, terms carry name.
						var raw = item.title && item.title.rendered ? item.title.rendered : item.name;
						var title = raw ? $('<div>').html(raw).text() : '#' + item.id;
						addChoice($list, $select, String(item.id), title, false);
					});
					applyFilter();
				});
			}, 250);
		});
	}

	$(function() {
		$('select[multiple].wbam-select2').each(function() {
			enhance(this);
		});
	});

})(jQuery);
