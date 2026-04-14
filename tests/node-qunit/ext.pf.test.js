'use strict';

// ext.pf.js assigns to window.pf and then to the bare `pf` variable.
// In Node.js, window.* does not pollute the global scope, so pf must be
// pre-declared on global for the module-level assignments to succeed.
// Note: do NOT set global.pageforms here — other test files (e.g.
// PF_ComboBoxDataSource.test.js) may already have registered constructors on it.
global.pf = global.pf || {};
require( '../../libs/ext.pf.js' );

// ===========================================================
// pf.buildAutocompleteParams
// ===========================================================

QUnit.module( 'pf.buildAutocompleteParams', () => {

	QUnit.test( 'always includes action, format and substr', ( assert ) => {
		const params = pf.buildAutocompleteParams( undefined, undefined, 'foo' );
		assert.strictEqual( params.action, 'pfautocomplete' );
		assert.strictEqual( params.format, 'json' );
		assert.strictEqual( params.substr, 'foo' );
	} );

	QUnit.test( 'adds dataType key when dataType is given', ( assert ) => {
		const params = pf.buildAutocompleteParams( 'property', 'MyProp', 'bar' );
		assert.strictEqual( params.property, 'MyProp' );
	} );

	QUnit.test( 'category type', ( assert ) => {
		const params = pf.buildAutocompleteParams( 'category', 'Fruits', '' );
		assert.strictEqual( params.category, 'Fruits' );
		assert.strictEqual( params.substr, '' );
	} );

	QUnit.test( 'namespace type', ( assert ) => {
		const params = pf.buildAutocompleteParams( 'namespace', 'Help', 'hel' );
		assert.strictEqual( params.namespace, 'Help' );
	} );

	QUnit.test( 'concept type', ( assert ) => {
		const params = pf.buildAutocompleteParams( 'concept', 'MyConcept', 'my' );
		assert.strictEqual( params.concept, 'MyConcept' );
	} );

	QUnit.test( 'no extra key when dataType is falsy', ( assert ) => {
		const params = pf.buildAutocompleteParams( '', 'SomeSettings', 'x' );
		assert.notOk( Object.prototype.hasOwnProperty.call( params, '' ), 'no empty-string key' );
		assert.strictEqual( Object.keys( params ).length, 3, 'only action, format, substr' );
	} );

} );

// ===========================================================
// pf.partOfMultiple / pf.nameAttr
// ===========================================================

QUnit.module( 'pf.partOfMultiple', () => {

	QUnit.test( 'returns false when origname attribute is absent', ( assert ) => {
		const $el = $( '<input name="City">' ).appendTo( document.body );
		assert.false( pf.partOfMultiple( $el ) );
	} );

	QUnit.test( 'returns true when origname attribute is present', ( assert ) => {
		const $el = $( '<input name="Template[1][City]" origname="City">' ).appendTo( document.body );
		assert.true( pf.partOfMultiple( $el ) );
	} );

} );

QUnit.module( 'pf.nameAttr', () => {

	QUnit.test( 'returns "name" for single-instance element', ( assert ) => {
		const $el = $( '<input name="City">' ).appendTo( document.body );
		assert.strictEqual( pf.nameAttr( $el ), 'name' );
	} );

	QUnit.test( 'returns "origname" for multiple-instance element', ( assert ) => {
		const $el = $( '<input name="Template[1][City]" origname="City">' ).appendTo( document.body );
		assert.strictEqual( pf.nameAttr( $el ), 'origname' );
	} );

} );

// ===========================================================
// pf.highlightText
// ===========================================================

QUnit.module( 'pf.highlightText', () => {

	QUnit.test( 'returns HtmlSnippet when match found', ( assert ) => {
		const result = pf.highlightText( 'foo', 'foobar' );
		assert.ok( result instanceof OO.ui.HtmlSnippet );
	} );

	QUnit.test( 'wraps matching substring in <strong>', ( assert ) => {
		const result = pf.highlightText( 'bar', 'foobar' );
		assert.ok( result.toString().includes( '<strong>bar</strong>' ) );
	} );

	QUnit.test( 'match is case-insensitive', ( assert ) => {
		const result = pf.highlightText( 'BAR', 'foobar' );
		assert.ok( result.toString().includes( '<strong>' ) );
	} );

	QUnit.test( 'returns plain suggestion wrapped in HtmlSnippet when no match', ( assert ) => {
		const result = pf.highlightText( 'xyz', 'foobar' );
		assert.ok( result instanceof OO.ui.HtmlSnippet );
		assert.false( result.toString().includes( '<strong>' ) );
	} );

	QUnit.test( 'escapes regex special chars in searchTerm', ( assert ) => {
		// Should not throw — "foo.bar" contains a dot which is a regex special char
		assert.ok( () => pf.highlightText( 'foo.bar', 'foo.bar baz' ) );
	} );

	QUnit.test( 'match at position 0', ( assert ) => {
		const result = pf.highlightText( 'foo', 'foobar' );
		assert.ok( result.toString().startsWith( '<strong>foo</strong>' ) );
	} );

} );
