/**
 * WB Ad Manager - Frontend JavaScript
 *
 * @package WB_Ad_Manager
 * @since   1.1.0
 */

( function() {
	'use strict';

	var WBAM = {
		/**
		 * Cookie utilities.
		 */
		cookies: {
			/**
			 * Get cookie value.
			 *
			 * @param {string} name Cookie name.
			 * @return {string|null} Cookie value or null.
			 */
			get: function( name ) {
				var value = '; ' + document.cookie;
				var parts = value.split( '; ' + name + '=' );
				if ( parts.length === 2 ) {
					return parts.pop().split( ';' ).shift();
				}
				return null;
			},

			/**
			 * Set cookie value.
			 *
			 * @param {string} name  Cookie name.
			 * @param {string} value Cookie value.
			 * @param {number} days  Days until expiration.
			 */
			set: function( name, value, days ) {
				var expires = '';
				if ( days ) {
					var date = new Date();
					date.setTime( date.getTime() + ( days * 24 * 60 * 60 * 1000 ) );
					expires = '; expires=' + date.toUTCString();
				}
				document.cookie = name + '=' + ( value || '' ) + expires + '; path=/';
			},

			/**
			 * Get closed ads from cookie.
			 *
			 * @return {Array} Array of closed ad IDs.
			 */
			getClosedAds: function() {
				var closed = this.get( 'wbam_closed_ads' );
				if ( closed ) {
					try {
						return JSON.parse( closed );
					} catch ( e ) {
						return [];
					}
				}
				return [];
			},

			/**
			 * Mark ad as closed.
			 *
			 * @param {number} adId Ad ID.
			 */
			markClosed: function( adId ) {
				var closed = this.getClosedAds();
				if ( closed.indexOf( adId ) === -1 ) {
					closed.push( adId );
				}
				this.set( 'wbam_closed_ads', JSON.stringify( closed ), 1 );
			},

			/**
			 * Check if ad was closed.
			 *
			 * @param {number} adId Ad ID.
			 * @return {boolean} True if closed.
			 */
			isClosed: function( adId ) {
				return this.getClosedAds().indexOf( adId ) !== -1;
			}
		},

		/**
		 * Sticky ads handler.
		 */
		sticky: {
			/**
			 * Initialize sticky ads.
			 */
			init: function() {
				var stickyAds = document.querySelectorAll( '.wbam-sticky-ad' );

				stickyAds.forEach( function( ad ) {
					var adId = parseInt( ad.getAttribute( 'data-ad-id' ), 10 );

					// Hide if previously closed.
					if ( WBAM.cookies.isClosed( adId ) ) {
						ad.style.display = 'none';
						return;
					}

					// Close button handler.
					var closeBtn = ad.querySelector( '.wbam-sticky-close' );
					if ( closeBtn ) {
						closeBtn.addEventListener( 'click', function( e ) {
							e.preventDefault();
							ad.style.display = 'none';
							WBAM.cookies.markClosed( adId );
						} );
					}
				} );
			}
		},

		/**
		 * Popup ads handler.
		 */
		popup: {
			shown: [],

			/**
			 * Initialize popup ads.
			 */
			init: function() {
				var popups = document.querySelectorAll( '.wbam-popup-overlay' );

				popups.forEach( function( popup ) {
					var adId    = parseInt( popup.getAttribute( 'data-ad-id' ), 10 );
					var trigger = popup.getAttribute( 'data-trigger' );
					var delay   = parseInt( popup.getAttribute( 'data-delay' ), 10 ) || 5;
					var scroll  = parseInt( popup.getAttribute( 'data-scroll' ), 10 ) || 50;

					// Skip if previously closed.
					if ( WBAM.cookies.isClosed( adId ) ) {
						return;
					}

					// Close button handler.
					var closeBtn = popup.querySelector( '.wbam-popup-close' );
					if ( closeBtn ) {
						closeBtn.addEventListener( 'click', function( e ) {
							e.preventDefault();
							WBAM.popup.close( popup, adId );
						} );
					}

					// Close on overlay click.
					popup.addEventListener( 'click', function( e ) {
						if ( e.target === popup ) {
							WBAM.popup.close( popup, adId );
						}
					} );

					// Close on Escape key.
					document.addEventListener( 'keydown', function( e ) {
						if ( e.key === 'Escape' && popup.style.display !== 'none' ) {
							WBAM.popup.close( popup, adId );
						}
					} );

					// Set up trigger.
					switch ( trigger ) {
						case 'delay':
							WBAM.popup.setupDelayTrigger( popup, adId, delay );
							break;
						case 'scroll':
							WBAM.popup.setupScrollTrigger( popup, adId, scroll );
							break;
						case 'exit':
							WBAM.popup.setupExitTrigger( popup, adId );
							break;
					}
				} );
			},

			/**
			 * Show popup.
			 *
			 * @param {Element} popup Popup element.
			 * @param {number}  adId  Ad ID.
			 */
			show: function( popup, adId ) {
				if ( this.shown.indexOf( adId ) !== -1 ) {
					return;
				}
				this.shown.push( adId );
				popup.style.display = 'flex';
				document.body.style.overflow = 'hidden';
			},

			/**
			 * Close popup.
			 *
			 * @param {Element} popup Popup element.
			 * @param {number}  adId  Ad ID.
			 */
			close: function( popup, adId ) {
				popup.style.display = 'none';
				document.body.style.overflow = '';
				WBAM.cookies.markClosed( adId );
			},

			/**
			 * Setup delay trigger.
			 *
			 * @param {Element} popup Popup element.
			 * @param {number}  adId  Ad ID.
			 * @param {number}  delay Delay in seconds.
			 */
			setupDelayTrigger: function( popup, adId, delay ) {
				var self = this;
				setTimeout( function() {
					self.show( popup, adId );
				}, delay * 1000 );
			},

			/**
			 * Setup scroll trigger.
			 *
			 * @param {Element} popup      Popup element.
			 * @param {number}  adId       Ad ID.
			 * @param {number}  percentage Scroll percentage.
			 */
			setupScrollTrigger: function( popup, adId, percentage ) {
				var self     = this;
				var triggered = false;

				var handler = function() {
					if ( triggered ) {
						return;
					}

					var scrollTop    = window.pageYOffset || document.documentElement.scrollTop;
					var docHeight    = document.documentElement.scrollHeight - window.innerHeight;
					var scrollPercent = ( scrollTop / docHeight ) * 100;

					if ( scrollPercent >= percentage ) {
						triggered = true;
						self.show( popup, adId );
						window.removeEventListener( 'scroll', handler );
					}
				};

				window.addEventListener( 'scroll', handler );
			},

			/**
			 * Setup exit intent trigger.
			 *
			 * @param {Element} popup Popup element.
			 * @param {number}  adId  Ad ID.
			 */
			setupExitTrigger: function( popup, adId ) {
				var self      = this;
				var triggered = false;

				var handler = function( e ) {
					if ( triggered ) {
						return;
					}

					// Detect mouse leaving viewport from top.
					if ( e.clientY <= 0 ) {
						triggered = true;
						self.show( popup, adId );
						document.removeEventListener( 'mouseout', handler );
					}
				};

				// Delay enabling exit intent to prevent immediate trigger.
				setTimeout( function() {
					document.addEventListener( 'mouseout', handler );
				}, 2000 );
			}
		},

		/**
		 * Lazy loading handler.
		 */
		lazy: {
			/**
			 * Initialize lazy loading.
			 */
			init: function() {
				var lazyAds = document.querySelectorAll( '.wbam-lazy' );

				if ( 'IntersectionObserver' in window ) {
					var observer = new IntersectionObserver( function( entries ) {
						entries.forEach( function( entry ) {
							if ( entry.isIntersecting ) {
								var ad = entry.target;
								ad.classList.add( 'wbam-loaded' );
								observer.unobserve( ad );
							}
						} );
					}, {
						rootMargin: '100px'
					} );

					lazyAds.forEach( function( ad ) {
						observer.observe( ad );
					} );
				} else {
					// Fallback for older browsers.
					lazyAds.forEach( function( ad ) {
						ad.classList.add( 'wbam-loaded' );
					} );
				}
			}
		},

		/**
		 * Email capture handler.
		 */
		emailCapture: {
			/**
			 * Initialize email capture forms.
			 */
			init: function() {
				var forms = document.querySelectorAll( '.wbam-ad-email-capture' );

				forms.forEach( function( container ) {
					var adId       = parseInt( container.getAttribute( 'data-ad-id' ), 10 );
					var cookieDays = parseInt( container.getAttribute( 'data-cookie-days' ), 10 ) || 7;
					var form       = container.querySelector( '.wbam-email-form' );
					var closeBtn   = container.querySelector( '.wbam-email-close' );

					// Close button handler.
					if ( closeBtn ) {
						closeBtn.addEventListener( 'click', function( e ) {
							e.preventDefault();
							WBAM.emailCapture.dismiss( container, adId, cookieDays );
						} );
					}

					// Form submission handler.
					if ( form ) {
						form.addEventListener( 'submit', function( e ) {
							e.preventDefault();
							WBAM.emailCapture.submit( form, container, adId, cookieDays );
						} );
					}
				} );
			},

			/**
			 * Dismiss email capture form.
			 *
			 * @param {Element} container Container element.
			 * @param {number}  adId      Ad ID.
			 * @param {number}  days      Cookie days.
			 */
			dismiss: function( container, adId, days ) {
				container.style.display = 'none';
				if ( days > 0 ) {
					WBAM.cookies.set( 'wbam_email_dismiss_' + adId, '1', days );
				}
			},

			/**
			 * Submit email capture form.
			 *
			 * @param {Element} form      Form element.
			 * @param {Element} container Container element.
			 * @param {number}  adId      Ad ID.
			 * @param {number}  days      Cookie days.
			 */
			submit: function( form, container, adId, days ) {
				var submitBtn     = form.querySelector( '.wbam-email-button' );
				var successEl     = container.querySelector( '.wbam-email-success' );
				var errorEl       = container.querySelector( '.wbam-email-error' );
				var errorMsgEl    = container.querySelector( '.wbam-email-error-message' );
				var formData      = new FormData( form );

				if ( ! window.wbamFrontend ) {
					return;
				}

				// Set loading state.
				container.classList.add( 'wbam-loading' );
				if ( submitBtn ) {
					submitBtn.disabled = true;
				}

				// Hide previous error.
				if ( errorEl ) {
					errorEl.style.display = 'none';
				}

				// Send AJAX request.
				var xhr = new XMLHttpRequest();
				xhr.open( 'POST', window.wbamFrontend.ajaxUrl, true );
				xhr.onreadystatechange = function() {
					if ( xhr.readyState !== 4 ) {
						return;
					}

					container.classList.remove( 'wbam-loading' );
					if ( submitBtn ) {
						submitBtn.disabled = false;
					}

					var response;
					try {
						response = JSON.parse( xhr.responseText );
					} catch ( e ) {
						WBAM.emailCapture.showError( errorEl, errorMsgEl, 'An error occurred. Please try again.' );
						return;
					}

					if ( response.success ) {
						// Set cookie to prevent showing again.
						if ( days > 0 ) {
							WBAM.cookies.set( 'wbam_email_dismiss_' + adId, '1', days );
						}

						// Check for redirect.
						if ( response.data && response.data.redirect ) {
							window.location.href = response.data.redirect;
							return;
						}

						// Show success message.
						form.style.display = 'none';
						if ( successEl ) {
							successEl.style.display = 'block';
						}

						// Auto-hide after 5 seconds.
						setTimeout( function() {
							container.style.display = 'none';
						}, 5000 );
					} else {
						var message = response.data && response.data.message ? response.data.message : 'An error occurred. Please try again.';
						WBAM.emailCapture.showError( errorEl, errorMsgEl, message );
					}
				};

				// Convert FormData to URL-encoded string.
				var params = new URLSearchParams( formData ).toString();
				xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
				xhr.send( params );
			},

			/**
			 * Show error message.
			 *
			 * @param {Element} errorEl    Error container.
			 * @param {Element} errorMsgEl Error message element.
			 * @param {string}  message    Error message.
			 */
			showError: function( errorEl, errorMsgEl, message ) {
				if ( errorEl && errorMsgEl ) {
					errorMsgEl.textContent = message;
					errorEl.style.display = 'block';
				}
			}
		},

		/**
		 * Click tracking handler.
		 */
		clicks: {
			/**
			 * Initialize click tracking.
			 */
			init: function() {
				var ads = document.querySelectorAll( '.wbam-ad-container[data-ad-id]' );

				ads.forEach( function( ad ) {
					var links = ad.querySelectorAll( 'a' );
					links.forEach( function( link ) {
						link.addEventListener( 'click', function() {
							WBAM.clicks.track( ad );
						} );
					} );
				} );
			},

			/**
			 * Track ad click.
			 *
			 * @param {Element} ad Ad container element.
			 */
			track: function( ad ) {
				var adId      = ad.getAttribute( 'data-ad-id' );
				var placement = ad.getAttribute( 'data-placement' ) || '';

				if ( ! adId || ! window.wbamFrontend ) {
					return;
				}

				// Send AJAX request to track click.
				var xhr = new XMLHttpRequest();
				xhr.open( 'POST', window.wbamFrontend.ajaxUrl, true );
				xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
				xhr.send(
					'action=wbam_track_click' +
					'&ad_id=' + encodeURIComponent( adId ) +
					'&placement=' + encodeURIComponent( placement ) +
					'&nonce=' + encodeURIComponent( window.wbamFrontend.nonce )
				);
			}
		},

		/**
		 * Initialize all handlers.
		 */
		init: function() {
			this.sticky.init();
			this.popup.init();
			this.lazy.init();
			this.clicks.init();
			this.emailCapture.init();
		}
	};

	// Initialize on DOM ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function() {
			WBAM.init();
		} );
	} else {
		WBAM.init();
	}

} )();
