require('../../libs/PageForms.js');

QUnit.module('window.validateAll', {
	beforeEach: (assert) => {
		mw.msg = (msg, val) => {
			if ( msg === 'pf_blank_error' ) {
				return 'the error message';
			}
			if ( msg === 'pf_not_unique_error' ) {
				return 'muss eindeutig sein';
			}
			return null;
		};
		mw.config = { get: (key) => key === 'wgPageFormsScriptPath' ? 'path' : null }
		mw.message = (key) => key === 'pf_formerrors_header' ? { escaped: () => 'header' } : null;
	}
});

QUnit.test('fails on missing year in mandatory date input field', (assert) => {
	createDateInput('', '', '');

	window.validateAll();

	assert.true($('.yearInput').hasClass('inputError'));
	assert.equal($('.dateInput div.errorMessage').text(), 'the error message');
});

QUnit.test('fails on missing month in mandatory date input field when day is present', (assert) => {
	createDateInput('2000', '', '1');

	window.validateAll();

	assert.true($('.monthInput').hasClass('inputError'));
	assert.equal($('.dateInput div.errorMessage').text(), 'the error message');
});

QUnit.test('succeeds on year present, month, day missing', (assert) => {
	createDateInput('2000', '', '');

	window.validateAll();

	assert.equal($('.inputError').length, 0);
	assert.equal($('.dateInput div.errorMessage').length, 0);
});

QUnit.test('succeeds on year, month present, day missing', (assert) => {
	createDateInput('2000', '1', '');

	window.validateAll();

	assert.equal($('.inputError').length, 0);
	assert.equal($('.dateInput div.errorMessage').length, 0);
});

QUnit.test('mandatory unique textarea shows "muss eindeutig sein" when value is already used', (assert) => {
	// Simulate an SMW API response that says the value is not unique (count > 0)
	const origAjax = $.ajax;
	$.ajax = function( opts ) {
		opts.success( { query: { meta: { count: 1 } } } );
	};

	createMandatoryUniqueTextarea( 'some existing value', '' );

	window.validateAll();

	$.ajax = origAjax;

	assert.equal(
		$('.inputSpan div.errorMessage').text(),
		'muss eindeutig sein',
		'unique error message is shown for mandatory textarea with non-unique value'
	);
} );

QUnit.test('mandatory unique textarea shows no error when value is unique', (assert) => {
	// Simulate an SMW API response that says the value is unique (count === 0)
	const origAjax = $.ajax;
	$.ajax = function( opts ) {
		opts.success( { query: { meta: { count: 0 } } } );
	};

	createMandatoryUniqueTextarea( 'unique value', '' );

	window.validateAll();

	$.ajax = origAjax;

	assert.equal(
		$('.inputSpan div.errorMessage').length,
		0,
		'no error message shown for mandatory textarea with unique value'
	);
} );

function createMandatoryUniqueTextarea( value, defaultValue ) {
	// Build a textarea that mirrors what PF_TextAreaInput renders for a mandatory+unique field:
	//   <span class="inputSpan mandatoryFieldSpan uniqueFieldSpan">
	//     <textarea class="mandatoryField uniqueField" id="input_1" ...>value</textarea>
	//     <input type="hidden" name="input_1_unique_property" value="MyProperty" />
	//   </span>
	const $span = $( '<span class="inputSpan mandatoryFieldSpan uniqueFieldSpan"></span>' );
	const $textarea = $( '<textarea class="mandatoryField uniqueField" id="input_1"></textarea>' ).val( value );
	// jsdom does not set defaultValue from .val(), so set it directly
	$textarea[ 0 ].defaultValue = defaultValue;
	// Hidden field that tells validateUniqueField which SMW property to query
	const $property = $( '<input type="hidden" name="input_1_unique_property" value="MyProperty" />' );
	$span.append( $textarea ).append( $property );
	$( document.body ).append( $span );
}

function createDateInput(year, month, day) {
	$(`
	    <div>
	      <span class="dateInput mandatoryFieldSpan">
	      	<input class="dayInput" value="${ day }" />
	      	<input class="monthInput" value="${ month }" />
	      	<input class="yearInput" value="${ year }" />
		  </span>
	    </div>
    `).appendTo(document.body);
}
