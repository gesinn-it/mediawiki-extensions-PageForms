'use strict';

const sinon = require( 'sinon' );

// PF_AutoEditRating.js is similar to PF_autoedit.js but drives a rateYo widget.
// It re-defines $.fn.applyRatingInput (different from PF_rating.js's version).

const SCRIPT = '../../libs/PF_AutoEditRating.js';

function freshRequire() {
	delete require.cache[ require.resolve( SCRIPT ) ];
	require( SCRIPT );
}

function createAutoeditRating( opts ) {
	opts = opts || {};
	const $form = $( '<form class="autoedit-data' + ( opts.confirmEdit ? ' confirm-edit' : '' ) + '">' )
		.append( $( '<input type="hidden" name="target">' ).val( opts.target || 'Test Page' ) )
		.append( $( '<input type="hidden" id="ratingInput">' ) );
	const $result = $( '<span class="autoedit-result">' );
	const $trigger = $( '<div>' ); // rateYo parent trigger
	const $div = $( '<div class="autoedit">' )
		.append( $form )
		.append( $result )
		.append( $trigger )
		.appendTo( document.body );
	return { $trigger, $result, $form, $div };
}

QUnit.module( 'PF_AutoEditRating', {
	beforeEach() {
		mw.msg = sinon.stub().callsFake( ( key ) => key );
		mw.util = { wikiScript: sinon.stub().returns( '/w/api.php' ) };
		mw.config = {
			get: sinon.stub().callsFake( ( key ) => key === 'wgUserName' ? 'TestUser' : null )
		};
			sinon.stub( mw.Api.prototype, 'post' ).returns( $.Deferred().resolve( { status: 200, responseText: '' } ) );
		// rateYo is defined by jquery.rateyo.js (excluded from nyc) — provide a minimal
		// stub on $.fn so sinon.stub() can wrap it.
		if ( !$.fn.rateYo ) {
			$.fn.rateYo = function () {
				return this;
			};
		}
		sinon.stub( $.fn, 'rateYo' ).callsFake( function () {
			return this;
		} );
	}
} );

// ── applyRatingInput defined ───────────────────────────────────────────────────

QUnit.test( 'applyRatingInput is defined on jQuery.fn after load', ( assert ) => {
	freshRequire();
	assert.strictEqual( typeof $.fn.applyRatingInput, 'function' );
} );

QUnit.test( 'applyRatingInput calls rateYo with correct settings (fullStar)', ( assert ) => {
	freshRequire();
	const $el = $( '<input>' )
		.attr( { 'data-starwidth': 20, 'data-numstars': 5, 'data-curvalue': 3 } )
		.appendTo( document.body );

	$el.applyRatingInput();

	assert.true( $.fn.rateYo.calledOnce );
	const settings = $.fn.rateYo.firstCall.args[ 0 ];
	assert.strictEqual( settings.rating, '3' );
	assert.true( settings.fullStar );
} );

QUnit.test( 'applyRatingInput sets halfStar when data-allows-half is present', ( assert ) => {
	freshRequire();
	const $el = $( '<input>' )
		.attr( { 'data-starwidth': 20, 'data-numstars': 5, 'data-allows-half': '1' } )
		.appendTo( document.body );

	$el.applyRatingInput();

	assert.true( $.fn.rateYo.firstCall.args[ 0 ].halfStar );
} );

QUnit.test( 'applyRatingInput defaults curValue to 0 when empty', ( assert ) => {
	freshRequire();
	const $el = $( '<input>' )
		.attr( { 'data-starwidth': 20, 'data-numstars': 5, 'data-curvalue': '' } )
		.appendTo( document.body );

	$el.applyRatingInput();

	assert.strictEqual( $.fn.rateYo.firstCall.args[ 0 ].rating, 0 );
} );

// ── handleAutoEditRating / sendData ───────────────────────────────────────────

QUnit.test( 'rateyo.set triggers sendData → post called on success', ( assert ) => {
	const done = assert.async();
	mw.Api.prototype.post.returns( $.Deferred().resolve( { status: 200, responseText: 'ok' } ) );
	const { $trigger, $result } = createAutoeditRating();
	freshRequire();

	// Manually call with a parent element to simulate rateyo.set
	$trigger.applyRatingInput();
	$trigger.trigger( 'rateyo.set', { rating: 4 } );

	setTimeout( () => {
		assert.true( mw.Api.prototype.post.calledOnce, 'post called' );
		setTimeout( () => {
			assert.true( $result.hasClass( 'autoedit-result-ok' ), 'ok class set' );
			done();
		}, 0 );
	}, 50 );
} );

QUnit.test( 'sendData: ajax error sets error class', ( assert ) => {
	const done = assert.async();
	mw.Api.prototype.post.returns( $.Deferred().reject(
		'http',
		{ xhr: { responseText: JSON.stringify( { responseText: 'fail', errors: [ { message: 'err' } ] } ) } }
	) );
	const { $trigger, $result } = createAutoeditRating();
	freshRequire();

	$trigger.applyRatingInput();
	$trigger.trigger( 'rateyo.set', { rating: 2 } );

	setTimeout( () => {
		setTimeout( () => {
			assert.true( $result.hasClass( 'autoedit-result-error' ), 'error class set' );
			done();
		}, 0 );
	}, 50 );
} );

QUnit.test( 'anon user declining confirm aborts sendData', ( assert ) => {
	const done = assert.async();
	mw.config.get = sinon.stub().returns( null ); // anon
	global.confirm = sinon.stub().returns( false );
	const { $trigger } = createAutoeditRating();
	freshRequire();

	$trigger.applyRatingInput();
	$trigger.trigger( 'rateyo.set', { rating: 3 } );

	setTimeout( () => {
		assert.false( mw.Api.prototype.post.called, 'post not called when anon declines' );
		done();
	}, 50 );
} );
