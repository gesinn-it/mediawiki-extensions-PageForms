'use strict';

const sinon = require( 'sinon' );

// Minimal rateYo stub — also serves as fallback when sinon.restore() runs.
jQuery.fn.rateYo = function () {
	return this;
};

require( '../../libs/PF_rating.js' );

QUnit.module( 'PF_rating', {
	beforeEach() {
		// Replace with a sinon stub so tests can inspect call arguments.
		// sinon.restore() from global afterEach falls back to the module-level stub above.
		sinon.stub( $.fn, 'rateYo' ).callsFake( function () {
			return this;
		} );
	}
} );

function createElement( attrs ) {
	const $el = $( '<input type="hidden">' );
	Object.keys( attrs ).forEach( ( key ) => {
		if ( attrs[ key ] !== undefined ) {
			$el.attr( key, attrs[ key ] );
		}
	} );
	return $el.appendTo( document.body );
}

// Regression test: PF_rating.js must load without a global `pf`.
QUnit.test( 'module loads without pf global', ( assert ) => {
	const saved = global.pf;
	delete global.pf;
	delete require.cache[ require.resolve( '../../libs/PF_rating.js' ) ];
	let threw = false;
	try {
		require( '../../libs/PF_rating.js' );
	} catch ( e ) {
		threw = true;
	} finally {
		global.pf = saved;
	}
	assert.false( threw, 'module loaded without ReferenceError' );
} );

// ── early return ──────────────────────────────────────────────────────────────

QUnit.test( 'returns early when data-starwidth is absent', ( assert ) => {
	const $el = createElement( {} );

	$el.applyRatingInput();

	assert.false( $.fn.rateYo.called, 'rateYo not called when starwidth missing' );
} );

// ── rating value sources ──────────────────────────────────────────────────────

QUnit.test( 'uses fromCalendar value when provided', ( assert ) => {
	const $el = createElement( { 'data-starwidth': 20, 'data-numstars': 5 } );

	$el.applyRatingInput( 3 );

	assert.true( $.fn.rateYo.calledOnce );
	assert.strictEqual( $.fn.rateYo.firstCall.args[ 0 ].rating, 3, 'rating = fromCalendar value' );
} );

QUnit.test( 'reads rating from data-curvalue when no fromCalendar', ( assert ) => {
	const $el = createElement( { 'data-starwidth': 20, 'data-numstars': 5, 'data-curvalue': 4 } );

	$el.applyRatingInput();

	assert.strictEqual( $.fn.rateYo.firstCall.args[ 0 ].rating, '4', 'rating = data-curvalue' );
} );

QUnit.test( 'defaults rating to 0 when data-curvalue is empty', ( assert ) => {
	const $el = createElement( { 'data-starwidth': 20, 'data-numstars': 5, 'data-curvalue': '' } );

	$el.applyRatingInput();

	assert.strictEqual( $.fn.rateYo.firstCall.args[ 0 ].rating, 0, 'rating defaults to 0' );
} );

// ── allowsHalf / disabled ─────────────────────────────────────────────────────

QUnit.test( 'sets fullStar when data-allows-half is absent', ( assert ) => {
	const $el = createElement( { 'data-starwidth': 20, 'data-numstars': 5 } );

	$el.applyRatingInput( 2 );

	const settings = $.fn.rateYo.firstCall.args[ 0 ];
	assert.true( settings.fullStar, 'fullStar = true' );
	assert.notOk( settings.halfStar, 'halfStar absent' );
} );

QUnit.test( 'sets halfStar when data-allows-half is present', ( assert ) => {
	const $el = createElement( { 'data-starwidth': 20, 'data-numstars': 5, 'data-allows-half': '1' } );

	$el.applyRatingInput( 2 );

	const settings = $.fn.rateYo.firstCall.args[ 0 ];
	assert.true( settings.halfStar, 'halfStar = true' );
	assert.notOk( settings.fullStar, 'fullStar absent' );
} );

QUnit.test( 'sets readOnly when element is disabled', ( assert ) => {
	const $el = createElement( { 'data-starwidth': 20, 'data-numstars': 5, disabled: 'disabled' } );

	$el.applyRatingInput( 1 );

	assert.true( $.fn.rateYo.firstCall.args[ 0 ].readOnly, 'readOnly = true for disabled element' );
} );

QUnit.test( 'does not set readOnly when element is not disabled', ( assert ) => {
	const $el = createElement( { 'data-starwidth': 20, 'data-numstars': 5 } );

	$el.applyRatingInput( 1 );

	assert.notOk( $.fn.rateYo.firstCall.args[ 0 ].readOnly, 'readOnly absent' );
} );

// ── rateyo.set event handler ──────────────────────────────────────────────────

QUnit.test( 'rateyo.set event updates the sibling hidden input value', ( assert ) => {
	const $parent = $( '<div>' ).appendTo( document.body );
	const $hidden = $( '<input type="hidden">' ).appendTo( $parent );
	const $el = $( '<input type="hidden">' )
		.attr( { 'data-starwidth': 20, 'data-numstars': 5 } )
		.appendTo( $parent );

	$el.applyRatingInput( 3 );
	$el.trigger( 'rateyo.set', { rating: 4.5 } );

	assert.strictEqual( $hidden.attr( 'value' ), '4.5', 'hidden input value updated' );
} );

