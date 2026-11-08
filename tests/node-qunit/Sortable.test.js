'use strict';

// Page Forms uses the bundled Sortable.js (libs/Sortable.js) for the rearranging of multiple-instance
// templates (PageForms.js) and of the tokens input (ext.pf.select2.tokens.js). The tests of those
// files replace Sortable with a stub; these tests run against the real library, so that an upgrade
// that changes the API Page Forms relies on is noticed. Real dragging needs layout and is not
// possible in jsdom; it is checked in a browser.

require( '../../libs/Sortable.js' );

// The library publishes its global on the jsdom window (1.8.x) or on globalThis (1.9 and later).
const Sortable = global.Sortable || window.Sortable;

QUnit.module( 'Sortable (real library)', {
	afterEach() {
		delete global.Sortable;
		delete window.Sortable;
	}
} );

function createList() {
	const $list = $( '<div class="multipleTemplateList">' ).appendTo( document.body );
	[ 'a', 'b', 'c' ].forEach( ( id ) => {
		$( '<div class="multipleTemplateInstance">' )
			.attr( 'data-id', id )
			.append( '<span class="instanceRearranger">::</span>' )
			.appendTo( $list );
	} );
	return $list;
}

function order( $list ) {
	return $list.children().map( ( i, el ) => $( el ).attr( 'data-id' ) ).get();
}

QUnit.test( 'is exposed as a global with create()', ( assert ) => {
	assert.strictEqual( typeof Sortable, 'function', 'Sortable is a constructor' );
	assert.strictEqual( typeof Sortable.create, 'function', 'Sortable.create exists' );
} );

QUnit.test( 'create() returns an instance that keeps the options Page Forms passes', ( assert ) => {
	const $list = createList();
	const onStart = () => {};

	const sortable = Sortable.create( $list[ 0 ], { handle: '.instanceRearranger', onStart } );

	assert.strictEqual( sortable.option( 'handle' ), '.instanceRearranger', 'handle' );
	assert.strictEqual( sortable.option( 'onStart' ), onStart, 'onStart callback' );
	assert.strictEqual( sortable.el, $list[ 0 ], 'instance is bound to the list element' );
} );

QUnit.test( 'create() keeps the ghostClass of the tokens input', ( assert ) => {
	const sortable = Sortable.create( createList()[ 0 ], { ghostClass: 'pfTokensGhost', onEnd: () => {} } );

	assert.strictEqual( sortable.option( 'ghostClass' ), 'pfTokensGhost', 'ghostClass' );
} );

QUnit.test( 'sort() rearranges the children and toArray() reports the order by data-id', ( assert ) => {
	const $list = createList();
	const sortable = Sortable.create( $list[ 0 ], { handle: '.instanceRearranger' } );

	assert.deepEqual( sortable.toArray(), [ 'a', 'b', 'c' ], 'initial order' );

	sortable.sort( [ 'c', 'a', 'b' ] );

	assert.deepEqual( order( $list ), [ 'c', 'a', 'b' ], 'DOM order follows sort()' );
	assert.deepEqual( sortable.toArray(), [ 'c', 'a', 'b' ], 'toArray() follows the DOM' );
} );

QUnit.test( 'two lists on one page are independent instances', ( assert ) => {
	const first = Sortable.create( createList()[ 0 ], { handle: '.instanceRearranger' } );
	const second = Sortable.create( createList()[ 0 ], { handle: '.instanceRearranger' } );

	first.option( 'handle', '.other' );

	assert.strictEqual( second.option( 'handle' ), '.instanceRearranger', 'second list keeps its own options' );
} );
