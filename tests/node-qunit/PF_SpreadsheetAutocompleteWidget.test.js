global.pf = global.pf || {};
// pf.highlightText and other shared helpers live in ext.pf.js; bridge window.pf → global.pf
// so that PF_SpreadsheetAutocompleteWidget thin wrappers can resolve them in the Node.js test environment.
require( '../../libs/ext.pf.js' );
Object.assign( global.pf, global.window.pf );
require( '../../libs/PF_AutocompleteWidget.js' );
require( '../../libs/PF_SpreadsheetAutocompleteWidget.js' );

function createWidgetDouble( overrides ) {
	const double = {
		config: {},
		getValue: () => '',
		highlightText: pf.AutocompleteWidget.prototype.highlightText,
		getNoMatchesOOUIMenuOptionWidget: pf.AutocompleteWidget.prototype.getNoMatchesOOUIMenuOptionWidget,
		checkIfAnyWordStartsWithInputValue: pf.AutocompleteWidget.prototype.checkIfAnyWordStartsWithInputValue
	};
	Object.keys( overrides || {} ).forEach( ( key ) => {
		double[ key ] = overrides[ key ];
	} );
	return double;
}

QUnit.module( 'PF_SpreadsheetAutocompleteWidget', {
	beforeEach: function () {
		mw.message = () => ( { text: () => '' } );
		mw.config = { get: () => undefined };
	}
} );

// --- getLookupMenuOptionsFromData: error response ---

QUnit.test( 'getLookupMenuOptionsFromData returns no-matches option on error response', ( assert ) => {
	const w = createWidgetDouble( { config: { autocompletedatatype: 'category' } } );
	const result = pf.spreadsheetAutocompleteWidget.prototype.getLookupMenuOptionsFromData.call(
		w, { error: 'some API error' }
	);
	assert.equal( result.length, 1, 'one option returned on error' );
	assert.true( result[ 0 ].isDisabled(), 'returned option is disabled' );
} );

// --- getLookupMenuOptionsFromData: category / dep_on / concept / property ---

[ 'category', 'dep_on', 'concept', 'property' ].forEach( ( dataType ) => {
	QUnit.test( 'getLookupMenuOptionsFromData (' + dataType + ') returns no-matches option for empty results', ( assert ) => {
		const w = createWidgetDouble( { config: { autocompletedatatype: dataType } } );
		const result = pf.spreadsheetAutocompleteWidget.prototype.getLookupMenuOptionsFromData.call(
			w, { pfautocomplete: [] }
		);
		assert.equal( result.length, 1, 'one option returned for empty results' );
		assert.true( result[ 0 ].isDisabled(), 'returned option is disabled' );
	} );

	QUnit.test( 'getLookupMenuOptionsFromData (' + dataType + ') returns one option per result', ( assert ) => {
		const w = createWidgetDouble( { config: { autocompletedatatype: dataType }, getValue: () => 'Fo' } );
		const result = pf.spreadsheetAutocompleteWidget.prototype.getLookupMenuOptionsFromData.call( w, {
			pfautocomplete: [ { title: 'Foo' }, { title: 'Bar' } ]
		} );
		assert.equal( result.length, 2, 'two options returned for two results' );
		assert.equal( result[ 0 ].getData(), 'Foo', 'first option data matches title' );
		assert.equal( result[ 1 ].getData(), 'Bar', 'second option data matches title' );
	} );
} );

// --- getLookupMenuOptionsFromData: external data ---

QUnit.test( 'getLookupMenuOptionsFromData (external data) returns no-matches option when settings missing', ( assert ) => {
	mw.config.get = ( key ) => key === 'wgPageFormsEDSettings' ? null : undefined;
	const w = createWidgetDouble( { config: { autocompletedatatype: 'external data', autocompletesettings: 'mySource' } } );
	const result = pf.spreadsheetAutocompleteWidget.prototype.getLookupMenuOptionsFromData.call( w, {} );
	assert.equal( result.length, 1, 'one option returned when ED settings missing' );
	assert.true( result[ 0 ].isDisabled(), 'returned option is disabled' );
} );

QUnit.test( 'getLookupMenuOptionsFromData (external data) returns matching options honoring all-chars flag', ( assert ) => {
	mw.config.get = ( key ) => ( {
		wgPageFormsEDSettings: { mySource: { title: 'titleField' } },
		edgValues: { titleField: [ 'Foo', 'Bar', 'Baz' ] },
		wgPageFormsAutocompleteOnAllChars: true
	} )[ key ];
	const w = createWidgetDouble( {
		config: { autocompletedatatype: 'external data', autocompletesettings: 'mySource' },
		getValue: () => 'a'
	} );
	const result = pf.spreadsheetAutocompleteWidget.prototype.getLookupMenuOptionsFromData.call( w, {} );
	assert.equal( result.length, 2, 'two options match substring "a"' );
	assert.equal( result[ 0 ].getData(), 'Bar', 'first match is Bar' );
	assert.equal( result[ 1 ].getData(), 'Baz', 'second match is Baz' );
} );

QUnit.test( 'getLookupMenuOptionsFromData (external data) returns no-matches option when nothing matches', ( assert ) => {
	mw.config.get = ( key ) => ( {
		wgPageFormsEDSettings: { mySource: { title: 'titleField' } },
		edgValues: { titleField: [ 'Foo', 'Bar' ] },
		wgPageFormsAutocompleteOnAllChars: true
	} )[ key ];
	const w = createWidgetDouble( {
		config: { autocompletedatatype: 'external data', autocompletesettings: 'mySource' },
		getValue: () => 'zzz'
	} );
	const result = pf.spreadsheetAutocompleteWidget.prototype.getLookupMenuOptionsFromData.call( w, {} );
	assert.equal( result.length, 1, 'one option returned when nothing matches' );
	assert.true( result[ 0 ].isDisabled(), 'returned option is disabled' );
} );

// --- getDependentFieldOpts ---

QUnit.test( 'getDependentFieldOpts reads base value and prop from DOM cell', ( assert ) => {
	$( '<td>' )
		.attr( { 'data-y': '2', origname: 'baseField', name: 'baseFieldRealName' } )
		.html( 'baseCellValue' )
		.appendTo( document.body );

	mw.config.get = ( key ) => key === 'wgPageFormsFieldProperties' ? {} : undefined;

	const w = createWidgetDouble( { config: { autocompletesettings: 'myProp' } } );
	const result = pf.spreadsheetAutocompleteWidget.prototype.getDependentFieldOpts.call( w, '2', 'baseField' );

	assert.equal( result.base_value, 'baseCellValue', 'base_value read from cell HTML' );
	assert.equal( result.base_prop, 'baseFieldRealName', 'base_prop falls back to name attribute' );
	assert.equal( result.prop, 'myProp', 'prop taken from autocompletesettings config' );
} );

QUnit.test( 'getDependentFieldOpts prefers wgPageFormsFieldProperties over name attribute', ( assert ) => {
	$( '<td>' )
		.attr( { 'data-y': '3', origname: 'baseField2', name: 'fallbackName' } )
		.html( 'val' )
		.appendTo( document.body );

	mw.config.get = ( key ) => key === 'wgPageFormsFieldProperties' ? { baseField2: 'configuredProp' } : undefined;

	const w = createWidgetDouble( { config: { autocompletesettings: 'myProp' } } );
	const result = pf.spreadsheetAutocompleteWidget.prototype.getDependentFieldOpts.call( w, '3', 'baseField2' );

	assert.equal( result.base_prop, 'configuredProp', 'wgPageFormsFieldProperties takes precedence' );
} );
