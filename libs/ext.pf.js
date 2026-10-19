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
 * Handles all standard data types (category, namespace, property, concept,
 * and any generic data_type=settings mapping).
 * Does NOT handle dep_on (requires runtime DOM access) or wikidata
 * (requires special field-substitution logic) — those remain in the
 * individual widget files.
 *
 * @param {string} dataType  e.g. 'category', 'property', 'namespace'
 * @param {string} settings  the autocompletesettings value
 * @param {string} substr    the current search term
 * @return {Object} API params ready for mw.Api().get()
 */
pf.buildAutocompleteParams = function ( dataType, settings, substr ) {
	const params = { action: 'pfautocomplete', format: 'json', substr: substr };
	if ( dataType ) {
		params[ dataType ] = settings;
	}
	return params;
};

/**
 * Returns whether the element is part of a multiple-instance template
 * (i.e. has an origname attribute).
 *
 * @param {jQuery} element
 * @return {boolean}
 */
pf.partOfMultiple = function ( element ) {
	return element.attr( 'origname' ) !== undefined;
};

/**
 * Returns the attribute name used to identify the field in the DOM.
 *
 * @param {jQuery} element
 * @return {string}  'origname' for multiple-instance templates, 'name' otherwise
 */
pf.nameAttr = function ( element ) {
	return pf.partOfMultiple( element ) ? 'origname' : 'name';
};

/**
 * Escape the characters that are significant in HTML text and attribute values.
 *
 * @param {string} text
 * @return {string}
 */
pf.escapeHtml = function ( text ) {
	const replacements = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' };
	return String( text ).replace( /[&<>"']/g, ( character ) => replacements[ character ] );
};

/**
 * Highlight the first occurrence of searchTerm inside suggestion with <strong> tags.
 * Matching is case-insensitive. Both strings are treated as plain text and
 * escaped; the result is returned as an OO.ui.HtmlSnippet so it can be used as
 * an OOUI menu-option label.
 *
 * @param {string} searchTerm  The current input / search string
 * @param {string} suggestion  The label text to highlight within
 * @return {OO.ui.HtmlSnippet}
 */
pf.highlightText = function ( searchTerm, suggestion ) {
	const searchRegexp = new RegExp( searchTerm.replace( /[\^$()[\]{}*.+?|\\]/g, '\\$&' ), 'i' );
	const match = searchRegexp.exec( suggestion );
	if ( !match ) {
		return new OO.ui.HtmlSnippet( pf.escapeHtml( suggestion ) );
	}
	const end = match.index + match[ 0 ].length;
	return new OO.ui.HtmlSnippet(
		pf.escapeHtml( suggestion.slice( 0, match.index ) ) +
		'<strong>' + pf.escapeHtml( match[ 0 ] ) + '</strong>' +
		pf.escapeHtml( suggestion.slice( end ) )
	);
};
