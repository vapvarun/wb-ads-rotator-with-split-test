/**
 * Editor script for the WB Ads blocks: `wb-ads/ad` and `wb-ads/placement`.
 *
 * Plain ES5-safe JS against `wp` globals - no JSX, no build step, matching
 * the rest of this plugin's front-end asset pipeline (Grunt copies/minifies,
 * it doesn't bundle React). Both blocks preview via ServerSideRender so the
 * editor always shows the exact HTML the front end renders.
 *
 * @package WB_Ad_Manager
 * @since   3.2.0
 */
( function( wp ) {
	'use strict';

	var el                 = wp.element.createElement;
	var useState            = wp.element.useState;
	var useEffect            = wp.element.useEffect;
	var registerBlockType  = wp.blocks.registerBlockType;
	var useBlockProps       = wp.blockEditor.useBlockProps;
	var InspectorControls   = wp.blockEditor.InspectorControls;
	var ServerSideRender    = wp.serverSideRender;
	var PanelBody           = wp.components.PanelBody;
	var SelectControl       = wp.components.SelectControl;
	var ComboboxControl     = wp.components.ComboboxControl;
	var useDebounce         = wp.compose.useDebounce;
	var addQueryArgs        = wp.url.addQueryArgs;
	var Placeholder         = wp.components.Placeholder;
	var __                  = wp.i18n.__;
	var apiFetch            = wp.apiFetch;

	/**
	 * Fetch a REST path once and hand the caller `[ items, error ]`.
	 *
	 * @param {string} path      REST path.
	 * @param {string} listKey   Key in the response holding the list.
	 * @return {Array} [ items, error ]
	 */
	function useWbamList( path, listKey ) {
		var state = useState( [] );
		var items = state[ 0 ];
		var setItems = state[ 1 ];

		useEffect(
			function() {
				var cancelled = false;

				if ( ! path ) {
					setItems( [] );
					return;
				}

				apiFetch( { path: path } ).then( function( response ) {
					if ( ! cancelled && response && response[ listKey ] ) {
						setItems( response[ listKey ] );
					}
				} ).catch( function() {
					// Editor picker degrades to "no options" - the block itself
					// still renders via ServerSideRender once an id is set.
				} );

				return function() {
					cancelled = true;
				};
			},
			[ path ]
		);

		return items;
	}

	function AdEdit( props ) {
		var attributes  = props.attributes;
		var setAttributes = props.setAttributes;
		var blockProps  = useBlockProps();
		var searchState = useState( '' );
		var setSearch   = useDebounce( searchState[ 1 ], 300 );

		// Search by title (20 at a time) instead of loading every ad, and
		// fetch the selected ad by id so its title shows even when it is
		// not among the search results.
		var ads      = useWbamList( addQueryArgs( '/wbam/v1/ads', { per_page: 20, search: searchState[ 0 ] } ), 'ads' );
		var selected = useWbamList( attributes.adId ? addQueryArgs( '/wbam/v1/ads', { include: [ attributes.adId ] } ) : '', 'ads' );

		var seen    = {};
		var options = selected.concat( ads ).filter( function( ad ) {
			var isNew = ! seen[ ad.id ];
			seen[ ad.id ] = true;
			return isNew;
		} ).map( function( ad ) {
			return { label: ad.title || ( '#' + ad.id ), value: String( ad.id ) };
		} );

		function onChange( value ) {
			setAttributes( { adId: parseInt( value, 10 ) || 0 } );
		}

		function picker( label ) {
			return el( ComboboxControl, {
				label: label,
				value: attributes.adId ? String( attributes.adId ) : null,
				options: options,
				onChange: onChange,
				onFilterValueChange: setSearch,
				placeholder: __( 'Search ads by title', 'wb-ads-rotator-with-split-test' ),
			} );
		}

		var inspector = el(
			InspectorControls,
			{},
			el(
				PanelBody,
				{ title: __( 'Ad', 'wb-ads-rotator-with-split-test' ) },
				picker( __( 'Ad', 'wb-ads-rotator-with-split-test' ) )
			)
		);

		if ( ! attributes.adId ) {
			return el(
				'div',
				blockProps,
				inspector,
				el(
					Placeholder,
					{
						icon: 'megaphone',
						label: __( 'WB Ad', 'wb-ads-rotator-with-split-test' ),
						instructions: __( 'Choose an ad. Only enabled ads are listed.', 'wb-ads-rotator-with-split-test' ),
					},
					picker( __( 'Ad', 'wb-ads-rotator-with-split-test' ) )
				)
			);
		}

		return el(
			'div',
			blockProps,
			inspector,
			el( ServerSideRender, { block: 'wb-ads/ad', attributes: attributes } )
		);
	}

	registerBlockType( 'wb-ads/ad', {
		edit: AdEdit,
		save: function() {
			return null;
		},
	} );

	function PlacementEdit( props ) {
		var attributes  = props.attributes;
		var setAttributes = props.setAttributes;
		var blockProps  = useBlockProps();
		var placements  = useWbamList( '/wbam/v1/ads/placements', 'placements' );

		var options = [ { label: __( 'Select a placement…', 'wb-ads-rotator-with-split-test' ), value: '' } ].concat(
			placements.map( function( placement ) {
				return { label: placement.label, value: placement.id };
			} )
		);

		function onChange( value ) {
			setAttributes( { placementId: value } );
		}

		var inspector = el(
			InspectorControls,
			{},
			el(
				PanelBody,
				{ title: __( 'Placement', 'wb-ads-rotator-with-split-test' ) },
				el( SelectControl, {
					label: __( 'Placement', 'wb-ads-rotator-with-split-test' ),
					value: attributes.placementId,
					options: options,
					onChange: onChange,
				} )
			)
		);

		if ( ! attributes.placementId ) {
			return el(
				'div',
				blockProps,
				inspector,
				el(
					Placeholder,
					{
						icon: 'layout',
						label: __( 'WB Ad Placement', 'wb-ads-rotator-with-split-test' ),
						instructions: __( 'Choose a placement. It shows whatever ad the rotation picks for that slot.', 'wb-ads-rotator-with-split-test' ),
					},
					el( SelectControl, { value: attributes.placementId, options: options, onChange: onChange } )
				)
			);
		}

		return el(
			'div',
			blockProps,
			inspector,
			el( ServerSideRender, { block: 'wb-ads/placement', attributes: attributes } )
		);
	}

	registerBlockType( 'wb-ads/placement', {
		edit: PlacementEdit,
		save: function() {
			return null;
		},
	} );
} )( window.wp );
