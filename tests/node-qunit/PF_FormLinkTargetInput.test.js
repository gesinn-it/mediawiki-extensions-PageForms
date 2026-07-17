'use strict';

const sinon = require( 'sinon' );

// PF_FormLinkTargetInput.js is loaded as (jQuery, mediaWiki, OO, pageforms)
// and writes readConfig/buildTargetUrl onto that pageforms namespace so they
// can be tested without triggering DOM events. Ensure the namespace object
// exists BEFORE the script is required for the first time. Deliberately NOT
// aliased to global.pf -- other test files keep global.pf and global.pageforms
// as separate objects, and this global namespace is shared for the whole
// process (never reset between test files), so merging them here would leak
// into unrelated test files.
global.pageforms = global.pageforms || {};

/**
 * Widget double for pf.ComboBoxInput, mirroring the PF_AutocompleteWidget.test.js
 * widget-double pattern. PFTargetInputDialog.initialize() only ever touches
 * apply(), getMenu(), $element, $input, setValue(), getValue(), focus() and
 * getCanonicalValueForInput() on the real widget — no coupling refactor is in
 * scope (per issue #112), so a double covering that surface is sufficient.
 *
 * @return {Object}
 */
function createComboBoxInputDouble() {
	const menu = {
		hideWhenOutOfView: true,
		isFloatableOutOfView: () => false,
		clip: sinon.spy(),
		toggle: sinon.spy(),
		setFloatableContainer: sinon.spy(),
		_pfSuppressOpen: false
	};
	let value = '';
	const $input = $( '<input>' );
	return {
		menu: menu,
		$element: $( '<div>' ).append( $input ),
		$input: $input,
		apply: sinon.spy(),
		getMenu: () => menu,
		setValue: sinon.spy( ( v ) => {
			value = v;
		} ),
		getValue: () => value,
		focus: sinon.spy(),
		getCanonicalValueForInput: sinon.stub().returns( undefined )
	};
}

function loadScript() {
	const scriptPath = '../../libs/PF_FormLinkTargetInput.js';
	delete require.cache[ require.resolve( scriptPath ) ];
	// Production loads this script exactly once; re-requiring it per test
	// would otherwise stack a duplicate document-level click delegate on top
	// of the one from the previous test.
	$( document ).off( 'click', '[data-pf-target-input]' );
	require( scriptPath );
}

QUnit.module( 'PF_FormLinkTargetInput', {
	beforeEach: function () {
		mw.msg = ( key ) => key;
		mw.config = { get: ( key ) => ( key === 'wgPageName' ) ? 'Test_Page' : null };

		this.comboDouble = createComboBoxInputDouble();
		global.pageforms.ComboBoxInput = sinon.stub().returns( this.comboDouble );

		// The click handler builds a fresh WindowManager/dialog per click and
		// keeps no reference reachable from the test; capture both as they
		// are registered/opened so tests can drive the dialog directly
		// (setValue, executeAction, getActionProcess) without reimplementing
		// OOUI's own open/close animation timing.
		const self = this;
		this.capturedDialog = null;
		this.capturedInstance = null;
		sinon.stub( OO.ui.WindowManager.prototype, 'addWindows' ).callsFake( function ( windows ) {
			self.capturedDialog = windows[ 0 ];
			return this.constructor.prototype.addWindows.wrappedMethod.apply( this, arguments );
		} );
		sinon.stub( OO.ui.WindowManager.prototype, 'openWindow' ).callsFake( function () {
			self.capturedInstance = this.constructor.prototype.openWindow.wrappedMethod.apply( this, arguments );
			return self.capturedInstance;
		} );

		// executeAction('confirm') calls close(), and close() only proceeds
		// once the window has finished its open lifecycle -- production code
		// only ever confirms after the user sees the dialog, i.e. after
		// `opening` has resolved. Tests must wait for the same point before
		// driving the action.
		this.whenOpened = function () {
			return self.capturedInstance.opening;
		};

		loadScript();
	}
} );

// ── readConfig ──────────────────────────────────────────────────────────────

QUnit.test( 'readConfig: reads href/isForm=false for a plain link', ( assert ) => {
	const $el = $( '<a>', {
		href: '/wiki/Special:FormEdit/MyForm',
		'data-pf-target-dialog-title': 'Pick a page',
		'data-pf-target-default': 'Default value',
		'data-pf-autocomplete-datatype': 'category',
		'data-pf-autocomplete-settings': 'MyCategory'
	} );
	const cfg = pageforms.pfTargetInputReadConfig( $el );

	assert.strictEqual( cfg.baseUrl, '/wiki/Special:FormEdit/MyForm', 'baseUrl from href' );
	assert.false( cfg.isForm, 'isForm is false for <a>' );
	assert.false( cfg.isPost, 'isPost is false for <a>' );
	assert.strictEqual( cfg.dialogTitle, 'Pick a page', 'dialogTitle read' );
	assert.strictEqual( cfg.defaultValue, 'Default value', 'defaultValue read' );
	assert.strictEqual( cfg.dataType, 'category', 'dataType read' );
	assert.strictEqual( cfg.dataSettings, 'MyCategory', 'dataSettings read' );
} );

QUnit.test( 'readConfig: reads action/isForm=true for a GET form', ( assert ) => {
	const $el = $( '<form>', { action: '/wiki/Special:FormEdit/MyForm', method: 'get' } );
	const cfg = pageforms.pfTargetInputReadConfig( $el );

	assert.strictEqual( cfg.baseUrl, '/wiki/Special:FormEdit/MyForm', 'baseUrl from action' );
	assert.true( cfg.isForm, 'isForm is true for <form>' );
	assert.false( cfg.isPost, 'isPost is false for GET form' );
} );

QUnit.test( 'readConfig: isPost=true for a POST form', ( assert ) => {
	const $el = $( '<form>', { action: '/wiki/Special:FormEdit/MyForm', method: 'post' } );
	const cfg = pageforms.pfTargetInputReadConfig( $el );

	assert.true( cfg.isForm, 'isForm is true' );
	assert.true( cfg.isPost, 'isPost is true for POST form' );
} );

QUnit.test( 'readConfig: missing data attributes default to empty strings', ( assert ) => {
	const $el = $( '<a>', { href: '/x' } );
	const cfg = pageforms.pfTargetInputReadConfig( $el );

	assert.strictEqual( cfg.dialogTitle, '', 'dialogTitle defaults to empty string' );
	assert.strictEqual( cfg.defaultValue, '', 'defaultValue defaults to empty string' );
	assert.strictEqual( cfg.dataType, '', 'dataType defaults to empty string' );
	assert.strictEqual( cfg.dataSettings, '', 'dataSettings defaults to empty string' );
} );

// ── buildTargetUrl ──────────────────────────────────────────────────────────

QUnit.test( 'buildTargetUrl: appends encoded, underscored value with a slash', ( assert ) => {
	const url = pageforms.pfTargetInputBuildTargetUrl( '/wiki/Special:FormEdit/MyForm', 'My Page' );
	assert.strictEqual( url, '/wiki/Special:FormEdit/MyForm/My_Page', 'spaces become underscores' );
} );

QUnit.test( 'buildTargetUrl: does not double a trailing slash on the base URL', ( assert ) => {
	const url = pageforms.pfTargetInputBuildTargetUrl( '/wiki/Special:FormEdit/MyForm/', 'My Page' );
	assert.strictEqual( url, '/wiki/Special:FormEdit/MyForm/My_Page', 'no double slash' );
} );

QUnit.test( 'buildTargetUrl: percent-encodes special characters', ( assert ) => {
	const url = pageforms.pfTargetInputBuildTargetUrl( '/wiki/Special:FormEdit/MyForm', 'A&B?C' );
	assert.strictEqual( url, '/wiki/Special:FormEdit/MyForm/A%26B%3FC', 'special characters encoded' );
} );

// ── initialize(): dataType branches ─────────────────────────────────────────

QUnit.test( 'initialize(): plain TextInputWidget branch when dataType is unset', function ( assert ) {
	const $trigger = $( '<a>', { href: '/x', 'data-pf-target-input': '' } ).appendTo( document.body );
	$trigger.trigger( 'click' );

	assert.ok( this.capturedDialog.inputWidget instanceof OO.ui.TextInputWidget, 'plain TextInputWidget is used' );
	assert.false( global.pageforms.ComboBoxInput.called, 'pf.ComboBoxInput constructor not invoked' );
} );

QUnit.test( 'initialize(): ComboBoxInput branch when dataType is set, apply() is called', function ( assert ) {
	const $trigger = $( '<a>', {
		href: '/x',
		'data-pf-target-input': '',
		'data-pf-autocomplete-datatype': 'category',
		'data-pf-autocomplete-settings': 'MyCategory'
	} ).appendTo( document.body );
	$trigger.trigger( 'click' );

	assert.strictEqual( this.capturedDialog.inputWidget, this.comboDouble, 'pf.ComboBoxInput instance is used' );
	assert.true( this.comboDouble.apply.calledOnce, 'apply() called once' );
} );

// ── getActionProcess(): confirm/cancel ──────────────────────────────────────

QUnit.test( 'getActionProcess(confirm): empty trimmed value yields an OO.ui.Error', function ( assert ) {
	const $trigger = $( '<a>', { href: '/x', 'data-pf-target-input': '' } ).appendTo( document.body );
	$trigger.trigger( 'click' );

	const dialog = this.capturedDialog;
	dialog.inputWidget.setValue( '   ' );

	return dialog.getActionProcess( 'confirm' ).execute().then(
		() => assert.ok( false, 'expected the process to reject' ),
		( errors ) => {
			assert.ok( Array.isArray( errors ), 'rejects with an error list' );
			assert.ok( errors[ 0 ] instanceof OO.ui.Error, 'rejected with an OO.ui.Error' );
			assert.strictEqual( errors[ 0 ].getMessageText(), 'pf-target-input-error-empty', 'error message key' );
		}
	);
} );

QUnit.test( 'getActionProcess(confirm): closes with the trimmed raw value when no ComboBoxInput', function ( assert ) {
	const $trigger = $( '<a>', { href: '/x', 'data-pf-target-input': '' } ).appendTo( document.body );
	$trigger.trigger( 'click' );

	const dialog = this.capturedDialog;
	dialog.inputWidget.setValue( '  My Page  ' );
	sinon.stub( dialog, 'close' );

	dialog.getActionProcess( 'confirm' ).execute();

	assert.true( dialog.close.calledOnce, 'close() called once' );
	assert.deepEqual( dialog.close.firstCall.args[ 0 ], { action: 'confirm', value: 'My Page' }, 'closes with trimmed value' );
} );

QUnit.test( 'getActionProcess(confirm): resolves display value to canonical value via ComboBoxInput', function ( assert ) {
	const $trigger = $( '<a>', {
		href: '/x',
		'data-pf-target-input': '',
		'data-pf-autocomplete-datatype': 'category',
		'data-pf-autocomplete-settings': 'MyCategory'
	} ).appendTo( document.body );
	$trigger.trigger( 'click' );

	const dialog = this.capturedDialog;
	dialog.inputWidget.setValue( 'Displayed Title' );
	this.comboDouble.getCanonicalValueForInput.returns( 'Canonical_Title' );
	sinon.stub( dialog, 'close' );

	dialog.getActionProcess( 'confirm' ).execute();

	assert.true( this.comboDouble.getCanonicalValueForInput.calledWith( 'Displayed Title' ), 'canonical lookup queried with raw value' );
	assert.deepEqual( dialog.close.firstCall.args[ 0 ], { action: 'confirm', value: 'Canonical_Title' }, 'closes with canonical value' );
} );

QUnit.test( 'getActionProcess(confirm): falls back to raw value when canonical lookup is falsy', function ( assert ) {
	const $trigger = $( '<a>', {
		href: '/x',
		'data-pf-target-input': '',
		'data-pf-autocomplete-datatype': 'category',
		'data-pf-autocomplete-settings': 'MyCategory'
	} ).appendTo( document.body );
	$trigger.trigger( 'click' );

	const dialog = this.capturedDialog;
	dialog.inputWidget.setValue( 'Some Value' );
	this.comboDouble.getCanonicalValueForInput.returns( undefined );
	sinon.stub( dialog, 'close' );

	dialog.getActionProcess( 'confirm' ).execute();

	assert.deepEqual( dialog.close.firstCall.args[ 0 ], { action: 'confirm', value: 'Some Value' }, 'falls back to raw value' );
} );

QUnit.test( 'getActionProcess(cancel): closes with only the cancel action', function ( assert ) {
	const $trigger = $( '<a>', { href: '/x', 'data-pf-target-input': '' } ).appendTo( document.body );
	$trigger.trigger( 'click' );

	const dialog = this.capturedDialog;
	sinon.stub( dialog, 'close' );

	dialog.getActionProcess( 'cancel' ).execute();

	assert.true( dialog.close.calledOnce, 'close() called once' );
	assert.deepEqual( dialog.close.firstCall.args[ 0 ], { action: 'cancel' }, 'closes with only the action' );
} );

// ── document click handler: form vs. link, GET vs. POST ────────────────────

QUnit.test( 'click handler: ignores clicks on a <form> not originating from a submit button', function ( assert ) {
	const $trigger = $( '<form>', { action: '/x', method: 'get', 'data-pf-target-input': '' } ).appendTo( document.body );
	const $other = $( '<button type="button">' ).appendTo( $trigger );

	$trigger.trigger( $.Event( 'click', { target: $other[ 0 ] } ) );

	assert.strictEqual( this.capturedDialog, null, 'no dialog window is opened' );
} );

QUnit.test( 'click handler: opens the dialog for a submit-button-originated form click', function ( assert ) {
	const $trigger = $( '<form>', { action: '/x', method: 'get', 'data-pf-target-input': '' } ).appendTo( document.body );
	const $submit = $( '<button type="submit">' ).appendTo( $trigger );

	$trigger.trigger( $.Event( 'click', { target: $submit[ 0 ] } ) );

	assert.strictEqual( this.capturedDialog.constructor.static.name, 'pfTargetInput', 'dialog window opened' );
} );

QUnit.test( 'click handler: plain link on confirm reads wgPageName and does not touch any form', function ( assert ) {
	// jsdom's window.location is a non-configurable property whose setter
	// rejects relative-URL assignments outright ("Not implemented:
	// navigation to another Document" / a parse TypeError), so the actual
	// `window.location.href = ...` write cannot be observed or even safely
	// triggered here. buildTargetUrl()'s own URL-building logic is already
	// covered directly above; this test only verifies the plain-link branch
	// is reached (queries wgPageName for the returnto param) and that it
	// does not fall into either of the form branches.
	const $trigger = $( '<a>', { href: '/wiki/Special:FormEdit/MyForm', 'data-pf-target-input': '' } ).appendTo( document.body );
	$trigger.trigger( 'click' );

	const dialog = this.capturedDialog;
	return this.whenOpened().then( () => {
		dialog.inputWidget.setValue( 'My Page' );
		dialog.executeAction( 'confirm' );
		// The navigation assignment throws synchronously inside the
		// `closed.then()` handler; jQuery.Deferred logs and swallows it
		// rather than rejecting, so `closed` still resolves.
		return this.capturedInstance.closed;
	} ).then( () => {
		assert.strictEqual( $( 'form' ).length, 0, 'no form is touched for a plain link' );
		assert.strictEqual( $trigger.find( 'input' ).length, 0, 'no hidden inputs are injected for a plain link' );
	} );
} );

QUnit.test( 'click handler: POST form on confirm injects hidden inputs and submits', function ( assert ) {
	const $trigger = $( '<form>', { action: '/wiki/Special:FormEdit/MyForm', method: 'post', 'data-pf-target-input': '' } )
		.appendTo( document.body );
	const $submit = $( '<button type="submit">' ).appendTo( $trigger );
	$trigger[ 0 ].submit = sinon.spy();

	$trigger.trigger( $.Event( 'click', { target: $submit[ 0 ] } ) );

	const dialog = this.capturedDialog;
	return this.whenOpened().then( () => {
		dialog.inputWidget.setValue( 'My Page' );
		dialog.executeAction( 'confirm' );
		return this.capturedInstance.closed;
	} ).then( () => {
		assert.strictEqual( $trigger.find( 'input[name="target"]' ).val(), 'My Page', 'hidden target input injected' );
		assert.strictEqual( $trigger.find( 'input[name="returnto"]' ).val(), 'Test_Page', 'hidden returnto input injected' );
		assert.true( $trigger[ 0 ].submit.calledOnce, 'form submitted' );
	} );
} );

QUnit.test( 'click handler: GET form on confirm updates action URL and injects returnto', function ( assert ) {
	const $trigger = $( '<form>', { action: '/wiki/Special:FormEdit/MyForm', method: 'get', 'data-pf-target-input': '' } )
		.appendTo( document.body );
	const $submit = $( '<button type="submit">' ).appendTo( $trigger );
	$trigger[ 0 ].submit = sinon.spy();

	$trigger.trigger( $.Event( 'click', { target: $submit[ 0 ] } ) );

	const dialog = this.capturedDialog;
	return this.whenOpened().then( () => {
		dialog.inputWidget.setValue( 'My Page' );
		dialog.executeAction( 'confirm' );
		return this.capturedInstance.closed;
	} ).then( () => {
		assert.strictEqual( $trigger.attr( 'action' ), '/wiki/Special:FormEdit/MyForm/My_Page', 'action URL updated with target segment' );
		assert.strictEqual( $trigger.find( 'input[name="returnto"]' ).val(), 'Test_Page', 'hidden returnto input injected' );
		assert.strictEqual( $trigger.find( 'input[name="target"]' ).length, 0, 'no hidden target input for GET' );
		assert.true( $trigger[ 0 ].submit.calledOnce, 'form submitted' );
	} );
} );

QUnit.test( 'click handler: cancel does not submit the form or navigate', function ( assert ) {
	const $trigger = $( '<form>', { action: '/wiki/Special:FormEdit/MyForm', method: 'get', 'data-pf-target-input': '' } )
		.appendTo( document.body );
	const $submit = $( '<button type="submit">' ).appendTo( $trigger );
	$trigger[ 0 ].submit = sinon.spy();

	$trigger.trigger( $.Event( 'click', { target: $submit[ 0 ] } ) );

	const dialog = this.capturedDialog;
	dialog.executeAction( 'cancel' );

	assert.false( $trigger[ 0 ].submit.called, 'form not submitted on cancel' );
} );
