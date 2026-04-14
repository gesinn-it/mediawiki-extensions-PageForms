'use strict';

const sinon = require( 'sinon' );

// PF_wikieditor.js creates window.ext.wikieditor with an init() function that
// wires up the wikiEditor extension via mw.loader.using / mw.addWikiEditor.

require( '../../libs/PF_wikieditor.js' );

// jQuery 3.x exposes $.ready as a function with `.then = readyList.then`.
// When adoptValue() calls it with `this = $.ready` (not the readyList),
// the $.when() chain stalls. Replace it with a proper resolved promise for
// all tests in this module so the $.when(loader, $.ready).then() chain fires.
let _savedReady;

QUnit.module( 'PF_wikieditor', {
	before() {
		_savedReady = $.ready;
		$.ready = $.Deferred().resolve().promise();
	},
	after() {
		$.ready = _savedReady;
	},
	beforeEach() {
		// Provide a loader stub that invokes the optional callback synchronously
		// and returns a resolved jQuery Deferred for the $.when() chain.
		mw.loader = {
			using: sinon.stub().callsFake( ( modules, callback ) => {
				if ( typeof callback === 'function' ) {
					callback();
				}
				return $.Deferred().resolve().promise();
			} )
		};
	}
} );

QUnit.test( 'window.ext.wikieditor is defined after load', ( assert ) => {
	assert.ok( window.ext, 'window.ext exists' );
	assert.ok( window.ext.wikieditor, 'window.ext.wikieditor exists' );
	assert.strictEqual( typeof window.ext.wikieditor.init, 'function', 'init is a function' );
} );

QUnit.test( 'init calls mw.addWikiEditor when defined', ( assert ) => {
	const done = assert.async();
	mw.addWikiEditor = sinon.stub();

	$( '<input>' ).attr( 'id', 'wikied-input' ).appendTo( document.body );
	window.ext.wikieditor.init( 'wikied-input' );

	// jQuery 3.x $.when().then() dispatches via window.setTimeout (jsdom layer).
	// await-ing a 50 ms delay flushes both jsdom and Node.js timer queues.
	setTimeout( () => {
		assert.ok( mw.loader.using.called, 'mw.loader.using was called' );
		assert.ok( mw.addWikiEditor.calledOnce, 'mw.addWikiEditor was called once' );
		done();
	}, 50 );
} );

QUnit.test( 'init does not call mw.addWikiEditor when it is not a function', ( assert ) => {
	const done = assert.async();
	// mw.addWikiEditor is not defined → the if-branch is skipped
	delete mw.addWikiEditor;

	$( '<input>' ).attr( 'id', 'wikied-input2' ).appendTo( document.body );
	window.ext.wikieditor.init( 'wikied-input2' );

	setTimeout( () => {
		assert.ok( mw.loader.using.called, 'mw.loader.using was still called for the outer check' );
		done();
	}, 50 );
} );
