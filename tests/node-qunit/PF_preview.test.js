'use strict';

const sinon = require( 'sinon' );

const PREVIEW_SCRIPT = '../../libs/PF_preview.js';

function freshRequire() {
	delete require.cache[ require.resolve( PREVIEW_SCRIPT ) ];
	require( PREVIEW_SCRIPT );
}

QUnit.module( 'PF_preview', {
	beforeEach() {
		// PF_preview.js registers a document-level VEForAllLoaded handler on every
		// freshRequire(); without this, handlers from earlier tests pile up on the
		// (never-reset) document and all fire together in later tests.
		$( document ).off( 'VEForAllLoaded' );
		global.validateAll = () => true;
		this.configValues = {
			wgAction: 'formedit',
			wgCanonicalSpecialPageName: null,
			wgCanonicalNamespace: null,
			wgPageName: 'TestPage'
		};
		mw.config = {
			get: ( key ) => Object.prototype.hasOwnProperty.call( this.configValues, key ) ? this.configValues[ key ] : null
		};
		this.paramValues = {};
		mw.util = { getParamValue: ( key ) => Object.prototype.hasOwnProperty.call( this.paramValues, key ) ? this.paramValues[ key ] : null };
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
			// synchronously, then dispatches a resize event via window.dispatchEvent.
			// This must not throw — the Event must be created from the jsdom-bound
			// window.Event constructor, not the Node.js global Event.
			$iframe.trigger( 'load' );

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

// ── validateAll guard ────────────────────────────────────────────────────────

QUnit.test( 'previewButtonClickedHandler does not call the API when validateAll returns false', ( assert ) => {
	const done = assert.async();
	global.validateAll = () => false;

	sinon.stub( mw.Api.prototype, 'post' ).returns( $.Deferred().promise() );

	$(
		'<form id="pfForm">' +
			'<input type="submit" id="wpPreview" value="Preview" />' +
		'</form>' +
		'<div id="wikiPreview" style="display:none;"></div>'
	).appendTo( document.body );

	freshRequire();

	setTimeout( () => {
		$( '#wpPreview' ).trigger( 'click' );
		assert.false( mw.Api.prototype.post.called, 'post was not called when validateAll returns false' );
		done();
	}, 0 );
} );

// ── Special:FormEdit branch with mw.util.getParamValue ──────────────────────

QUnit.test( 'Special:FormEdit branch uses form/target query params when present', function ( assert ) {
	const done = assert.async();

	this.configValues.wgAction = null;
	this.configValues.wgCanonicalNamespace = 'Special';
	this.configValues.wgCanonicalSpecialPageName = 'FormEdit';
	this.configValues.wgPageName = 'Ignored/Slash/Path';
	this.paramValues.form = 'ParamForm';
	this.paramValues.target = 'ParamTarget';

	sinon.stub( mw.Api.prototype, 'post' ).returns( $.Deferred().promise() );

	$(
		'<form id="pfForm">' +
			'<input type="submit" id="wpPreview" value="Preview" />' +
		'</form>' +
		'<div id="wikiPreview" style="display:none;"></div>'
	).appendTo( document.body );

	freshRequire();

	setTimeout( () => {
		$( '#wpPreview' ).trigger( 'click' );
		const data = mw.Api.prototype.post.args[ 0 ][ 0 ];
		assert.strictEqual( data.form, 'ParamForm', 'form taken from query param, not slash-split fallback' );
		assert.strictEqual( data.target, 'ParamTarget', 'target taken from query param, not slash-split fallback' );
		done();
	}, 0 );
} );

QUnit.test( 'Special:FormEdit branch falls back to slash-split pagename when params are absent', function ( assert ) {
	const done = assert.async();

	this.configValues.wgAction = null;
	this.configValues.wgCanonicalNamespace = 'Special';
	this.configValues.wgCanonicalSpecialPageName = 'FormEdit';
	this.configValues.wgPageName = 'Ignored/MyForm/Some/Deep/Page';

	sinon.stub( mw.Api.prototype, 'post' ).returns( $.Deferred().promise() );

	$(
		'<form id="pfForm">' +
			'<input type="submit" id="wpPreview" value="Preview" />' +
		'</form>' +
		'<div id="wikiPreview" style="display:none;"></div>'
	).appendTo( document.body );

	freshRequire();

	setTimeout( () => {
		$( '#wpPreview' ).trigger( 'click' );
		const data = mw.Api.prototype.post.args[ 0 ][ 0 ];
		assert.strictEqual( data.form, 'MyForm', 'form taken from parts[1] when param is absent' );
		assert.strictEqual( data.target, 'Some/Deep/Page', 'target re-joins remaining parts with slashes preserved' );
		done();
	}, 0 );
} );

// ── AJAX error handler ───────────────────────────────────────────────────────

QUnit.test( 'AJAX error response renders responseText and concatenated error messages as text', ( assert ) => {
	const done = assert.async();

	sinon.stub( mw.Api.prototype, 'post' ).returns( $.Deferred().reject( 'http', {
		xhr: {
			responseText: JSON.stringify( {
				responseText: 'Something went wrong.',
				errors: [ { message: 'Field A is required.' }, { message: 'Field B is invalid.' } ]
			} )
		}
	} ) );

	$(
		'<form id="pfForm">' +
			'<input type="submit" id="wpPreview" value="Preview" />' +
		'</form>' +
		'<div id="wikiPreview" style="display:none;"></div>'
	).appendTo( document.body );

	freshRequire();

	setTimeout( () => {
		$( '#wpPreview' ).trigger( 'click' );
		setTimeout( () => {
			const $previewpane = $( '#wikiPreview' );
			assert.notEqual( $previewpane.css( 'display' ), 'none', 'preview pane is shown' );
			const text = $previewpane.find( 'p' ).text();
			assert.true( text.includes( 'Something went wrong.' ), 'responseText included' );
			assert.true( text.includes( 'Field A is required.' ), 'first error message appended' );
			assert.true( text.includes( 'Field B is invalid.' ), 'second error message appended' );
			done();
		}, 0 );
	}, 0 );
} );

// ── VEForAllLoaded click interception ───────────────────────────────────────

QUnit.test( 'VEForAllLoaded actualizes VE fields before running the preview handler', ( assert ) => {
	const done = assert.async();

	mw.pageFormsActualizeVisualEditorFields = sinon.stub().callsFake( ( callback ) => callback() );

	sinon.stub( mw.Api.prototype, 'post' ).returns(
		$.Deferred().resolve( { result: '<html><body></body></html>' } ).promise()
	);

	$(
		'<form id="pfForm">' +
			'<input type="submit" id="wpPreview" value="Preview" />' +
			'<div class="visualeditor"></div>' +
		'</form>' +
		'<div id="wikiPreview" style="display:none;"></div>'
	).appendTo( document.body );

	freshRequire();

	setTimeout( () => {
		$( document ).trigger( 'VEForAllLoaded' );
		$( '#wpPreview' ).trigger( 'click' );

		setTimeout( () => {
			assert.true( mw.pageFormsActualizeVisualEditorFields.called, 'VE fields are actualized before the preview handler runs' );
			assert.true( mw.Api.prototype.post.called, 'preview API call still happens after VE actualization' );
			done();
			delete mw.pageFormsActualizeVisualEditorFields;
		}, 0 );
	}, 0 );
} );

QUnit.test( 'VEForAllLoaded does not intercept clicks when no .visualeditor is present', ( assert ) => {
	const done = assert.async();

	mw.pageFormsActualizeVisualEditorFields = sinon.stub().callsFake( ( callback ) => callback() );

	sinon.stub( mw.Api.prototype, 'post' ).returns(
		$.Deferred().resolve( { result: '<html><body></body></html>' } ).promise()
	);

	$(
		'<form id="pfForm">' +
			'<input type="submit" id="wpPreview" value="Preview" />' +
		'</form>' +
		'<div id="wikiPreview" style="display:none;"></div>'
	).appendTo( document.body );

	freshRequire();

	setTimeout( () => {
		$( document ).trigger( 'VEForAllLoaded' );
		$( '#wpPreview' ).trigger( 'click' );

		setTimeout( () => {
			assert.false(
				mw.pageFormsActualizeVisualEditorFields.called,
				'VE fields are not actualized when no .visualeditor element is present'
			);
			assert.true( mw.Api.prototype.post.called, 'preview API call still happens normally' );
			done();
			delete mw.pageFormsActualizeVisualEditorFields;
		}, 0 );
	}, 0 );
} );
