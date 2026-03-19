require('../../libs/PageForms.js');

QUnit.module('window.validateAll', {
	beforeEach: (assert) => {
		mw.msg = (msg, val) => msg === 'pf_blank_error' ? 'the error message' : null;
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
