'use strict';

const sinon = require( 'sinon' );

// Both modules capture OO as `oo` at load time — require once, stub per test.

QUnit.module( 'PF_datepicker / PF_datetimepicker', {
	before() {
		// Load once (require is cached). Stub is set up per test in beforeEach
		// because the global QUnit.hooks.afterEach calls sinon.restore() which
		// would destroy any before()-level stub.
		require( '../../libs/PF_datepicker.js' );
		require( '../../libs/PF_datetimepicker.js' );
	},
	beforeEach() {
		sinon.stub( OO.ui, 'infuse' );
	}
	// global afterEach calls sinon.restore() — no explicit afterEach needed
} );

// ── applyDatePicker ─────────────────────────────────────────────────────────

QUnit.test( 'applyDatePicker is defined on jQuery prototype', ( assert ) => {
	assert.strictEqual( typeof $.fn.applyDatePicker, 'function' );
} );

QUnit.test( 'applyDatePicker calls OO.ui.infuse for a normal element', ( assert ) => {
	const $el = $( '<input>' ).appendTo( document.body );

	$el.applyDatePicker();

	assert.true( OO.ui.infuse.calledOnce, 'OO.ui.infuse called' );
	assert.strictEqual( OO.ui.infuse.firstCall.args[ 0 ], $el[ 0 ], 'passed raw DOM element' );
} );

QUnit.test( 'applyDatePicker skips disabled elements', ( assert ) => {
	const $el = $( '<input>' ).addClass( 'oo-ui-widget-disabled' ).appendTo( document.body );

	$el.applyDatePicker();

	assert.false( OO.ui.infuse.called, 'OO.ui.infuse not called for disabled element' );
} );

QUnit.test( 'applyDatePicker handles mixed disabled/enabled set', ( assert ) => {
	const $enabled = $( '<input>' ).appendTo( document.body );
	const $disabled = $( '<input>' ).addClass( 'oo-ui-widget-disabled' ).appendTo( document.body );

	$enabled.add( $disabled ).applyDatePicker();

	assert.ok( OO.ui.infuse.calledOnce, 'only one infuse call (enabled element)' );
	assert.strictEqual( OO.ui.infuse.firstCall.args[ 0 ], $enabled[ 0 ] );
} );

// ── applyDateTimePicker ──────────────────────────────────────────────────────

QUnit.test( 'applyDateTimePicker is defined on jQuery prototype', ( assert ) => {
	assert.strictEqual( typeof $.fn.applyDateTimePicker, 'function' );
} );

QUnit.test( 'applyDateTimePicker calls OO.ui.infuse', ( assert ) => {
	const $el = $( '<input>' ).appendTo( document.body );

	$el.applyDateTimePicker();

	assert.true( OO.ui.infuse.calledOnce, 'OO.ui.infuse called' );
	assert.strictEqual( OO.ui.infuse.firstCall.args[ 0 ], $el[ 0 ], 'passed raw DOM element' );
} );
