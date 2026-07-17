'use strict';

const sinon = require( 'sinon' );

global.pf = global.pf || {};
// pf.SpreadsheetComboBoxInput/pf.spreadsheetAutocompleteWidget are read by
// PF_spreadsheet.js's getEditorForAutocompletion(); bridge window.pf →
// global.pf the same way PF_SpreadsheetComboBoxInput.test.js does, so both
// widgets are resolvable in the Node.js test environment.
require( '../../libs/ext.pf.js' );
Object.assign( global.pf, global.window.pf );
require( '../../libs/PF_AutocompleteWidget.js' );
require( '../../libs/PF_SpreadsheetAutocompleteWidget.js' );
require( '../../libs/PF_SpreadsheetComboBoxInput.js' );

const jexcelMock = require( './jexcelMock.js' );

// PF_spreadsheet.js runs its '.pfSpreadsheet' DOM scan (initSpreadsheets())
// synchronously at require() time - same require()-then-DOM-driven pattern
// as PF_maps.test.js, except the fixture has to exist *before* require()
// since there is no jQuery(document).ready() deferral here. window.pfGenerateUUID
// (from PageForms.js) is needed for the "uuid" default-value column test.
require( '../../libs/PageForms.js' );

const SCRIPT = '../../libs/PF_spreadsheet.js';

function freshRequire() {
	delete require.cache[ require.resolve( SCRIPT ) ];
	require( SCRIPT );
}

/**
 * Build the '.pfSpreadsheet' markup PFSpreadsheetInput::getHTML() renders
 * for the non-editMultiplePages (single-page, in-form) case and append it
 * to document.body, along with the wgPageFormsGridParams/wgPageFormsGridValues
 * config PF_spreadsheet.js reads for that template.
 *
 * @param {Object} opts
 * @param {string} [opts.id] spreadsheet element id
 * @param {string} [opts.templateName]
 * @param {string} [opts.formName]
 * @param {Array} [opts.gridParams] column definitions for the template
 * @param {Array} [opts.gridValues] initial row values for the template
 * @param {Object} [opts.extraConfig] additional wg* keys merged into mw.config.get()
 * @return {Object} { $wrapper, id, templateName }
 */
function createSpreadsheet( opts ) {
	opts = opts || {};
	const id = opts.id || 'spreadsheet1';
	const templateName = opts.templateName || 'MyTemplate';
	const formName = opts.formName || 'MyForm';
	const gridParams = opts.gridParams || [
		{ name: 'page' },
		{ name: 'Field1' }
	];
	const gridValues = opts.gridValues || [
		{ page: 'Page1', Field1: 'Value1' }
	];

	const config = Object.assign( {
		wgScriptPath: '/w',
		wgPageFormsGridParams: { [ templateName ]: gridParams },
		wgPageFormsGridValues: { [ templateName ]: gridValues },
		wgPageFormsContLangYes: 'Yes',
		wgPageFormsContLangNo: 'No',
		wgPageFormsDependentFields: []
	}, opts.extraConfig || {} );
	mw.config = { get: ( key ) => config[ key ] };

	const $wrapper = $( '<div>' )
		.addClass( 'pfSpreadsheet' )
		.attr( 'id', id )
		.attr( 'data-template-name', templateName )
		.attr( 'data-form-name', formName )
		.appendTo( document.body );

	return { $wrapper, id, templateName };
}

QUnit.module( 'PF_spreadsheet', {
	beforeEach: function () {
		mw.msg = ( key ) => key;
		mw.message = ( key ) => ( { text: () => key } );
		mw.notify = () => {};
		this.teardownJexcel = jexcelMock.install();
	},
	afterEach: function () {
		this.teardownJexcel();
		delete require.cache[ require.resolve( SCRIPT ) ];
	}
} );

// --- require()-time safety (the load-time crash this issue fixes) ---

QUnit.test( 'require()ing the module without a .pfSpreadsheet element in the DOM does not throw', ( assert ) => {
	mw.config = { get: () => undefined };
	assert.equal( $( '.pfSpreadsheet' ).length, 0, 'no .pfSpreadsheet fixture present' );
	freshRequire();
	assert.true( true, 'require() completed without throwing' );
} );

QUnit.test( 'require()ing the module builds the icon HTML lazily via mw.msg, not at load time', ( assert ) => {
	let msgCalls = 0;
	mw.msg = ( key ) => {
		msgCalls++;
		return key;
	};
	mw.config = { get: () => undefined };
	freshRequire();
	assert.equal( msgCalls, 0, 'mw.msg is not called merely by requiring the module' );
} );

// --- populateSpreadsheet(): single-page (non-editMultiplePages) path ---

QUnit.test( 'populateSpreadsheet builds a jexcel instance from wgPageFormsGridValues', ( assert ) => {
	const { id, templateName } = createSpreadsheet( {
		gridParams: [ { name: 'page' }, { name: 'Field1' } ],
		gridValues: [ { page: 'Page1', Field1: 'Value1' } ]
	} );

	freshRequire();

	const instance = mw.spreadsheets[ id ];
	assert.ok( instance, 'a jexcel instance is registered under mw.spreadsheets[id]' );
	assert.equal( instance._data.length, 1, 'one data row built from gridValues' );
	assert.equal( instance._data[ 0 ][ 0 ], 'Page1', 'first column holds the page value' );
	assert.equal( instance._data[ 0 ][ 1 ], 'Value1', 'second column holds the field value' );
	assert.equal( templateName, 'MyTemplate' );
} );

QUnit.test( 'populateSpreadsheet renders a checkbox column value via valueIsYes decoding', ( assert ) => {
	const { id } = createSpreadsheet( {
		gridParams: [ { name: 'page' }, { name: 'Active', type: 'checkbox' } ],
		gridValues: [ { page: 'Page1', Active: 'Yes' } ]
	} );

	freshRequire();

	const instance = mw.spreadsheets[ id ];
	assert.strictEqual( instance._data[ 0 ][ 1 ], true, 'checkbox column decodes "Yes" to boolean true' );
} );

QUnit.test( 'the management column carries save/cancel HTML built from mw.msg icons', ( assert ) => {
	const { id } = createSpreadsheet();

	freshRequire();

	const instance = mw.spreadsheets[ id ];
	const manageCellHtml = instance._data[ 0 ][ 2 ];
	assert.ok( manageCellHtml.includes( 'save-changes' ), 'management cell contains a save-changes link' );
	assert.ok( manageCellHtml.includes( 'cancel-changes' ), 'management cell contains a cancel-changes link' );
	assert.ok( manageCellHtml.includes( 'mit-row-icons' ), 'non-editMultiplePages management cell also has raise/lower/delete icons' );
} );

// --- rowAdded2(): default values for a newly inserted row ---

QUnit.test( 'adding a row fills in a "current user" default value', ( assert ) => {
	mw.config = { get: ( key ) => ( {
		wgScriptPath: '/w',
		wgPageFormsGridParams: { MyTemplate: [
			{ name: 'page' },
			{ name: 'Owner', default: 'current user' }
		] },
		wgPageFormsGridValues: { MyTemplate: [ { page: 'Page1', Owner: 'Alice' } ] },
		wgUserName: 'Bob'
	} )[ key ] };

	const $wrapper = $( '<div>' )
		.addClass( 'pfSpreadsheet' )
		.attr( 'id', 'spreadsheet1' )
		.attr( 'data-template-name', 'MyTemplate' )
		.attr( 'data-form-name', 'MyForm' )
		.appendTo( document.body );

	freshRequire();

	// Simulate the table row jexcel would have rendered for the new row, as
	// rowAdded2() expects to find it via $instance.find('tr').last(). 4 tds:
	// row-selector, page, Owner, and the manage column ($cell below reads
	// the *last* td, which must stay distinct from the Owner default cell).
	$( '<table>' ).append( '<tr><td></td><td></td><td></td><td></td></tr>' ).appendTo( $wrapper );

	const instance = mw.spreadsheets.spreadsheet1;
	instance.options.oninsertrow( $wrapper[ 0 ] );

	// rowAdded2() writes column N's default at td index (N + 1) - td 0 is
	// jexcel's row-selector column, so "Owner" (gridParams index 1) lands at td 2.
	const $ownerCell = $wrapper.find( 'tr' ).last().children( 'td' ).eq( 2 );
	assert.equal( $ownerCell.html(), 'Bob', 'the "current user" default is filled from wgUserName' );
} );

// --- editMade(): wiring up the save-changes click handler ---

QUnit.test( 'editMade wires a click handler that calls jexcel.prototype.saveChanges via mw.spreadsheets setValue', ( assert ) => {
	const { $wrapper, id } = createSpreadsheet();
	freshRequire();

	const instance = mw.spreadsheets[ id ];
	// Build the DOM a jexcel row would have, inside the wrapper div whose id
	// is spreadsheetID: editMade()'s own selectors are all scoped to
	// "div#" + spreadsheetID, so the fixture has to live there too. A
	// "table.jexcel td[data-y=0]" cell is required too - saveChanges()'s
	// own setValue() call is inside a .each() over exactly that selector.
	const $table = $( '<table>' ).addClass( 'jexcel' ).appendTo( $wrapper );
	$( '<td>' ).attr( 'data-x', '0' ).attr( 'data-y', '0' ).appendTo( $table );
	const $manageCell = $( '<td>' ).attr( 'data-y', '0' ).appendTo( $wrapper );
	const $span = $( '<span>' ).attr( 'id', 'page-span-Page1' ).appendTo( $manageCell );
	$( '<a>' ).addClass( 'save-changes' ).appendTo( $span );

	sinon.stub( instance, 'setValue' );

	instance.options.onchange( $wrapper[ 0 ], null, 0, 0, 'NewValue1' );

	$span.find( 'a.save-changes' ).trigger( 'click' );

	assert.true( instance.setValue.called, 'saveChanges() reads back through mw.spreadsheets[id].setValue' );
} );

// --- deleteRow / moveRow: management-icon click wiring ---

QUnit.test( 'clicking the delete-row icon removes the row via jexcel.prototype.deleteRow', ( assert ) => {
	const { $wrapper, id } = createSpreadsheet( {
		gridValues: [
			{ page: 'Page1', Field1: 'Value1' },
			{ page: 'Page2', Field1: 'Value2' }
		]
	} );
	// The delete-row click handler is bound via a direct (non-delegated)
	// $('div#' + spreadsheetID + ' a.delete-row').click(...) at
	// populateSpreadsheet() time, so the link has to already be inside
	// $wrapper (id === spreadsheetID) before freshRequire() runs.
	$( '<a>' ).addClass( 'delete-row' ).attr( 'data-y', '0' ).appendTo( $wrapper );

	freshRequire();

	const instance = mw.spreadsheets[ id ];
	assert.equal( instance._data.length, 2, 'two rows populated initially' );

	$wrapper.find( 'a.delete-row' ).trigger( 'click' );

	assert.equal( instance._data.length, 1, 'one row remains after delete-row is clicked' );
} );

QUnit.test( 'clicking add-row inserts a new row when data already exists', ( assert ) => {
	const { $wrapper, id } = createSpreadsheet();
	// Same direct-binding constraint as the delete-row test above: the
	// span has to exist inside $wrapper before freshRequire() runs.
	const $addRowSpan = $( '<span>' ).addClass( 'add-row' ).appendTo( $wrapper );

	freshRequire();

	const instance = mw.spreadsheets[ id ];
	sinon.spy( instance, 'insertRow' );

	$addRowSpan.trigger( 'click' );

	assert.true( instance.insertRow.called, 'insertRow() is called when the spreadsheet already has data' );
} );

// --- form submission: hidden-input generation for #pfForm ---

QUnit.test( '#pfForm submit adds a hidden spreadsheet_templates marker input', ( assert ) => {
	const { $wrapper, templateName } = createSpreadsheet();
	// The submit handler is bound via $("#pfForm").submit(...) at
	// require() time, so #pfForm has to exist beforehand.
	const $form = $( '<form>' ).attr( 'id', 'pfForm' ).appendTo( document.body );
	$wrapper.appendTo( $form );

	freshRequire();

	$form.trigger( 'submit' );

	const $marker = $form.find( 'input[name="spreadsheet_templates[' + templateName + ']"]' );
	assert.equal( $marker.length, 1, 'a hidden marker input is added for the template' );
	assert.equal( $marker.val(), 'true' );
} );

QUnit.test( '#pfForm submit adds one hidden input per data cell using getMWValueFromCell', ( assert ) => {
	const { $wrapper, templateName } = createSpreadsheet( {
		gridParams: [ { name: 'page' }, { name: 'Field1' } ]
	} );
	const $form = $( '<form>' ).attr( 'id', 'pfForm' ).appendTo( document.body );
	$wrapper.appendTo( $form );

	const $cell = $( '<td>' ).attr( { 'data-y': '0', 'data-x': '1' } ).html( 'CellValue' );
	$wrapper.append( $cell );

	freshRequire();

	$form.trigger( 'submit' );

	// rowNum is read from the "data-y" DOM attribute as a string, so
	// "rowNum + 1" string-concatenates rather than adds numerically -
	// row 0 becomes "01", not "1". Pre-existing behavior, unrelated to
	// this file's require()-time refactor; asserted here as-is.
	const $input = $form.find( 'input[name="' + templateName + '[01][Field1]"]' );
	assert.equal( $input.length, 1, 'a hidden input is created for the data cell' );
	assert.equal( $input.val(), 'CellValue' );
} );

// --- getEditorForAutocompletion: autocomplete-aware cell editors ---

QUnit.test( 'getEditorForAutocompletion builds a spreadsheetAutocompleteWidget editor for a "property" cell', ( assert ) => {
	createSpreadsheet();
	freshRequire();

	const cell = document.createElement( 'td' );
	cell.setAttribute( 'origname', 'MyTemplate[Field1]' );

	const result = jexcel.prototype.getEditorForAutocompletion(
		'text', 0, 0, 'property', 'MyProperty', cell, 'input'
	);

	assert.true( result.pfSpreadsheetAutocomplete, 'property data type is recognised as autocomplete-aware' );
	assert.ok( result.editor, 'an editor element is returned' );
} );

QUnit.test( 'getEditorForAutocompletion builds a SpreadsheetComboBoxInput editor when inputType is "combobox"', ( assert ) => {
	createSpreadsheet();
	freshRequire();

	const cell = document.createElement( 'td' );
	cell.setAttribute( 'origname', 'MyTemplate[Field1]' );

	const result = jexcel.prototype.getEditorForAutocompletion(
		'combobox', 0, 0, 'category', 'MyCategory', cell, 'input'
	);

	assert.true( result.pfSpreadsheetAutocomplete, 'category data type is recognised as autocomplete-aware' );
	assert.ok( result.editor, 'an editor element is returned' );
} );

QUnit.test( 'getEditorForAutocompletion falls back to a plain input for a non-autocomplete cell', ( assert ) => {
	createSpreadsheet();
	freshRequire();

	const cell = document.createElement( 'td' );
	const result = jexcel.prototype.getEditorForAutocompletion(
		'text', 0, 0, undefined, undefined, cell, 'input'
	);

	assert.false( result.pfSpreadsheetAutocomplete, 'no autocomplete data type -> not autocomplete-aware' );
	assert.equal( result.editor.tagName, 'INPUT', 'a plain <input> element is used as the editor' );
} );

QUnit.test( 'getEditorForAutocompletion resolves a "dep_on" cell via dependenton()', ( assert ) => {
	createSpreadsheet( { extraConfig: { wgPageFormsDependentFields: [ [ 'BaseField', 'Field1' ] ] } } );
	freshRequire();

	const cell = document.createElement( 'td' );
	cell.setAttribute( 'origname', 'Field1' );
	cell.setAttribute( 'name', 'MyTemplate[Field1]' );

	const result = jexcel.prototype.getEditorForAutocompletion(
		'text', 0, 0, 'dep_on', undefined, cell, 'input'
	);

	assert.true( result.pfSpreadsheetAutocomplete, 'a resolvable dependent field is autocomplete-aware' );
} );

QUnit.test( 'getEditorForAutocompletion falls back to a plain input for an unresolvable "dep_on" cell', ( assert ) => {
	createSpreadsheet( { extraConfig: { wgPageFormsDependentFields: [] } } );
	freshRequire();

	const cell = document.createElement( 'td' );
	cell.setAttribute( 'origname', 'Field1' );

	const result = jexcel.prototype.getEditorForAutocompletion(
		'text', 0, 0, 'dep_on', undefined, cell, 'input'
	);

	assert.false( result.pfSpreadsheetAutocomplete, 'a dependent field with no match is not autocomplete-aware' );
} );

// --- getAutocompleteAttributes / setAutocompleteAttributesOfColumns / setAutocompleteAttributesOfCells ---

QUnit.test( 'getAutocompleteAttributes reads attributes directly from the cell when present', ( assert ) => {
	createSpreadsheet();
	freshRequire();

	const $cell = $( '<td>' ).attr( {
		'data-autocomplete-data-type': 'category',
		'data-autocomplete-settings': 'MyCategory'
	} ).appendTo( document.body );

	const result = jexcel.prototype.getAutocompleteAttributes( $cell[ 0 ] );

	assert.equal( result.autocompletedatatype, 'category' );
	assert.equal( result.autocompletesettings, 'MyCategory' );
} );

QUnit.test( 'getAutocompleteAttributes falls back to the column header (Special:MultipageEdit) when cell attributes are missing', ( assert ) => {
	createSpreadsheet();
	freshRequire();

	const $table = $( '<table>' ).appendTo( document.body );
	$( '<thead>' ).appendTo( $table ).append(
		$( '<td>' ).attr( {
			'data-x': '2',
			'data-autocomplete-data-type': 'property',
			'data-autocomplete-settings': 'MyProperty'
		} )
	);
	const $tbody = $( '<tbody>' ).appendTo( $table );
	const $cell = $( '<td>' ).attr( 'data-x', '2' ).appendTo( $tbody );

	const result = jexcel.prototype.getAutocompleteAttributes( $cell[ 0 ] );

	assert.equal( result.autocompletedatatype, 'property', 'falls back to the thead column definition' );
	assert.equal( result.autocompletesettings, 'MyProperty' );
} );

// --- editMultiplePages: save-back-to-wiki via the pfautoedit/move APIs ---

QUnit.test( 'saveChanges posts a pfautoedit query built from generateQueryStringForSave when editMultiplePages is set', ( assert ) => {
	createSpreadsheet();
	freshRequire();
	mw.spreadsheets.spreadsheet1 = { setValue: () => {} };

	const ajaxCalls = [];
	const origAjax = $.ajax;
	$.ajax = function ( opts ) {
		ajaxCalls.push( opts );
		return $.Deferred().resolve( {} ).promise();
	};

	const columns = [ { title: 'page' }, { title: 'Field1' } ];
	jexcel.prototype.saveChanges(
		'spreadsheet1', 'MyTemplate', 'Page1', '', 'MyForm', 0,
		{ page: 'Page1', Field1: 'NewValue' }, columns, 'true'
	);

	$.ajax = origAjax;

	assert.equal( ajaxCalls.length, 1, 'one AJAX request is issued' );
	assert.equal( ajaxCalls[ 0 ].data.action, 'pfautoedit' );
	assert.ok( ajaxCalls[ 0 ].data.query.includes( 'form=MyForm' ), 'query string includes the form name' );
	// Only the value is passed through encodeURIComponent(); the
	// "templateName[columnName]" key portion is left as literal brackets.
	assert.ok( ajaxCalls[ 0 ].data.query.includes( 'MyTemplate[Field1]=NewValue' ), 'query string includes the changed field value' );
} );

QUnit.test( 'saveChanges triggers movePage via the move API when the page was renamed', ( assert ) => {
	createSpreadsheet();
	freshRequire();
	mw.spreadsheets.spreadsheet1 = { setValue: () => {} };

	// movePage() chains $.post() (getToken) -> $.ajax() (the actual move)
	// through $.when(...).then(...), which resolves on a later tick - so
	// this has to be an async test, not a synchronous assert-right-after-call.
	const done = assert.async();

	const ajaxCalls = [];
	const origAjax = $.ajax;
	const origPost = $.post;
	$.ajax = function ( opts ) {
		ajaxCalls.push( opts );
		if ( opts.data && opts.data.action === 'pfautoedit' ) {
			opts.success( {} );
		}
		return $.Deferred().resolve( {} ).promise();
	};
	$.post = function () {
		return $.Deferred().resolve( { query: { tokens: { csrftoken: 'abc123' } } } ).promise();
	};

	const columns = [ { title: 'page' }, { title: 'Field1' } ];
	const savePromise = jexcel.prototype.saveChanges(
		'spreadsheet1', 'MyTemplate', 'OldPage', 'NewPage', 'MyForm', 0,
		{ page: 'NewPage', Field1: 'V' }, columns, 'true'
	);

	savePromise.always( () => {
		setTimeout( () => {
			$.ajax = origAjax;
			$.post = origPost;

			const moveCall = ajaxCalls.find( ( c ) => typeof c.url === 'string' && c.url.includes( 'action=move' ) );
			assert.ok( moveCall, 'a move API request is issued when newPageName differs from pageName' );
			assert.ok( moveCall.url.includes( 'from=OldPage' ) );
			assert.ok( moveCall.url.includes( 'to=NewPage' ) );
			done();
		}, 10 );
	} );
} );

QUnit.test( 'saveNewRow posts a pfautoedit query for the new page when editMultiplePages is set', ( assert ) => {
	const { $wrapper } = createSpreadsheet();
	freshRequire();

	$( '<td>' ).appendTo( $wrapper );
	const $manageCell = $( '<td>' ).appendTo( $wrapper );
	$( '<span>' ).addClass( 'save-or-cancel' ).appendTo( $manageCell );

	const ajaxCalls = [];
	const origAjax = $.ajax;
	$.ajax = function ( opts ) {
		ajaxCalls.push( opts );
	};

	const columns = [ { title: 'page' }, { title: 'Field1' } ];
	jexcel.prototype.saveNewRow(
		'spreadsheet1', 'MyTemplate', 'MyForm', 0, 'NewPage1',
		{ page: 'NewPage1', Field1: 'V' }, columns, 'true'
	);

	$.ajax = origAjax;

	assert.equal( ajaxCalls.length, 1, 'one AJAX request is issued for the new row' );
	assert.equal( ajaxCalls[ 0 ].data.action, 'pfautoedit' );
	assert.ok( ajaxCalls[ 0 ].data.query.includes( 'target=NewPage1' ) );
} );

// --- dependenton() ---

QUnit.test( 'dependenton returns the base field name for a matching dependent field pair', ( assert ) => {
	createSpreadsheet();
	freshRequire();
	mw.config = { get: ( key ) => key === 'wgPageFormsDependentFields' ? [ [ 'BaseField', 'DependentField' ] ] : undefined };
	assert.equal( jexcel.prototype.dependenton( 'DependentField' ), 'BaseField' );
} );

QUnit.test( 'dependenton returns undefined when no dependent field pair matches', ( assert ) => {
	createSpreadsheet();
	freshRequire();
	mw.config = { get: ( key ) => key === 'wgPageFormsDependentFields' ? [ [ 'BaseField', 'DependentField' ] ] : undefined };
	assert.equal( jexcel.prototype.dependenton( 'SomeOtherField' ), undefined );
} );
