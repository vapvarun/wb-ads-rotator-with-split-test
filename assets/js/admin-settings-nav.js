/**
 * Settings screen: Ad Display sub-nav pills + Geo Targeting field toggles.
 *
 * Pure progressive enhancement — every field this script hides stays in the
 * DOM and keeps posting its stored value either way (see
 * WBAM\Admin\Settings::render_ad_display_subsections() /
 * render_geo_provider_field()); this script only narrows what's visible.
 *
 * @package WB_Ad_Manager
 * @since   3.2.0
 */
( function () {
	'use strict';

	function initAdDisplaySubnav() {
		var wrap = document.getElementById( 'wbam-ad-display-sections' );
		var nav  = document.querySelector( '.wbam-ad-display-subnav' );

		if ( ! wrap || ! nav ) {
			return;
		}

		wrap.classList.add( 'wbam-js-enhanced' );

		function activate( target ) {
			nav.querySelectorAll( '.wbam-ad-display-subnav__item' ).forEach( function ( item ) {
				item.classList.toggle( 'is-active', item.getAttribute( 'data-subsection' ) === target );
			} );

			wrap.querySelectorAll( '.wbam-ad-display-subsection' ).forEach( function ( section ) {
				section.classList.toggle( 'is-active', section.getAttribute( 'data-subsection' ) === target );
			} );
		}

		nav.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.wbam-ad-display-subnav__item' );
			if ( ! button ) {
				return;
			}

			activate( button.getAttribute( 'data-subsection' ) );
		} );

		// Deep link support, e.g. the legacy-geo-provider admin notice
		// linking straight to "#wbam_geo" (Settings\get_legacy_geo_provider_notice()).
		// Falls back to whichever pill server-rendered as .is-active.
		var requested = window.location.hash.replace( '#', '' );
		if ( requested && wrap.querySelector( '.wbam-ad-display-subsection[data-subsection="' + requested + '"]' ) ) {
			activate( requested );
		}
	}

	/**
	 * Geo Targeting section: hide the provider picker + its two
	 * provider-specific rows while geolocation is off, and show only the
	 * row matching whichever provider is selected (owner decision 8).
	 */
	function initGeoProviderToggle() {
		var enabledToggle = document.getElementById( 'wbam_setting_geo_enabled' );
		var providerRadios = document.querySelectorAll( '.wbam-geo-provider-radio' );

		if ( ! enabledToggle || ! providerRadios.length ) {
			return;
		}

		var providerRow = document.querySelector( '.wbam-geo-provider-picker' ).closest( 'tr' );
		var fieldRows   = Array.prototype.map.call(
			document.querySelectorAll( '.wbam-geo-provider-field' ),
			function ( field ) {
				return field.closest( 'tr' );
			}
		);

		function selectedProvider() {
			var checked = document.querySelector( '.wbam-geo-provider-radio:checked' );
			return checked ? checked.value : '';
		}

		function refresh() {
			var enabled  = enabledToggle.checked;
			var provider = selectedProvider();

			if ( providerRow ) {
				providerRow.classList.toggle( 'wbam-geo-is-hidden', ! enabled );
			}

			fieldRows.forEach( function ( row, index ) {
				if ( ! row ) {
					return;
				}
				var field = document.querySelectorAll( '.wbam-geo-provider-field' )[ index ];
				var isMatch = field.classList.contains( 'wbam-geo-provider-' + provider );
				row.classList.toggle( 'wbam-geo-is-hidden', ! enabled || ! isMatch );
			} );
		}

		enabledToggle.addEventListener( 'change', refresh );
		providerRadios.forEach( function ( radio ) {
			radio.addEventListener( 'change', refresh );
		} );

		refresh();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initAdDisplaySubnav();
		initGeoProviderToggle();
	} );
}() );
