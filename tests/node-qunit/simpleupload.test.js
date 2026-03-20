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

function createInput() {
	$(`
		<span id="parent">
			<input id="input_1" value="123">
			<span class="simpleUploadInterface" data-input-id="input_1" />		
		</span>
    `).appendTo(document.body);
	return { $starter: $('.simpleUploadInterface'), $input: $('#input_1'), $parent: $('#parent') };
}
