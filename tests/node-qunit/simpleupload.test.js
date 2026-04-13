require('../../libs/PF_simpleupload.js');
const sinon = require('sinon');

QUnit.module('simpleupload', {
	beforeEach: (assert) => {
		mw.config = { get: (key) => key === 'wgArticlePath' ? 'article-path/$1' : null }
		mw.util.wikiScript = () => "https://example.com/api.php";
		mw.message = () => ({ text: () => 'Select a file' });
	}
});

QUnit.test('adds upload button to input with empty value', (assert) => {
	const { $starter, $parent } = createInput();

	$starter.initializeSimpleUpload();

	const html = $parent.get(0).innerHTML;
	assert.true(html.includes("Select a file"));
});


QUnit.test('does not show preview while typing (input event must not trigger preview)', (assert) => {
	// Typing partial text fires DOM 'input' on every keystroke. Loading a
	// preview for partial text causes 404s. Only confirmed values (blur→change
	// or dropdown selection→OOUI change) should trigger the preview.
	const clock = sinon.useFakeTimers();
	sinon.replace(OO.ui, OO.ui.SelectFileInputWidget ? 'SelectFileInputWidget' : 'SelectFileWidget', function() {
		this.currentFiles = [];
		this.on = function() {};
	});

	const { $starter, $input, $parent } = createInput();
	$starter.initializeSimpleUpload();

	$input.val( 'hel' );
	$input.trigger( 'input' );
	clock.tick( 10 );
	clock.restore();

	const html = $parent.get(0).innerHTML;
	assert.false( html.includes( 'file/hel' ), 'no preview for partial text must be loaded' );
});

QUnit.test('updates preview when combobox fires pf-combobox-choose event (dropdown selection)', (assert) => {
	// pf.ComboBoxInput overrides onMenuChoose() to fire a 'pf-combobox-choose'
	// jQuery event on the native input.  simpleupload listens to this event to
	// update the preview only on confirmed selection, not while typing.
	const clock = sinon.useFakeTimers();
	sinon.replace(OO.ui, OO.ui.SelectFileInputWidget ? 'SelectFileInputWidget' : 'SelectFileWidget', function() {
		this.currentFiles = [];
		this.on = function() {};
	});

	const { $starter, $input, $parent } = createInput();
	$starter.initializeSimpleUpload();

	// Simulate pf.ComboBoxInput.onMenuChoose() triggering the custom event.
	$input.val( '1.png' );
	$input.trigger( 'pf-combobox-choose' );
	clock.tick( 1 );
	clock.restore();

	const html = $parent.get(0).innerHTML;
	assert.true( html.includes( 'Special:Redirect/file/1.png' ), 'preview must update on pf-combobox-choose' );
});

QUnit.test('sets input value and creates preview after selecting a file', (assert) => {
	let selectedFileCallback;
	let mockWidget;
	// Mock whichever widget class the production code will use:
	// SelectFileInputWidget on MW ≥ 1.43, SelectFileWidget on older versions.
	const widgetProp = OO.ui.SelectFileInputWidget ? 'SelectFileInputWidget' : 'SelectFileWidget';
	sinon.replace(OO.ui, widgetProp, function() {
		mockWidget = this;
		this.currentFiles = [];
		this.on = function(trigger, callback) {
			if (trigger === "change") {
				selectedFileCallback = callback;
			}
		};
		this.setValue = function() {};
	});
	sinon.replace($, 'ajax', ({data, success}) => {
		success({
			upload: { filename: data.get("filename") }
		});
	});
	const { $starter, $input, $parent } = createInput();
	$starter.initializeSimpleUpload();
	const file = { name: "file.txt" };

	// Simulate widget behaviour: currentFiles holds the actual File objects;
	// callback is invoked (arg value is ignored by code after the fix).
	mockWidget.currentFiles = [ file ];
	selectedFileCallback();

	assert.equal($input.val(), "file.txt");
	const html = $parent.get(0).innerHTML;
	assert.true(html.includes('<div class="simpleupload_prv"><img src="article-path/Special:Redirect/file/file.txt?width=150"></div>'));
});

QUnit.test('strips wiki link syntax from duplicate-upload error message', (assert) => {
	// The MW upload API returns error.info containing raw wiki markup such as
	// "[[:Datei:1.png]]" or "[[:File:1.png]]". The alert must show plain text.
	let successCallback;
	sinon.replace($, 'ajax', ({success}) => {
 successCallback = success;
});

	const widgetProp = OO.ui.SelectFileInputWidget ? 'SelectFileInputWidget' : 'SelectFileWidget';
	sinon.replace(OO.ui, widgetProp, function() {
		this.currentFiles = [ { name: 'image.jpg' } ];
		this.on = function(trigger, cb) {
 if (trigger === 'change') {
 cb();
}
};
		this.setValue = function() {};
	});

	const alerts = [];
	sinon.replace(window, 'alert', (msg) => alerts.push(msg));

	const { $starter } = createInput();
	$starter.initializeSimpleUpload();

	successCallback({ error: { info: "The upload is an exact duplicate of the current version of [[:Datei:1.png]]." } });

	assert.strictEqual( alerts.length, 1, 'alert should have been called once' );
	assert.false( alerts[0].includes('[['), 'alert must not contain raw wiki link brackets' );
	assert.true( alerts[0].includes('1.png'), 'alert must contain the plain filename' );
});

QUnit.test('sends the actual File object when SelectFileInputWidget emits change with a string value', (assert) => {
	// Regression test: SelectFileInputWidget (MW ≥ 1.43) emits 'change' with the
	// raw $input.val() string (e.g. "C:\fakepath\image.jpg"), not a File[].
	// The upload handler must read the file from widget.currentFiles, not from
	// the event argument, to avoid sending a single character as the "file" field.
	let selectedFileCallback;
	let mockWidget;
	const widgetProp = OO.ui.SelectFileInputWidget ? 'SelectFileInputWidget' : 'SelectFileWidget';
	sinon.replace(OO.ui, widgetProp, function() {
		mockWidget = this;
		this.currentFiles = [];
		this.on = function(trigger, callback) {
			if (trigger === "change") {
				selectedFileCallback = callback;
			}
		};
	});

	let capturedAjaxData;
	sinon.replace($, 'ajax', ({data}) => {
		capturedAjaxData = data;
	});

	const { $starter } = createInput();
	$starter.initializeSimpleUpload();

	const file = { name: "image.jpg" };
	// Simulate SelectFileInputWidget: currentFiles is set by onFileSelected,
	// but 'change' is emitted with the fake path string by onEdit → setValue.
	mockWidget.currentFiles = [ file ];
	selectedFileCallback( 'C:\\fakepath\\image.jpg' );

	assert.ok( capturedAjaxData, 'ajax should have been called' );
	assert.strictEqual( capturedAjaxData.get( 'file' ), file, 'must send the actual File object, not a fake-path string' );
});

QUnit.test( 'adds token option via DOM-safe value/text assignment', ( assert ) => {
	let selectedFileCallback;
	let mockWidget;
	const maliciousFileName = 'x\"><img id="pf-upload-xss" src="x">';

	global.pf = global.pf || {};
	global.pf.select2 = {
		tokens: function() {
			this.refresh = () => {};
		}
	};

	const widgetProp = OO.ui.SelectFileInputWidget ? 'SelectFileInputWidget' : 'SelectFileWidget';
	sinon.replace( OO.ui, widgetProp, function() {
		mockWidget = this;
		this.currentFiles = [];
		this.on = function( trigger, callback ) {
			if ( trigger === 'change' ) {
				selectedFileCallback = callback;
			}
		};
		this.setValue = function() {};
	} );

	sinon.replace( $, 'ajax', ( { data, success } ) => {
		success( {
			upload: { filename: data.get( 'filename' ) }
		} );
	} );

	const { $starter, $input } = createTokensInput();
	$starter.initializeSimpleUpload();

	mockWidget.currentFiles = [ { name: maliciousFileName } ];
	selectedFileCallback();

	const $option = $input.find( 'option' ).first();
	assert.strictEqual( $option.val(), maliciousFileName, 'option value preserves full file name as plain text' );
	assert.strictEqual( $option.text(), maliciousFileName, 'option label preserves full file name as plain text' );
	assert.strictEqual( $( '#pf-upload-xss' ).length, 0, 'no injected DOM node is created' );
} );

function createInput() {
	$(`
		<span id="parent">
			<input id="input_1" value="123">
			<span class="simpleUploadInterface" data-input-id="input_1" />		
		</span>
    `).appendTo(document.body);
	return { $starter: $('.simpleUploadInterface'), $input: $('#input_1'), $parent: $('#parent') };
}

function createTokensInput() {
	$(`
		<span id="parent">
			<select id="input_1" class="pfTokens" multiple></select>
			<span class="simpleUploadInterface" data-input-id="input_1" />
		</span>
	`).appendTo( document.body );
	return { $starter: $( '.simpleUploadInterface' ), $input: $( '#input_1' ), $parent: $( '#parent' ) };
}
