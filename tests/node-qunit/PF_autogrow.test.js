'use strict';

require( '../../libs/PF_autogrow.js' );

function createTextarea( id, cols, rows, value ) {
	const ta = document.createElement( 'textarea' );
	ta.id = id;
	ta.cols = cols;
	ta.rows = rows;
	ta.value = value;
	document.body.appendChild( ta );
	return ta;
}

QUnit.module( 'PF_autogrow' );

// ===========================================================
// autoGrow — row calculation
// ===========================================================

QUnit.test( 'single short line keeps default rows', ( assert ) => {
	const ta = createTextarea( 'ag1', 40, 5, 'hi' );
	// autoGrowSetDefaultValues stores defaults; autoGrow recalculates
	jQuery( ta ).autoGrow();
	// "hi" = 1 line (length 2 / 40 cols → floor(0.05+1)=1). 1 < 5 → keep default
	assert.strictEqual( ta.rows, 5 );
} );

QUnit.test( 'very long single line expands rows', ( assert ) => {
	// 80 chars / 10 cols: floor(80/10+1) = 9 linesCount; 9 >= 3 rows → rows = 9+1 = 10
	const ta = createTextarea( 'ag2', 10, 3, 'a'.repeat( 80 ) );
	jQuery( ta ).autoGrow();
	assert.strictEqual( ta.rows, 10 );
} );

QUnit.test( 'multiple newlines expand rows', ( assert ) => {
	// 5 lines of 1 char each in 40-col textarea; 5 >= 3 → rows = 6
	const ta = createTextarea( 'ag3', 40, 3, 'a\nb\nc\nd\ne' );
	jQuery( ta ).autoGrow();
	assert.strictEqual( ta.rows, 6 );
} );

QUnit.test( 'empty textarea keeps default rows', ( assert ) => {
	// empty value: 1 empty line, floor(0/40+1)=1, 1 < 5 → keep default
	const ta = createTextarea( 'ag4', 40, 5, '' );
	jQuery( ta ).autoGrow();
	assert.strictEqual( ta.rows, 5 );
} );

QUnit.test( 'shrinks back after content is cleared', ( assert ) => {
	const ta = createTextarea( 'ag5', 10, 3, 'a'.repeat( 80 ) );
	jQuery( ta ).autoGrow();
	assert.strictEqual( ta.rows, 10, 'expanded first' );

	// Simulate the user erasing all content and triggering onkeyup
	ta.value = '';
	ta.onkeyup();
	assert.strictEqual( ta.rows, 3, 'shrinks back to default' );
} );

QUnit.test( 'onkeyup handler is attached after autoGrow()', ( assert ) => {
	const ta = createTextarea( 'ag6', 40, 3, '' );
	jQuery( ta ).autoGrow();
	assert.strictEqual( typeof ta.onkeyup, 'function' );
} );
