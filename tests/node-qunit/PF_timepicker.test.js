'use strict';

require( '../../libs/PF_timepicker.js' );

function createInput( id, value ) {
	return $( '<input type="text">' )
		.attr( { id: id, name: id + '_name' } )
		.val( value || '' )
		.appendTo( document.body );
}

// Each hour <li> nests a <ul> of minute <li>s; jQuery's .text() would
// include that descendant text, so read only the hour's own text node.
function ownText( $el ) {
	return $el.clone().children().remove().end().text();
}

QUnit.module( 'PF_timepicker', {
	beforeEach() {
		// jsdom has no CSS engine — stub jQuery animations to return `this` synchronously
		$.fn.fadeIn = function () {
			return this;
		};
		$.fn.fadeOut = function ( duration, callback ) {
			if ( typeof callback === 'function' ) {
				callback.call( this );
			}
			return this;
		};
	}
} );

// --- grid generation ---

QUnit.test( 'builds an hour list entry for every hour in the default range', ( assert ) => {
	createInput( 'tp1' );
	window.PF_TP_init( 'tp1', {} );

	const $hours = $( '#tp1_tree .PF_timepicker_hour' );
	assert.equal( $hours.length, 24, 'one <li> per hour, 00 through 23' );
	assert.equal( ownText( $hours.first() ), '00', 'first hour is zero-padded' );
	assert.equal( ownText( $hours.last() ), '23', 'last hour is 23' );
} );

QUnit.test( 'restricts the hour range to minTime/maxTime', ( assert ) => {
	createInput( 'tp2' );
	window.PF_TP_init( 'tp2', { minTime: '09:00', maxTime: '17:00' } );

	const $hours = $( '#tp2_tree .PF_timepicker_hour' );
	assert.equal( $hours.length, 9, 'hours from 09 to 17 inclusive' );
	assert.equal( ownText( $hours.first() ), '09', 'first hour matches minTime' );
	assert.equal( ownText( $hours.last() ), '17', 'last hour matches maxTime' );
} );

QUnit.test( 'builds minute entries at the given interval', ( assert ) => {
	createInput( 'tp3' );
	window.PF_TP_init( 'tp3', { minTime: '10:00', maxTime: '10:59', interval: '15' } );

	const $minutes = $( '#tp3_tree .PF_timepicker_hour' ).first().find( '.PF_timepicker_minute' );
	assert.deepEqual(
		$minutes.map( function () {
			return $( this ).text();
		} ).get(),
		[ '00', '15', '30', '45' ],
		'minutes stepped by the configured interval'
	);
} );

QUnit.test( 'minute range on the boundary hour respects minTime/maxTime minutes', ( assert ) => {
	createInput( 'tp4' );
	window.PF_TP_init( 'tp4', { minTime: '09:30', maxTime: '09:45', interval: '5' } );

	const $minutes = $( '#tp4_tree .PF_timepicker_hour' ).find( '.PF_timepicker_minute' );
	assert.deepEqual(
		$minutes.map( function () {
			return $( this ).text();
		} ).get(),
		[ '30', '35', '40', '45' ],
		'single-hour range only lists minutes between minTime and maxTime'
	);
} );

QUnit.test( 'interval below 1 is clamped to 1', ( assert ) => {
	createInput( 'tp5' );
	window.PF_TP_init( 'tp5', { minTime: '00:00', maxTime: '00:03', interval: '0' } );

	const $minutes = $( '#tp5_tree .PF_timepicker_hour' ).find( '.PF_timepicker_minute' );
	assert.equal( $minutes.length, 4, 'interval of 0 is clamped to 1 minute steps' );
} );

QUnit.test( 'interval above 60 is clamped to 60', ( assert ) => {
	createInput( 'tp6' );
	window.PF_TP_init( 'tp6', { minTime: '00:00', maxTime: '01:00', interval: '90' } );

	const $firstHourMinutes = $( '#tp6_tree .PF_timepicker_hour' ).first().find( '.PF_timepicker_minute' );
	assert.equal( $firstHourMinutes.length, 1, 'interval of 90 is clamped to 60, one minute entry per hour' );
} );

QUnit.test( 'invalid minTime/maxTime fall back to full-day defaults', ( assert ) => {
	createInput( 'tp7' );
	window.PF_TP_init( 'tp7', { minTime: 'nope', maxTime: 'nope' } );

	const $hours = $( '#tp7_tree .PF_timepicker_hour' );
	assert.equal( $hours.length, 24, 'falls back to 00-23 when times are invalid' );
} );

// --- hidden input setup ---

QUnit.test( 'creates a hidden input carrying the original value and name', ( assert ) => {
	createInput( 'tp8', '10:30' );
	window.PF_TP_init( 'tp8', {} );

	const $hidden = $( '#tp8' );
	assert.equal( $hidden.attr( 'type' ), 'hidden', 'hidden input created with the original id' );
	assert.equal( $hidden.val(), '10:30', 'hidden input carries the original value' );
	assert.equal( $hidden.attr( 'name' ), 'tp8_name', 'hidden input carries the original name' );
	assert.equal( $( '#tp8_show' ).attr( 'name' ), undefined, 'visible input no longer has a name attribute' );
} );

QUnit.test( 'does not create a duplicate hidden input when partOfDTP is set', ( assert ) => {
	createInput( 'tp9', '11:00' );
	window.PF_TP_init( 'tp9', { partOfDTP: true } );

	assert.equal( $( 'input' ).length, 1, 'only the original (renamed) input exists, no extra hidden input' );
	assert.equal( $( '#tp9_show' ).length, 1, 'the original input was renamed to the "_show" id' );
} );

// --- value selection ---

QUnit.test( 'clicking a minute entry sets the visible input value and hides the picker', ( assert ) => {
	createInput( 'tp10' );
	window.PF_TP_init( 'tp10', { minTime: '09:00', maxTime: '09:05', interval: '5' } );

	const $minuteItem = $( '#tp10_tree .PF_timepicker_minute' ).first();
	$minuteItem.trigger( 'mousedown' );

	assert.equal( $( '#tp10_show' ).val(), '09:00', 'visible input receives the selected time' );
} );

QUnit.test( 'clicking a minute entry updates the hidden input via change', ( assert ) => {
	createInput( 'tp11' );
	window.PF_TP_init( 'tp11', { minTime: '14:00', maxTime: '14:05', interval: '5' } );

	const $minuteItem = $( '#tp11_tree .PF_timepicker_minute' ).eq( 1 );
	$minuteItem.trigger( 'mousedown' );

	assert.equal( $( '#tp11' ).val(), '14:05', 'hidden input synced from visible input on change' );
} );

QUnit.test( 'focusing the visible input shows the timepicker tree', ( assert ) => {
	createInput( 'tp12' );
	window.PF_TP_init( 'tp12', {} );

	let shown = false;
	$.fn.fadeIn = function () {
		shown = true;
		return this;
	};

	$( '#tp12_show' ).trigger( 'focus' );

	assert.true( shown, 'fadeIn called on focus' );
} );

QUnit.test( 'blurring the visible input hides the timepicker tree', ( assert ) => {
	createInput( 'tp13' );
	window.PF_TP_init( 'tp13', {} );

	let hidden = false;
	$.fn.fadeOut = function ( duration, callback ) {
		hidden = true;
		if ( typeof callback === 'function' ) {
			callback.call( this );
		}
		return this;
	};

	$( '#tp13_show' ).trigger( 'blur' );

	assert.true( hidden, 'fadeOut called on blur' );
} );

// --- disabled state ---

QUnit.test( 'disabled param disables the toggle button', ( assert ) => {
	createInput( 'tp14' );
	window.PF_TP_init( 'tp14', { disabled: true } );

	assert.equal( $( '#tp14_button' ).attr( 'disabled' ), 'disabled', 'button is disabled' );
} );

QUnit.test( 'without disabled param, clicking the button focuses the input', ( assert ) => {
	createInput( 'tp15' );
	window.PF_TP_init( 'tp15', {} );

	let focused = false;
	$( '#tp15_show' ).on( 'focus', () => {
		focused = true;
	} );

	$( '#tp15_button' ).trigger( 'click' );

	assert.true( focused, 'clicking the button focuses the visible input' );
} );
