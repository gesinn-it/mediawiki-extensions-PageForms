global.pf = global.pf || {};
// pf.highlightText lives in ext.pf.js; bridge window.pf → global.pf so that
// PF_SpreadsheetComboBoxInput can resolve it in the Node.js test environment.
require( '../../libs/ext.pf.js' );
Object.assign( global.pf, global.window.pf );
require( '../../libs/PF_SpreadsheetComboBoxInput.js' );

QUnit.module( 'PF_SpreadsheetComboBoxInput', {
	beforeEach: function () {
		mw.message = ( key ) => ( { text: () => key } );
		mw.config = { get: () => undefined };
	}
} );

// --- construction ---

QUnit.test( 'constructor builds an OO.ui.ComboBoxInputWidget', ( assert ) => {
	const widget = new pf.SpreadsheetComboBoxInput( { autocompletesettings: 'myProp' } );
	assert.true( widget instanceof OO.ui.ComboBoxInputWidget, 'instance is a ComboBoxInputWidget' );
	assert.equal( widget.config.autocompletesettings, 'myProp', 'config is stored on the widget' );
} );

QUnit.test( 'constructor defaults config to an empty object when omitted', ( assert ) => {
	const widget = new pf.SpreadsheetComboBoxInput();
	assert.deepEqual( widget.config, {}, 'config defaults to {}' );
} );

QUnit.test( 'focusing the input triggers setValues', ( assert ) => {
	const widget = new pf.SpreadsheetComboBoxInput( {} );
	widget.setValues = () => {
		assert.true( true, 'setValues called on focus' );
	};
	assert.expect( 1 );
	widget.$input.trigger( 'focus' );
} );

QUnit.test( 'keyup with a non-arrow key triggers setValues', ( assert ) => {
	const widget = new pf.SpreadsheetComboBoxInput( {} );
	let called = false;
	widget.setValues = () => {
		called = true;
	};
	widget.$input.trigger( $.Event( 'keyup', { keyCode: 65 } ) );
	assert.true( called, 'setValues called for a regular key' );
} );

[ 37, 38, 39, 40 ].forEach( ( keyCode ) => {
	QUnit.test( 'keyup with arrow key ' + keyCode + ' does not trigger setValues', ( assert ) => {
		const widget = new pf.SpreadsheetComboBoxInput( {} );
		let called = false;
		widget.setValues = () => {
			called = true;
		};
		widget.$input.trigger( $.Event( 'keyup', { keyCode: keyCode } ) );
		assert.false( called, 'setValues is not called for arrow key ' + keyCode );
	} );
} );

// --- highlightText ---

QUnit.test( 'highlightText wraps matching substring in <strong>', ( assert ) => {
	const widget = new pf.SpreadsheetComboBoxInput( {} );
	widget.setValue( 'test' );
	const result = widget.highlightText( 'a test value' );
	assert.ok( result.toString().includes( '<strong>test</strong>' ), 'matched text is bolded' );
} );

QUnit.test( 'highlightText strips a leading space from the search term', ( assert ) => {
	const widget = new pf.SpreadsheetComboBoxInput( {} );
	widget.setValue( ' test' );
	const result = widget.highlightText( 'a test value' );
	assert.ok( result.toString().includes( '<strong>test</strong>' ), 'leading space stripped before matching' );
} );

// --- getNoMatchesOption ---

QUnit.test( 'getNoMatchesOption returns a disabled option carrying the current value', ( assert ) => {
	const widget = new pf.SpreadsheetComboBoxInput( {} );
	widget.setValue( 'abc' );
	const option = widget.getNoMatchesOption();
	assert.equal( option.data, 'abc', 'data is the current input value' );
	assert.true( option.disabled, 'option is disabled' );
	assert.equal( option.label, 'pf-autocomplete-no-matches', 'label uses the no-matches message' );
} );

// --- checkIfAnyWordStartsWithInputValue ---

QUnit.test( 'checkIfAnyWordStartsWithInputValue is true when a word starts with the value', ( assert ) => {
	const widget = new pf.SpreadsheetComboBoxInput( {} );
	assert.true( widget.checkIfAnyWordStartsWithInputValue( 'Foo Bar', 'Bar' ), 'matches second word' );
} );

QUnit.test( 'checkIfAnyWordStartsWithInputValue is false when no word starts with the value', ( assert ) => {
	const widget = new pf.SpreadsheetComboBoxInput( {} );
	assert.false( widget.checkIfAnyWordStartsWithInputValue( 'Foo Bar', 'ooB' ), 'does not match mid-word substring' );
} );

QUnit.test( 'checkIfAnyWordStartsWithInputValue is case-insensitive', ( assert ) => {
	const widget = new pf.SpreadsheetComboBoxInput( {} );
	assert.true( widget.checkIfAnyWordStartsWithInputValue( 'Foo Bar', 'bar' ), 'lowercase value still matches' );
} );

// --- getConditionForAutocompleteOnAllChars ---

QUnit.test( 'getConditionForAutocompleteOnAllChars matches any substring position', ( assert ) => {
	const widget = new pf.SpreadsheetComboBoxInput( {} );
	assert.true( widget.getConditionForAutocompleteOnAllChars( 'Foobar', 'oob' ), 'matches mid-word substring' );
	assert.false( widget.getConditionForAutocompleteOnAllChars( 'Foobar', 'xyz' ), 'no match for unrelated substring' );
} );

// --- getDependentFieldOpts ---

QUnit.test( 'getDependentFieldOpts reads base value and prop from DOM cell', ( assert ) => {
	$( '<td>' )
		.attr( { 'data-y': '2', origname: 'baseField', name: 'baseFieldRealName' } )
		.html( 'baseCellValue' )
		.appendTo( document.body );

	mw.config.get = ( key ) => key === 'wgPageFormsFieldProperties' ? {} : undefined;

	const widget = new pf.SpreadsheetComboBoxInput( { autocompletesettings: 'myProp' } );
	const result = widget.getDependentFieldOpts( '2', 'baseField' );

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

	const widget = new pf.SpreadsheetComboBoxInput( { autocompletesettings: 'myProp' } );
	const result = widget.getDependentFieldOpts( '3', 'baseField2' );

	assert.equal( result.base_prop, 'configuredProp', 'wgPageFormsFieldProperties takes precedence' );
} );
