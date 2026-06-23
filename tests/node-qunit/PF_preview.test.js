'use strict';

const sinon = require( 'sinon' );

const PREVIEW_SCRIPT = '../../libs/PF_preview.js';

function freshRequire() {
	delete require.cache[ require.resolve( PREVIEW_SCRIPT ) ];
	require( PREVIEW_SCRIPT );
}

QUnit.module( 'PF_preview', {
	beforeEach() {
		global.validateAll = () => true;
		mw.config = {
			get: ( key ) => {
				const values = {
					wgAction: 'formedit',
					wgCanonicalSpecialPageName: null,
					wgCanonicalNamespace: null,
					wgPageName: 'TestPage'
				};
				return Object.prototype.hasOwnProperty.call( values, key ) ? values[ key ] : null;
			}
		};
		mw.util = { getParamValue: () => null };
	}
} );

// ── iframe load event binding ────────────────────────────────────────────────

QUnit.test( 'pfAjaxPreview creates an iframe and wires the load event via .on()', ( assert ) => {
	const done = assert.async();
	const fakeHtml = '<html><body><div id="wikiPreview">Preview</div></body></html>';

	// Stub BEFORE freshRequire: module-level `const api = new mw.Api()` picks it up.
	sinon.stub( mw.Api.prototype, 'post' ).returns(
		$.Deferred().resolve( { result: fakeHtml } ).promise()
	);

	// Set up DOM before freshRequire so document.ready in the module finds #wpPreview.
	$(
		'<form id="pfForm">' +
			'<input type="submit" id="wpPreview" value="Preview" />' +
		'</form>' +
		'<div id="wikiPreview" style="display:none;"></div>'
	).appendTo( document.body );

	freshRequire();

	// Wait for document.ready in PF_preview.js to register pfAjaxPreview.
	setTimeout( () => {
		$( '#wpPreview' ).trigger( 'click' );

		// The API `.then()` fires via setTimeout in jQuery 3.
		// Wait for it to settle before asserting.
		setTimeout( () => {
			const $iframe = $( '#wikiPreview' ).children( 'iframe' );
			assert.strictEqual( $iframe.length, 1, 'iframe is created and appended to the preview pane' );

			// Fire load on the iframe.  loadFrameHandler calls $previewpane.show()
			// synchronously on line 62, before the window.dispatchEvent call.
			try {
				$iframe.trigger( 'load' );
			} catch ( e ) {
				// jsdom does not support window.dispatchEvent(new Event('resize'))
			}

			assert.notEqual(
				$( '#wikiPreview' ).css( 'display' ), 'none',
				'preview pane is visible after the iframe load event fires'
			);
			done();
		}, 50 );
	}, 0 );
} );

QUnit.test( 'pfAjaxPreview bails out silently when #wikiPreview is absent', ( assert ) => {
	sinon.stub( mw.Api.prototype, 'post' ).returns( $.Deferred().promise() );
	$( '<input type="submit" id="wpPreview" />' ).appendTo( document.body );
	freshRequire();

	let threw = false;
	try {
		$( '#wpPreview' ).trigger( 'click' );
	} catch ( e ) {
		threw = true;
	}
	assert.false( threw, 'no exception when the preview pane is absent' );
} );
