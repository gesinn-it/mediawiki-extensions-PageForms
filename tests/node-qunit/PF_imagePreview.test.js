'use strict';

const sinon = require( 'sinon' );

const PREVIEW_SCRIPT = '../../libs/PF_imagePreview.js';

function freshRequire() {
	delete require.cache[ require.resolve( PREVIEW_SCRIPT ) ];
	require( PREVIEW_SCRIPT );
}

function createDOM( inputId ) {
	inputId = inputId || 'pfImgInput';
	const $input = $( '<input>' ).attr( 'id', inputId ).appendTo( document.body );
	$( '<div>' ).attr( 'id', inputId + '_imagepreview' ).appendTo( document.body );
	$( '<a>' )
		.addClass( 'pfImagePreview' )
		.attr( 'data-input-id', inputId )
		.appendTo( document.body );
	return $input;
}

QUnit.module( 'PF_imagePreview', {
	beforeEach() {
		mw.config = { get: ( key ) => key === 'wgScriptPath' ? '/w' : null };
	}
} );

// ── getPreviewImage ──────────────────────────────────────────────────────────

QUnit.test( 'getPreviewImage: builds correct URL and title param', ( assert ) => {
	freshRequire();
	sinon.stub( $, 'getJSON' ).callsFake( ( url, params ) => {
		assert.ok( url.endsWith( '/api.php' ), 'URL ends with /api.php' );
		assert.strictEqual( params.titles, 'File:TestImage.png' );
		assert.strictEqual( params.iiurlwidth, 200 );
	} );

	global.getPreviewImage( { title: 'TestImage.png', width: 200 }, () => {} );

	assert.ok( $.getJSON.calledOnce, '$.getJSON called' );
} );

QUnit.test( 'getPreviewImage: callback receives thumburl for valid data', ( assert ) => {
	freshRequire();
	const thumbUrl = 'http://example.com/thumb.png';
	sinon.stub( $, 'getJSON' ).callsFake( ( url, params, cb ) => {
		cb( { query: { pages: { 1: { imageinfo: [ { thumburl: thumbUrl } ] } } } } );
	} );

	let result;
	global.getPreviewImage( { title: 'Test.png', width: 200 }, ( url ) => {
		result = url;
	} );

	assert.strictEqual( result, thumbUrl );
} );

QUnit.test( 'getPreviewImage: callback receives false when imageinfo is empty', ( assert ) => {
	freshRequire();
	sinon.stub( $, 'getJSON' ).callsFake( ( url, params, cb ) => {
		cb( { query: { pages: { 1: { imageinfo: [] } } } } );
	} );

	let result = 'initial';
	global.getPreviewImage( { title: 'Test.png', width: 200 }, ( url ) => {
		result = url;
	} );

	assert.false( result );
} );

QUnit.test( 'getPreviewImage: callback receives false when no query in response', ( assert ) => {
	freshRequire();
	sinon.stub( $, 'getJSON' ).callsFake( ( url, params, cb ) => {
		cb( {} );
	} );

	let result = 'initial';
	global.getPreviewImage( { title: 'Test.png', width: 200 }, ( url ) => {
		result = url;
	} );

	assert.false( result );
} );

// ── document.ready / showPreview ─────────────────────────────────────────────

QUnit.test( 'change event triggers preview with image and sets img src', ( assert ) => {
	const done = assert.async();
	const thumbUrl = 'http://example.com/preview.png';
	sinon.stub( $, 'getJSON' ).callsFake( ( url, params, cb ) => {
		cb( { query: { pages: { 1: { imageinfo: [ { thumburl: thumbUrl } ] } } } } );
	} );
	const $input = createDOM();
	freshRequire();

	// jQuery 3.x fires document.ready via window.setTimeout — wait for handler attachment
	setTimeout( () => {
		$input.val( 'SomeImage.png' ).trigger( 'change' );
		const $img = $( '#pfImgInput_imagepreview img' );
		assert.strictEqual( $img.attr( 'src' ), thumbUrl, 'preview img src is set' );
		done();
	}, 50 );
} );

QUnit.test( 'change event clears preview div when getPreviewImage returns false', ( assert ) => {
	const done = assert.async();
	sinon.stub( $, 'getJSON' ).callsFake( ( url, params, cb ) => {
		cb( {} ); // no data → callback(false)
	} );
	const $input = createDOM();
	$( '#pfImgInput_imagepreview' ).html( '<img src="old.png">' ); // pre-fill
	freshRequire();

	// jQuery 3.x fires document.ready via window.setTimeout — wait for handler attachment
	setTimeout( () => {
		$input.val( 'Missing.png' ).trigger( 'change' );
		assert.strictEqual( $( '#pfImgInput_imagepreview' ).html(), '', 'preview div cleared' );
		done();
	}, 50 );
} );
