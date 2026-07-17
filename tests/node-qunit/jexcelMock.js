'use strict';

/**
 * Fake implementation of the `jexcel`/`jspreadsheet` global that
 * `libs/PF_spreadsheet.js` wraps. Covers only the API surface that
 * wrapper actually calls: the factory function (invoked as
 * `jexcel( table, options )`, not `new jexcel(...)`) and the instance
 * methods `setValue`/`deleteRow`/`moveRow`/`getData`/`insertRow`/`setData`
 * called on the object it returns (`mw.spreadsheets[id]`).
 *
 * install() assigns `global.jexcel` and returns a teardown function that
 * deletes it again. `jexcel.prototype` is a plain object so that
 * production code's `jexcel.prototype.foo = function () {...}` extension
 * pattern keeps working unmodified.
 */

function makeInstance( table, options ) {
	const data = ( options && options.data ) ? options.data.map( ( row ) => row.slice() ) : [];

	return {
		table,
		options,
		// Test helper (not part of the real jexcel API): inspect/mutate
		// rows directly instead of guessing what production code stored.
		_data: data,
		setValue: function ( cell, value ) {
			this._lastSetValue = { cell, value };
		},
		deleteRow: function ( rowNum ) {
			this._data.splice( rowNum, 1 );
		},
		moveRow: function ( from, to ) {
			const row = this._data.splice( from, 1 )[ 0 ];
			this._data.splice( to, 0, row );
		},
		getData: function () {
			return this._data;
		},
		insertRow: function () {
			this._data.push( [] );
		},
		setData: function ( newData ) {
			this._data = newData;
		}
	};
}

function makeJexcel() {
	function jexcel( table, options ) {
		return makeInstance( table, options );
	}
	jexcel.prototype = {};
	return jexcel;
}

/**
 * Install a fresh jexcel fake as a global.
 *
 * @return {Function} teardown() - deletes the global again.
 */
function install() {
	global.jexcel = makeJexcel();

	return function teardown() {
		delete global.jexcel;
	};
}

module.exports = { install };
