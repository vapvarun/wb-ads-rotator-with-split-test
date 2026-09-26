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

				// Escape dismisses the sticky ad, unless a popup is open: then
				// Escape belongs to the popup.
				if ( stickyAds.length ) {
					document.addEventListener( 'keydown', function( e ) {
						if ( e.key !== 'Escape' || document.body.classList.contains( 'wbam-popup-open' ) ) {
							return;
						}
						stickyAds.forEach( function( ad ) {
							if ( ad.style.display !== 'none' ) {
								ad.style.display = 'none';
								WBAM.cookies.markClosed( parseInt( ad.getAttribute( 'data-ad-id' ), 10 ) );
							}
						} );
					} );
				}

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
						var closeHandler = function( e ) {
							e.preventDefault();
							ad.style.display = 'none';
							WBAM.cookies.markClosed( adId );
						};
						closeBtn.addEventListener( 'click', closeHandler );
						closeBtn.addEventListener( 'touchend', closeHandler );
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

				// Page views this browser session, for the "never on a phone's
				// first page view" rule.
				var views = 1;
				try {
					views = ( parseInt( window.sessionStorage.getItem( 'wbam_pv' ), 10 ) || 0 ) + 1;
					window.sessionStorage.setItem( 'wbam_pv', String( views ) );
				} catch ( e ) {}
				var phoneFirstView = views === 1 && window.matchMedia && window.matchMedia( '(max-width: 767px)' ).matches;

				popups.forEach( function( popup ) {
					var adId    = parseInt( popup.getAttribute( 'data-ad-id' ), 10 );
					var trigger = popup.getAttribute( 'data-trigger' );
					var delay   = parseInt( popup.getAttribute( 'data-delay' ), 10 ) || 5;
					var scroll  = parseInt( popup.getAttribute( 'data-scroll' ), 10 ) || 50;

					// Skip if closed, already seen within its repeat window, or
					// a phone's first page view (unless the owner allows it).
					if ( WBAM.cookies.isClosed( adId ) || WBAM.cookies.get( 'wbam_popup_seen_' + adId ) ) {
						return;
					}
					if ( phoneFirstView && popup.getAttribute( 'data-mobile-first-view' ) !== '1' ) {
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

					// Escape closes; Tab stays inside the dialog while it is open.
					document.addEventListener( 'keydown', function( e ) {
						if ( popup.hidden ) {
							return;
						}
						if ( e.key === 'Escape' ) {
							WBAM.popup.close( popup, adId );
						} else if ( e.key === 'Tab' ) {
							WBAM.popup.trapTab( popup, e );
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
				if ( this.shown.indexOf( adId ) !== -1 || document.body.classList.contains( 'wbam-popup-open' ) ) {
					return;
				}
				this.shown.push( adId );

				var days = parseInt( popup.getAttribute( 'data-repeat-days' ), 10 ) || 0;
				if ( days > 0 ) {
					WBAM.cookies.set( 'wbam_popup_seen_' + adId, '1', days );
				}

				this.returnFocus = document.activeElement;
				popup.hidden = false;
				document.body.style.overflow = 'hidden';
				document.body.classList.add( 'wbam-popup-open' );

				var closeBtn = popup.querySelector( '.wbam-popup-close' );
				if ( closeBtn ) {
					closeBtn.focus();
				}
			},

			/**
			 * Keep Tab focus inside the open dialog.
			 *
			 * @param {Element}       popup Popup element.
			 * @param {KeyboardEvent} e     Keydown event.
			 */
			trapTab: function( popup, e ) {
				var focusable = popup.querySelectorAll( 'a[href], button:not([disabled]), input:not([disabled]), select, textarea, iframe, [tabindex]:not([tabindex="-1"])' );
				if ( ! focusable.length ) {
					e.preventDefault();
					return;
				}
				var first = focusable[0];
				var last  = focusable[ focusable.length - 1 ];
				if ( e.shiftKey && ( document.activeElement === first || ! popup.contains( document.activeElement ) ) ) {
					e.preventDefault();
					last.focus();
				} else if ( ! e.shiftKey && ( document.activeElement === last || ! popup.contains( document.activeElement ) ) ) {
					e.preventDefault();
					first.focus();
				}
			},

			/**
			 * Close popup.
			 *
			 * @param {Element} popup Popup element.
			 * @param {number}  adId  Ad ID.
			 */
			close: function( popup, adId ) {
				popup.hidden = true;
				document.body.style.overflow = '';
				document.body.classList.remove( 'wbam-popup-open' );
				WBAM.cookies.markClosed( adId );
				if ( this.returnFocus && this.returnFocus.focus ) {
					this.returnFocus.focus();
				}
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
					errorEl.hidden = true;
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
							successEl.hidden = false;
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
					errorEl.hidden = false;
				}
			}
		},

		/**
		 * Viewable impressions.
		 *
		 * With the owner's "Count Impressions When Seen" setting on, popup,
		 * sticky and code/network ads carry data-wbam-viewable (the beacon
		 * URL) and are not counted at render. The impression is sent once
		 * at least half the ad has been on screen for one second (IAB) and
		 * the ad has a creative: an empty network unit never counts.
		 */
		viewable: {
			/**
			 * Observe every ad waiting for a viewable impression.
			 */
			init: function() {
				var ads = document.querySelectorAll( '[data-wbam-viewable]' );

				if ( ! ads.length ) {
					return;
				}

				if ( ! ( 'IntersectionObserver' in window ) ) {
					ads.forEach( WBAM.viewable.send );
					return;
				}

				var observer = new IntersectionObserver( function( entries ) {
					entries.forEach( function( entry ) {
						var ad = entry.target;

						if ( entry.isIntersecting && entry.intersectionRatio >= 0.5 ) {
							if ( ad.wbamViewTimer ) {
								return;
							}
							// ponytail: re-checks each second while in view, so a
							// unit that fills late counts about a second after it
							// fills rather than after exactly one continuous second.
							var check = function() {
								if ( document.hidden || ! WBAM.viewable.hasCreative( ad ) ) {
									ad.wbamViewTimer = setTimeout( check, 1000 );
									return;
								}
								ad.wbamViewTimer = null;
								observer.unobserve( ad );
								WBAM.viewable.send( ad );
							};
							ad.wbamViewTimer = setTimeout( check, 1000 );
						} else if ( ad.wbamViewTimer ) {
							clearTimeout( ad.wbamViewTimer );
							ad.wbamViewTimer = null;
						}
					} );
				}, {
					threshold: 0.5
				} );

				ads.forEach( function( ad ) {
					observer.observe( ad );
				} );
			},

			/**
			 * Whether the ad shows a creative: an element other than the
			 * disclosure label with a size, and no unfilled AdSense unit.
			 *
			 * @param {Element} ad Ad container element.
			 * @return {boolean} True when something is on screen.
			 */
			hasCreative: function( ad ) {
				if ( ad.querySelector( '[data-ad-status="unfilled"]' ) ) {
					return false;
				}

				for ( var i = 0; i < ad.children.length; i++ ) {
					var child = ad.children[ i ];
					if ( ! child.classList.contains( 'wbam-ad-label' ) && child.offsetWidth > 0 && child.offsetHeight > 0 ) {
						return true;
					}
				}

				return false;
			},

			/**
			 * Send the impression beacon once.
			 *
			 * @param {Element} ad Ad container element.
			 */
			send: function( ad ) {
				var url = ad.getAttribute( 'data-wbam-viewable' );

				if ( ! url || ( navigator.sendBeacon && navigator.sendBeacon( url ) ) ) {
					return;
				}

				var xhr = new XMLHttpRequest();
				xhr.open( 'POST', url, true );
				xhr.send();
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
				// Match both the canonical .wbam-ad class and the
				// .wbam-ad-slot wrapper emitted by the placement engine,
				// so tracking still attaches if a filter strips one class.
				var selector = '.wbam-ad[data-ad-id], .wbam-ad-slot[data-ad-id]';
				var ads      = document.querySelectorAll( selector );

				ads.forEach( function( ad ) {
					// Skip nested containers. Some ad-type handlers emit their
					// own .wbam-ad[data-ad-id] inside the placement wrapper, so
					// the same <a> sat inside TWO matched elements and received
					// two listeners - one click reported two clicks and every
					// click stat was doubled. Only the outermost container binds.
					if ( ad.parentElement && ad.parentElement.closest( selector ) ) {
						return;
					}

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
			this.viewable.init();
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
