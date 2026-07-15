global.pageforms = global.pageforms || {};
require( '../../libs/ext.pf.select2.base.js' );
require( '../../libs/ext.pf.select2.tokens.js' );
const sinon = require( 'sinon' );

QUnit.module( 'pf.select2.tokens displaytitle handling', {
	beforeEach: function() {
		this.configValues = {
			wgPageFormsAutocompleteValues: {},
			wgPageFormsDependentFields: [],
			wgScriptPath: ''
		};
		mw.config = {
			get: ( key ) => Object.prototype.hasOwnProperty.call( this.configValues, key ) ? this.configValues[key] : null
		};
		mw.util = {
			wikiScript: () => '/api.php'
		};

		$( '<select id="input_1"></select>' ).appendTo( document.body );
	}
} );

QUnit.test( 'local autocomplete map uses title as id and displaytitle as text', function( assert ) {
	this.configValues.wgPageFormsAutocompleteValues = {
		Scientists: {
			Albert_Einstein: 'Albert Einstein'
		}
	};
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';
	tokens.dependentOn = () => null;

	const values = tokens.getData( 'Scientists' );

	assert.strictEqual( values[0].id, 'Albert_Einstein' );
	assert.strictEqual( values[0].text, 'Albert Einstein' );
} );

QUnit.test( 'dependent autocomplete uses title as id and displaytitle as text', ( assert ) => {
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';
	tokens.dependentOn = () => 'Country';
	tokens.getDependentFieldOpts = () => ( {
		prop: 'City',
		base_prop: 'Country',
		base_value: 'Germany'
	} );

	sinon.replace( $, 'ajax', ( options ) => {
		options.success( {
			pfautocomplete: [
				{
					title: 'Berlin_(DE)',
					displaytitle: 'Berlin'
				}
			]
		} );
		return {};
	} );

	const values = tokens.getData( 'Cities' );

	assert.strictEqual( values[0].id, 'Berlin_(DE)' );
	assert.strictEqual( values[0].text, 'Berlin' );
} );

QUnit.test( 'remote autocomplete processResults uses title as id and displaytitle as text', ( assert ) => {
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';
	$( '#input_1' )
		.attr( 'autocompletedatatype', 'category' )
		.attr( 'autocompletesettings', 'Scientists' );

	const ajaxOpts = tokens.getAjaxOpts();
	const processed = ajaxOpts.processResults( {
		pfautocomplete: [
			{
				title: 'Albert_Einstein',
				displaytitle: 'Albert Einstein'
			}
		]
	} );

	assert.strictEqual( processed.results[0].id, 'Albert_Einstein' );
	assert.strictEqual( processed.results[0].text, 'Albert Einstein' );
} );

QUnit.test( 'insertTag skips duplicate free tag for existing displaytitle result', ( assert ) => {
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';
	tokens.existingValuesOnly = false;
	$( '#input_1' ).attr( 'autocompletesettings', 'Scientists' );

	const options = tokens.setOptions();
	const data = [
		{ id: 'Albert_Einstein', text: 'Albert Einstein' },
		{ id: 'Niels_Bohr', text: 'Niels Bohr' }
	];

	options.insertTag( data, { id: 'Albert Einstein', text: ' Albert Einstein ' } );

	assert.strictEqual( data.length, 2 );
	assert.deepEqual( data[0], { id: 'Albert_Einstein', text: 'Albert Einstein' } );
} );

QUnit.test( 'selecting a suggestion clears inline search text', function( assert ) {
	const $input = $( '<select id="input_2" name="Scientists[]" autocompletesettings="Scientists" multiple></select>' )
		.append( '<option value="Albert_Einstein">Albert Einstein</option>' );
	const $wrapper = $( '<span class="inputSpan"></span>' )
		.append( $input )
		.appendTo( document.body );
	const $container = $( '<span class="select2-container"><span class="select2-selection"><ul class="select2-selection__rendered"><li class="select2-search select2-search--inline"><input class="select2-search__field" /></li></ul></span></span>' )
		.appendTo( $wrapper );

	this.configValues.wgPageFormsScriptPath = '/extensions/PageForms';
	this.configValues.wgPageFormsAutocompleteValues = {
		Scientists: {
			Albert_Einstein: 'Albert Einstein'
		}
	};

	const inputData = {
		$container,
		$results: $( '<ul></ul>' ),
		val: () => [],
		results: {},
		dropdown: {
			$searchContainer: $( '<span></span>' )
		}
	};

	global.Sortable = {
		create: () => ( {} )
	};
	if ( typeof $.fn.select2 !== 'function' ) {
		$.fn.select2 = function() {
			return this;
		};
	}

	sinon.stub( $.fn, 'select2' ).callsFake( function() {
		this.data( 'select2', inputData );
		return this;
	} );

	const tokens = new pageforms.select2.tokens();
	tokens.apply( $input );

	const $searchField = inputData.$container.find( '.select2-search__field' );
	$searchField.val( 'türe' );

	$input.trigger( {
		type: 'select2:select',
		params: {
			data: {
				id: 'Albert_Einstein',
				text: 'Albert Einstein',
				element: $input.find( 'option[value="Albert_Einstein"]' )[0]
			}
		}
	} );

	assert.strictEqual( $searchField.val(), '' );
	delete global.Sortable;
} );

// ===========================================================
// setOptions
// ===========================================================

QUnit.module( 'pf.select2.tokens.setOptions', {
	beforeEach: function() {
		this.configValues = {
			wgPageFormsAutocompleteValues: {},
			wgPageFormsDependentFields: [],
			wgScriptPath: '',
			wgPageFormsAutocompleteOnAllChars: false
		};
		mw.config = {
			get: ( key ) => Object.prototype.hasOwnProperty.call( this.configValues, key ) ? this.configValues[key] : null
		};
		mw.util = { wikiScript: () => '/api.php' };
		mw.msg = ( key ) => key;

		$( '<select id="input_1" autocompletesettings="Scientists"></select>' ).appendTo( document.body );
	}
} );

QUnit.test( 'matcher matches when term is a prefix of the text', ( assert ) => {
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';

	const opts = tokens.setOptions();

	const text = { text: 'foobar' };
	assert.strictEqual( opts.matcher( { term: 'foo' }, text ), text );
} );

QUnit.test( 'matcher matches when term follows a space', ( assert ) => {
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';

	const opts = tokens.setOptions();

	const text = { text: 'bar foo' };
	assert.strictEqual( opts.matcher( { term: 'foo' }, text ), text );
} );

QUnit.test( 'matcher returns null when the term is not found', ( assert ) => {
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';

	const opts = tokens.setOptions();

	assert.strictEqual( opts.matcher( { term: 'xyz' }, { text: 'foobar' } ), null );
} );

QUnit.test( 'matcher is not defined when wgPageFormsAutocompleteOnAllChars is true', function( assert ) {
	this.configValues.wgPageFormsAutocompleteOnAllChars = true;
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';

	const opts = tokens.setOptions();

	assert.strictEqual( opts.matcher, undefined );
} );

QUnit.test( 'maximumSelectionLength and language.maximumSelected are set from maxvalues attribute', ( assert ) => {
	$( '#input_1' ).attr( 'maxvalues', '3' );
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';

	const opts = tokens.setOptions();

	assert.strictEqual( opts.maximumSelectionLength, '3' );
	assert.strictEqual( typeof opts.language.maximumSelected, 'function' );
} );

QUnit.test( 'maximumSelectionLength is not set when maxvalues attribute is absent', ( assert ) => {
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';

	const opts = tokens.setOptions();

	assert.strictEqual( opts.maximumSelectionLength, undefined );
	assert.strictEqual( opts.language.maximumSelected, undefined );
} );

QUnit.test( 'adaptContainerCssClass blanks out mandatoryField and passes through other classes', ( assert ) => {
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';

	const opts = tokens.setOptions();

	assert.strictEqual( opts.adaptContainerCssClass( 'mandatoryField' ), '' );
	assert.strictEqual( opts.adaptContainerCssClass( 'someOtherClass' ), 'someOtherClass' );
} );

QUnit.test( 'tokenSeparators delegates to getDelimiter', ( assert ) => {
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';
	tokens.getDelimiter = () => ';';

	const opts = tokens.setOptions();

	assert.strictEqual( opts.tokenSeparators, ';' );
} );

QUnit.test( 'templateResult delegates to textHighlight using results.lastParams.term', ( assert ) => {
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';

	const opts = tokens.setOptions();
	$( '#input_1' ).data( 'select2', {
		results: { lastParams: { term: 'foo' } }
	} );

	const result = opts.templateResult( { text: 'bar foo baz' } );
	const boldStart = String.fromCharCode( 1 );
	assert.true( result.includes( boldStart + 'foo' ), 'delegates to textHighlight with the lastParams term' );
} );

QUnit.test( 'templateResult falls back to $dropdown textContent when lastParams.term is empty', ( assert ) => {
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';

	const opts = tokens.setOptions();
	$( '#input_1' ).data( 'select2', {
		results: {},
		$dropdown: [ { textContent: 'foo' } ]
	} );

	const result = opts.templateResult( { text: 'bar foo baz' } );
	const boldStart = String.fromCharCode( 1 );
	assert.true( result.includes( boldStart + 'foo' ), 'falls back to $dropdown textContent' );
} );

QUnit.test( 'templateResult falls back to the last selection child value when dropdown text is also empty', ( assert ) => {
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';

	const opts = tokens.setOptions();
	$( '#input_1' ).data( 'select2', {
		results: {},
		$dropdown: [ { textContent: '' } ],
		// htmlElements[last].children[0].value is read by the code; the outer
		// nesting mirrors $selection[0].children[0].children being the search's
		// sibling <li> elements, one of which wraps an <input> with a .value.
		$selection: [ { children: [ { children: [ { children: [ { value: 'foo' } ] } ] } ] } ]
	} );

	const result = opts.templateResult( { text: 'bar foo baz' } );
	const boldStart = String.fromCharCode( 1 );
	assert.true( result.includes( boldStart + 'foo' ), 'falls back to the last selection child value' );
} );

// ===========================================================
// Sortable onEnd handler (via apply)
// ===========================================================

QUnit.module( 'pf.select2.tokens Sortable onEnd handler', {
	beforeEach: function() {
		this.configValues = {
			wgPageFormsAutocompleteValues: {},
			wgPageFormsDependentFields: [],
			wgScriptPath: '',
			wgPageFormsScriptPath: ''
		};
		mw.config = {
			get: ( key ) => Object.prototype.hasOwnProperty.call( this.configValues, key ) ? this.configValues[key] : null
		};
		mw.util = { wikiScript: () => '/api.php' };
	},
	afterEach: function() {
		delete global.Sortable;
		sinon.restore();
	}
} );

QUnit.test( 'reorders the backing select options to match the reordered token list', ( assert ) => {
	const $input = $( '<select id="input_1" name="Scientists[]" autocompletesettings="Scientists" multiple></select>' )
		.append( '<option value="Albert_Einstein">Albert Einstein</option>' )
		.append( '<option value="Niels_Bohr">Niels Bohr</option>' );
	const $wrapper = $( '<span class="inputSpan"></span>' ).append( $input ).appendTo( document.body );
	// "x" prefix mirrors select2's built-in remove-icon text that onEnd strips off.
	const $tokensUl = $(
		'<ul class="select2-selection__rendered">' +
			'<li class="select2-selection__choice">xNiels Bohr</li>' +
			'<li class="select2-selection__choice">xAlbert Einstein</li>' +
		'</ul>'
	).appendTo( $wrapper );

	const inputData = {
		$container: $( '<span></span>' ),
		$results: $( '<ul></ul>' ),
		dropdown: { $searchContainer: $( '<span></span>' ) }
	};

	if ( typeof $.fn.select2 !== 'function' ) {
		$.fn.select2 = function() {
			return this;
		};
	}
	sinon.stub( $.fn, 'select2' ).callsFake( function() {
		this.data( 'select2', inputData );
		return this;
	} );

	let capturedOnEnd;
	global.Sortable = {
		create: ( el, sortableOpts ) => {
			capturedOnEnd = sortableOpts.onEnd;
			return {};
		}
	};

	const tokens = new pageforms.select2.tokens();
	tokens.apply( $input );

	const changeSpy = sinon.spy();
	$input.on( 'change', changeSpy );

	capturedOnEnd();

	const optionValues = $input.find( 'option' ).map( function() {
		return this.value;
	} ).get();
	assert.deepEqual( optionValues, [ 'Niels_Bohr', 'Albert_Einstein' ], 'select options reordered to match the <ul>' );
	assert.true( changeSpy.called, 'change is triggered on the backing select' );
} );

// ===========================================================
// keyup/Tab-to-select-highlighted-option handler (via apply)
// ===========================================================

QUnit.module( 'pf.select2.tokens keyup Tab handler', {
	beforeEach: function() {
		this.configValues = {
			wgPageFormsAutocompleteValues: {},
			wgPageFormsDependentFields: [],
			wgScriptPath: '',
			wgPageFormsScriptPath: ''
		};
		mw.config = {
			get: ( key ) => Object.prototype.hasOwnProperty.call( this.configValues, key ) ? this.configValues[key] : null
		};
		mw.util = { wikiScript: () => '/api.php' };

		global.Sortable = { create: () => ( {} ) };
		if ( typeof $.fn.select2 !== 'function' ) {
			$.fn.select2 = function() {
				return this;
			};
		}
	},
	afterEach: function() {
		delete global.Sortable;
		sinon.restore();
	}
} );

function buildTokensFixture( inputDataOverrides ) {
	const $input = $( '<select id="input_1" name="Scientists[]" autocompletesettings="Scientists" multiple></select>' );
	$( '<span class="inputSpan"></span>' ).append( $input ).appendTo( document.body );

	const inputData = Object.assign( {
		$container: $( '<span></span>' ),
		$results: $( '<ul></ul>' ),
		dropdown: { $searchContainer: $( '<span></span>' ) },
		val: () => []
	}, inputDataOverrides );

	sinon.stub( $.fn, 'select2' ).callsFake( function() {
		this.data( 'select2', inputData );
		return this;
	} );

	const tokens = new pageforms.select2.tokens();
	tokens.apply( $input );

	return { $input, inputData };
}

QUnit.test( 'Tab with a highlighted option lacking data appends and selects a new option from its text', ( assert ) => {
	const inputData = {
		$results: $( '<ul><li class="select2-results__option--highlighted">Marie Curie</li></ul>' ),
		val: () => []
	};
	const { $input, inputData: applied } = buildTokensFixture( inputData );

	applied.$container.trigger( $.Event( 'keyup', { keyCode: 9 } ) );

	assert.strictEqual( $input.find( 'option[value="Marie Curie"]' ).length, 1, 'a new option is appended for the raw text' );
	assert.deepEqual( $input.val(), [ 'Marie Curie' ], 'the value is set to the newly appended option' );
} );

QUnit.test( 'Tab with a highlighted option carrying data uses its id/text, without duplicating an existing option', ( assert ) => {
	const inputData = {
		$results: $( '<ul><li class="select2-results__option--highlighted">Albert Einstein</li></ul>' ),
		val: () => []
	};
	const { $input, inputData: applied } = buildTokensFixture( inputData );
	$input.append( '<option value="Albert_Einstein">Albert Einstein</option>' );
	applied.$results.find( '.select2-results__option--highlighted' ).data( 'data', { id: 'Albert_Einstein', text: 'Albert Einstein' } );

	applied.$container.trigger( $.Event( 'keyup', { keyCode: 9 } ) );

	assert.strictEqual( $input.find( 'option[value="Albert_Einstein"]' ).length, 1, 'no duplicate option created' );
	assert.deepEqual( $input.val(), [ 'Albert_Einstein' ] );
} );

QUnit.test( 'Tab dedups an already-selected value instead of adding it twice', ( assert ) => {
	const inputData = {
		$results: $( '<ul><li class="select2-results__option--highlighted">Marie Curie</li></ul>' ),
		val: () => [ 'Marie Curie', 'Niels Bohr' ]
	};
	const { $input, inputData: applied } = buildTokensFixture( inputData );

	applied.$container.trigger( $.Event( 'keyup', { keyCode: 9 } ) );

	assert.strictEqual( $input.find( 'option[value="Marie Curie"]' ).length, 1, 'still only one option for the existing value' );
} );

QUnit.test( 'Tab with no highlighted option does not change the selected value', ( assert ) => {
	const inputData = {
		$results: $( '<ul></ul>' ),
		val: () => []
	};
	const { $input, inputData: applied } = buildTokensFixture( inputData );

	applied.$container.trigger( $.Event( 'keyup', { keyCode: 9 } ) );

	// An empty-value <option> may still be appended (optionValue stays ''), but
	// it is never selected since newValue never gains an empty-string entry.
	assert.deepEqual( $input.val(), [], 'value stays empty when nothing is highlighted' );
} );

QUnit.test( 'non-Tab keyup is a no-op', ( assert ) => {
	const inputData = {
		$results: $( '<ul><li class="select2-results__option--highlighted">Marie Curie</li></ul>' ),
		val: () => []
	};
	const { $input, inputData: applied } = buildTokensFixture( inputData );

	applied.$container.trigger( $.Event( 'keyup', { keyCode: 65 } ) );

	assert.strictEqual( $input.find( 'option' ).length, 0, 'no option is added for a non-Tab key' );
} );

QUnit.test( 'Tab is a no-op when existingValuesOnly is true', ( assert ) => {
	const $input = $( '<select id="input_1" name="Scientists[]" autocompletesettings="Scientists" existingvaluesonly="true" multiple></select>' );
	$( '<span class="inputSpan"></span>' ).append( $input ).appendTo( document.body );

	const inputData = {
		$container: $( '<span></span>' ),
		$results: $( '<ul><li class="select2-results__option--highlighted">Marie Curie</li></ul>' ),
		dropdown: { $searchContainer: $( '<span></span>' ) },
		val: () => []
	};
	sinon.stub( $.fn, 'select2' ).callsFake( function() {
		this.data( 'select2', inputData );
		return this;
	} );

	const tokens = new pageforms.select2.tokens();
	tokens.apply( $input );

	inputData.$container.trigger( $.Event( 'keyup', { keyCode: 9 } ) );

	assert.strictEqual( $input.find( 'option' ).length, 0, 'no option is added when existingValuesOnly is true' );
} );

// ===========================================================
// dblclick-to-edit-token handler (via apply)
// ===========================================================

QUnit.module( 'pf.select2.tokens dblclick handler', {
	beforeEach: function() {
		this.configValues = {
			wgPageFormsAutocompleteValues: {},
			wgPageFormsDependentFields: [],
			wgScriptPath: '',
			wgPageFormsScriptPath: ''
		};
		mw.config = {
			get: ( key ) => Object.prototype.hasOwnProperty.call( this.configValues, key ) ? this.configValues[key] : null
		};
		mw.util = { wikiScript: () => '/api.php' };

		global.Sortable = { create: () => ( {} ) };
		if ( typeof $.fn.select2 !== 'function' ) {
			$.fn.select2 = function() {
				return this;
			};
		}
	},
	afterEach: function() {
		delete global.Sortable;
		sinon.restore();
	}
} );

QUnit.test( 'dblclick on a token removes it from the value and prefills the search field for editing', ( assert ) => {
	const $input = $( '<select id="input_1" name="Scientists[]" autocompletesettings="Scientists" multiple></select>' )
		.append( '<option value="Albert_Einstein">Albert Einstein</option>' );
	const $wrapper = $( '<span class="inputSpan"></span>' ).append( $input ).appendTo( document.body );
	const $searchField = $( '<input class="select2-search__field" />' );
	const $tokensUl = $( '<ul class="select2-selection__rendered"></ul>' ).append( $searchField ).appendTo( $wrapper );
	const $token = $( '<li class="select2-selection__choice">xAlbert Einstein</li>' )
		.data( 'data', { id: 'Albert_Einstein', text: 'Albert Einstein' } )
		.appendTo( $tokensUl );

	const inputData = {
		$container: $( '<span></span>' ).append( $searchField ),
		$results: $( '<ul></ul>' ),
		dropdown: { $searchContainer: $( '<span></span>' ) },
		val: () => [ 'Albert_Einstein' ]
	};
	sinon.stub( $.fn, 'select2' ).callsFake( function() {
		this.data( 'select2', inputData );
		return this;
	} );

	const tokens = new pageforms.select2.tokens();
	tokens.apply( $input );

	$token.trigger( 'dblclick' );

	assert.deepEqual( $input.val(), [], 'clicked value removed from selection' );
	assert.strictEqual( $searchField.val(), 'Albert Einstein', 'search field prefilled with the clicked label' );
} );

QUnit.test( 'dblclick reassigns the target to the parent <li> when the inner match span is clicked', ( assert ) => {
	const $input = $( '<select id="input_1" name="Scientists[]" autocompletesettings="Scientists" multiple></select>' )
		.append( '<option value="Albert_Einstein">Albert Einstein</option>' );
	const $wrapper = $( '<span class="inputSpan"></span>' ).append( $input ).appendTo( document.body );
	const $searchField = $( '<input class="select2-search__field" />' );
	const $tokensUl = $( '<ul class="select2-selection__rendered"></ul>' ).append( $searchField ).appendTo( $wrapper );
	const $matchSpan = $( '<span class="select2-match-entire">Albert Einstein</span>' );
	const $token = $( '<li class="select2-selection__choice">x</li>' )
		.append( $matchSpan )
		.data( 'data', { id: 'Albert_Einstein', text: 'Albert Einstein' } )
		.appendTo( $tokensUl );

	const inputData = {
		$container: $( '<span></span>' ).append( $searchField ),
		$results: $( '<ul></ul>' ),
		dropdown: { $searchContainer: $( '<span></span>' ) },
		val: () => [ 'Albert_Einstein' ]
	};
	sinon.stub( $.fn, 'select2' ).callsFake( function() {
		this.data( 'select2', inputData );
		return this;
	} );

	const tokens = new pageforms.select2.tokens();
	tokens.apply( $input );

	$matchSpan.trigger( 'dblclick' );

	assert.deepEqual( $input.val(), [], 'clicked value removed from selection even when the inner span was the click target' );
} );

QUnit.test( 'dblclick falls back to the title attribute when there is no data payload', ( assert ) => {
	const $input = $( '<select id="input_1" name="Scientists[]" autocompletesettings="Scientists" multiple></select>' )
		.append( '<option value="Albert Einstein">Albert Einstein</option>' );
	const $wrapper = $( '<span class="inputSpan"></span>' ).append( $input ).appendTo( document.body );
	const $searchField = $( '<input class="select2-search__field" />' );
	const $tokensUl = $( '<ul class="select2-selection__rendered"></ul>' ).append( $searchField ).appendTo( $wrapper );
	const $token = $( '<li class="select2-selection__choice" title="Albert Einstein">xAlbert Einstein</li>' )
		.appendTo( $tokensUl );

	const inputData = {
		$container: $( '<span></span>' ).append( $searchField ),
		$results: $( '<ul></ul>' ),
		dropdown: { $searchContainer: $( '<span></span>' ) },
		val: () => [ 'Albert Einstein' ]
	};
	sinon.stub( $.fn, 'select2' ).callsFake( function() {
		this.data( 'select2', inputData );
		return this;
	} );

	const tokens = new pageforms.select2.tokens();
	tokens.apply( $input );

	$token.trigger( 'dblclick' );

	assert.deepEqual( $input.val(), [], 'title-attribute fallback value removed from selection' );
	assert.strictEqual( $searchField.val(), 'Albert Einstein' );
} );

QUnit.test( 'dblclick handler is not registered when existingvaluesonly is true', ( assert ) => {
	const $input = $( '<select id="input_1" name="Scientists[]" autocompletesettings="Scientists" existingvaluesonly="true" multiple></select>' )
		.append( '<option value="Albert_Einstein" selected>Albert Einstein</option>' );
	const $wrapper = $( '<span class="inputSpan"></span>' ).append( $input ).appendTo( document.body );
	const $tokensUl = $( '<ul class="select2-selection__rendered"></ul>' ).appendTo( $wrapper );
	const $token = $( '<li class="select2-selection__choice">xAlbert Einstein</li>' )
		.data( 'data', { id: 'Albert_Einstein', text: 'Albert Einstein' } )
		.appendTo( $tokensUl );

	const inputData = {
		$container: $( '<span></span>' ),
		$results: $( '<ul></ul>' ),
		dropdown: { $searchContainer: $( '<span></span>' ) },
		val: () => [ 'Albert_Einstein' ]
	};
	sinon.stub( $.fn, 'select2' ).callsFake( function() {
		this.data( 'select2', inputData );
		return this;
	} );

	const tokens = new pageforms.select2.tokens();
	tokens.apply( $input );

	$token.trigger( 'dblclick' );

	assert.deepEqual( $input.val(), [ 'Albert_Einstein' ], 'value unchanged since no dblclick handler was registered' );
} );

// ===========================================================
// getAjaxOpts().data() wikidata dependent-field substitution
// ===========================================================

QUnit.module( 'pf.select2.tokens.getAjaxOpts wikidata substitution', {
	beforeEach: function() {
		mw.util = { wikiScript: () => '/api.php' };
		$( '<select id="input_1"></select>' ).appendTo( document.body );
	}
} );

QUnit.test( 'substitutes a dependent field value into the wikidata query string', ( assert ) => {
	$( '<input name="[Country]" value="Germany">' ).appendTo( document.body );
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';
	$( '#input_1' ).attr( 'autocompletedatatype', 'wikidata' ).attr( 'autocompletesettings', 'P17=[Country]' );

	const ajaxOpts = tokens.getAjaxOpts();
	const reqParams = ajaxOpts.data( { term: 'Berlin' } );

	assert.strictEqual( reqParams.wikidata, 'P17=Germany', 'dependent field value substituted into the query' );
} );

QUnit.test( 'leaves the placeholder unsubstituted when the dependent field value is blank', ( assert ) => {
	$( '<input name="[Country]" value="   ">' ).appendTo( document.body );
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';
	$( '#input_1' ).attr( 'autocompletedatatype', 'wikidata' ).attr( 'autocompletesettings', 'P17=[Country]' );

	const ajaxOpts = tokens.getAjaxOpts();
	const reqParams = ajaxOpts.data( { term: 'Berlin' } );

	assert.strictEqual( reqParams.wikidata, 'P17=[Country]', 'placeholder left as-is when dependent value is blank' );
} );

QUnit.test( 'shows the loading icon while building request params', ( assert ) => {
	$( '<span id="loading-input_1" style="display:none;"></span>' ).appendTo( document.body );
	const tokens = new pageforms.select2.tokens();
	tokens.id = 'input_1';
	$( '#input_1' ).attr( 'autocompletedatatype', 'category' ).attr( 'autocompletesettings', 'Scientists' );

	const ajaxOpts = tokens.getAjaxOpts();
	ajaxOpts.data( { term: 'Ein' } );

	assert.notEqual( $( '#loading-input_1' ).css( 'display' ), 'none', 'loading icon is shown' );
} );

// ===========================================================
// getDelimiter
// ===========================================================

QUnit.module( 'pf.select2.tokens.getDelimiter', () => {

	QUnit.test( 'defaults to a comma when no list delimiter is configured', ( assert ) => {
		const tokens = new pageforms.select2.tokens();
		const $element = $( '<select autocompletesettings="Scientists,external data">' );

		assert.strictEqual( tokens.getDelimiter( $element ), ',' );
	} );

	QUnit.test( 'uses the configured list delimiter for list-type autocomplete settings', ( assert ) => {
		const tokens = new pageforms.select2.tokens();
		const $element = $( '<select autocompletesettings="Scientists,list,;">' );

		assert.strictEqual( tokens.getDelimiter( $element ), ';' );
	} );

	QUnit.test( 'falls back to prevObject when the element itself has no autocompletesettings attribute', ( assert ) => {
		$( '<select id="input_1" autocompletesettings="Scientists,list,;"><option>a</option></select>' ).appendTo( document.body );
		const tokens = new pageforms.select2.tokens();

		// Mirrors an element derived via a jQuery traversal (e.g. .find()) whose
		// original selection carried no autocompletesettings attribute of its own.
		const $derived = $( '<div></div>' );
		$derived.prevObject = [ { firstElementChild: { id: 'input_1' } } ];

		assert.strictEqual( tokens.getDelimiter( $derived ), ';' );
	} );

} );
