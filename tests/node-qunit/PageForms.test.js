'use strict';

require( '../../libs/PageForms.js' );

// MediaWiki core's default $wgUrlProtocols, as consumed by validateURLField().
const WG_URL_PROTOCOLS = 'bitcoin\\:|ftp\\:\\/\\/|ftps\\:\\/\\/|geo\\:|git\\:\\/\\/|gopher\\:\\/\\/|http\\:\\/\\/|https\\:\\/\\/|' +
	'irc\\:\\/\\/|ircs\\:\\/\\/|magnet\\:|mailto\\:|mms\\:\\/\\/|news\\:|nntp\\:\\/\\/|redis\\:\\/\\/|sftp\\:\\/\\/|sip\\:|sips\\:|' +
	'sms\\:|ssh\\:\\/\\/|svn\\:\\/\\/|tel\\:|telnet\\:\\/\\/|urn\\:|worldwind\\:\\/\\/|xmpp\\:|\\/\\/';

function mwConfigGet( key ) {
	if ( key === 'wgPageFormsScriptPath' ) {
		return 'path';
	}
	if ( key === 'wgUrlProtocols' ) {
		return WG_URL_PROTOCOLS;
	}
	return null;
}

QUnit.module( 'PageForms validation', {
	beforeEach: ( assert ) => {
		mw.msg = ( msg ) => msg;
		mw.message = ( key ) => ( { escaped: () => key } );
		mw.config = { get: mwConfigGet };
	}
} );

QUnit.test( 'validateMandatoryComboBox fails when value is empty', ( assert ) => {
	const $span = createSpan( '<select class="mandatoryField"><option value="">""</option></select>' );

	assert.false( $span.validateMandatoryComboBox() );
	assert.true( $span.find( 'select' )[ 0 ].classList.contains( 'inputError' ) );
} );

QUnit.test( 'validateMandatoryComboBox succeeds when value is set', ( assert ) => {
	const $span = createSpan( '<select class="mandatoryField"><option value="x" selected>x</option></select>' );

	assert.true( $span.validateMandatoryComboBox() );
} );

QUnit.test( 'validateMandatoryRadioButton fails when nothing is checked', ( assert ) => {
	const $span = createSpan( '<input type="radio" name="r" value="a"><input type="radio" name="r" value="b">' );

	assert.false( $span.validateMandatoryRadioButton() );
} );

QUnit.test( 'validateMandatoryRadioButton succeeds when an option is checked', ( assert ) => {
	const $span = createSpan( '<input type="radio" name="r" value="a" checked><input type="radio" name="r" value="b">' );

	assert.true( $span.validateMandatoryRadioButton() );
} );

QUnit.test( 'validateMandatoryCheckboxes fails when nothing is checked', ( assert ) => {
	const $span = createSpan( '<input type="checkbox" value="a"><input type="checkbox" value="b">' );

	assert.false( $span.validateMandatoryCheckboxes() );
} );

QUnit.test( 'validateMandatoryCheckboxes succeeds when at least one is checked', ( assert ) => {
	const $span = createSpan( '<input type="checkbox" value="a" checked><input type="checkbox" value="b">' );

	assert.true( $span.validateMandatoryCheckboxes() );
} );

QUnit.test( 'validateMandatoryGeoCoordinatesMaps fails on blank value', ( assert ) => {
	const $input = $( '<input class="pfCoordsInput" value="  ">' ).appendTo( document.body );
	$( '<span></span>' ).appendTo( document.body ).append( $input );

	assert.false( $input.validateMandatoryGeoCoordinatesMaps() );
} );

QUnit.test( 'validateMandatoryGeoCoordinatesMaps succeeds on non-blank value', ( assert ) => {
	const $input = $( '<input class="pfCoordsInput" value="51.5,-0.1">' ).appendTo( document.body );
	$( '<span></span>' ).appendTo( document.body ).append( $input );

	assert.true( $input.validateMandatoryGeoCoordinatesMaps() );
} );

QUnit.test( 'validateMandatoryTree fails when input value is missing', ( assert ) => {
	const $wrapper = $( '<div><input type="hidden"></div>' ).appendTo( document.body );

	assert.false( $wrapper.validateMandatoryTree() );
} );

QUnit.test( 'validateMandatoryTree succeeds when input has a value', ( assert ) => {
	const $wrapper = $( '<div><input type="hidden" value="Category:Foo"></div>' ).appendTo( document.body );

	assert.true( $wrapper.validateMandatoryTree() );
} );

QUnit.test( 'validateMandatoryDatePicker fails when input value is missing', ( assert ) => {
	const $wrapper = $( '<div><input class="pfPicker"></div>' ).appendTo( document.body );

	assert.false( $wrapper.validateMandatoryDatePicker() );
} );

QUnit.test( 'validateMandatoryDatePicker succeeds when input has a value', ( assert ) => {
	const $wrapper = $( '<div><input class="pfPicker" value="2024-01-01"></div>' ).appendTo( document.body );

	assert.true( $wrapper.validateMandatoryDatePicker() );
} );

QUnit.test( 'validateMandatoryDateTimeField fails when any part is blank', ( assert ) => {
	const $span = createDateTimeInput( '2024', '1', '1', '', '30', '00' );

	assert.false( $span.validateMandatoryDateTimeField() );
} );

QUnit.test( 'validateMandatoryDateTimeField succeeds when all required parts are filled', ( assert ) => {
	const $span = createDateTimeInput( '2024', '1', '1', '10', '30', '' );

	assert.true( $span.validateMandatoryDateTimeField() );
} );

QUnit.test( 'validateURLField succeeds on empty value', ( assert ) => {
	const $span = createSpan( '<input value="">' );

	assert.true( $span.validateURLField() );
} );

QUnit.test( 'validateURLField succeeds on a well-formed URL', ( assert ) => {
	const $span = createSpan( '<input value="https://example.com/path">' );

	assert.true( $span.validateURLField() );
} );

QUnit.test( 'validateURLField fails on a malformed value', ( assert ) => {
	const $span = createSpan( '<input value="not a url">' );

	assert.false( $span.validateURLField() );
	assert.equal( $span.find( '.errorMessage' ).text(), 'pf_bad_url_error' );
} );

QUnit.test( 'validateEmailField succeeds on empty value', ( assert ) => {
	const $span = createSpan( '<input value="">' );

	assert.true( $span.validateEmailField() );
} );

QUnit.test( 'validateEmailField succeeds on a well-formed address', ( assert ) => {
	const $span = createSpan( '<input value="user@example.com">' );

	assert.true( $span.validateEmailField() );
} );

QUnit.test( 'validateEmailField fails on a malformed address', ( assert ) => {
	const $span = createSpan( '<input value="not-an-email">' );

	assert.false( $span.validateEmailField() );
	assert.equal( $span.find( '.errorMessage' ).text(), 'pf_bad_email_error' );
} );

QUnit.test( 'validateNumberField succeeds on empty value', ( assert ) => {
	const $span = createSpan( '<input value="">' );

	assert.true( $span.validateNumberField() );
} );

QUnit.test( 'validateNumberField succeeds on a decimal number', ( assert ) => {
	const $span = createSpan( '<input value="3.14">' );

	assert.true( $span.validateNumberField() );
} );

QUnit.test( 'validateNumberField succeeds on scientific notation', ( assert ) => {
	const $span = createSpan( '<input value="1.2e-3">' );

	assert.true( $span.validateNumberField() );
} );

QUnit.test( 'validateNumberField fails on non-numeric text', ( assert ) => {
	const $span = createSpan( '<input value="abc">' );

	assert.false( $span.validateNumberField() );
	assert.equal( $span.find( '.errorMessage' ).text(), 'pf_bad_number_error' );
} );

QUnit.test( 'validateIntegerField succeeds on empty value', ( assert ) => {
	const $span = createSpan( '<input value="">' );

	assert.true( $span.validateIntegerField() );
} );

QUnit.test( 'validateIntegerField succeeds on an integer', ( assert ) => {
	const $span = createSpan( '<input value="42">' );

	assert.true( $span.validateIntegerField() );
} );

QUnit.test( 'validateIntegerField fails on a decimal value', ( assert ) => {
	const $span = createSpan( '<input value="3.14">' );

	assert.false( $span.validateIntegerField() );
	assert.equal( $span.find( '.errorMessage' ).text(), 'pf_bad_integer_error' );
} );

QUnit.test( 'validateNumInstances fails when below minimum', ( assert ) => {
	const $list = $( '<div minimumInstances="2" maximumInstances="5"></div>' ).appendTo( document.body );
	$list.append( '<div class="multipleTemplateInstance"></div>' );

	assert.false( $list.validateNumInstances() );
} );

QUnit.test( 'validateNumInstances fails when above maximum', ( assert ) => {
	const $list = $( '<div minimumInstances="0" maximumInstances="1"></div>' ).appendTo( document.body );
	$list.append( '<div class="multipleTemplateInstance"></div>' );
	$list.append( '<div class="multipleTemplateInstance"></div>' );

	assert.false( $list.validateNumInstances() );
} );

QUnit.test( 'validateNumInstances succeeds within range', ( assert ) => {
	const $list = $( '<div minimumInstances="1" maximumInstances="2"></div>' ).appendTo( document.body );
	$list.append( '<div class="multipleTemplateInstance"></div>' );

	assert.true( $list.validateNumInstances() );
} );

QUnit.test( 'checkForPipes allows a pipe inside a link', ( assert ) => {
	const $span = createSpan( '<input value="[[Page|Label]]">' );

	assert.true( $span.checkForPipes() );
} );

QUnit.test( 'checkForPipes rejects a standalone pipe outside brackets', ( assert ) => {
	const $span = createSpan( '<input value="a|b">' );

	assert.false( $span.checkForPipes() );
	assert.equal( $span.find( '.errorMessage' ).text(), 'pf_pipe_error' );
} );

QUnit.test( 'checkForPipes allows a pipe inside a <pre> tag', ( assert ) => {
	const $span = createSpan( '<textarea><pre>a|b</pre></textarea>' );

	assert.true( $span.checkForPipes() );
} );

QUnit.test( 'checkForPipes succeeds on a value with no pipe at all', ( assert ) => {
	const $span = createSpan( '<input value="no pipes here">' );

	assert.true( $span.checkForPipes() );
} );

QUnit.module( 'window.validateAll extra branches', {
	beforeEach: ( assert ) => {
		mw.msg = ( msg ) => msg;
		mw.config = { get: mwConfigGet };
		mw.message = ( key ) => ( { escaped: () => key } );
	}
} );

QUnit.test( 'flags a mandatory combobox with no selection as an error', ( assert ) => {
	$( `
		<span class="comboboxSpan mandatoryFieldSpan">
			<select class="mandatoryField"><option value="">""</option></select>
		</span>
	` ).appendTo( document.body );

	assert.false( window.validateAll() );
	assert.equal( $( '.errorMessage' ).length, 1 );
} );

QUnit.test( 'passes when a mandatory combobox has a selection', ( assert ) => {
	$( `
		<span class="comboboxSpan mandatoryFieldSpan">
			<select class="mandatoryField"><option value="x" selected>x</option></select>
		</span>
	` ).appendTo( document.body );

	assert.true( window.validateAll() );
} );

QUnit.test( 'flags too few multiple-template instances as an error', ( assert ) => {
	$( '<div class="multipleTemplateList" minimumInstances="2" maximumInstances="5"></div>' )
		.appendTo( document.body )
		.append( '<div class="multipleTemplateInstance"></div>' );

	assert.false( window.validateAll() );
} );

QUnit.test( 'flags a mandatory radio-button group with nothing checked as an error', ( assert ) => {
	$( `
		<span class="radioButtonSpan mandatoryFieldSpan">
			<input type="radio" name="r" value="a">
			<input type="radio" name="r" value="b">
		</span>
	` ).appendTo( document.body );

	assert.false( window.validateAll() );
} );

QUnit.test( 'flags a mandatory checkboxes group with nothing checked as an error', ( assert ) => {
	$( `
		<span class="checkboxesSpan mandatoryFieldSpan">
			<input type="checkbox" value="a">
		</span>
	` ).appendTo( document.body );

	assert.false( window.validateAll() );
} );

QUnit.test( 'flags a mandatory tree input with no value as an error', ( assert ) => {
	$( '<div class="pfTreeInputWrapper mandatory"><input type="hidden"></div>' ).appendTo( document.body );

	assert.false( window.validateAll() );
} );

QUnit.test( 'flags a mandatory date picker with no value as an error', ( assert ) => {
	$( '<div class="pfPickerWrapper mandatory"><div class="pfPicker"><input></div></div>' ).appendTo( document.body );

	assert.false( window.validateAll() );
} );

QUnit.test( 'flags a mandatory geo-coordinates field with a blank value as an error', ( assert ) => {
	$( '<span><input class="pfCoordsInput mandatoryFieldSpan" value="  "></span>' ).appendTo( document.body );

	assert.false( window.validateAll() );
} );

QUnit.test( 'flags a malformed URL field as an error', ( assert ) => {
	$( '<span class="URLInput"><input value="not a url"></span>' ).appendTo( document.body );

	assert.false( window.validateAll() );
} );

QUnit.test( 'flags a malformed email field as an error', ( assert ) => {
	$( '<span class="emailInput"><input value="not-an-email"></span>' ).appendTo( document.body );

	assert.false( window.validateAll() );
} );

QUnit.test( 'flags a malformed number field as an error', ( assert ) => {
	$( '<span class="numberInput"><input value="abc"></span>' ).appendTo( document.body );

	assert.false( window.validateAll() );
} );

QUnit.test( 'flags a malformed integer field as an error', ( assert ) => {
	$( '<span class="integerInput"><input value="3.14"></span>' ).appendTo( document.body );

	assert.false( window.validateAll() );
} );

QUnit.test( 'flags a modified input as an error', ( assert ) => {
	$( '<span><input class="modifiedInput"></span>' ).appendTo( document.body );

	assert.false( window.validateAll() );
	assert.equal( $( '.errorMessage' ).text(), 'pf_modified_input_error' );
} );

QUnit.test( 'flags a standalone pipe outside a link as an error', ( assert ) => {
	$( '<span class="inputSpan"><input value="a|b"></span>' ).appendTo( document.body );

	assert.false( window.validateAll() );
} );

QUnit.test( 'flags an end date before the start date as an error', ( assert ) => {
	$( '<span class="startDateInput">' + dateInputsHtml( '2024', '2', '1' ) + '</span>' ).appendTo( document.body );
	$( '<span class="endDateInput">' + dateInputsHtml( '2024', '1', '1' ) + '</span>' ).appendTo( document.body );

	assert.false( window.validateAll() );
	assert.equal( $( '.errorMessage' ).text(), 'pf_start_end_date_error' );
} );

QUnit.test( 'passes when the end date is after the start date', ( assert ) => {
	$( '<span class="startDateInput">' + dateInputsHtml( '2024', '1', '1' ) + '</span>' ).appendTo( document.body );
	$( '<span class="endDateInput">' + dateInputsHtml( '2024', '2', '1' ) + '</span>' ).appendTo( document.body );

	assert.true( window.validateAll() );
} );

QUnit.test( 'flags an end datetime before the start datetime as an error', ( assert ) => {
	$( '<span class="startDateTimeInput">' + dateTimeInputsHtml( '2024', '1', '1', '10', '00', '00', 'PM' ) + '</span>' )
		.appendTo( document.body );
	$( '<span class="endDateTimeInput">' + dateTimeInputsHtml( '2024', '1', '1', '9', '00', '00', 'AM' ) + '</span>' )
		.appendTo( document.body );

	assert.false( window.validateAll() );
	assert.equal( $( '.errorMessage' ).text(), 'pf_start_end_datetime_error' );
} );

QUnit.test( 'passes when the end datetime is after the start datetime', ( assert ) => {
	$( '<span class="startDateTimeInput">' + dateTimeInputsHtml( '2024', '1', '1', '9', '00', '00', 'AM' ) + '</span>' )
		.appendTo( document.body );
	$( '<span class="endDateTimeInput">' + dateTimeInputsHtml( '2024', '1', '1', '10', '00', '00', 'PM' ) + '</span>' )
		.appendTo( document.body );

	assert.true( window.validateAll() );
} );

QUnit.test( 'fires the pf.formValidationBefore and pf.formValidationAfter hooks', ( assert ) => {
	let beforeFired = false;
	let afterFired = false;
	mw.hook( 'pf.formValidationBefore' ).add( () => {
		beforeFired = true;
	} );
	mw.hook( 'pf.formValidationAfter' ).add( () => {
		afterFired = true;
	} );

	window.validateAll();

	assert.true( beforeFired );
	assert.true( afterFired );
} );

QUnit.test( 'counts extra errors added via the pf.formValidation hook', ( assert ) => {
	mw.hook( 'pf.formValidation' ).add( ( args ) => {
		args.numErrors += 1;
	} );

	assert.false( window.validateAll() );
} );

/**
 * Wraps the given inner HTML in a span so jQuery plugin methods
 * bound to `this` (an inputSpan) have a `.errorMessage` container
 * and `input`/`textarea`/`select` descendants to operate on.
 *
 * @param {string} innerHtml
 * @return {jQuery}
 */
function createSpan( innerHtml ) {
	return $( '<span>' + innerHtml + '</span>' ).appendTo( document.body );
}

/**
 * @param {string} year
 * @param {string} month
 * @param {string} day
 * @param {string} hours
 * @param {string} minutes
 * @param {string} seconds
 * @return {jQuery}
 */
function createDateTimeInput( year, month, day, hours, minutes, seconds ) {
	return createSpan( `
		<input class="yearInput" value="${ year }" />
		<input class="monthInput" value="${ month }" />
		<input class="dayInput" value="${ day }" />
		<input class="hoursInput" value="${ hours }" />
		<input class="minutesInput" value="${ minutes }" />
		<input class="secondsInput" value="${ seconds }" />
	` );
}

/**
 * @param {string} year
 * @param {string} month
 * @param {string} day
 * @return {string}
 */
function dateInputsHtml( year, month, day ) {
	return `
		<input class="yearInput" value="${ year }" />
		<input class="monthInput" value="${ month }" />
		<input class="dayInput" value="${ day }" />
	`;
}

/**
 * @param {string} year
 * @param {string} month
 * @param {string} day
 * @param {string} hours
 * @param {string} minutes
 * @param {string} seconds
 * @param {string} ampm
 * @return {string}
 */
function dateTimeInputsHtml( year, month, day, hours, minutes, seconds, ampm ) {
	return `
		<input class="yearInput" value="${ year }" />
		<input class="monthInput" value="${ month }" />
		<input class="dayInput" value="${ day }" />
		<input class="hoursInput" value="${ hours }" />
		<input class="minutesInput" value="${ minutes }" />
		<input class="secondsInput" value="${ seconds }" />
		<input class="ampmInput" value="${ ampm }" />
	`;
}
