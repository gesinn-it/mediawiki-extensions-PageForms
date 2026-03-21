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
