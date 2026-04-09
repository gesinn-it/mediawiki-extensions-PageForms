/*
* ext.pf.js
*
* @file
*
*
* @licence GNU GPL v2+
* @author Jatin Mehta
*
*/

window.pf = ( function ( $, undefined ) {
	'use strict';
	/**
	 *
	 * Declares methods and properties that are available through the pf
	 * namespace.
	 *
	 * @class pf
	 * @classdesc alternateClassName pageforms
	 * @singleton
	 */
	return {

	};
}( jQuery ) );

// Assigning namespace — extend any existing window.pageforms object so that
// modules loaded before ext.pageforms.main (e.g. ext.pageforms.originalValueLookup)
// are not overwritten.
window.pf = window.pageforms = Object.assign( window.pageforms || {}, pf );

/**
 * Build the API request parameters for a pfautocomplete API call.
 *
 * Handles all standard data types (cargo field, category, namespace,
 * property, concept, and any generic data_type=settings mapping).
 * Does NOT handle dep_on (requires runtime DOM access) or wikidata
 * (requires special field-substitution logic) — those remain in the
 * individual widget files.
 *
 * @param {string} dataType  e.g. 'category', 'cargo field', 'property'
 * @param {string} settings  the autocompletesettings value
 * @param {string} substr    the current search term
 * @return {Object} API params ready for mw.Api().get()
 */
pf.buildAutocompleteParams = function ( dataType, settings, substr ) {
	const params = { action: 'pfautocomplete', format: 'json', substr: substr };
	if ( dataType === 'cargo field' ) {
		const parts = settings.split( '|' );
		params.cargo_table = parts[ 0 ];
		params.cargo_field = parts[ 1 ];
		if ( parts.length > 2 ) {
			params.cargo_where = parts[ 2 ];
		}
	} else if ( dataType ) {
		params[ dataType ] = settings;
	}
	return params;
};
