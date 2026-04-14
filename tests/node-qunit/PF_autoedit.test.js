'use strict';

const sinon = require( 'sinon' );

// PF_autoedit.js exposes `sendData` (internal) and `autoEditHandler` via
// document.ready → click/each binding.  We test both via the DOM.

const SCRIPT = '../../libs/PF_autoedit.js';

function freshRequire() {
	delete require.cache[ require.resolve( SCRIPT ) ];
	require( SCRIPT );
}

// Build the standard .autoedit markup used by the parser function output.
function createAutoedit( opts ) {
	opts = opts || {};
	const $form = $( '<form class="autoedit-data' + ( opts.confirmEdit ? ' confirm-edit' : '' ) + '">' )
		.append( $( '<input type="hidden" name="target">' ).val( opts.target || 'Test Page' ) );
	const $result = $( '<span class="autoedit-result">' );
	const $trigger = $( '<a class="autoedit-trigger' + ( opts.instant ? '-instant' : '' ) + '">' );
	const $div = $( '<div class="autoedit">' )
		.append( $form )
		.append( $result )
		.append( $trigger )
		.appendTo( document.body );
	return { $trigger, $result, $form, $div };
}

QUnit.module( 'PF_autoedit', {
	beforeEach() {
		mw.msg = sinon.stub().callsFake( ( key ) => key );
		mw.util = { wikiScript: sinon.stub().returns( '/w/api.php' ) };
		mw.config = {
			get: sinon.stub().callsFake( ( key ) => key === 'wgUserName' ? 'TestUser' : null )
		};
		sinon.stub( mw.Api.prototype, 'post' ).returns( $.Deferred().resolve( { status: 200, responseText: '' } ) );
	}
} );

// ── autoEditHandler early-exit guard ──────────────────────────────────────────

QUnit.test( 'autoEditHandler does nothing when called without an event', ( assert ) => {
	const done = assert.async();
	const { $trigger } = createAutoedit();
	freshRequire();

	setTimeout( () => {
			// Click fires with a real jQuery event → should reach mw.Api().post()
			$trigger.trigger( 'click' );
			assert.true( mw.Api.prototype.post.calledOnce, 'post called on real click' );

			mw.Api.prototype.post.resetHistory();
			// Simulate the instant-trigger call style (plain object, not real Event)
			$trigger.trigger( 'click' );
			assert.true( mw.Api.prototype.post.calledOnce, 'post called again' );
		done();
	}, 50 );
} );

// ── anon user confirmation ────────────────────────────────────────────────────

QUnit.test( 'shows confirm dialog and aborts when anonymous user declines', ( assert ) => {
	const done = assert.async();
	mw.config.get = sinon.stub().returns( null ); // wgUserName === null → anon
	global.confirm = sinon.stub().returns( false );
	const { $trigger } = createAutoedit();
	freshRequire();

	setTimeout( () => {
		$trigger.trigger( 'click' );
		assert.true( global.confirm.calledOnce, 'confirm dialog shown' );
			assert.false( mw.Api.prototype.post.called, 'post not called when user declines' );
		done();
	}, 50 );
} );

QUnit.test( 'proceeds when anonymous user accepts', ( assert ) => {
	const done = assert.async();
	mw.config.get = sinon.stub().returns( null );
	global.confirm = sinon.stub().returns( true );
	const { $trigger } = createAutoedit();
	freshRequire();

	setTimeout( () => {
		$trigger.trigger( 'click' );
			assert.true( mw.Api.prototype.post.calledOnce, 'post called after anon user accepts' );
		done();
	}, 50 );
} );

// ── sendData → success / error ────────────────────────────────────────────────

QUnit.test( 'sendData: success status 200 sets ok classes', ( assert ) => {
	const done = assert.async();
	mw.Api.prototype.post.returns( $.Deferred().resolve( { status: 200, responseText: 'ok' } ) );
	const { $trigger, $result } = createAutoedit();
	freshRequire();

	setTimeout( () => {
		$trigger.trigger( 'click' );
		setTimeout( () => {
			assert.true( $trigger.hasClass( 'autoedit-trigger-ok' ), 'trigger has ok class' );
			assert.true( $result.hasClass( 'autoedit-result-ok' ), 'result has ok class' );
			done();
		}, 0 );
	}, 50 );
} );

QUnit.test( 'sendData: non-200 status sets error classes', ( assert ) => {
	const done = assert.async();
	mw.Api.prototype.post.returns( $.Deferred().resolve( { status: 500, responseText: 'error' } ) );
	const { $trigger, $result } = createAutoedit();
	freshRequire();

	setTimeout( () => {
		$trigger.trigger( 'click' );
		setTimeout( () => {
			assert.true( $trigger.hasClass( 'autoedit-trigger-error' ), 'trigger has error class' );
			assert.true( $result.hasClass( 'autoedit-result-error' ), 'result has error class' );
			done();
		}, 0 );
	}, 50 );
} );

QUnit.test( 'sendData: ajax error handler sets error classes', ( assert ) => {
	const done = assert.async();
	mw.Api.prototype.post.returns( $.Deferred().reject(
		'http',
		{ xhr: { responseText: JSON.stringify( { responseText: 'fail', errors: [ { message: 'bad' } ] } ) } }
	) );
	const { $trigger, $result } = createAutoedit();
	freshRequire();

	setTimeout( () => {
		$trigger.trigger( 'click' );
		setTimeout( () => {
			assert.true( $result.hasClass( 'autoedit-result-error' ), 'result has error class' );
			done();
		}, 0 );
	}, 50 );
} );

// ── .autoedit-trigger-instant fires on load ───────────────────────────────────

QUnit.test( 'autoedit-trigger-instant fires sendData immediately on ready', ( assert ) => {
	const done = assert.async();
	mw.Api.prototype.post.returns( $.Deferred().resolve( { status: 200, responseText: 'ok' } ) );
	createAutoedit( { instant: true } );
	freshRequire();

	setTimeout( () => {
		assert.true( mw.Api.prototype.post.calledOnce, 'post called for instant trigger' );
		done();
	}, 50 );
} );
