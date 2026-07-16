'use strict';

const sinon = require( 'sinon' );

// PF_editWarning.js:
// - Listens to pf.addTemplateInstance hook to mark changesWereMade=true
// - Inside document.ready: reads mw.user.options.get('useeditwarning')
//   - if falsy → returns early (no hook attached)
//   - if truthy → calls mw.confirmCloseWindow and attaches #pfForm submit handler

const SCRIPT = '../../libs/PF_editWarning.js';

function freshRequire() {
	delete require.cache[ require.resolve( SCRIPT ) ];
	require( SCRIPT );
}

function createForm() {
	$( '<form id="pfForm">' )
		.append( $( '<textarea>' ) )
		.append( $( '<input type="text">' ) )
		.appendTo( document.body );
}

QUnit.module( 'PF_editWarning', {
	beforeEach() {
		// mw.user is set by setup.js but without .options — add it here
		mw.user.options = { get: sinon.stub().returns( true ) };
		mw.confirmCloseWindow = sinon.stub().returns( {
			release: sinon.stub()
		} );
		mw.config = {
			get: sinon.stub().callsFake( ( key ) => {
				if ( key === 'wgAction' ) {
					return 'formedit';
				}
				if ( key === 'wgCanonicalSpecialPageName' ) {
					return 'FormEdit';
				}
				return null;
			} )
		};
		// $.fn.textSelection is used inside editWarning to read/compare values
		$.fn.textSelection = sinon.stub().callsFake( function ( method ) {
			if ( method === 'getContents' ) {
				return this.val() || '';
			}
		} );
	}
} );

// ── early-return branch ────────────────────────────────────────────────────────

QUnit.test( 'does not call confirmCloseWindow when useeditwarning is false', ( assert ) => {
	const done = assert.async();
	mw.user.options.get = sinon.stub().returns( false );
	createForm();
	freshRequire();

	setTimeout( () => {
		assert.false( mw.confirmCloseWindow.called, 'confirmCloseWindow not called' );
		done();
	}, 50 );
} );

// ── main path ─────────────────────────────────────────────────────────────────

QUnit.test( 'calls confirmCloseWindow when useeditwarning is true', ( assert ) => {
	const done = assert.async();
	createForm();
	freshRequire();

	setTimeout( () => {
		assert.true( mw.confirmCloseWindow.calledOnce, 'confirmCloseWindow called' );
		done();
	}, 50 );
} );

QUnit.test( 'confirmCloseWindow test() returns false when wgAction is submit', ( assert ) => {
	const done = assert.async();
	mw.config.get = sinon.stub().callsFake( ( key ) => key === 'wgAction' ? 'submit' : null );
	createForm();
	freshRequire();

	setTimeout( () => {
		const testFn = mw.confirmCloseWindow.firstCall.args[ 0 ].test;
		assert.false( testFn(), 'submit action → no warning' );
		done();
	}, 50 );
} );

QUnit.test( 'confirmCloseWindow test() returns false when not FormEdit and not formedit', ( assert ) => {
	const done = assert.async();
	mw.config.get = sinon.stub().callsFake( ( key ) => {
		if ( key === 'wgAction' ) {
			return 'view';
		}
		if ( key === 'wgCanonicalSpecialPageName' ) {
			return 'SomethingElse';
		}
		return null;
	} );
	createForm();
	freshRequire();

	setTimeout( () => {
		const testFn = mw.confirmCloseWindow.firstCall.args[ 0 ].test;
		assert.false( testFn(), 'non-formedit context → no warning' );
		done();
	}, 50 );
} );

QUnit.test( 'submit handler calls release()', ( assert ) => {
	const done = assert.async();
	const releaseStub = sinon.stub();
	mw.confirmCloseWindow = sinon.stub().returns( { release: releaseStub } );
	createForm();
	freshRequire();

	setTimeout( () => {
		$( '#pfForm' ).trigger( 'submit' );
		assert.true( releaseStub.calledOnce, 'release() called on form submit' );
		done();
	}, 50 );
} );

// ── change-detection branches ───────────────────────────────────────────────────

QUnit.test( 'test() returns false when no input value changed', ( assert ) => {
	const done = assert.async();
	createForm();
	freshRequire();

	setTimeout( () => {
		const testFn = mw.confirmCloseWindow.firstCall.args[ 0 ].test;
		assert.false( testFn(), 'unchanged plain input → no warning' );
		done();
	}, 50 );
} );

QUnit.test( 'test() returns true when a plain input value changed', ( assert ) => {
	const done = assert.async();
	createForm();
	freshRequire();

	setTimeout( () => {
		$( '#pfForm input[type=text]' ).val( 'changed value' );
		const testFn = mw.confirmCloseWindow.firstCall.args[ 0 ].test;
		assert.true( testFn(), 'changed plain input → warning' );
		done();
	}, 50 );
} );

QUnit.test( 'test() returns false when a pfComboBox input is unchanged', ( assert ) => {
	const done = assert.async();
	$( '<form id="pfForm">' )
		.append( $( '<input type="text" id="comboInput" class="pfComboBox">' ).val( 'combo value' ) )
		.appendTo( document.body );
	freshRequire();

	setTimeout( () => {
		const testFn = mw.confirmCloseWindow.firstCall.args[ 0 ].test;
		assert.false( testFn(), 'unchanged pfComboBox input → no warning' );
		done();
	}, 50 );
} );

QUnit.test( 'test() returns true when a pfComboBox input changed', ( assert ) => {
	const done = assert.async();
	$( '<form id="pfForm">' )
		.append( $( '<input type="text" id="comboInput" class="pfComboBox">' ).val( 'combo value' ) )
		.appendTo( document.body );
	freshRequire();

	setTimeout( () => {
		$( '#comboInput' ).val( 'changed combo value' );
		const testFn = mw.confirmCloseWindow.firstCall.args[ 0 ].test;
		assert.true( testFn(), 'changed pfComboBox input → warning' );
		done();
	}, 50 );
} );

QUnit.test( 'test() returns false when a multi-select value is unchanged', ( assert ) => {
	const done = assert.async();
	$( '<form id="pfForm">' )
		.append(
			$( '<select multiple>' )
				.append( $( '<option value="a" selected>' ) )
				.append( $( '<option value="b">' ) )
		)
		.appendTo( document.body );
	freshRequire();

	setTimeout( () => {
		const testFn = mw.confirmCloseWindow.firstCall.args[ 0 ].test;
		assert.false( testFn(), 'unchanged multi-select → no warning' );
		done();
	}, 50 );
} );

QUnit.test( 'test() returns true when a multi-select value changed', ( assert ) => {
	const done = assert.async();
	$( '<form id="pfForm">' )
		.append(
			$( '<select multiple>' )
				.append( $( '<option value="a" selected>' ) )
				.append( $( '<option value="b">' ) )
		)
		.appendTo( document.body );
	freshRequire();

	setTimeout( () => {
		$( '#pfForm select option[value=b]' ).prop( 'selected', true );
		const testFn = mw.confirmCloseWindow.firstCall.args[ 0 ].test;
		assert.true( testFn(), 'changed multi-select → warning' );
		done();
	}, 50 );
} );

// ── pf.addTemplateInstance hook ──────────────────────────────────────────────────

QUnit.test( 'firing pf.addTemplateInstance sets changesWereMade to true', ( assert ) => {
	const done = assert.async();
	createForm();
	freshRequire();

	setTimeout( () => {
		mw.hook( 'pf.addTemplateInstance' ).fire( $( '<div>' ) );
		const testFn = mw.confirmCloseWindow.firstCall.args[ 0 ].test;
		assert.true( testFn(), 'changesWereMade flipped by hook → warning, even with unchanged inputs' );
		done();
	}, 50 );
} );
