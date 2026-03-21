'use strict';

require( '../../libs/PF_originalValueLookup.js' );

QUnit.module( 'PF_originalValueLookup', {
	beforeEach() {
		mw.config = {
			get: () => null
		};
	}
} );

QUnit.test( 'identity for unknown element type', ( assert ) => {
	const $el = $( '<input>' );
	const lookup = window.pageforms.originalValueLookup( $el );
	assert.strictEqual( lookup( 'foo' ), 'foo', 'returns value unchanged for unknown element type' );
} );

QUnit.test( 'radiobutton: looks up original value from data-original-value', ( assert ) => {
	document.body.innerHTML = `
		<span class="radioButtonSpan">
			<label class="radioButtonItem">
				<input data-original-value="0" type="radio" value="" name="test">
			</label>
			<label class="radioButtonItem">
				<input data-original-value="1" type="radio" value="Displayed Value" name="test">
			</label>
		</span>`;
	const $el = $( 'input[value="Displayed Value"]' );
	const lookup = window.pageforms.originalValueLookup( $el );
	assert.strictEqual( lookup( 'Displayed Value' ), '1', 'maps displayed value to original via data-original-value' );
	assert.strictEqual( lookup( 'nonexistent' ), 'nonexistent', 'returns value unchanged when no match' );
} );

// Regression test: TypeError when autocompletesettings key is absent from
// wgPageFormsAutocompleteValues (e.g. field uses remote autocompletion).
QUnit.test( 'autocomplete: null-guard — key missing from wgPageFormsAutocompleteValues', ( assert ) => {
	mw.config = {
		get: ( key ) => key === 'wgPageFormsAutocompleteValues'
			? { 'SomeOtherField,list,;': {} }
			: null
	};
	const $el = $( '<select>' ).attr( 'autocompletesettings', 'Subject Area,list,;' );
	let threw = false;
	let result;
	try {
		const lookup = window.pageforms.originalValueLookup( $el );
		result = lookup( 'some value' );
	} catch ( e ) {
		threw = true;
	}
	assert.false( threw, 'does not throw TypeError when mapping key is absent' );
	assert.strictEqual( result, 'some value', 'returns identity when mapping is absent' );
} );

QUnit.test( 'autocomplete: null-guard — wgPageFormsAutocompleteValues is null', ( assert ) => {
	mw.config = { get: () => null };
	const $el = $( '<select>' ).attr( 'autocompletesettings', 'SomeField,list,;' );
	let threw = false;
	let result;
	try {
		const lookup = window.pageforms.originalValueLookup( $el );
		result = lookup( 'some value' );
	} catch ( e ) {
		threw = true;
	}
	assert.false( threw, 'does not throw when wgPageFormsAutocompleteValues is null' );
	assert.strictEqual( result, 'some value', 'returns identity when config is null' );
} );

QUnit.test( 'autocomplete: maps displayed value to original via wgPageFormsAutocompleteValues', ( assert ) => {
	mw.config = {
		get: ( key ) => key === 'wgPageFormsAutocompleteValues'
			? {
				'MyField,list,;': {
					'Internal:Page1': 'Displayed Page 1',
					'Internal:Page2': 'Displayed Page 2'
				}
			}
			: null
	};
	const $el = $( '<select>' ).attr( 'autocompletesettings', 'MyField,list,;' );
	const lookup = window.pageforms.originalValueLookup( $el );
	assert.strictEqual( lookup( 'Displayed Page 1' ), 'Internal:Page1', 'maps first display value to original key' );
	assert.strictEqual( lookup( 'Displayed Page 2' ), 'Internal:Page2', 'maps second display value to original key' );
	assert.strictEqual( lookup( 'unknown display' ), 'unknown display', 'returns unchanged for unknown display value' );
} );
