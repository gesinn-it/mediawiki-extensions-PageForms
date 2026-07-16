'use strict';

const sinon = require( 'sinon' );

// PF_sfselect.js writes pure helper functions (parseFieldIdentifier,
// parsePlainlistQueryResult, arrayEqual, getRawNameAndValues,
// getSelectFieldPat) onto window.pageforms so that they can be tested
// without triggering DOM events. Ensure the namespace object exists BEFORE
// the script is required for the first time.
window.pageforms = window.pageforms || {};

// handleChange() calls pf.originalValueLookup(), defined in a separate file
// that PageForms loads before PF_sfselect.js in production (ext.pageforms.main).
require( '../../libs/PF_originalValueLookup.js' );

function loadSfselectScript() {
	const scriptPath = '../../libs/PF_sfselect.js';
	delete require.cache[ require.resolve( scriptPath ) ];
	require( scriptPath );
}

// initialize() registers the 'change'/'pf-combobox-choose' handler on
// form#pfForm inside a $( () => {...} ) ready callback, which jQuery defers
// to a macrotask (not a microtask) even when the document is already
// "complete". Awaiting a real timer flushes it before a test interacts with
// the form.
function flushReady() {
	return new Promise( ( resolve ) => {
		setTimeout( resolve, 0 );
	} );
}

QUnit.module( 'PF_sfselect', {
	beforeEach: () => {
		// jsdom's window.Option is not exposed as a bare global by setup.js;
		// setDependentValues() uses the bare `new Option(...)` constructor.
		global.Option = window.Option;
		// initialize() inside the IIFE calls mw.config.get('sf_select'); return
		// null so getSfsObjects() yields an empty array (no DOM interaction)
		// unless a test overrides mw.config before requiring the script.
		mw.config = { get: () => null };
		loadSfselectScript();
		// Flush this load's deferred ready callback now, so it can't fire
		// during a later test (which would re-run registerChangeHandlers()
		// against whatever form#pfForm exists in the DOM at that point).
		return flushReady();
	}
} );

// ── parseFieldIdentifier ────────────────────────────────────────────────────

QUnit.test( 'parseFieldIdentifier: simple field — no index, no list', ( assert ) => {
	const result = window.pageforms.parseFieldIdentifier( 'MyTemplate[MyField]' );
	assert.strictEqual( result.template, 'MyTemplate', 'template' );
	assert.strictEqual( result.property, 'MyField', 'property' );
	assert.strictEqual( result.index, null, 'index is null' );
	assert.false( result.isList, 'isList is false' );
} );

QUnit.test( 'parseFieldIdentifier: field with numeric index, no list', ( assert ) => {
	const result = window.pageforms.parseFieldIdentifier( 'MyTemplate[0][MyField]' );
	assert.strictEqual( result.template, 'MyTemplate', 'template' );
	assert.strictEqual( result.property, 'MyField', 'property' );
	assert.strictEqual( result.index, '0', 'index extracted correctly' );
	assert.false( result.isList, 'isList is false' );
} );

QUnit.test( 'parseFieldIdentifier: list field — no index', ( assert ) => {
	const result = window.pageforms.parseFieldIdentifier( 'MyTemplate[MyField][]' );
	assert.strictEqual( result.template, 'MyTemplate', 'template' );
	assert.strictEqual( result.property, 'MyField', 'property' );
	assert.strictEqual( result.index, null, 'index is null' );
	assert.true( result.isList, 'isList is true' );
} );

QUnit.test( 'parseFieldIdentifier: list field with numeric index', ( assert ) => {
	const result = window.pageforms.parseFieldIdentifier( 'MyTemplate[0][MyField][]' );
	assert.strictEqual( result.template, 'MyTemplate', 'template' );
	assert.strictEqual( result.property, 'MyField', 'property' );
	assert.strictEqual( result.index, '0', 'index extracted correctly' );
	assert.true( result.isList, 'isList is true' );
} );

QUnit.test( 'parseFieldIdentifier: non-zero index is preserved as string', ( assert ) => {
	const result = window.pageforms.parseFieldIdentifier( 'Tpl[42][Field]' );
	assert.strictEqual( result.index, '42', 'index string preserved' );
} );

// ── parsePlainlistQueryResult ───────────────────────────────────────────────

QUnit.test( 'parsePlainlistQueryResult: plain value without brackets returns identity pair', ( assert ) => {
	const result = window.pageforms.parsePlainlistQueryResult( [ 'Plain Value' ] );
	assert.deepEqual( result, [ [ 'Plain Value', 'Plain Value' ] ] );
} );

QUnit.test( 'parsePlainlistQueryResult: "Page (Label)" splits into value/label pair', ( assert ) => {
	const result = window.pageforms.parsePlainlistQueryResult( [ 'Page Title (Display Label)' ] );
	assert.deepEqual( result, [ [ 'Page Title', 'Display Label' ] ] );
} );

QUnit.test( 'parsePlainlistQueryResult: nested brackets — only outermost pair is split', ( assert ) => {
	const result = window.pageforms.parsePlainlistQueryResult( [ 'Page (Nested (Inner) Label)' ] );
	assert.deepEqual( result, [ [ 'Page', 'Nested (Inner) Label' ] ] );
} );

QUnit.test( 'parsePlainlistQueryResult: empty string returns identity pair', ( assert ) => {
	const result = window.pageforms.parsePlainlistQueryResult( [ '' ] );
	assert.deepEqual( result, [ [ '', '' ] ] );
} );

QUnit.test( 'parsePlainlistQueryResult: null input is treated as empty string', ( assert ) => {
	// value = value || '' coerces null → ''
	const result = window.pageforms.parsePlainlistQueryResult( [ null ] );
	assert.deepEqual( result, [ [ '', '' ] ] );
} );

QUnit.test( 'parsePlainlistQueryResult: multiple values are processed independently', ( assert ) => {
	const result = window.pageforms.parsePlainlistQueryResult( [ 'A (X)', 'B (Y)', 'C' ] );
	assert.deepEqual( result, [ [ 'A', 'X' ], [ 'B', 'Y' ], [ 'C', 'C' ] ] );
} );

QUnit.test( 'parsePlainlistQueryResult: leading/trailing whitespace is trimmed', ( assert ) => {
	const result = window.pageforms.parsePlainlistQueryResult( [ '  Page  ( Label ) ' ] );
	assert.deepEqual( result, [ [ 'Page', 'Label' ] ] );
} );

// ── arrayEqual ──────────────────────────────────────────────────────────────

QUnit.test( 'arrayEqual: two empty arrays are equal', ( assert ) => {
	assert.true( window.pageforms.arrayEqual( [], [] ) );
} );

QUnit.test( 'arrayEqual: identical single-element arrays are equal', ( assert ) => {
	assert.true( window.pageforms.arrayEqual( [ 'a' ], [ 'a' ] ) );
} );

QUnit.test( 'arrayEqual: same elements in different order are equal (sort-normalised)', ( assert ) => {
	assert.true( window.pageforms.arrayEqual( [ 'b', 'a' ], [ 'a', 'b' ] ) );
} );

QUnit.test( 'arrayEqual: arrays with different elements are not equal', ( assert ) => {
	assert.false( window.pageforms.arrayEqual( [ 'a' ], [ 'b' ] ) );
} );

QUnit.test( 'arrayEqual: arrays of different lengths are not equal', ( assert ) => {
	assert.false( window.pageforms.arrayEqual( [ 'a' ], [ 'a', 'b' ] ) );
} );

QUnit.test( 'arrayEqual: multi-element unsorted match is equal', ( assert ) => {
	assert.true( window.pageforms.arrayEqual( [ 'c', 'a', 'b' ], [ 'b', 'c', 'a' ] ) );
} );

// ── getRawNameAndValues ─────────────────────────────────────────────────────

QUnit.test( 'getRawNameAndValues: checkbox checked returns [\'true\'] and strips [value]', ( assert ) => {
	const $el = $( '<input>', { type: 'checkbox', name: 'Tpl[Field][value]', checked: true } );
	const result = window.pageforms.getRawNameAndValues( $el );
	assert.strictEqual( result.name, 'Tpl[Field]', 'name has trailing [value] stripped' );
	assert.deepEqual( result.values, [ 'true' ], 'checked checkbox normalises to [\'true\']' );
} );

QUnit.test( 'getRawNameAndValues: checkbox unchecked returns [\'false\']', ( assert ) => {
	const $el = $( '<input>', { type: 'checkbox', name: 'Tpl[Field][value]' } );
	const result = window.pageforms.getRawNameAndValues( $el );
	assert.deepEqual( result.values, [ 'false' ], 'unchecked checkbox normalises to [\'false\']' );
} );

QUnit.test( 'getRawNameAndValues: multi-value <select multiple> returns the array as-is', ( assert ) => {
	const $el = $( '<select>', { name: 'Tpl[Field][]', multiple: true } );
	$el.append( $( '<option>', { value: 'A', selected: true } ) );
	$el.append( $( '<option>', { value: 'B', selected: true } ) );
	const result = window.pageforms.getRawNameAndValues( $el );
	assert.strictEqual( result.name, 'Tpl[Field][]', 'name is unchanged for non-checkbox elements' );
	assert.deepEqual( result.values, [ 'A', 'B' ], 'array value is passed through unchanged' );
} );

QUnit.test( 'getRawNameAndValues: `;`-separated string value is split and trimmed', ( assert ) => {
	const $el = $( '<input>', { name: 'Tpl[Field]', value: 'A ; B ;C' } );
	const result = window.pageforms.getRawNameAndValues( $el );
	assert.deepEqual( result.values, [ 'A', 'B', 'C' ], '`;`-joined value is split and each part trimmed' );
} );

QUnit.test( 'getRawNameAndValues: empty value returns an empty values array', ( assert ) => {
	const $el = $( '<input>', { name: 'Tpl[Field]', value: '' } );
	const result = window.pageforms.getRawNameAndValues( $el );
	assert.deepEqual( result.values, [], 'no value yields an empty array, not [\'\']' );
} );

// ── getSelectFieldPat ────────────────────────────────────────────────────────

QUnit.test( 'getSelectFieldPat: single (non-multiple) template matches both plain and list-suffixed select', ( assert ) => {
	const nameObj = { template: 'Tpl', index: null, property: 'Field', isList: false };
	const f = { selectismultiple: false, selecttemplate: 'Tpl', selectfield: 'Field' };
	const pat = window.pageforms.getSelectFieldPat( nameObj, f );
	assert.strictEqual(
		pat,
		"select[name='Tpl[Field]'], select[name='Tpl[Field][]']",
		'matches both the plain and the [] list-suffixed selector'
	);
} );

QUnit.test( 'getSelectFieldPat: multiple template, same selecttemplate/valuetemplate uses the row index', ( assert ) => {
	const nameObj = { template: 'Tpl', index: '2', property: 'Field', isList: false };
	const f = { selectismultiple: true, selecttemplate: 'Tpl', valuetemplate: 'Tpl', selectfield: 'DepField' };
	const pat = window.pageforms.getSelectFieldPat( nameObj, f );
	assert.strictEqual(
		pat,
		"select[name='Tpl[2][DepField]'],select[name='Tpl[2][DepField][]']",
		'pins the selector to the same row index as the driving field'
	);
} );

QUnit.test( 'getSelectFieldPat: multiple template, different selecttemplate/valuetemplate matches across all rows', ( assert ) => {
	const nameObj = { template: 'DrivingTpl', index: '0', property: 'Field', isList: false };
	const f = { selectismultiple: true, selecttemplate: 'DepTpl', valuetemplate: 'DrivingTpl', selectfield: 'DepField' };
	const pat = window.pageforms.getSelectFieldPat( nameObj, f );
	assert.strictEqual(
		pat,
		"select[name^='DepTpl'][name$='[DepField]'], select[name^='DepTpl'][name$='[DepField][]']",
		'no row index — matches the dependent field on every instance of DepTpl'
	);
} );

// ── getSfsObjects (via initialize()'s ready callback + change trigger) ──────

QUnit.test( 'getSfsObjects: deduplicates multiple-instance configs sharing the same selecttemplate/selectfield', ( assert ) => {
	document.body.innerHTML = `
		<form id="pfForm">
			<input name="DrivingTpl[0][Field]" value="foo">
		</form>`;
	const configs = [
		{ selectismultiple: true, selecttemplate: 'DepTpl', selectfield: 'DepField',
			valuetemplate: 'DrivingTpl', valuefield: 'Field', sep: ',', selectquery: '[[Category:@@@@]]' },
		{ selectismultiple: true, selecttemplate: 'DepTpl', selectfield: 'DepField',
			valuetemplate: 'DrivingTpl', valuefield: 'Field', sep: ',', selectquery: '[[Category:@@@@]]' }
	];
	mw.config = { get: ( key ) => key === 'sf_select' ? JSON.stringify( configs ) : null };
	loadSfselectScript();

	return flushReady().then( () => {
		// executeQuery() only fires for entries whose (valuetemplate, valuefield)
		// matches the changed field; a $.get stub records one call per surviving
		// (non-deduplicated) config that reached executeQuery().
		const getStub = sinon.stub( $, 'get' ).returns( $.Deferred().promise() );
		$( 'input[name="DrivingTpl[0][Field]"]' ).trigger( 'change' );

		assert.strictEqual( getStub.callCount, 1, 'duplicate multiple-instance config collapsed to a single query' );
	} );
} );

QUnit.test( 'getSfsObjects: non-multiple configs are never deduplicated', ( assert ) => {
	document.body.innerHTML = `
		<form id="pfForm">
			<input name="DrivingTpl[Field]" value="foo">
		</form>`;
	const configs = [
		{ selectismultiple: false, selecttemplate: 'DepTpl', selectfield: 'DepField',
			valuetemplate: 'DrivingTpl', valuefield: 'Field', sep: ',', selectquery: '[[Category:@@@@]]' },
		{ selectismultiple: false, selecttemplate: 'DepTpl', selectfield: 'DepField',
			valuetemplate: 'DrivingTpl', valuefield: 'Field', sep: ',', selectquery: '[[Category:@@@@]]' }
	];
	mw.config = { get: ( key ) => key === 'sf_select' ? JSON.stringify( configs ) : null };
	loadSfselectScript();

	return flushReady().then( () => {
		const getStub = sinon.stub( $, 'get' ).returns( $.Deferred().promise() );
		$( 'input[name="DrivingTpl[Field]"]' ).trigger( 'change' );

		assert.strictEqual( getStub.callCount, 2, 'identical non-multiple configs are both kept and both queried' );
	} );
} );

// ── handleChange / executeQuery / setDependentValues (DOM + AJAX stub) ─────

QUnit.test( 'handleChange: ignores change events from elements that are not select/input', ( assert ) => {
	document.body.innerHTML = `
		<form id="pfForm">
			<textarea name="DrivingTpl[Field]">foo</textarea>
		</form>`;
	const configs = [ { selectismultiple: false, selecttemplate: 'DepTpl', selectfield: 'DepField',
		valuetemplate: 'DrivingTpl', valuefield: 'Field', sep: ',' } ];
	mw.config = { get: ( key ) => key === 'sf_select' ? JSON.stringify( configs ) : null };
	loadSfselectScript();

	return flushReady().then( () => {
		const getStub = sinon.stub( $, 'get' ).returns( $.Deferred().promise() );
		$( 'textarea[name="DrivingTpl[Field]"]' ).trigger( 'change' );

		assert.strictEqual( getStub.callCount, 0, 'textarea change events are ignored (not select/input)' );
	} );
} );

QUnit.test( 'handleChange: ignores nameless elements (e.g. select2 search input)', ( assert ) => {
	document.body.innerHTML = `
		<form id="pfForm">
			<input type="text">
		</form>`;
	const configs = [ { selectismultiple: false, selecttemplate: 'DepTpl', selectfield: 'DepField',
		valuetemplate: 'DrivingTpl', valuefield: 'Field', sep: ',' } ];
	mw.config = { get: ( key ) => key === 'sf_select' ? JSON.stringify( configs ) : null };
	loadSfselectScript();

	return flushReady().then( () => {
		const getStub = sinon.stub( $, 'get' ).returns( $.Deferred().promise() );
		$( 'input[type="text"]' ).trigger( 'change' );

		assert.strictEqual( getStub.callCount, 0, 'nameless element is skipped before reaching executeQuery' );
	} );
} );

QUnit.test( 'executeQuery: empty driving value short-circuits to setDependentValues([]) without an AJAX call', ( assert ) => {
	document.body.innerHTML = `
		<form id="pfForm">
			<input name="DrivingTpl[Field]" value="">
			<select name="DepTpl[DepField]"><option value="old">old</option></select>
		</form>`;
	const configs = [ { selectismultiple: false, selecttemplate: 'DepTpl', selectfield: 'DepField',
		valuetemplate: 'DrivingTpl', valuefield: 'Field', sep: ',' } ];
	mw.config = { get: ( key ) => key === 'sf_select' ? JSON.stringify( configs ) : null };
	loadSfselectScript();

	return flushReady().then( () => {
		const getStub = sinon.stub( $, 'get' ).returns( $.Deferred().promise() );
		$( 'input[name="DrivingTpl[Field]"]' ).trigger( 'change' );

		assert.strictEqual( getStub.callCount, 0, 'no AJAX call is made when the driving value is empty' );
		assert.strictEqual( $( 'select[name="DepTpl[DepField]"]' ).find( 'option' ).length, 0,
			'dependent select is cleared via setDependentValues( ..., [] )' );
	} );
} );

QUnit.test( 'executeQuery: builds an SMW #ask query and populates options on AJAX success', ( assert ) => {
	document.body.innerHTML = `
		<form id="pfForm">
			<input name="DrivingTpl[Field]" value="foo">
			<select name="DepTpl[DepField]"></select>
		</form>`;
	const configs = [ { selectismultiple: false, selecttemplate: 'DepTpl', selectfield: 'DepField',
		valuetemplate: 'DrivingTpl', valuefield: 'Field', sep: ',',
		selectquery: '[[Category:@@@@]]' } ];
	mw.config = {
		get: ( key ) => {
			if ( key === 'sf_select' ) {
				return JSON.stringify( configs );
			}
			if ( key === 'wgScriptPath' ) {
				return '/w';
			}
			return null;
		}
	};
	loadSfselectScript();

	return flushReady().then( () => {
		sinon.stub( $, 'get' ).callsFake( ( url, param ) => {
			assert.strictEqual( url, '/w/api.php', 'requests the sformsselect API at wgScriptPath' );
			assert.strictEqual( param.action, 'sformsselect', 'action param is sformsselect' );
			assert.strictEqual( param.approach, 'smw', 'approach is smw when selectquery is configured' );
			assert.strictEqual( param.query, '[[Category:foo]]', '@@@@ placeholder is replaced with the driving value' );
			return $.Deferred().resolve( { sformsselect: { values: [ 'Option A', 'Option B' ] } } ).promise();
		} );
		$( 'input[name="DrivingTpl[Field]"]' ).trigger( 'change' );
		return flushReady();
	} ).then( () => {
		const $options = $( 'select[name="DepTpl[DepField]"]' ).find( 'option' );
		assert.strictEqual( $options.length, 2, 'dependent select is populated with the API result' );
		assert.strictEqual( $options.eq( 0 ).val(), 'Option A', 'first option value from API result' );
		assert.strictEqual( $options.eq( 1 ).val(), 'Option B', 'second option value from API result' );
	} );
} );

QUnit.test( 'executeQuery: builds a parser-function query when selectquery is absent', ( assert ) => {
	document.body.innerHTML = `
		<form id="pfForm">
			<input name="DrivingTpl[Field]" value="foo,bar">
			<select name="DepTpl[DepField]"></select>
		</form>`;
	const configs = [ { selectismultiple: false, selecttemplate: 'DepTpl', selectfield: 'DepField',
		valuetemplate: 'DrivingTpl', valuefield: 'Field', sep: ',',
		selectfunction: '{{#myfunc:@@@@}}' } ];
	mw.config = {
		get: ( key ) => key === 'sf_select' ? JSON.stringify( configs ) : null
	};
	loadSfselectScript();

	let getStub;
	return flushReady().then( () => {
		getStub = sinon.stub( $, 'get' ).callsFake( ( url, param ) => {
			assert.strictEqual( param.approach, 'function', 'approach is function when selectquery is absent' );
			assert.strictEqual( param.query, '{{#myfunc:foo,bar}}', '@@@@ placeholder is replaced, values joined with `,`' );
			return $.Deferred().resolve( { sformsselect: { values: [] } } ).promise();
		} );
		$( 'input[name="DrivingTpl[Field]"]' ).trigger( 'change' );
		return flushReady();
	} ).then( () => {
		assert.strictEqual( getStub.callCount, 1 );
	} );
} );

QUnit.test( 'executeQuery: logs and does not throw when the AJAX call fails', ( assert ) => {
	document.body.innerHTML = `
		<form id="pfForm">
			<input name="DrivingTpl[Field]" value="foo">
			<select name="DepTpl[DepField]"></select>
		</form>`;
	const configs = [ { selectismultiple: false, selecttemplate: 'DepTpl', selectfield: 'DepField',
		valuetemplate: 'DrivingTpl', valuefield: 'Field', sep: ',',
		selectquery: '[[Category:@@@@]]' } ];
	mw.config = {
		get: ( key ) => key === 'sf_select' ? JSON.stringify( configs ) : null
	};
	loadSfselectScript();

	assert.expect( 1 );
	return flushReady().then( () => {
		sinon.stub( $, 'get' ).returns( $.Deferred().reject().promise() );
		const consoleStub = sinon.stub( console, 'log' );
		$( 'input[name="DrivingTpl[Field]"]' ).trigger( 'change' );
		return flushReady().then( () => {
			assert.true( consoleStub.calledWith( 'SF_Select: API call failed.' ), 'logs a failure message instead of throwing' );
			consoleStub.restore();
		}, () => {
			consoleStub.restore();
		} );
	} );
} );

// ── setDependentValues (label mode, selectrm removal, change re-triggering) ─

QUnit.test( 'setDependentValues: label mode splits "Value (Label)" into option value/text pairs', ( assert ) => {
	document.body.innerHTML = `
		<form id="pfForm">
			<input name="DrivingTpl[Field]" value="foo">
			<select name="DepTpl[DepField]"></select>
		</form>`;
	const configs = [ { selectismultiple: false, selecttemplate: 'DepTpl', selectfield: 'DepField',
		valuetemplate: 'DrivingTpl', valuefield: 'Field', sep: ',', label: true,
		selectquery: '[[Category:@@@@]]' } ];
	mw.config = { get: ( key ) => key === 'sf_select' ? JSON.stringify( configs ) : null };
	loadSfselectScript();

	return flushReady().then( () => {
		sinon.stub( $, 'get' ).returns(
			$.Deferred().resolve( { sformsselect: { values: [ 'Page1 (Label One)' ] } } ).promise()
		);
		$( 'input[name="DrivingTpl[Field]"]' ).trigger( 'change' );
		return flushReady();
	} ).then( () => {
		const $option = $( 'select[name="DepTpl[DepField]"]' ).find( 'option' );
		assert.strictEqual( $option.val(), 'Page1', 'option value is the page-title part' );
		assert.strictEqual( $option.text(), 'Label One', 'option text is the bracketed label part' );
	} );
} );

QUnit.test( 'setDependentValues: removes the multiple-instance div when selectrm and no value survives', ( assert ) => {
	document.body.innerHTML = `
		<form id="pfForm">
			<input name="DrivingTpl[0][Field]" value="foo">
			<div class="multipleTemplateInstance">
				<select name="DepTpl[0][DepField]"><option value="stale" selected>stale</option></select>
			</div>
		</form>`;
	const configs = [ { selectismultiple: true, selecttemplate: 'DepTpl', selectfield: 'DepField',
		valuetemplate: 'DrivingTpl', valuefield: 'Field', sep: ',', selectrm: true,
		selectquery: '[[Category:@@@@]]' } ];
	mw.config = { get: ( key ) => key === 'sf_select' ? JSON.stringify( configs ) : null };
	loadSfselectScript();

	return flushReady().then( () => {
		sinon.stub( $, 'get' ).returns( $.Deferred().resolve( { sformsselect: { values: [] } } ).promise() );
		$( 'input[name="DrivingTpl[0][Field]"]' ).trigger( 'change' );
		return flushReady();
	} ).then( () => {
		assert.strictEqual( $( 'div.multipleTemplateInstance' ).length, 0,
			'the whole instance div is removed once its dependent select has no surviving value' );
	} );
} );
