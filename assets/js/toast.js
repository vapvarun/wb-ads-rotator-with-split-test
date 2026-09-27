/**
 * WB Ad Manager — Toast Notification & Confirm Dialog Toolkit
 *
 * Replaces all browser alert() / confirm() with inline toast + dialog UI
 * (see the "no native alert/confirm/prompt" admin UX rule). Shared by both
 * the free plugin and WB Ad Manager Pro — Pro depends on this handle
 * (`wbam-toast`) rather than shipping its own copy. Moved here from Pro in
 * 2.10.0 because Pro cannot be active without this plugin, so a primitive
 * both plugins need belongs in the base, exactly like admin-family.css.
 *
 * Usage:
 *   wbamToast.success('Saved successfully');
 *   wbamToast.error('Something went wrong');
 *   wbamToast.warning('Are you sure?');
 *   wbamToast.info('Processing...');
 *   wbamToast.confirm('Delete this item?', function() { onYes(); });
 *
 * Delegated confirm attribute:
 *   Any clickable element (link or submit button/input) carrying
 *   `data-wbam-confirm="message"` gets a confirm dialog before its default
 *   action runs — no per-site JS handler needed. Optional
 *   `data-wbam-confirm-tone="danger|warning|info"` (default "warning") picks
 *   the dialog's accent colour, and `data-wbam-confirm-text="Label"`
 *   overrides the confirm button label.
 *
 *   <a href="..." data-wbam-confirm="Delete this link?" data-wbam-confirm-tone="danger">Delete</a>
 *   <button type="submit" data-wbam-confirm="Remove all demo data?">Remove</button>
 *
 *   Native confirm() is synchronous — its return value cancels the browser's
 *   default action inline. This dialog is callback-based, so the listener
 *   below prevents the default action immediately and only re-triggers it
 *   (navigate the link's href, or submit its form) from the onConfirm
 *   callback once the visitor agrees. Cancelling — including dismissing via
 *   the overlay — simply does nothing further, leaving the page exactly as
 *   it was, which matches what `onclick="return confirm(...)"` did on cancel.
 *
 * @package WB_Ad_Manager
 * @since   1.5.0 Introduced in WB Ad Manager Pro.
 * @since   2.10.0 Moved to the free plugin as the shared toolkit; added the
 *                  delegated `data-wbam-confirm` attribute pattern.
 */
(function () {
	'use strict';

	// wp-i18n is a dependency (Plugin::register_shared_assets()); the
	// fallback only guards a page that printed this file by hand.
	var __ = ( window.wp && window.wp.i18n ) ? window.wp.i18n.__ : function ( text ) { return text; };

	var CONTAINER_ID = 'wbam-toast-container';
	var TOAST_DURATION = 4000;

	function getContainer() {
		var container = document.getElementById(CONTAINER_ID);
		if (!container) {
			container = document.createElement('div');
			container.id = CONTAINER_ID;
			container.setAttribute('role', 'alert');
			container.setAttribute('aria-live', 'polite');
			document.body.appendChild(container);
		}
		return container;
	}

	function createIcon(type) {
		var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		svg.setAttribute('width', '20');
		svg.setAttribute('height', '20');
		svg.setAttribute('viewBox', '0 0 20 20');

		var colors = { success: '#00a32a', error: '#d63638', warning: '#dba617', info: '#2271b1' };
		var circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
		circle.setAttribute('cx', '10');
		circle.setAttribute('cy', '10');
		circle.setAttribute('r', '10');
		circle.setAttribute('fill', colors[type] || colors.info);
		svg.appendChild(circle);

		var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
		path.setAttribute('stroke', '#fff');
		path.setAttribute('stroke-width', '2');
		path.setAttribute('fill', 'none');

		if (type === 'success') {
			path.setAttribute('d', 'M6 10l3 3 5-5');
		} else if (type === 'error') {
			path.setAttribute('d', 'M7 7l6 6M13 7l-6 6');
		} else {
			path.setAttribute('d', 'M10 6v5M10 13v1');
			path.setAttribute('stroke-linecap', 'round');
		}
		svg.appendChild(path);
		return svg;
	}

	function show(message, type, duration) {
		type = type || 'info';
		duration = typeof duration === 'number' ? duration : TOAST_DURATION;

		var container = getContainer();

		var toast = document.createElement('div');
		toast.className = 'wbam-toast wbam-toast--' + type;

		var iconWrap = document.createElement('span');
		iconWrap.className = 'wbam-toast__icon';
		iconWrap.appendChild(createIcon(type));
		toast.appendChild(iconWrap);

		var msgEl = document.createElement('span');
		msgEl.className = 'wbam-toast__message';
		msgEl.textContent = message;
		toast.appendChild(msgEl);

		var closeBtn = document.createElement('button');
		closeBtn.type = 'button';
		closeBtn.className = 'wbam-toast__close';
		closeBtn.setAttribute('aria-label', __( 'Dismiss', 'wb-ads-rotator-with-split-test' ));
		closeBtn.textContent = '×';
		closeBtn.addEventListener('click', function () { dismiss(toast); });
		toast.appendChild(closeBtn);

		container.appendChild(toast);
		requestAnimationFrame(function () { toast.classList.add('wbam-toast--visible'); });

		if (duration > 0) {
			setTimeout(function () { dismiss(toast); }, duration);
		}
		return toast;
	}

	function dismiss(toast) {
		if (!toast || toast.classList.contains('wbam-toast--leaving')) return;
		toast.classList.add('wbam-toast--leaving');
		toast.classList.remove('wbam-toast--visible');
		setTimeout(function () {
			if (toast.parentNode) toast.parentNode.removeChild(toast);
		}, 300);
	}

	function confirm(message, onConfirm, onCancel, options) {
		options = options || {};
		var confirmText = options.confirmText || __( 'Yes, proceed', 'wb-ads-rotator-with-split-test' );
		var cancelText = options.cancelText || __( 'Cancel', 'wb-ads-rotator-with-split-test' );
		// Focus goes back where it was when the dialog closes.
		var returnFocus = document.activeElement;
		var type = options.type || 'warning';

		var overlay = document.createElement('div');
		overlay.className = 'wbam-confirm-overlay';

		var dialog = document.createElement('div');
		dialog.className = 'wbam-confirm-dialog';
		dialog.setAttribute('role', 'alertdialog');
		dialog.setAttribute('aria-modal', 'true');

		var body = document.createElement('div');
		body.className = 'wbam-confirm-dialog__body';

		var msg = document.createElement('p');
		msg.className = 'wbam-confirm-dialog__message';
		msg.id = 'wbam-confirm-message-' + Date.now();
		msg.textContent = message;
		body.appendChild(msg);
		dialog.setAttribute('aria-labelledby', msg.id);

		// Optional structured detail rows rendered beneath the headline.
		// Each entry is either a plain string (single line) or an object
		// { label, value, emphasis } for label/value pairs. Everything
		// runs through textContent so there's no HTML injection surface.
		if (Array.isArray(options.details) && options.details.length) {
			var detailsWrap = document.createElement('dl');
			detailsWrap.className = 'wbam-confirm-dialog__details';
			options.details.forEach(function (row) {
				if (!row) return;
				if (typeof row === 'string') {
					var line = document.createElement('p');
					line.className = 'wbam-confirm-dialog__detail-line';
					line.textContent = row;
					detailsWrap.appendChild(line);
					return;
				}
				var dt = document.createElement('dt');
				dt.className = 'wbam-confirm-dialog__detail-label';
				dt.textContent = row.label || '';
				var dd = document.createElement('dd');
				dd.className = 'wbam-confirm-dialog__detail-value';
				if (row.emphasis) dd.classList.add('wbam-confirm-dialog__detail-value--emphasis');
				dd.textContent = row.value || '';
				detailsWrap.appendChild(dt);
				detailsWrap.appendChild(dd);
			});
			body.appendChild(detailsWrap);
		}

		var actions = document.createElement('div');
		actions.className = 'wbam-confirm-dialog__actions';

		var cancelBtn = document.createElement('button');
		cancelBtn.type = 'button';
		cancelBtn.className = 'wbam-confirm-dialog__btn wbam-confirm-dialog__btn--cancel';
		cancelBtn.textContent = cancelText;

		var confirmBtn = document.createElement('button');
		confirmBtn.type = 'button';
		confirmBtn.className = 'wbam-confirm-dialog__btn wbam-confirm-dialog__btn--confirm wbam-confirm-dialog__btn--' + type;
		confirmBtn.textContent = confirmText;

		actions.appendChild(cancelBtn);
		actions.appendChild(confirmBtn);
		body.appendChild(actions);
		dialog.appendChild(body);

		function cleanup() {
			document.removeEventListener('keydown', onKeydown, true);
			if (returnFocus && typeof returnFocus.focus === 'function' && document.contains(returnFocus)) {
				returnFocus.focus();
			}
			overlay.classList.add('wbam-confirm-overlay--leaving');
			dialog.classList.add('wbam-confirm-dialog--leaving');
			setTimeout(function () {
				if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
				if (dialog.parentNode) dialog.parentNode.removeChild(dialog);
			}, 200);
		}

		confirmBtn.addEventListener('click', function () {
			cleanup();
			if (typeof onConfirm === 'function') onConfirm();
		});
		cancelBtn.addEventListener('click', function () {
			cleanup();
			if (typeof onCancel === 'function') onCancel();
		});
		overlay.addEventListener('click', function () {
			cleanup();
			if (typeof onCancel === 'function') onCancel();
		});

		// A modal alert dialog (WAI-ARIA APG): Escape cancels, and Tab stays
		// on the two buttons while it is open.
		function onKeydown(event) {
			if ('Escape' === event.key) {
				event.preventDefault();
				cancelBtn.click();
				return;
			}
			if ('Tab' === event.key) {
				event.preventDefault();
				(document.activeElement === cancelBtn ? confirmBtn : cancelBtn).focus();
			}
		}
		document.addEventListener('keydown', onKeydown, true);

		document.body.appendChild(overlay);
		document.body.appendChild(dialog);
		requestAnimationFrame(function () {
			overlay.classList.add('wbam-confirm-overlay--visible');
			dialog.classList.add('wbam-confirm-dialog--visible');
		});
		// The least destructive choice gets focus, so Enter never confirms
		// by accident.
		cancelBtn.focus();
	}

	window.wbamToast = {
		show:    show,
		success: function (msg, dur) { return show(msg, 'success', dur); },
		error:   function (msg, dur) { return show(msg, 'error', dur || 6000); },
		warning: function (msg, dur) { return show(msg, 'warning', dur); },
		info:    function (msg, dur) { return show(msg, 'info', dur); },
		confirm: confirm,
		dismiss: dismiss
	};

	// ── Delegated data-wbam-confirm wiring ──────────────────────────────
	//
	// One document-level listener services every `[data-wbam-confirm]`
	// element on the page, in either plugin, admin or frontend — no
	// per-screen script needs to bind its own handler. See the docblock
	// above for the attribute contract and the sync-vs-callback note.
	var TONE_TO_TYPE = { danger: 'error', warning: 'warning', info: 'info' };

	document.addEventListener('click', function (event) {
		var el = event.target.closest ? event.target.closest('[data-wbam-confirm]') : null;
		if (!el) {
			return;
		}

		var message = el.getAttribute('data-wbam-confirm');
		if (!message) {
			return;
		}

		event.preventDefault();

		var tone = el.getAttribute('data-wbam-confirm-tone') || 'warning';
		var confirmText = el.getAttribute('data-wbam-confirm-text') || undefined;

		confirm(message, function () {
			if (el.tagName === 'A') {
				window.location.href = el.href;
				return;
			}

			// Submit buttons/inputs expose their owning form via `.form`;
			// fall back to closest('form') for any other element type.
			var form = el.form || el.closest('form');
			if (form) {
				form.submit();
			}
		}, null, {
			type: TONE_TO_TYPE[tone] || 'warning',
			confirmText: confirmText
		});
	});
})();
