/**
 * WB Ad Manager - Links Frontend Click Tracking
 *
 * Lightweight, non-blocking click tracking using Beacon API.
 * Only tracks clicks on managed links (with data-link-id attribute).
 *
 * @package WB_Ad_Manager
 * @since 1.0.0
 */

(function() {
	'use strict';

	// Exit early if no tracking data available.
	if (typeof wbamLinks === 'undefined') {
		return;
	}

	var config = wbamLinks;

	/**
	 * Track link click via Beacon API (non-blocking).
	 *
	 * @param {number} linkId Link ID.
	 * @param {string} url Destination URL.
	 */
	function trackClick(linkId, url) {
		// Prefer Beacon API for non-blocking requests.
		if (navigator.sendBeacon) {
			var data = new FormData();
			data.append('action', 'wbam_track_link_click');
			data.append('link_id', linkId);
			data.append('nonce', config.nonce);
			data.append('referrer', window.location.href);

			navigator.sendBeacon(config.ajaxUrl, data);
		} else {
			// Fallback to async XHR for older browsers.
			var xhr = new XMLHttpRequest();
			xhr.open('POST', config.ajaxUrl, true);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			xhr.send(
				'action=wbam_track_link_click' +
				'&link_id=' + encodeURIComponent(linkId) +
				'&nonce=' + encodeURIComponent(config.nonce) +
				'&referrer=' + encodeURIComponent(window.location.href)
			);
		}
	}

	/**
	 * Initialize click tracking using event delegation.
	 * Single event listener on document for better performance.
	 */
	function init() {
		document.addEventListener('click', function(e) {
			// Find closest link with tracking data.
			var link = e.target.closest('a[data-link-id]');

			if (!link) {
				return;
			}

			var linkId = link.getAttribute('data-link-id');

			if (linkId) {
				trackClick(linkId, link.href);
			}

			// Don't prevent default - let the link work normally.
		}, false);
	}

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
