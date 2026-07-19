'use strict';

// PF_tree.js defines pf.TreeInput (setOptions/check/uncheck/setCurValue/handleSearch)
// and the $.fn.applyJSTree jQuery plugin, which wraps the vendored jsTree library
// (libs/jstree.js, excluded from coverage). A minimal $.fn.jstree stub — chainable,
// firing select_node.jstree/deselect_node.jstree — is enough to exercise applyJSTree
// without loading the real jsTree.

global.pf = global.pf || {};

require( '../../libs/PF_tree.js' );

QUnit.module( 'PF_tree pf.TreeInput prototype' );

function createTreeElement( attrs ) {
	const $el = $( '<div class="pfTreeInput">' )
		.attr( Object.assign( {
			id: 'myFieldtreeinput',
			data: JSON.stringify( [ { text: 'A' }, { text: 'B' } ] ),
			params: JSON.stringify( { delimiter: ',', multiple: false, cur_value: '' } )
		}, attrs || {} ) )
		.appendTo( document.body );
	$( '<input type="hidden" class="PFTree_data">' ).insertAfter( $el );
	return $el;
}

// --- constructor ---

QUnit.test( 'constructor stores element, id, class and isDisabled', ( assert ) => {
	const $el = createTreeElement( { id: 'myTree', class: 'pfTreeInput' } );

	const tree = new pf.TreeInput( $el[ 0 ] );

	assert.equal( tree.id, 'myTree', 'id read from element' );
	assert.equal( tree.class, 'pfTreeInput', 'class read from element' );
	assert.false( tree.isDisabled, 'isDisabled false without pfTreeInputDisabled class' );
} );

QUnit.test( 'constructor sets isDisabled when class includes pfTreeInputDisabled', ( assert ) => {
	const $el = createTreeElement( { class: 'pfTreeInput pfTreeInputDisabled' } );

	const tree = new pf.TreeInput( $el[ 0 ] );

	assert.true( tree.isDisabled, 'isDisabled true with pfTreeInputDisabled class' );
} );

QUnit.test( 'constructor tolerates a missing element', ( assert ) => {
	const tree = new pf.TreeInput();

	assert.strictEqual( tree.id, null, 'id is null without an element' );
	assert.strictEqual( tree.class, '', 'class defaults to empty string' );
	assert.false( tree.isDisabled, 'isDisabled false without an element' );
} );

// --- setOptions ---

QUnit.test( 'setOptions parses data/params and returns jsTree options', ( assert ) => {
	const $el = createTreeElement( {
		data: JSON.stringify( [ { text: 'A' } ] ),
		params: JSON.stringify( { delimiter: ',', multiple: true, cur_value: 'A' } )
	} );
	const tree = new pf.TreeInput( $el[ 0 ] );

	const options = tree.setOptions();

	assert.deepEqual( tree.data, [ { text: 'A' } ], 'data parsed from data attribute' );
	assert.equal( tree.delimiter, ',', 'delimiter read from params' );
	assert.true( tree.multiple, 'multiple read from params' );
	assert.equal( tree.cur_value, 'A', 'cur_value read from params' );
	assert.deepEqual( tree.values, [], 'values initialised to an empty array' );
	assert.deepEqual( options.plugins, [ 'checkbox' ], 'checkbox plugin enabled by default' );
	assert.equal( options.core.data, tree.data, 'core.data uses parsed tree data' );
	assert.true( options.core.multiple, 'core.multiple mirrors params.multiple' );
	assert.equal( options.checkbox.three_state, false, 'checkbox.three_state is false' );
	assert.equal( options.checkbox.cascade, 'none', 'checkbox.cascade is none' );
} );

QUnit.test( 'setOptions adds the search plugin when search-input param is set', ( assert ) => {
	const $el = createTreeElement( {
		params: JSON.stringify( { delimiter: ',', multiple: false, cur_value: '', 'search-input': true } )
	} );
	const tree = new pf.TreeInput( $el[ 0 ] );

	const options = tree.setOptions();

	assert.deepEqual( options.plugins, [ 'checkbox', 'search' ], 'search plugin appended' );
} );

QUnit.test( 'setOptions omits the search plugin when search-input param is false', ( assert ) => {
	const $el = createTreeElement( {
		params: JSON.stringify( { delimiter: ',', multiple: false, cur_value: '', 'search-input': false } )
	} );
	const tree = new pf.TreeInput( $el[ 0 ] );

	const options = tree.setOptions();

	assert.deepEqual( options.plugins, [ 'checkbox' ], 'search plugin not appended' );
} );

// --- check / uncheck ---

QUnit.test( 'check appends a single value to the sibling hidden input', ( assert ) => {
	const $el = createTreeElement();
	const tree = new pf.TreeInput( $el[ 0 ] );
	tree.setOptions();

	tree.check( 'A' );

	const $input = $el.next( 'input.PFTree_data' );
	assert.equal( $input.attr( 'value' ), 'A', 'hidden input set to checked value' );
	assert.deepEqual( tree.values, [ 'A' ], 'values tracks the checked value' );
} );

QUnit.test( 'check joins multiple values with the delimiter when multiple is true', ( assert ) => {
	const $el = createTreeElement( {
		params: JSON.stringify( { delimiter: ',', multiple: true, cur_value: '' } )
	} );
	const tree = new pf.TreeInput( $el[ 0 ] );
	tree.setOptions();

	tree.check( 'A' );
	tree.check( 'B' );

	const $input = $el.next( 'input.PFTree_data' );
	assert.equal( $input.attr( 'value' ), 'A,B', 'hidden input joins values with delimiter' );
	assert.deepEqual( tree.values, [ 'A', 'B' ], 'values accumulates checked values' );
} );

QUnit.test( 'check overwrites the hidden input value when multiple is false', ( assert ) => {
	const $el = createTreeElement( {
		params: JSON.stringify( { delimiter: ',', multiple: false, cur_value: '' } )
	} );
	const tree = new pf.TreeInput( $el[ 0 ] );
	tree.setOptions();

	tree.check( 'A' );
	tree.check( 'B' );

	const $input = $el.next( 'input.PFTree_data' );
	assert.equal( $input.attr( 'value' ), 'B', 'hidden input holds only the latest value' );
} );

QUnit.test( 'uncheck removes a value and updates the hidden input', ( assert ) => {
	const $el = createTreeElement( {
		params: JSON.stringify( { delimiter: ',', multiple: true, cur_value: '' } )
	} );
	const tree = new pf.TreeInput( $el[ 0 ] );
	tree.setOptions();
	tree.check( 'A' );
	tree.check( 'B' );

	tree.uncheck( 'A' );

	const $input = $el.next( 'input.PFTree_data' );
	assert.equal( $input.attr( 'value' ), 'B', 'hidden input no longer includes unchecked value' );
	assert.deepEqual( tree.values, [ 'B' ], 'values no longer includes unchecked value' );
} );

// --- setCurValue ---

QUnit.test( 'setCurValue sets the hidden input and values when cur_value is non-empty', ( assert ) => {
	const $el = createTreeElement( {
		params: JSON.stringify( { delimiter: ',', multiple: true, cur_value: 'A,B' } )
	} );
	const tree = new pf.TreeInput( $el[ 0 ] );
	tree.setOptions();

	tree.setCurValue();

	const $input = $el.next( 'input.PFTree_data' );
	assert.equal( $input.attr( 'value' ), 'A,B', 'hidden input set to cur_value' );
	assert.deepEqual( tree.values, [ 'A', 'B' ], 'values split from cur_value by delimiter' );
} );

[ null, undefined, '' ].forEach( ( curValue ) => {
	QUnit.test( 'setCurValue is a no-op when cur_value is ' + JSON.stringify( curValue ), ( assert ) => {
		const $el = createTreeElement( {
			params: JSON.stringify( { delimiter: ',', multiple: false, cur_value: curValue } )
		} );
		const tree = new pf.TreeInput( $el[ 0 ] );
		tree.setOptions();

		tree.setCurValue();

		const $input = $el.next( 'input.PFTree_data' );
		assert.notOk( $input.attr( 'value' ), 'hidden input left unset' );
		assert.deepEqual( tree.values, [], 'values left untouched (still the empty array set by setOptions)' );
	} );
} );

// --- handleSearch ---

QUnit.test( 'handleSearch wires a keyup handler that calls jstree search', ( assert ) => {
	const $el = createTreeElement( { id: 'myTree' } );
	$( '<input id="myTreetreeinput_searchinput">' ).appendTo( document.body );
	const tree = new pf.TreeInput( $el[ 0 ] );
	tree.id = 'myTreetreeinput';

	const searchCalls = [];
	const jsTree = {
		jstree: ( ...args ) => searchCalls.push( args )
	};

	tree.handleSearch( { id: 'myTreetreeinput' }, jsTree );
	$( '#myTreetreeinput_searchinput' ).val( 'foo' ).trigger( 'keyup' );

	assert.equal( searchCalls.length, 1, 'jstree search invoked once' );
	assert.equal( searchCalls[ 0 ][ 0 ], 'search', 'first argument is the search command' );
	assert.equal( searchCalls[ 0 ][ 1 ], 'foo', 'second argument is the search string' );
} );

QUnit.test( 'handleSearch escapes brackets in the tree id selector', ( assert ) => {
	const $el = createTreeElement();
	// Real '[' and ']' characters in the id attribute value (not literal backslashes) —
	// handleSearch() is responsible for escaping these for use in its jQuery selector.
	const $searchInput = $( '<input>' )
		.attr( 'id', 'tree[0]treeinput_searchinput' )
		.appendTo( document.body );
	const tree = new pf.TreeInput( $el[ 0 ] );

	const searchCalls = [];
	const jsTree = {
		jstree: ( ...args ) => searchCalls.push( args )
	};

	tree.handleSearch( { id: 'tree[0]treeinput' }, jsTree );
	$searchInput.val( 'foo' ).trigger( 'keyup' );

	assert.equal( searchCalls.length, 1, 'jstree search invoked once, proving the escaped selector matched the element' );
} );

// --- applyJSTree ---

let deselectAllCalls;

QUnit.module( 'PF_tree $.fn.applyJSTree', {
	beforeEach() {
		deselectAllCalls = [];
		// Minimal jsTree stub: chainable, and records deselect_all calls so the
		// disabled-tree branch (which calls jstree('deselect_all', true)) can be asserted.
		global.$.fn.jstree = function ( command, ...args ) {
			if ( command === 'deselect_all' ) {
				deselectAllCalls.push( [ command, ...args ] );
			}
			return this;
		};
	},
	afterEach() {
		delete global.$.fn.jstree;
	}
} );

QUnit.test( 'checking a node calls tree.check with the node text', ( assert ) => {
	const $el = createTreeElement( {
		params: JSON.stringify( { delimiter: ',', multiple: true, cur_value: '' } )
	} );

	$el.applyJSTree();
	$el.trigger( 'select_node.jstree', { node: { text: 'A' } } );

	const $input = $el.next( 'input.PFTree_data' );
	assert.equal( $input.attr( 'value' ), 'A', 'checked node text written to hidden input' );
} );

QUnit.test( 'deselecting a node calls tree.uncheck with the node text', ( assert ) => {
	const $el = createTreeElement( {
		params: JSON.stringify( { delimiter: ',', multiple: true, cur_value: '' } )
	} );

	$el.applyJSTree();
	$el.trigger( 'select_node.jstree', { node: { text: 'A' } } );
	$el.trigger( 'select_node.jstree', { node: { text: 'B' } } );
	$el.trigger( 'deselect_node.jstree', { node: { text: 'A' } } );

	const $input = $el.next( 'input.PFTree_data' );
	assert.equal( $input.attr( 'value' ), 'B', 'unchecked node text removed from hidden input' );
} );

QUnit.test( 'applies cur_value on initialisation', ( assert ) => {
	const $el = createTreeElement( {
		params: JSON.stringify( { delimiter: ',', multiple: true, cur_value: 'A,B' } )
	} );

	$el.applyJSTree();

	const $input = $el.next( 'input.PFTree_data' );
	assert.equal( $input.attr( 'value' ), 'A,B', 'cur_value applied to hidden input on init' );
} );

QUnit.test( 'a disabled tree does not check/uncheck on select/deselect, but forces deselection', ( assert ) => {
	const $el = createTreeElement( {
		class: 'pfTreeInput pfTreeInputDisabled',
		params: JSON.stringify( { delimiter: ',', multiple: true, cur_value: '' } )
	} );

	$el.applyJSTree();
	$el.trigger( 'select_node.jstree', { node: { text: 'A' } } );

	const $input = $el.next( 'input.PFTree_data' );
	assert.notOk( $input.attr( 'value' ), 'hidden input untouched — tree.check is never called when disabled' );
	assert.equal( deselectAllCalls.length, 1, 'jstree(deselect_all, true) invoked to force deselection' );
	assert.deepEqual( deselectAllCalls[ 0 ], [ 'deselect_all', true ], 'called with true to skip an extra event trigger' );
} );

QUnit.test( 'a disabled tree stops propagation on before_open to keep the tree collapsed', ( assert ) => {
	const $el = createTreeElement( { class: 'pfTreeInput pfTreeInputDisabled' } );
	$el.applyJSTree();

	let bubbledToParent = false;
	$el.parent().on( 'before_open.jstree', () => {
		bubbledToParent = true;
	} );

	const $event = $.Event( 'before_open.jstree' );
	$el.trigger( $event );

	assert.true( $event.isPropagationStopped(), 'event propagation stopped' );
	assert.false( bubbledToParent, 'event does not bubble to a parent handler' );
} );

QUnit.test( 'a disabled tree blocks clicks on tree anchors/checkboxes/toggles', ( assert ) => {
	const $el = createTreeElement( { class: 'pfTreeInput pfTreeInputDisabled' } );
	$el.applyJSTree();
	const $anchor = $( '<a class="jstree-anchor">' ).appendTo( $el );

	const $event = $.Event( 'click' );
	$anchor.trigger( $event );

	assert.true( $event.isDefaultPrevented(), 'default action prevented for a click on a tree anchor' );
} );
