/**
 * Settings-page behaviours: the placements matrix, the Delete Data
 * confirmation and the Inactive Link URL row.
 *
 * Two behaviours: an advertiser gate can never outlive its site gate, and
 * closing a slot that carries live creatives asks first. Both are
 * conveniences — sanitize_settings() enforces the intersection server-side.
 */
( function () {
	'use strict';

	// Ticking Delete Data on Uninstall asks first (card 10344382999).
	var deleteData = document.getElementById( 'wbam_setting_delete_data_on_uninstall' );
	if ( deleteData && window.wbamToast ) {
		deleteData.addEventListener( 'change', function () {
			if ( deleteData.checked ) {
				window.wbamToast.confirm( window.wbamPlacementSettings.confirmDeleteData, function () {}, function () {
					deleteData.checked = false;
				} );
			}
		} );
	}

	// The Inactive Link URL only matters for "Redirect to custom URL", and is
	// required then.
	var inactiveAction = document.getElementById( 'wbam_setting_link_inactive_action' );
	var inactiveUrl = document.getElementById( 'wbam_setting_link_inactive_url' );
	if ( inactiveAction && inactiveUrl ) {
		var syncInactiveUrl = function () {
			var custom = 'custom' === inactiveAction.value;
			var row = inactiveUrl.closest( 'tr' );
			if ( row ) {
				// WP's .form-table tr { display: table-row } beats [hidden].
				row.hidden = ! custom;
				row.style.display = custom ? '' : 'none';
			}
			inactiveUrl.required = custom;
		};
		inactiveAction.addEventListener( 'change', syncInactiveUrl );
		syncInactiveUrl();
	}

	var table = document.querySelector( '.wbam-placement-matrix' );
	if ( ! table ) {
		return;
	}

	table.addEventListener( 'change', function ( event ) {
		var box = event.target;
		if ( ! box.classList.contains( 'wbam-gate-site' ) ) {
			return;
		}

		var id = box.getAttribute( 'data-placement' );
		var count = parseInt( box.getAttribute( 'data-count' ), 10 ) || 0;
		var advertiser = table.querySelector(
			'.wbam-gate-advertiser[data-placement="' + id + '"]'
		);

		function applyGate() {
			if ( advertiser ) {
				advertiser.disabled = ! box.checked;
				if ( ! box.checked ) {
					advertiser.checked = false;
				}
			}
		}

		// window.confirm() is synchronous — its return value decided
		// in-line whether to revert the checkbox. wbamToast.confirm() is
		// callback-based, so the checkbox is left in its already-toggled
		// (unchecked) state while the dialog is open, and the two possible
		// outcomes are handled explicitly: confirmed applies the gate to
		// the advertiser checkbox exactly as before; cancelled restores
		// the site checkbox to checked and leaves the advertiser checkbox
		// untouched, matching the original revert-on-cancel behaviour.
		if ( ! box.checked && count > 0 ) {
			var message = window.wbamPlacementSettings.confirmDisable.replace( '%d', count );
			window.wbamToast.confirm( message, function () {
				applyGate();
			}, function () {
				box.checked = true;
			} );
			return;
		}

		applyGate();
	} );
}() );
