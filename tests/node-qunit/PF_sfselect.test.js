'use strict';

// PF_sfselect.js writes three pure helper functions (parseFieldIdentifier,
// parsePlainlistQueryResult, arrayEqual) onto window.pageforms so that they
// can be tested without triggering DOM events.  Ensure the namespace object
// exists BEFORE the script is required for the first time.
window.pageforms = window.pageforms || {};

function loadSfselectScript() {
	const scriptPath = '../../libs/PF_sfselect.js';
	delete require.cache[ require.resolve( scriptPath ) ];
	require( scriptPath );
}

QUnit.module( 'PF_sfselect', {
	beforeEach: () => {
		// initialize() inside the IIFE calls mw.config.get('sf_select'); return
		// null so getSfsObjects() yields an empty array (no DOM interaction).
		mw.config = { get: () => null };
		loadSfselectScript();
	}
} );

// ── parseFieldIdentifier ────────────────────────────────────────────────────

QUnit.test( 'parseFieldIdentifier: simple field — no index, no list', ( assert ) => {
	const result = window.pageforms.parseFieldIdentifier( 'MyTemplate[MyField]' );
	assert.strictEqual( result.template, 'MyTemplate', 'template' );
	assert.strictEqual( result.property, 'MyField', 'property' );
	assert.strictEqual( result.index, null, 'index is null' );
	assert.false( result.isList, 'isList is false' );
} );

QUnit.test( 'parseFieldIdentifier: field with numeric index, no list', ( assert ) => {
	const result = window.pageforms.parseFieldIdentifier( 'MyTemplate[0][MyField]' );
	assert.strictEqual( result.template, 'MyTemplate', 'template' );
	assert.strictEqual( result.property, 'MyField', 'property' );
	assert.strictEqual( result.index, '0', 'index extracted correctly' );
	assert.false( result.isList, 'isList is false' );
} );

QUnit.test( 'parseFieldIdentifier: list field — no index', ( assert ) => {
	const result = window.pageforms.parseFieldIdentifier( 'MyTemplate[MyField][]' );
	assert.strictEqual( result.template, 'MyTemplate', 'template' );
	assert.strictEqual( result.property, 'MyField', 'property' );
	assert.strictEqual( result.index, null, 'index is null' );
	assert.true( result.isList, 'isList is true' );
} );

QUnit.test( 'parseFieldIdentifier: list field with numeric index', ( assert ) => {
	const result = window.pageforms.parseFieldIdentifier( 'MyTemplate[0][MyField][]' );
	assert.strictEqual( result.template, 'MyTemplate', 'template' );
	assert.strictEqual( result.property, 'MyField', 'property' );
	assert.strictEqual( result.index, '0', 'index extracted correctly' );
	assert.true( result.isList, 'isList is true' );
} );

QUnit.test( 'parseFieldIdentifier: non-zero index is preserved as string', ( assert ) => {
	const result = window.pageforms.parseFieldIdentifier( 'Tpl[42][Field]' );
	assert.strictEqual( result.index, '42', 'index string preserved' );
} );

// ── parsePlainlistQueryResult ───────────────────────────────────────────────

QUnit.test( 'parsePlainlistQueryResult: plain value without brackets returns identity pair', ( assert ) => {
	const result = window.pageforms.parsePlainlistQueryResult( [ 'Plain Value' ] );
	assert.deepEqual( result, [ [ 'Plain Value', 'Plain Value' ] ] );
} );

QUnit.test( 'parsePlainlistQueryResult: "Page (Label)" splits into value/label pair', ( assert ) => {
	const result = window.pageforms.parsePlainlistQueryResult( [ 'Page Title (Display Label)' ] );
	assert.deepEqual( result, [ [ 'Page Title', 'Display Label' ] ] );
} );

QUnit.test( 'parsePlainlistQueryResult: nested brackets — only outermost pair is split', ( assert ) => {
	const result = window.pageforms.parsePlainlistQueryResult( [ 'Page (Nested (Inner) Label)' ] );
	assert.deepEqual( result, [ [ 'Page', 'Nested (Inner) Label' ] ] );
} );

QUnit.test( 'parsePlainlistQueryResult: empty string returns identity pair', ( assert ) => {
	const result = window.pageforms.parsePlainlistQueryResult( [ '' ] );
	assert.deepEqual( result, [ [ '', '' ] ] );
} );

QUnit.test( 'parsePlainlistQueryResult: null input is treated as empty string', ( assert ) => {
	// value = value || '' coerces null → ''
	const result = window.pageforms.parsePlainlistQueryResult( [ null ] );
	assert.deepEqual( result, [ [ '', '' ] ] );
} );

QUnit.test( 'parsePlainlistQueryResult: multiple values are processed independently', ( assert ) => {
	const result = window.pageforms.parsePlainlistQueryResult( [ 'A (X)', 'B (Y)', 'C' ] );
	assert.deepEqual( result, [ [ 'A', 'X' ], [ 'B', 'Y' ], [ 'C', 'C' ] ] );
} );

QUnit.test( 'parsePlainlistQueryResult: leading/trailing whitespace is trimmed', ( assert ) => {
	const result = window.pageforms.parsePlainlistQueryResult( [ '  Page  ( Label ) ' ] );
	assert.deepEqual( result, [ [ 'Page', 'Label' ] ] );
} );

// ── arrayEqual ──────────────────────────────────────────────────────────────

QUnit.test( 'arrayEqual: two empty arrays are equal', ( assert ) => {
	assert.true( window.pageforms.arrayEqual( [], [] ) );
} );

QUnit.test( 'arrayEqual: identical single-element arrays are equal', ( assert ) => {
	assert.true( window.pageforms.arrayEqual( [ 'a' ], [ 'a' ] ) );
} );

QUnit.test( 'arrayEqual: same elements in different order are equal (sort-normalised)', ( assert ) => {
	assert.true( window.pageforms.arrayEqual( [ 'b', 'a' ], [ 'a', 'b' ] ) );
} );

QUnit.test( 'arrayEqual: arrays with different elements are not equal', ( assert ) => {
	assert.false( window.pageforms.arrayEqual( [ 'a' ], [ 'b' ] ) );
} );

QUnit.test( 'arrayEqual: arrays of different lengths are not equal', ( assert ) => {
	assert.false( window.pageforms.arrayEqual( [ 'a' ], [ 'a', 'b' ] ) );
} );

QUnit.test( 'arrayEqual: multi-element unsorted match is equal', ( assert ) => {
	assert.true( window.pageforms.arrayEqual( [ 'c', 'a', 'b' ], [ 'b', 'c', 'a' ] ) );
} );
