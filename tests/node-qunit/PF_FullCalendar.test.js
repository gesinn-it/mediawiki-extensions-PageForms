'use strict';
/* eslint-disable no-jquery/no-sizzle */

const sinon = require( 'sinon' );
const fullCalendarMock = require( './fullCalendarMock.js' );

global.pf = global.pf || {};
// select.tokens() is instantiated unconditionally on every ':input' in the
// popup form (only .apply()-ed for elements with the "pfTokens" class); the
// fixtures below never add that class, so a bare constructor is enough.
global.pf.select2 = {
	tokens: function () {
		this.apply = function () {};
	}
};

// No-op doubles for optional jQuery plugins PF_FullCalendar.js calls on
// popup-form inputs. None of these are defined by PF_FullCalendar.js itself,
// and the fixtures below never mark an input with the classes that would
// make production code invoke them with interesting arguments - only their
// presence (so calling them doesn't throw) matters here.
global.$.fn.applyRatingInput = function () {
	return this;
};
global.$.fn.applyJSTree = function () {
	return this;
};
global.$.fn.applyFancytree = function () {
	return this;
};
global.$.fn.attachAutocomplete = function () {
	return this;
};

const MODULE_PATH = require.resolve( '../../libs/PF_FullCalendar.js' );

function freshRequire() {
	delete require.cache[ MODULE_PATH ];
	require( MODULE_PATH );
}

/**
 * Build the '.pfFullCalendarJS' fixture div PF_FullCalendar.js scans for via
 * `$( '.pfFullCalendarJS' ).each(...)`, plus the wg* config it reads for the
 * given template, and a '#fullCalendarLoading' node it flips visibility on.
 *
 * @param {Object} opts
 * @param {string} [opts.templateName]
 * @param {string} [opts.calendarId]
 * @param {boolean} [opts.oneDayEvent] true - single "event-date-field"; false - start/end fields.
 * @param {Array} [opts.calendarParams] field definitions (name/type) for the template.
 * @param {Array} [opts.calendarValues] event rows for the template.
 * @param {string} [opts.formHtml] the popup form fragment for the template.
 * @return {Object} { $fcDiv, calendarId, templateName }
 */
function createCalendar( opts ) {
	opts = opts || {};
	const templateName = opts.templateName || 'MyTemplate';
	const calendarId = opts.calendarId || 'MyTemplateFullCalendar';
	const oneDayEvent = opts.oneDayEvent !== false;
	const calendarParams = opts.calendarParams || [
		{ name: 'Title', type: 'text' },
		{ name: 'EventDate', type: 'date' }
	];
	const calendarValues = opts.calendarValues || [];
	const formHtml = opts.formHtml !== undefined ? opts.formHtml :
		'<input name="' + templateName + '[cf][Title]">' +
		'<input name="' + templateName + '[cf][EventDate][day]">' +
		'<input name="' + templateName + '[cf][EventDate][month]">' +
		'<input name="' + templateName + '[cf][EventDate][year]">';

	const config = {
		monthMessages: [ 'January', 'February', 'March', 'April', 'May', 'June',
			'July', 'August', 'September', 'October', 'November', 'December' ],
		wgPageFormsCalendarParams: { [ templateName ]: calendarParams },
		wgPageFormsCalendarValues: { [ templateName ]: calendarValues },
		wgPageFormsCalendarHTML: { [ templateName ]: formHtml },
		wgAmericanDates: false
	};
	global.mw.config = { get: ( key ) => config[ key ] };
	global.mw.msg = ( key ) => key;

	$( '<div>' ).attr( 'id', 'fullCalendarLoading' ).appendTo( document.body );
	const $fcDiv = $( '<div>' )
		.addClass( 'pfFullCalendarJS' )
		.attr( 'id', calendarId )
		.attr( 'template-name', templateName )
		.attr( 'title-field', 'Title' )
		.appendTo( document.body );

	if ( oneDayEvent ) {
		$fcDiv.attr( 'event-date-field', 'EventDate' );
	} else {
		$fcDiv.attr( 'event-start-date-field', 'StartDate' );
		$fcDiv.attr( 'event-end-date-field', 'EndDate' );
	}

	return { $fcDiv, calendarId, templateName };
}

QUnit.module( 'PF_FullCalendar', {
	beforeEach: function () {
		this.teardownFullCalendar = fullCalendarMock.install();
		// Fancybox normally injects the popup markup into a DOM overlay;
		// PF_FullCalendar.js immediately queries that markup's inputs by
		// name (e.g. to pre-fill dates), so the stub has to actually insert
		// it into document.body instead of just recording the call.
		global.$.fancybox = {
			open: sinon.fake( ( markup ) => {
				$( '<div id="fancyboxMock">' ).html( markup ).appendTo( document.body );
			} )
		};
	},
	afterEach: function () {
		this.teardownFullCalendar();
		delete global.$.fancybox;
		delete require.cache[ MODULE_PATH ];
	}
} );

// --- require()-time safety (the load-time crash the original stub guards) ---

QUnit.test( 'throws ReferenceError when pf global is missing', ( assert ) => {
	const saved = global.pf;
	delete global.pf;
	createCalendar();
	assert.throws(
		() => freshRequire(),
		ReferenceError,
		'loading without pf global throws ReferenceError'
	);
	global.pf = saved;
} );

QUnit.test( 'loads without error when no .pfFullCalendarJS element is present', ( assert ) => {
	assert.expect( 0 );
	freshRequire();
} );

// --- init: populating the calendar from wgPageFormsCalendarValues ---

QUnit.test( 'renders one event per calendar-values row with a date-type field', ( assert ) => {
	const { $fcDiv, calendarId } = createCalendar( {
		calendarValues: [
			{ Title: 'Meeting', EventDate: '2026/07/15' }
		]
	} );

	freshRequire();

	const instance = $fcDiv.data( 'fullCalendarMockInstance' );
	const events = instance.allEvents();
	assert.equal( events.length, 1, 'one event rendered' );
	assert.equal( events[ 0 ].title, 'Meeting' );
	assert.equal( events[ 0 ].start, '2026-07-15' );
} );

QUnit.test( 'skips rows whose date field does not match the expected format', ( assert ) => {
	const { $fcDiv } = createCalendar( {
		calendarValues: [
			{ Title: 'Bad row', EventDate: 'not-a-date' },
			{ Title: 'Good row', EventDate: '2026/07/15' }
		]
	} );

	freshRequire();

	const instance = $fcDiv.data( 'fullCalendarMockInstance' );
	const events = instance.allEvents();
	assert.equal( events.length, 1, 'only the row with a valid date is rendered' );
	assert.equal( events[ 0 ].title, 'Good row' );
} );

QUnit.test( 'renders a start/end event pair for multi-day (event-start/end-date-field) templates', ( assert ) => {
	const { $fcDiv } = createCalendar( {
		oneDayEvent: false,
		calendarParams: [
			{ name: 'Title', type: 'text' },
			{ name: 'StartDate', type: 'date' },
			{ name: 'EndDate', type: 'date' }
		],
		calendarValues: [
			{ Title: 'Conference', StartDate: '2026/07/15', EndDate: '2026/07/17' }
		]
	} );

	freshRequire();

	const instance = $fcDiv.data( 'fullCalendarMockInstance' );
	const events = instance.allEvents();
	assert.equal( events.length, 1 );
	assert.equal( events[ 0 ].start, '2026-07-15' );
	// End dates for date-type (not datetime) fields are advanced by one day
	// so FullCalendar's exclusive-end range covers the last calendar day.
	assert.equal( events[ 0 ].end, '2026-07-18' );
} );

// --- select: creating a new event via the day-click popup ---

QUnit.test( 'day-click (select) opens the create-event popup', ( assert ) => {
	const { $fcDiv } = createCalendar();
	freshRequire();

	const instance = $fcDiv.data( 'fullCalendarMockInstance' );
	const start = fullCalendarMock.fakeMoment( { DD: '20', MM: '07', YYYY: '2026' } );
	const end = fullCalendarMock.fakeMoment( { DD: '21', MM: '07', YYYY: '2026' } );

	instance.options().select( start, end );

	assert.true( global.$.fancybox.open.calledOnce, 'fancybox.open() called to show the popup' );
	assert.ok(
		global.$.fancybox.open.firstCall.args[ 0 ].includes( 'form_submit' ),
		'popup markup includes the create-event submit button'
	);
} );

QUnit.test( 'day-click pre-fills the date fields of the popup form from the selected day', ( assert ) => {
	const { $fcDiv } = createCalendar();
	freshRequire();

	const instance = $fcDiv.data( 'fullCalendarMockInstance' );
	const start = fullCalendarMock.fakeMoment( { DD: '20', MM: '07', YYYY: '2026' } );
	const end = fullCalendarMock.fakeMoment( { DD: '21', MM: '07', YYYY: '2026' } );

	instance.options().select( start, end );

	assert.equal( $( ':input[name="MyTemplate[cf][EventDate][day]"]' ).val(), '20' );
	assert.equal( $( ':input[name="MyTemplate[cf][EventDate][year]"]' ).val(), '2026' );
	assert.equal( $( ':input[name="MyTemplate[cf][EventDate][month]"]' ).val(), '07' );
} );

QUnit.test( 'submitting the create-event popup renders a new event on the calendar', ( assert ) => {
	const { $fcDiv } = createCalendar();
	freshRequire();

	const instance = $fcDiv.data( 'fullCalendarMockInstance' );
	const start = fullCalendarMock.fakeMoment( { DD: '20', MM: '07', YYYY: '2026' } );
	const end = fullCalendarMock.fakeMoment( { DD: '21', MM: '07', YYYY: '2026' } );
	instance.options().select( start, end );

	$( ':input[name="MyTemplate[cf][Title]"]' ).val( 'New Event' );
	$( '#form_submit' ).trigger( 'click' );

	const events = instance.allEvents();
	assert.equal( events.length, 1, 'a new event was rendered via renderEvent' );
	assert.equal( events[ 0 ].title, 'New Event' );
	assert.equal( events[ 0 ].start, '2026-07-20' );
} );

// --- eventClick: editing and deleting an existing event ---

function renderExistingEvent( instance, overrides ) {
	instance.command( 'renderEvent', Object.assign( {
		title: 'Existing Event',
		start: '2026-07-15',
		end: '2026-07-15T23:59:59',
		id: 'evt1',
		contents: [
			{ name: 'MyTemplate[cf][Title]', value: 'Existing Event' },
			{ name: 'MyTemplate[cf][EventDate][day]', value: '15' },
			{ name: 'MyTemplate[cf][EventDate][month]', value: '07' },
			{ name: 'MyTemplate[cf][EventDate][year]', value: '2026' }
		]
	}, overrides ) );
}

QUnit.test( 'eventClick opens the edit/delete popup and pre-fills its fields', ( assert ) => {
	const { $fcDiv } = createCalendar();
	freshRequire();

	const instance = $fcDiv.data( 'fullCalendarMockInstance' );
	renderExistingEvent( instance );

	instance.options().eventClick( { id: 'evt1' } );

	assert.true( global.$.fancybox.open.calledOnce );
	const popupMarkup = global.$.fancybox.open.firstCall.args[ 0 ];
	assert.ok( popupMarkup.includes( 'event_delete' ), 'popup markup includes the delete-event button' );
	assert.equal( $( ':input[name="MyTemplate[cf][Title]"]' ).val(), 'Existing Event' );
	assert.equal( $( ':input[name="MyTemplate[cf][EventDate][day]"]' ).val(), '15' );
} );

QUnit.test( 'clicking the delete button in the edit popup removes the event from the calendar', ( assert ) => {
	const { $fcDiv } = createCalendar();
	freshRequire();

	const instance = $fcDiv.data( 'fullCalendarMockInstance' );
	renderExistingEvent( instance );
	instance.options().eventClick( { id: 'evt1' } );

	$( '#event_delete' ).trigger( 'click' );

	assert.equal( instance.allEvents().length, 0, 'the event was removed via removeEvents' );
} );

QUnit.test( 'submitting the edit popup updates the event title and date', ( assert ) => {
	const { $fcDiv } = createCalendar();
	freshRequire();

	const instance = $fcDiv.data( 'fullCalendarMockInstance' );
	renderExistingEvent( instance );
	const info = { id: 'evt1' };
	instance.options().eventClick( info );

	$( ':input[name="MyTemplate[cf][Title]"]' ).val( 'Renamed Event' );
	$( ':input[name="MyTemplate[cf][EventDate][day]"]' ).val( '16' );
	$( '#form_submit' ).trigger( 'click' );

	const events = instance.allEvents();
	assert.equal( events.length, 1, 'updateEvent replaces the existing event rather than adding a new one' );
	assert.equal( events[ 0 ].title, 'Renamed Event' );
	assert.equal( events[ 0 ].start, '2026-07-16' );
} );

// --- #pfForm submit: serialising calendar events back into the wiki form ---

QUnit.test( '#pfForm submit adds one hidden input per event field, keyed by row number', ( assert ) => {
	const { $fcDiv } = createCalendar( {
		calendarValues: [
			{ Title: 'Meeting', EventDate: '2026/07/15' }
		]
	} );
	const $form = $( '<form>' ).attr( 'id', 'pfForm' ).appendTo( document.body );
	$fcDiv.appendTo( $form );

	freshRequire();

	$form.trigger( 'submit' );

	const $titleInput = $form.find( 'input[name="MyTemplate[1][Title]"]' );
	assert.equal( $titleInput.length, 1, 'a hidden input is created for the Title field of row 1' );
	assert.equal( $titleInput.val(), 'Meeting' );

	const $dateInput = $form.find( 'input[name="MyTemplate[1][EventDate]"]' );
	assert.equal( $dateInput.val(), '2026/07/15', 'the date field is reassembled from day/month/year' );
} );
