global.pf = global.pf || {};
require( '../../libs/PF_AutocompleteWidget.js' );

function createWidgetDouble( overrides ) {
	const double = {
		maxSuggestions: undefined,
		getValue: () => '',
		highlightText: pf.AutocompleteWidget.prototype.highlightText
	};
	Object.keys( overrides || {} ).forEach( function ( key ) {
		double[ key ] = overrides[ key ];
	} );
	return double;
}

QUnit.module( 'PF_AutocompleteWidget', {
	beforeEach: function () {
		mw.message = () => ( { text: () => '' } );
	}
} );

// --- highlightText ---

QUnit.test( 'highlightText returns OO.ui.HtmlSnippet', function ( assert ) {
	const w = createWidgetDouble( { getValue: () => 'test' } );
	const result = pf.AutocompleteWidget.prototype.highlightText.call( w, 'a test value' );
	assert.ok( result instanceof OO.ui.HtmlSnippet, 'result is HtmlSnippet' );
} );

QUnit.test( 'highlightText wraps matching substring in <strong>', function ( assert ) {
	const w = createWidgetDouble( { getValue: () => 'test' } );
	const result = pf.AutocompleteWidget.prototype.highlightText.call( w, 'a test value' );
	assert.ok( result.toString().includes( '<strong>test</strong>' ), 'matched text is bolded' );
} );

QUnit.test( 'highlightText returns plain label when no match', function ( assert ) {
	const w = createWidgetDouble( { getValue: () => 'xyz' } );
	const result = pf.AutocompleteWidget.prototype.highlightText.call( w, 'a test value' );
	assert.equal( result.toString(), 'a test value', 'label returned unchanged when no match' );
} );

QUnit.test( 'highlightText match is case-insensitive', function ( assert ) {
	const w = createWidgetDouble( { getValue: () => 'TEST' } );
	const result = pf.AutocompleteWidget.prototype.highlightText.call( w, 'a test value' );
	assert.ok( result.toString().includes( '<strong>' ), 'case-insensitive match produces bold' );
} );

// --- getLookupCacheDataFromResponse ---

QUnit.test( 'getLookupCacheDataFromResponse returns response as-is', function ( assert ) {
	const response = { pfautocomplete: [ { title: 'Foo' } ] };
	const result = pf.AutocompleteWidget.prototype.getLookupCacheDataFromResponse.call( {}, response );
	assert.deepEqual( result, response, 'response passed through unchanged' );
} );

QUnit.test( 'getLookupCacheDataFromResponse returns empty array for falsy response', function ( assert ) {
	const result = pf.AutocompleteWidget.prototype.getLookupCacheDataFromResponse.call( {}, null );
	assert.deepEqual( result, [], 'null response falls back to empty array' );
} );

// --- getLookupMenuOptionsFromData ---

QUnit.test( 'getLookupMenuOptionsFromData returns empty array when pfautocomplete is missing', function ( assert ) {
	const w = createWidgetDouble();
	const result = pf.AutocompleteWidget.prototype.getLookupMenuOptionsFromData.call( w, {} );
	assert.deepEqual( result, [], 'empty array when pfautocomplete key absent' );
} );

QUnit.test( 'getLookupMenuOptionsFromData returns single disabled option for empty results', function ( assert ) {
	const w = createWidgetDouble();
	const result = pf.AutocompleteWidget.prototype.getLookupMenuOptionsFromData.call(
		w, { pfautocomplete: [] }
	);
	assert.equal( result.length, 1, 'one option returned for empty results' );
	assert.true( result[ 0 ].isDisabled(), 'returned option is disabled' );
} );

QUnit.test( 'getLookupMenuOptionsFromData returns one option per result', function ( assert ) {
	const w = createWidgetDouble( { getValue: () => 'Fo' } );
	const result = pf.AutocompleteWidget.prototype.getLookupMenuOptionsFromData.call( w, {
		pfautocomplete: [ { title: 'Foo' }, { title: 'Bar' } ]
	} );
	assert.equal( result.length, 2, 'two options returned for two results' );
	assert.equal( result[ 0 ].getData(), 'Foo', 'first option data matches title' );
	assert.equal( result[ 1 ].getData(), 'Bar', 'second option data matches title' );
} );

QUnit.test( 'getLookupMenuOptionsFromData respects maxSuggestions limit', function ( assert ) {
	const w = createWidgetDouble( { maxSuggestions: 2, getValue: () => '' } );
	const result = pf.AutocompleteWidget.prototype.getLookupMenuOptionsFromData.call( w, {
		pfautocomplete: [ { title: 'Foo' }, { title: 'Bar' }, { title: 'Baz' } ]
	} );
	assert.equal( result.length, 1, 'result count limited to maxSuggestions - 1' );
} );
