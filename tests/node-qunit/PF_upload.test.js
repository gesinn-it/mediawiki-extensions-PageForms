'use strict';

// PF_upload.js runs at module load and attaches a `change` handler to #wpUploadFile
// via document.ready. We use fresh-require + assert.async(50ms) for the jQuery
// async ready behaviour (see testing notes).

const SCRIPT = '../../libs/PF_upload.js';

function freshRequire() {
	delete require.cache[ require.resolve( SCRIPT ) ];
	require( SCRIPT );
}

function createDOM() {
	// jsdom does not allow programmatic value setting on <input type="file">.
	// The handler only reads $(this).val() — any input type works for testing.
	$( '<input type="text">' ).attr( 'id', 'wpUploadFile' ).appendTo( document.body );
	$( '<input type="text">' ).attr( 'id', 'wpDestFile' ).appendTo( document.body );
}

QUnit.module( 'PF_upload' );

// ── fname extraction ──────────────────────────────────────────────────────────

QUnit.test( 'plain filename (no slashes) → used as-is, capitalised', ( assert ) => {
	const done = assert.async();
	createDOM();
	freshRequire();

	setTimeout( () => {
		$( '#wpUploadFile' ).val( 'myfile.png' ).trigger( 'change' );
		assert.strictEqual( $( '#wpDestFile' ).val(), 'Myfile.png' );
		done();
	}, 50 );
} );

QUnit.test( 'unix path (forward slash) → basename extracted', ( assert ) => {
	const done = assert.async();
	createDOM();
	freshRequire();

	setTimeout( () => {
		$( '#wpUploadFile' ).val( '/home/user/uploads/photo.jpg' ).trigger( 'change' );
		assert.strictEqual( $( '#wpDestFile' ).val(), 'Photo.jpg' );
		done();
	}, 50 );
} );

QUnit.test( 'windows path (backslash) → basename extracted', ( assert ) => {
	const done = assert.async();
	createDOM();
	freshRequire();

	setTimeout( () => {
		$( '#wpUploadFile' ).val( 'C:\\Users\\Alex\\my_file.png' ).trigger( 'change' );
		assert.strictEqual( $( '#wpDestFile' ).val(), 'My_file.png' );
		done();
	}, 50 );
} );

QUnit.test( 'forward slash wins when both slash types present', ( assert ) => {
	const done = assert.async();
	createDOM();
	freshRequire();

	setTimeout( () => {
		// slash at pos 5, backslash at pos 2 → slash wins
		$( '#wpUploadFile' ).val( 'a\\b/file.png' ).trigger( 'change' );
		assert.strictEqual( $( '#wpDestFile' ).val(), 'File.png' );
		done();
	}, 50 );
} );

QUnit.test( 'spaces in filename are replaced by underscores', ( assert ) => {
	const done = assert.async();
	createDOM();
	freshRequire();

	setTimeout( () => {
		$( '#wpUploadFile' ).val( 'my file name.png' ).trigger( 'change' );
		assert.strictEqual( $( '#wpDestFile' ).val(), 'My_file_name.png' );
		done();
	}, 50 );
} );

QUnit.test( 'URL-encoded characters are decoded in destination filename', ( assert ) => {
	const done = assert.async();
	createDOM();
	freshRequire();

	setTimeout( () => {
		$( '#wpUploadFile' ).val( 'my%20photo.png' ).trigger( 'change' );
		// Code order: replace spaces → capitalise → decodeURIComponent.
		// No literal space in input, so space-replace is a no-op.
		// decodeURIComponent('My%20photo.png') → 'My photo.png' (space, not underscore).
		assert.strictEqual( $( '#wpDestFile' ).val(), 'My photo.png' );
		done();
	}, 50 );
} );

QUnit.test( 'invalid URI encoding falls back to raw fname', ( assert ) => {
	const done = assert.async();
	createDOM();
	freshRequire();

	setTimeout( () => {
		// '%XX' with invalid hex → decodeURIComponent throws → fallback to raw fname
		$( '#wpUploadFile' ).val( 'file%ZZinvalid.png' ).trigger( 'change' );
		assert.strictEqual( $( '#wpDestFile' ).val(), 'File%ZZinvalid.png' );
		done();
	}, 50 );
} );

QUnit.test( 'no wpDestFile in DOM → no error thrown', ( assert ) => {
	const done = assert.async();
	// Only wpUploadFile, no wpDestFile
	$( '<input type="text">' ).attr( 'id', 'wpUploadFile' ).appendTo( document.body );
	freshRequire();

	setTimeout( () => {
		let threw = false;
		try {
			$( '#wpUploadFile' ).val( 'file.png' ).trigger( 'change' );
		} catch ( e ) {
			threw = true;
		}
		assert.false( threw, 'no exception when #wpDestFile is absent' );
		done();
	}, 50 );
} );
