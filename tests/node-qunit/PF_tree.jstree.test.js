'use strict';

// Runs $.fn.applyJSTree against the real, bundled jsTree (libs/jstree.js) instead of a stub,
// so that a change in the jsTree API or events is noticed when the library is upgraded.
// PF_tree.test.js covers the wrapper logic with a stub.

global.pf = global.pf || {};

require( '../../libs/jstree.js' );
require( '../../libs/PF_tree.js' );

// PF_tree.test.js removes $.fn.jstree again after each of its tests; keep the real plugin.
const realJstree = $.fn.jstree;

QUnit.module( 'PF_tree with the real jsTree', {
	beforeEach() {
		$.fn.jstree = realJstree;
	}
} );

const DATA = [
	{ text: 'Alpha', id: 'a' },
	{ text: 'Beta', id: 'b' },
	{ text: 'Gamma', id: 'g' }
];

/**
 * @param {Object} params
 * @param {string} [cssClass]
 * @return {jQuery} the tree element; its hidden value input is the next sibling
 */
function createTree( params, cssClass ) {
	const $el = $( '<div>' )
		.attr( {
			id: 'Tpl[field]treeinput',
			class: cssClass || 'pfTreeInput',
			data: JSON.stringify( DATA ),
			params: JSON.stringify( Object.assign( { delimiter: ',', multiple: false, cur_value: '' }, params ) )
		} )
		.appendTo( document.body );
	$( '<input type="hidden" class="PFTree_data">' ).insertAfter( $el );
	return $el;
}

/**
 * Applies jsTree and calls back once it has rendered the nodes.
 *
 * @param {jQuery} $el
 * @param {Function} callback
 */
function applyAndWait( $el, callback ) {
	$el.on( 'ready.jstree', callback );
	$el.applyJSTree();
}

function valueOf( $el ) {
	return $el.next( 'input.PFTree_data' ).attr( 'value' );
}

QUnit.test( 'renders one node per data entry and switches the icons off', ( assert ) => {
	const done = assert.async();
	const $el = createTree();

	applyAndWait( $el, () => {
		assert.deepEqual(
			$el.find( 'li.jstree-node > a.jstree-anchor' ).map( ( i, a ) => $( a ).text().trim() ).get(),
			[ 'Alpha', 'Beta', 'Gamma' ],
			'node labels'
		);
		assert.strictEqual( $el.find( '.jstree-checkbox' ).length, 3, 'checkbox plugin adds a checkbox per node' );
		assert.true(
			$el.find( 'ul.jstree-container-ul' ).hasClass( 'jstree-no-icons' ),
			'theme icons are switched off'
		);
		done();
	} );
} );

QUnit.test( 'selecting and deselecting a node writes the hidden input (single select)', ( assert ) => {
	const done = assert.async();
	const $el = createTree();
	applyAndWait( $el, () => {
		$el.jstree( 'select_node', 'b' );
		assert.strictEqual( valueOf( $el ), 'Beta', 'selected node text is stored' );

		$el.jstree( 'deselect_node', 'b' );
		assert.strictEqual( valueOf( $el ), '', 'value is cleared after deselect' );
		done();
	} );
} );

QUnit.test( 'multiple selection joins the node texts with the delimiter', ( assert ) => {
	const done = assert.async();
	const $el = createTree( { multiple: true, delimiter: ';' } );
	applyAndWait( $el, () => {
		$el.jstree( 'select_node', 'a' );
		$el.jstree( 'select_node', 'g' );
		assert.strictEqual( valueOf( $el ), 'Alpha;Gamma', 'both values' );

		$el.jstree( 'deselect_node', 'a' );
		assert.strictEqual( valueOf( $el ), 'Gamma', 'one value left' );
		done();
	} );
} );

QUnit.test( 'checking a node does not cascade to other nodes', ( assert ) => {
	const done = assert.async();
	const $el = createTree( { multiple: true } );
	applyAndWait( $el, () => {
		$el.jstree( 'select_node', 'a' );

		assert.deepEqual( $el.jstree( 'get_selected' ), [ 'a' ], 'only the clicked node is selected' );
		done();
	} );
} );

QUnit.test( 'cur_value is written to the hidden input', ( assert ) => {
	const done = assert.async();
	const $el = createTree( { multiple: true, cur_value: 'Alpha,Beta' } );
	applyAndWait( $el, () => {
		assert.strictEqual( valueOf( $el ), 'Alpha,Beta', 'initial value is kept' );
		done();
	} );
} );

QUnit.test( 'a disabled tree ignores selections', ( assert ) => {
	const done = assert.async();
	const $el = createTree( {}, 'pfTreeInput pfTreeInputDisabled' );
	applyAndWait( $el, () => {
		$el.jstree( 'select_node', 'a' );

		assert.deepEqual( $el.jstree( 'get_selected' ), [], 'the node is deselected again' );
		assert.notOk( valueOf( $el ), 'the hidden input is not written' );
		done();
	} );
} );

QUnit.test( 'typing in the search input shows only matching nodes', ( assert ) => {
	const done = assert.async();
	const $el = createTree( { 'search-input': true } );
	const $search = $( '<input type="text">' ).attr( 'id', 'Tpl[field]treeinput_searchinput' )
		.appendTo( document.body );
	applyAndWait( $el, () => {
		$search.val( 'Bet' ).trigger( 'keyup' );

		const $visible = $el.find( 'li.jstree-node' ).filter( ( i, li ) => !$( li ).hasClass( 'jstree-hidden' ) );
		assert.deepEqual(
			$visible.find( '> a.jstree-anchor' ).map( ( i, a ) => $( a ).text().trim() ).get(),
			[ 'Beta' ],
			'only the match is visible'
		);
		done();
	} );
} );
