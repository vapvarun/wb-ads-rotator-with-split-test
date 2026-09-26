/**
 * Settings screen: mobile section `<select>` auto-submit + Geo Targeting
 * field toggles.
 *
 * Pure progressive enhancement — every field this script hides stays in the
 * DOM and keeps posting its stored value either way (see
 * render_geo_provider_field()); this script only narrows what's visible.
 *
 * @package WB_Ad_Manager
 * @since   3.2.0
 * @since   3.2.0 The Ad Display sub-nav pills this file also used to drive
 *          were removed — each former pill is now its own top-level
 *          settings section (General, Ads & Display, Links, Location, ...),
 *          so there is no in-page tab bar left to enhance.
 */
( function () {
	'use strict';

	/**
	 * Mobile settings-rail `<select>` (UX::settings_nav()). The `<select>`
	 * lives in a plain GET `<form>` with its own "Go" submit button, so
	 * navigation works with JS off. With JS on, auto-submit on change and
	 * hide the now-redundant button — no inline `onchange` attribute.
	 */
	function initSettingsNavSelect() {
		var form   = document.querySelector( '.wbam-settings-nav__form' );
		var select = form && form.querySelector( '.wbam-settings-nav__select' );

		if ( ! form || ! select ) {
			return;
		}

		form.classList.add( 'wbam-js-enhanced' );
		select.addEventListener( 'change', function () {
			form.submit();
		} );
	}

	/**
	 * "On this page" jump row (UX::page_jump_nav()). Confirms JS can run
	 * (CSS only swaps to the <select> at <=782px once this class lands —
	 * see admin-family.css), then makes that select jump to the chosen
	 * card's anchor. Not a form/submit — a same-page fragment isn't
	 * something a GET form can carry per option, see the PHP docblock.
	 */
	function initPageJumpNav() {
		document.querySelectorAll( '.wbam-page-jump' ).forEach( function ( nav ) {
			var select = nav.querySelector( '.wbam-page-jump__select' );
			if ( ! select ) {
				return;
			}

			nav.classList.add( 'wbam-js-enhanced' );
			select.addEventListener( 'change', function () {
				if ( this.value ) {
					window.location.hash = this.value;
				}
			} );
		} );
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
		initSettingsNavSelect();
		initPageJumpNav();
		initGeoProviderToggle();
	} );
}() );
