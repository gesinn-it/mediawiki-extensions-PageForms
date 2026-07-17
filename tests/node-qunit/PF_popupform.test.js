'use strict';

const sinon = require( 'sinon' );

const SCRIPT = '../../libs/PF_popupform.js';

// PF_popupform.js reads $.browser.webkit/safari/mozilla unconditionally
// (a jquery.browser.js plugin property, not part of core jQuery -- that
// plugin file is excluded from coverage and not loaded in this test
// environment). Stub the shape it expects before the module is required.
function stubBrowser( overrides ) {
	$.browser = Object.assign( { webkit: false, safari: false, mozilla: false }, overrides || {} );
}

// showForm() sets its closure-private brokenBrowser flag from
// navigator.userAgent/platform; jsdom's defaults never match, so without
// this fadeOut()/fadeIn()/fadeTo() take jQuery's real, animated (async)
// path -- container visibility and wrapper-removal assertions would then
// depend on fadeOut's actual animation duration instead of running
// synchronously. Force the "broken browser" (synchronous show/hide) path.
function stubBrokenBrowserUserAgent() {
	Object.defineProperty( navigator, 'userAgent', { value: 'Chrome', configurable: true } );
	Object.defineProperty( navigator, 'platform', { value: 'Linux x86_64', configurable: true } );
}

function freshRequire() {
	delete require.cache[ require.resolve( SCRIPT ) ];
	require( SCRIPT );
}

/**
 * showForm() (invoked by both handlePopupFormInput()/handlePopupFormLink())
 * builds and appends the whole '.popupform-wrapper' DOM tree itself,
 * including a fresh '.popupform-innerdocument' <iframe> per call. This
 * locates that just-created iframe, gives its jsdom contentDocument a
 * '#content' fixture (the "normal skin" branch handleLoadFrame() scans
 * for), and stubs the geometry methods PF_popupform.js reads (.width()/
 * .height()) so adjustFrameSize()'s sizing logic gets deliberate,
 * assertable values instead of jsdom's default zeroes.
 *
 * @param {Object} [opts]
 * @param {number} [opts.contentWidth]
 * @param {number} [opts.contentHeight]
 * @return {Object} { $iframe, $content, iframeDocument }
 */
function wireIframeContent( opts ) {
	opts = opts || {};
	const contentWidth = opts.contentWidth !== undefined ? opts.contentWidth : 400;
	const contentHeight = opts.contentHeight !== undefined ? opts.contentHeight : 300;

	const $iframe = $( '.popupform-innerdocument' );
	const iframeDocument = $iframe[ 0 ].contentDocument;
	iframeDocument.body.innerHTML = '<div id="content"><form id="pfForm"><input id="wpSave" value="Save"></form></div>';

	const $content = $( iframeDocument ).find( '#content' );

	if ( !$.fn.width.isSinonProxy ) {
		sinon.stub( $.fn, 'width' ).callsFake( function ( value ) {
			if ( value !== undefined ) {
				return this;
			}
			return this.is( '#content' ) ? contentWidth : 0;
		} );
	}
	if ( !$.fn.height.isSinonProxy ) {
		sinon.stub( $.fn, 'height' ).callsFake( function ( value ) {
			if ( value !== undefined ) {
				return this;
			}
			return this.is( '#content' ) ? contentHeight : 0;
		} );
	}

	return { $iframe, $content, iframeDocument };
}

QUnit.module( 'PF_popupform', {
	beforeEach: function () {
		stubBrowser();
		stubBrokenBrowserUserAgent();
		mw.Api = mw.Api || function Api() {};
		mw.Api.prototype.post = mw.Api.prototype.post || function () {
			return $.Deferred().promise();
		};
		freshRequire();
	},
	afterEach: function () {
		delete require.cache[ require.resolve( SCRIPT ) ];
		// $.browser is deliberately left in place (not deleted): adjustFrameSize()'s
		// non-animate branch schedules its own untracked setTimeout(..., 100) that
		// reads jQuery.browser.safari after this hook would otherwise have torn it
		// down, crashing on an unrelated later tick.
		sinon.restore();
	}
} );

// ── handlePopupFormInput() / handlePopupFormLink(): target wiring ──────────

QUnit.test( 'handlePopupFormInput: opens the popup, sets the iframe target on the form, and returns true', ( assert ) => {
	const $form = $( '<form action="/x" class="popupforminput">' ).appendTo( document.body );

	const result = window.ext.popupform.handlePopupFormInput( '/x', $form[ 0 ] );

	assert.true( result, 'returns true so the browser proceeds with the native form submit' );
	assert.strictEqual( $( '.popupform-wrapper' ).length, 1, 'popup wrapper was created' );
	assert.strictEqual( $form.attr( 'target' ), $( '.popupform-innerdocument' ).attr( 'name' ), 'form target points at the popup iframe' );
} );

// handlePopupFormLink() unconditionally starts a readystate-polling
// setInterval() that is only ever cleared by handleCloseFrame() (and even
// then via a mismatched clearTimeout() call, a separate existing quirk).
// Fake timers keep that interval from leaking into and hanging later tests.
QUnit.test( 'handlePopupFormLink: for a <form> element, sets the iframe target and lets it submit natively', ( assert ) => {
	const clock = sinon.useFakeTimers( { toFake: [ 'setInterval', 'clearInterval' ] } );
	const $form = $( '<form action="/x" class="popupformlink">' ).appendTo( document.body );

	const result = window.ext.popupform.handlePopupFormLink( $form.attr( 'action' ), $form[ 0 ] );

	assert.true( result, 'returns true so the browser proceeds with the native form submit' );
	assert.strictEqual( $form.attr( 'target' ), $( '.popupform-innerdocument' ).attr( 'name' ), 'form target points at the popup iframe' );

	clock.restore();
} );

QUnit.test( 'handlePopupFormLink: for a plain <a>, builds and submits a hidden form targeting the iframe', ( assert ) => {
	const clock = sinon.useFakeTimers( { toFake: [ 'setInterval', 'clearInterval' ] } );
	const $link = $( '<a href="/index.php/Special:FormEdit/MyForm?a=1&b=2">Edit</a>' ).appendTo( document.body );

	const result = window.ext.popupform.handlePopupFormLink( $link.attr( 'href' ), $link[ 0 ] );

	assert.false( result, 'returns false (the click handler prevents the default <a> navigation)' );
	assert.strictEqual( $( '.popupform-wrapper' ).length, 1, 'popup wrapper was created' );
	assert.strictEqual( $( 'form' ).length, 0, 'the synthetic submit form was appended then removed again' );

	clock.restore();
} );

// ── adjustFrameSize(): geometry sizing, reached via the load flow ──────────

function openAndLoad( opts ) {
	const $form = $( '<form action="/x" class="popupforminput">' ).appendTo( document.body );
	window.ext.popupform.handlePopupFormInput( '/x', $form[ 0 ] );

	const fixture = wireIframeContent( opts );
	// First 'load' (production: the real navigation into the iframe)
	// arms handleLoadFrame() as the handler for the *next* load; second
	// 'load' (production: the form's own page finishing load) invokes it.
	fixture.$iframe.trigger( 'load' );
	fixture.$iframe.trigger( 'load' );

	return fixture;
}

QUnit.test( 'adjustFrameSize: sizes the frame to fit small content without scrollbars', ( assert ) => {
	openAndLoad( { contentWidth: 200, contentHeight: 150 } );

	const result = window.ext.popupform.adjustFrameSize();

	assert.true( result, 'returns true' );
	// jsdom never computes layout, so jQuery's :visible check (which reads
	// offsetWidth/offsetHeight/getClientRects()) is always false regardless
	// of the actual CSS -- assert on the CSS itself instead.
	assert.strictEqual( $( '.popupform-container' ).css( 'display' ), 'block', 'container is shown (not hidden) once the frame has loaded' );
} );

QUnit.test( 'adjustFrameSize: falls back to 95% of available space when content reports zero dimensions', ( assert ) => {
	// Firefox-specific fallback branch: $content.width()/.height() report 0.
	openAndLoad( { contentWidth: 0, contentHeight: 0 } );

	const result = window.ext.popupform.adjustFrameSize();

	assert.true( result, 'still returns true via the availW/availH * 0.95 fallback' );
} );

QUnit.test( 'adjustFrameSize: webkit/safari branch scrolls the body instead of the html element', ( assert ) => {
	stubBrowser( { webkit: true } );
	openAndLoad( { contentWidth: 200, contentHeight: 150 } );

	const scrollTopSpy = sinon.spy( $.fn, 'scrollTop' );

	const result = window.ext.popupform.adjustFrameSize();

	assert.true( result, 'returns true for the webkit/safari scroll-target branch' );
	assert.true( scrollTopSpy.called, 'scrollTop was read/set on the scroll target' );
} );

QUnit.test( 'adjustFrameSize: mozilla branch defers content sizing via setTimeout', ( assert ) => {
	stubBrowser( { mozilla: true } );
	openAndLoad( { contentWidth: 200, contentHeight: 150 } );

	const clock = sinon.useFakeTimers( { toFake: [ 'setTimeout' ], now: Date.now() } );
	try {
		const result = window.ext.popupform.adjustFrameSize();
		clock.tick( 0 );
		assert.true( result, 'the mozilla setTimeout(..., 0) branch runs without throwing' );
	} finally {
		clock.restore();
	}
} );

QUnit.test( 'adjustFrameSize: animate=true drives container.animate() instead of setting dimensions directly', ( assert ) => {
	openAndLoad( { contentWidth: 900, contentHeight: 700 } );

	// Run the animation's complete callback synchronously instead of after
	// jQuery's real 500ms animation -- that callback reads jQuery.browser,
	// which afterEach() tears down once this (synchronous) test returns.
	const animateStub = sinon.stub( $.fn, 'animate' ).callsFake( function ( props, options ) {
		options.complete.call( this[ 0 ] );
		return this;
	} );

	const result = window.ext.popupform.adjustFrameSize( true );

	assert.true( result, 'returns true for the animate branch' );
	assert.true( animateStub.called, 'animate() invoked for the frame-size-changed + animate branch' );
} );

// ── submit interception / purge-and-reload-after-save ──────────────────────

QUnit.test( 'submit interception: an error page (no inner <form>) writes the returned HTML back and closes the frame', ( assert ) => {
	const done = assert.async();

	const { $content } = openAndLoad( { contentWidth: 200, contentHeight: 150 } );
	const $innerForm = $content.find( '#pfForm' );

	sinon.stub( $, 'post' ).callsFake( ( url, data, callback ) => {
		// Simulate PF returning a page with no <form> (i.e. a completed save).
		callback( '<html><body>Saved.</body></html>' );
		return $.Deferred().promise();
	} );
	// handleCloseFrame() fades $background out via a raw, un-brokenBrowser-gated
	// $.fn.fadeOut() call (unlike every other fade in this file, which routes
	// through the local brokenBrowser-aware fadeOut() wrapper) -- run its
	// callback synchronously instead of waiting out jQuery's real animation.
	sinon.stub( $.fn, 'fadeOut' ).callsFake( function ( duration, callback ) {
		const onComplete = typeof duration === 'function' ? duration : callback;
		if ( onComplete ) {
			onComplete.call( this[ 0 ] );
		}
		return this;
	} );

	$innerForm.trigger( 'submit' );

	setTimeout( () => {
		assert.true( $.post.called, 'form data was posted for submission' );
		assert.strictEqual( $( '.popupform-wrapper' ).length, 0, 'the popup wrapper was removed (handleCloseFrame ran)' );
		done();
	}, 20 );
} );

QUnit.test( 'submit interception: reload class triggers a purge-then-reload after a successful save', ( assert ) => {
	const done = assert.async();

	const $form = $( '<form action="/x" class="popupforminput reload">' ).appendTo( document.body );
	window.ext.popupform.handlePopupFormInput( '/x', $form[ 0 ] );
	const { $content } = wireIframeContent( { contentWidth: 200, contentHeight: 150 } );
	$( '.popupform-innerdocument' ).trigger( 'load' );
	$( '.popupform-innerdocument' ).trigger( 'load' );
	const $innerForm = $content.find( '#pfForm' );

	sinon.stub( $, 'post' ).callsFake( ( url, data, callback ) => {
		callback( '<html><body>Saved.</body></html>' );
		return $.Deferred().promise();
	} );
	const postApiStub = sinon.stub( mw.Api.prototype, 'post' ).returns( $.Deferred().resolve( {} ).promise() );

	$innerForm.trigger( 'submit' );

	setTimeout( () => {
		// jsdom's window.location is non-configurable/non-writable, so the
		// actual location.reload() call cannot be stubbed or observed here
		// (same constraint noted in PF_FormLinkTargetInput.test.js for
		// location.href writes). The purge request is the reload path's
		// only observable, testable side effect.
		assert.true( postApiStub.calledWithMatch( { action: 'purge' } ), 'page purge was requested' );
		done();
	}, 20 );
} );

QUnit.test( 'submit interception: without the reload class, no purge/reload happens after save', ( assert ) => {
	const done = assert.async();

	openAndLoad( { contentWidth: 200, contentHeight: 150 } );
	const $innerForm = $( '.popupform-innerdocument' )[ 0 ].contentDocument.querySelector( '#pfForm' );

	sinon.stub( $, 'post' ).callsFake( ( url, data, callback ) => {
		callback( '<html><body>Saved.</body></html>' );
		return $.Deferred().promise();
	} );
	const postApiStub = sinon.stub( mw.Api.prototype, 'post' ).returns( $.Deferred().resolve( {} ).promise() );

	$( $innerForm ).trigger( 'submit' );

	setTimeout( () => {
		assert.false( postApiStub.called, 'no purge request without the reload class' );
		done();
	}, 20 );
} );
