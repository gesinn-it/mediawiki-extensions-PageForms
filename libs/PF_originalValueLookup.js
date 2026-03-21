/**
 * Creates a function to look up the original (internal) value corresponding
 * to a displayed value used in a PageForms element with value mapping.
 *
 * Originally maintained as res/pf.originalValueLookup.js in the
 * SemanticFormsSelect extension. The SFS authors noted this belongs in PF.
 *
 * @author Alexander Gesinn
 * @file
 */

/* global mediaWiki */

window.pageforms = window.pageforms || {};
window.pf = window.pf || window.pageforms;

( function ( $, mw, pf ) {
	'use strict';

	/**
	 * Create a function to look up the original value corresponding to a
	 * (possibly display-mapped) value used in a PageForms element.
	 *
	 * For elements with `autocompletesettings`, the lookup uses
	 * `wgPageFormsAutocompleteValues`. Fields using remote autocompletion are
	 * absent from that config variable; the lookup gracefully returns the
	 * identity function in that case.
	 *
	 * @param {jQuery} element the element for which to look up original values
	 * @return {function(string): string} lookup function
	 */
	pf.originalValueLookup = function originalValueLookup( element ) {
		const variant = Object.keys( elementVariants )
			.map( ( k ) => elementVariants[ k ] )
			.find( ( v ) => v.condition( element ) );
		return variant ?
			lookupIn( variant.mappings( element ) ) :
			( value ) => value;
	};

	const elementVariants = {
		radiobutton: {
			condition: ( e ) => e.parent().hasClass( 'radioButtonItem' ),
			mappings: ( e ) => (
			e.parents( '.radioButtonSpan' )
				.find( '[data-original-value]' )
				.map( function () {
					return {
						original: $( this ).attr( 'data-original-value' ),
						value: $( this ).attr( 'value' )
					};
				} )
				.get()
		)
		},
		autocomplete: {
			condition: ( e ) => !!e.attr( 'autocompletesettings' ),
			mappings: ( e ) => {
				const autocompletesettings = e.attr( 'autocompletesettings' );
				const allValues = mw.config.get( 'wgPageFormsAutocompleteValues' );
				const mapping = allValues && allValues[ autocompletesettings ];
				if ( !mapping ) {
					// Field uses remote autocompletion or values not preloaded:
					// no mapping available, return empty so identity is used.
					return [];
				}
				return Object.keys( mapping )
					.map( ( k ) => ( { original: k, value: mapping[ k ] } ) );
			}
		}
	};

	function lookupIn( mappings ) {
		return ( value ) => {
			const match = mappings.find( ( m ) => m.value === value );
			return match ? match.original : value;
		};
	}

}( jQuery, mediaWiki, window.pageforms ) );
