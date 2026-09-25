/**
 * Settings screen: Ad Display sub-nav pills.
 *
 * Pure progressive enhancement — every `.wbam-ad-display-subsection` is
 * already visible in the markup (see WBAM\Admin\Settings::render_ad_display_subsections()),
 * so the whole form always posts regardless of which pill is active. This
 * script only adds the "show one at a time" behaviour once it can run.
 *
 * @package WB_Ad_Manager
 * @since   3.2.0
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrap = document.getElementById( 'wbam-ad-display-sections' );
		var nav  = document.querySelector( '.wbam-ad-display-subnav' );

		if ( ! wrap || ! nav ) {
			return;
		}

		wrap.classList.add( 'wbam-js-enhanced' );

		nav.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.wbam-ad-display-subnav__item' );
			if ( ! button ) {
				return;
			}

			var target = button.getAttribute( 'data-subsection' );

			nav.querySelectorAll( '.wbam-ad-display-subnav__item' ).forEach( function ( item ) {
				item.classList.toggle( 'is-active', item === button );
			} );

			wrap.querySelectorAll( '.wbam-ad-display-subsection' ).forEach( function ( section ) {
				section.classList.toggle( 'is-active', section.getAttribute( 'data-subsection' ) === target );
			} );
		} );
	} );
}() );
