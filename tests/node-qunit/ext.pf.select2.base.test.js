'use strict';

// Note: do NOT set global.pageforms here — other test files may already have
// registered constructors on it.
global.pf = global.pf || {};
require( '../../libs/ext.pf.js' );

// ext.pf.select2.base.js reads pf.select2 from the global pf namespace
// and extends mw.config; set up the necessary stubs before loading.
require( '../../libs/ext.pf.select2.base.js' );

// ext.pf.select2.base.js registers pf.select2.base on the `pageforms` global
// (its IIFE receives `pageforms` as the `pf` parameter).
const proto = pageforms.select2.base.prototype;

// ===========================================================
// removeDiacritics
// ===========================================================

QUnit.module( 'pf.select2.base.removeDiacritics', () => {

	QUnit.test( 'ASCII string passes through unchanged', ( assert ) => {
		assert.strictEqual( proto.removeDiacritics( 'hello' ), 'hello' );
	} );

	QUnit.test( 'replaces accented vowels with ASCII equivalents', ( assert ) => {
		assert.strictEqual( proto.removeDiacritics( 'é' ), 'e' );
		assert.strictEqual( proto.removeDiacritics( 'ü' ), 'u' );
		assert.strictEqual( proto.removeDiacritics( 'ñ' ), 'n' );
	} );

	QUnit.test( 'handles full word with diacritics', ( assert ) => {
		assert.strictEqual( proto.removeDiacritics( 'München' ), 'Munchen' );
	} );

	QUnit.test( 'converts to string before processing', ( assert ) => {
		// numbers have no diacritics but should not throw
		assert.strictEqual( proto.removeDiacritics( 42 ), '42' );
	} );

	QUnit.test( 'replaces uppercase diacritics', ( assert ) => {
		assert.strictEqual( proto.removeDiacritics( 'Ä' ), 'A' );
		assert.strictEqual( proto.removeDiacritics( 'Ö' ), 'O' );
	} );

} );

// ===========================================================
// escapeMarkupAndAddHTML
// ===========================================================

QUnit.module( 'pf.select2.base.escapeMarkupAndAddHTML', () => {

	QUnit.test( 'returns non-string input unchanged', ( assert ) => {
		assert.strictEqual( proto.escapeMarkupAndAddHTML( 42 ), 42 );
		assert.strictEqual( proto.escapeMarkupAndAddHTML( null ), null );
	} );

	QUnit.test( 'escapes & < > " \' / \\', ( assert ) => {
		const result = proto.escapeMarkupAndAddHTML( '&<>"\'/\\' );
		assert.ok( result.includes( '&amp;' ), 'escapes &' );
		assert.ok( result.includes( '&lt;' ), 'escapes <' );
		assert.ok( result.includes( '&gt;' ), 'escapes >' );
		assert.ok( result.includes( '&quot;' ), 'escapes "' );
		assert.ok( result.includes( '&#39;' ), "escapes '" );
		assert.ok( result.includes( '&#47;' ), 'escapes /' );
		assert.ok( result.includes( '&#92;' ), 'escapes \\' );
	} );

	QUnit.test( 'wraps result in select2-match-entire span', ( assert ) => {
		const result = proto.escapeMarkupAndAddHTML( 'hello' );
		assert.ok( result.startsWith( '<span class="select2-match-entire">' ) );
		assert.ok( result.endsWith( '</span>' ) );
	} );

	QUnit.test( 'plain text with no special chars is wrapped but not escaped', ( assert ) => {
		const result = proto.escapeMarkupAndAddHTML( 'hello world' );
		assert.ok( result.includes( 'hello world' ) );
	} );

} );

// ===========================================================
// textHighlight
// ===========================================================

QUnit.module( 'pf.select2.base.textHighlight', {
	beforeEach() {
		mw.config = { get: () => false };
	}
}, () => {

	QUnit.test( 'returns empty string when text is undefined', ( assert ) => {
		const result = proto.textHighlight( undefined, 'foo' );
		assert.strictEqual( result, '' );
	} );

	QUnit.test( 'returns plain text when term not found', ( assert ) => {
		const result = proto.textHighlight( 'hello', 'xyz' );
		assert.strictEqual( result, 'hello' );
	} );

	QUnit.test( 'returns markup with bold markers when term found at word start', ( assert ) => {
		// Matches " foo" in "bar foo", word-start matching (onAllChars=false)
		const result = proto.textHighlight( 'bar foo baz', 'foo' );
		const boldStart = String.fromCharCode( 1 );
		const boldEnd = String.fromCharCode( 2 );
		assert.ok( result.includes( boldStart + 'foo' + boldEnd ), 'term wrapped in bold markers' );
	} );

	QUnit.test( 'matches anywhere when wgPageFormsAutocompleteOnAllChars=true', ( assert ) => {
		mw.config = { get: ( key ) => key === 'wgPageFormsAutocompleteOnAllChars' ? true : null };
		const result = proto.textHighlight( 'foobar', 'oba' );
		const boldStart = String.fromCharCode( 1 );
		assert.ok( result.includes( boldStart ), 'match found mid-word when onAllChars=true' );
	} );

	QUnit.test( 'match is case-insensitive', ( assert ) => {
		const result = proto.textHighlight( 'Hello World', 'world' );
		const boldStart = String.fromCharCode( 1 );
		assert.ok( result.includes( boldStart ), 'case-insensitive match' );
	} );

	QUnit.test( 'diacritics are normalized before matching', ( assert ) => {
		// "München" should match "munchen"
		mw.config = { get: ( key ) => key === 'wgPageFormsAutocompleteOnAllChars' ? true : null };
		const result = proto.textHighlight( 'München', 'unchen' );
		const boldStart = String.fromCharCode( 1 );
		assert.ok( result.includes( boldStart ), 'matches after diacritic removal' );
	} );

} );
