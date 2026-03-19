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
	// Mock whichever widget class the production code will use:
	// SelectFileInputWidget on MW ≥ 1.43, SelectFileWidget on older versions.
	const widgetProp = OO.ui.SelectFileInputWidget ? 'SelectFileInputWidget' : 'SelectFileWidget';
	sinon.replace(OO.ui, widgetProp, function() {
		this.on = function(trigger, callback) {
			if (trigger === "change") {
				selectedFileCallback = callback;
			}
		}
	});
	sinon.replace($, 'ajax', ({data, success}) => {
		success({
			upload: { filename: data.get("filename") }
		});
	});
	const { $starter, $input, $parent } = createInput();
	$starter.initializeSimpleUpload();
	const file = { name: "file.txt" };

	// User has selected
	selectedFileCallback([ file ]);

	assert.equal($input.val(), "file.txt");
	const html = $parent.get(0).innerHTML;
	assert.true(html.includes('<div class="simpleupload_prv"><img src="article-path/Special:Redirect/file/file.txt?width=150"></div>'));
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
