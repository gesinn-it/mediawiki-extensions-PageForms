'use strict';

// Page Forms uses the bundled Sortable.js (libs/Sortable.js) for the rearranging of multiple-instance
// templates (PageForms.js) and of the tokens input (ext.pf.select2.tokens.js). The tests of those
// files replace Sortable with a stub; these tests run against the real library, so that an upgrade
// that changes the API Page Forms relies on is noticed. Real dragging needs layout and is not
// possible in jsdom; it is checked in a browser.

const fs = require( 'fs' );
const path = require( 'path' );

/**
 * Evaluates libs/Sortable.js like ResourceLoader does: as a plain script of which "this" is the window,
 * with a "module" that is defined. The library must still publish the global "Sortable" then.
 *
 * @return {{Sortable: Function, moduleExports: Object}}
 */
function loadLikeResourceLoader() {
	const source = fs.readFileSync( path.resolve( __dirname, '../../libs/Sortable.js' ), 'utf8' );
	const module = { exports: {} };
	// eslint-disable-next-line no-new-func
	new Function( 'module', 'exports', source ).call( window, module, module.exports );
	return { Sortable: window.Sortable, moduleExports: module.exports };
}

let Sortable;

QUnit.module( 'Sortable (real library)', {
	beforeEach() {
		Sortable = loadLikeResourceLoader().Sortable;
	},
	afterEach() {
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

QUnit.test( 'is exposed as a global although "module" is defined', ( assert ) => {
	delete window.Sortable;

	const loaded = loadLikeResourceLoader();

	assert.strictEqual( typeof window.Sortable, 'function', 'window.Sortable is a constructor' );
	assert.strictEqual( typeof window.Sortable.create, 'function', 'Sortable.create exists' );
	assert.deepEqual( Object.keys( loaded.moduleExports ), [], 'nothing is exported through module.exports' );
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
