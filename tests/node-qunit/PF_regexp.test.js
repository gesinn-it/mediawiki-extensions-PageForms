'use strict';

require( '../../libs/PF_regexp.js' );

// Note: $.fn.addErrorMessage is provided by PageForms.js (loaded as a side-effect
// of window.validateAll.test.js which loads before the tests run). We must set
// mw.msg in beforeEach so the real addErrorMessage does not throw.

function createInput( id, value ) {
	$( '<span><input id="' + id + '" value="' + value + '"></span>' ).appendTo( document.body );
}

QUnit.module( 'PF_RE_validate', {
	beforeEach() {
		mw.msg = ( msg ) => msg;
		createInput( 'test_input', '' );
	}
} );

QUnit.test( 'returns true when value matches pattern', ( assert ) => {
	$( '#test_input' ).val( 'hello123' );
	const result = window.PF_RE_validate( 'test_input', {
		retext: '^[a-z0-9]+$',
		inverse: false,
		message: 'no match'
	} );
	assert.true( result );
} );

QUnit.test( 'returns false when value does not match (and adds error)', ( assert ) => {
	$( '#test_input' ).val( 'hello 123' );
	const result = window.PF_RE_validate( 'test_input', {
		retext: '^[a-z0-9]+$',
		inverse: false,
		message: 'must be alphanumeric'
	} );
	assert.false( result );
	assert.strictEqual( $( '#test_input' ).parent().find( '.errorMessage' ).text(), 'must be alphanumeric' );
} );

QUnit.test( 'inverse=true: returns true when pattern does NOT match', ( assert ) => {
	$( '#test_input' ).val( 'hello 123' );
	const result = window.PF_RE_validate( 'test_input', {
		retext: '^[a-z0-9]+$',
		inverse: true,
		message: 'no error expected'
	} );
	assert.true( result );
} );

QUnit.test( 'inverse=true: returns false when pattern DOES match', ( assert ) => {
	$( '#test_input' ).val( 'hello123' );
	const result = window.PF_RE_validate( 'test_input', {
		retext: '^[a-z0-9]+$',
		inverse: true,
		message: 'should not match'
	} );
	assert.false( result );
} );

QUnit.test( 'invalid regexp is caught: returns false and uses params.error', ( assert ) => {
	$( '#test_input' ).val( 'anything' );
	const result = window.PF_RE_validate( 'test_input', {
		retext: '[invalid(regexp',
		inverse: false,
		message: 'unused',
		error: 'Invalid regex: $1'
	} );
	assert.false( result );
	// The real addErrorMessage appends a .errorMessage div with the interpolated key
	const errorText = $( '#test_input' ).parent().find( '.errorMessage' ).text();
	assert.ok( typeof errorText === 'string' && errorText.startsWith( 'Invalid regex:' ) );
} );

QUnit.test( 'empty string matches empty pattern', ( assert ) => {
	$( '#test_input' ).val( '' );
	const result = window.PF_RE_validate( 'test_input', {
		retext: '',
		inverse: false,
		message: 'no match'
	} );
	assert.true( result );
} );
