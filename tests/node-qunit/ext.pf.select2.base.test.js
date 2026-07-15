'use strict';

// Note: do NOT set global.pageforms here — other test files may already have
// registered constructors on it.
global.pf = global.pf || {};
require( '../../libs/ext.pf.js' );

// ext.pf.select2.base.js reads pf.select2 from the global pf namespace
// and extends mw.config; set up the necessary stubs before loading.
require( '../../libs/ext.pf.select2.base.js' );

// ext.pf.select2.base.js registers pf.select2.base on the `pageforms` global
// (its IIFE receives `pageforms` as the `pf` parameter).
const proto = pageforms.select2.base.prototype;

// ===========================================================
// removeDiacritics
// ===========================================================

QUnit.module( 'pf.select2.base.removeDiacritics', () => {

	QUnit.test( 'ASCII string passes through unchanged', ( assert ) => {
		assert.strictEqual( proto.removeDiacritics( 'hello' ), 'hello' );
	} );

	QUnit.test( 'replaces accented vowels with ASCII equivalents', ( assert ) => {
		assert.strictEqual( proto.removeDiacritics( 'é' ), 'e' );
		assert.strictEqual( proto.removeDiacritics( 'ü' ), 'u' );
		assert.strictEqual( proto.removeDiacritics( 'ñ' ), 'n' );
	} );

	QUnit.test( 'handles full word with diacritics', ( assert ) => {
		assert.strictEqual( proto.removeDiacritics( 'München' ), 'Munchen' );
	} );

	QUnit.test( 'converts to string before processing', ( assert ) => {
		// numbers have no diacritics but should not throw
		assert.strictEqual( proto.removeDiacritics( 42 ), '42' );
	} );

	QUnit.test( 'replaces uppercase diacritics', ( assert ) => {
		assert.strictEqual( proto.removeDiacritics( 'Ä' ), 'A' );
		assert.strictEqual( proto.removeDiacritics( 'Ö' ), 'O' );
	} );

} );

// ===========================================================
// escapeMarkupAndAddHTML
// ===========================================================

QUnit.module( 'pf.select2.base.escapeMarkupAndAddHTML', () => {

	QUnit.test( 'returns non-string input unchanged', ( assert ) => {
		assert.strictEqual( proto.escapeMarkupAndAddHTML( 42 ), 42 );
		assert.strictEqual( proto.escapeMarkupAndAddHTML( null ), null );
	} );

	QUnit.test( 'escapes & < > " \' / \\', ( assert ) => {
		const result = proto.escapeMarkupAndAddHTML( '&<>"\'/\\' );
		assert.ok( result.includes( '&amp;' ), 'escapes &' );
		assert.ok( result.includes( '&lt;' ), 'escapes <' );
		assert.ok( result.includes( '&gt;' ), 'escapes >' );
		assert.ok( result.includes( '&quot;' ), 'escapes "' );
		assert.ok( result.includes( '&#39;' ), "escapes '" );
		assert.ok( result.includes( '&#47;' ), 'escapes /' );
		assert.ok( result.includes( '&#92;' ), 'escapes \\' );
	} );

	QUnit.test( 'wraps result in select2-match-entire span', ( assert ) => {
		const result = proto.escapeMarkupAndAddHTML( 'hello' );
		assert.ok( result.startsWith( '<span class="select2-match-entire">' ) );
		assert.ok( result.endsWith( '</span>' ) );
	} );

	QUnit.test( 'plain text with no special chars is wrapped but not escaped', ( assert ) => {
		const result = proto.escapeMarkupAndAddHTML( 'hello world' );
		assert.ok( result.includes( 'hello world' ) );
	} );

} );

// ===========================================================
// textHighlight
// ===========================================================

QUnit.module( 'pf.select2.base.textHighlight', {
	beforeEach() {
		mw.config = { get: () => false };
	}
}, () => {

	QUnit.test( 'returns empty string when text is undefined', ( assert ) => {
		const result = proto.textHighlight( undefined, 'foo' );
		assert.strictEqual( result, '' );
	} );

	QUnit.test( 'returns plain text when term not found', ( assert ) => {
		const result = proto.textHighlight( 'hello', 'xyz' );
		assert.strictEqual( result, 'hello' );
	} );

	QUnit.test( 'returns markup with bold markers when term found at word start', ( assert ) => {
		// Matches " foo" in "bar foo", word-start matching (onAllChars=false)
		const result = proto.textHighlight( 'bar foo baz', 'foo' );
		const boldStart = String.fromCharCode( 1 );
		const boldEnd = String.fromCharCode( 2 );
		assert.ok( result.includes( boldStart + 'foo' + boldEnd ), 'term wrapped in bold markers' );
	} );

	QUnit.test( 'matches anywhere when wgPageFormsAutocompleteOnAllChars=true', ( assert ) => {
		mw.config = { get: ( key ) => key === 'wgPageFormsAutocompleteOnAllChars' ? true : null };
		const result = proto.textHighlight( 'foobar', 'oba' );
		const boldStart = String.fromCharCode( 1 );
		assert.ok( result.includes( boldStart ), 'match found mid-word when onAllChars=true' );
	} );

	QUnit.test( 'match is case-insensitive', ( assert ) => {
		const result = proto.textHighlight( 'Hello World', 'world' );
		const boldStart = String.fromCharCode( 1 );
		assert.ok( result.includes( boldStart ), 'case-insensitive match' );
	} );

	QUnit.test( 'diacritics are normalized before matching', ( assert ) => {
		// "München" should match "munchen"
		mw.config = { get: ( key ) => key === 'wgPageFormsAutocompleteOnAllChars' ? true : null };
		const result = proto.textHighlight( 'München', 'unchen' );
		const boldStart = String.fromCharCode( 1 );
		assert.ok( result.includes( boldStart ), 'matches after diacritic removal' );
	} );

} );

const sinon = require( 'sinon' );

// ===========================================================
// getAutocompleteOpts
// ===========================================================

QUnit.module( 'pf.select2.base.getAutocompleteOpts', {
	beforeEach() {
		$( '<input id="input_1">' ).appendTo( document.body );
	}
}, () => {

	QUnit.test( 'returns autocompletedatatype and autocompletesettings from attributes', ( assert ) => {
		$( '#input_1' ).attr( 'autocompletedatatype', 'category' ).attr( 'autocompletesettings', 'Scientists' );
		const instance = new pageforms.select2.base();
		instance.id = 'input_1';

		const opts = instance.getAutocompleteOpts();

		assert.strictEqual( opts.autocompletedatatype, 'category' );
		assert.strictEqual( opts.autocompletesettings, 'Scientists' );
	} );

	QUnit.test( 'throws when autocompletesettings attribute is absent', ( assert ) => {
		const instance = new pageforms.select2.base();
		instance.id = 'input_1';

		assert.throws( () => {
			instance.getAutocompleteOpts();
		}, /^Error: No autocomplete settings set for input #input_1$/ );
	} );

} );

// ===========================================================
// dependentOn
// ===========================================================

QUnit.module( 'pf.select2.base.dependentOn', {
	beforeEach() {
		// Other test files (e.g. PF_ComboBoxInput.scroll.test.js) replace the shared
		// pageforms.nameAttr shim with a hardcoded `() => 'name'` stub; restore the
		// real origname/name logic here since dependentOn() depends on it.
		pageforms.partOfMultiple = ( element ) => element.attr( 'origname' ) !== undefined;
		pageforms.nameAttr = ( element ) => ( pageforms.partOfMultiple( element ) ? 'origname' : 'name' );
		$( '<input id="input_1" name="City">' ).appendTo( document.body );
	}
}, () => {

	QUnit.test( 'returns the base field name when this field is listed as dependent', ( assert ) => {
		mw.config = { get: () => [ [ 'Country', 'City' ] ] };
		const instance = new pageforms.select2.base();
		instance.id = 'input_1';

		assert.strictEqual( instance.dependentOn(), 'Country' );
	} );

	QUnit.test( 'returns null when this field is not listed as dependent', ( assert ) => {
		mw.config = { get: () => [ [ 'Country', 'SomeOtherField' ] ] };
		const instance = new pageforms.select2.base();
		instance.id = 'input_1';

		assert.strictEqual( instance.dependentOn(), null );
	} );

	QUnit.test( 'returns null when wgPageFormsDependentFields is empty', ( assert ) => {
		mw.config = { get: () => [] };
		const instance = new pageforms.select2.base();
		instance.id = 'input_1';

		assert.strictEqual( instance.dependentOn(), null );
	} );

	QUnit.test( 'uses origname when the field is part of a multiple-instance template', ( assert ) => {
		$( '#input_1' ).removeAttr( 'name' ).attr( 'origname', 'City' );
		mw.config = { get: () => [ [ 'Country', 'City' ] ] };
		const instance = new pageforms.select2.base();
		instance.id = 'input_1';

		assert.strictEqual( instance.dependentOn(), 'Country' );
	} );

} );

// ===========================================================
// dependentOnMe
// ===========================================================

QUnit.module( 'pf.select2.base.dependentOnMe', () => {

	QUnit.test( 'returns all fields dependent on this field', ( assert ) => {
		mw.config = { get: () => [ [ 'Country', 'City' ], [ 'Country', 'Region' ], [ 'City', 'Street' ] ] };
		const instance = new pageforms.select2.base();
		const $element = $( '<input name="Country">' );

		assert.deepEqual( instance.dependentOnMe( $element ), [ 'City', 'Region' ] );
	} );

	QUnit.test( 'returns an empty array when no fields depend on this field', ( assert ) => {
		mw.config = { get: () => [ [ 'Country', 'City' ] ] };
		const instance = new pageforms.select2.base();
		const $element = $( '<input name="Unrelated">' );

		assert.deepEqual( instance.dependentOnMe( $element ), [] );
	} );

} );

// ===========================================================
// getDependentFieldOpts
// ===========================================================

QUnit.module( 'pf.select2.base.getDependentFieldOpts', {
	beforeEach() {
		mw.config = { get: () => ( {} ) };
	}
}, () => {

	QUnit.test( 'reads base value/prop from a plain [name=...] element', ( assert ) => {
		$( '<input name="Country" value="Germany">' ).appendTo( document.body );
		$( '<input id="input_1" name="City" autocompletesettings="City,list">' ).appendTo( document.body );

		const instance = new pageforms.select2.base();
		instance.id = 'input_1';

		const opts = instance.getDependentFieldOpts( 'Country' );

		assert.strictEqual( opts.base_value, 'Germany' );
		assert.strictEqual( opts.prop, 'City' );
	} );

	QUnit.test( 'base_prop falls back to the base element autocompletesettings attribute', ( assert ) => {
		$( '<input name="Country" value="Germany" autocompletesettings="Country">' ).appendTo( document.body );
		$( '<input id="input_1" name="City" autocompletesettings="City,list">' ).appendTo( document.body );

		const instance = new pageforms.select2.base();
		instance.id = 'input_1';

		const opts = instance.getDependentFieldOpts( 'Country' );

		assert.strictEqual( opts.base_prop, 'Country' );
	} );

	QUnit.test( 'base_prop prefers wgPageFormsFieldProperties over the attribute fallback', ( assert ) => {
		mw.config = { get: () => ( { Country: 'CountryProp' } ) };
		$( '<input name="Country" value="Germany" autocompletesettings="IgnoredAttr">' ).appendTo( document.body );
		$( '<input id="input_1" name="City" autocompletesettings="City,list">' ).appendTo( document.body );

		const instance = new pageforms.select2.base();
		instance.id = 'input_1';

		const opts = instance.getDependentFieldOpts( 'Country' );

		assert.strictEqual( opts.base_prop, 'CountryProp' );
	} );

	QUnit.test( 'reads the base element from the enclosing multipleTemplateInstance via origname', ( assert ) => {
		$(
			'<div class="multipleTemplateInstance">' +
				'<input origname="Country" value="Germany">' +
				'<input id="input_1" origname="City" autocompletesettings="City,list">' +
			'</div>'
		).appendTo( document.body );

		const instance = new pageforms.select2.base();
		instance.id = 'input_1';

		const opts = instance.getDependentFieldOpts( 'Country' );

		assert.strictEqual( opts.base_value, 'Germany' );
		assert.strictEqual( opts.prop, 'City' );
	} );

} );

// ===========================================================
// destroy / refresh
// ===========================================================

QUnit.module( 'pf.select2.base.destroy', {
	afterEach() {
		sinon.restore();
	}
}, () => {

	QUnit.test( 'calls select2("destroy") on the element', ( assert ) => {
		const $element = $( '<input>' );
		if ( typeof $.fn.select2 !== 'function' ) {
			$.fn.select2 = function () {
				return this;
			};
		}
		const select2Stub = sinon.stub( $.fn, 'select2' );
		const instance = new pageforms.select2.base();

		instance.destroy( $element );

		assert.true( select2Stub.calledWith( 'destroy' ) );
	} );

} );

QUnit.module( 'pf.select2.base.refresh', {
	afterEach() {
		sinon.restore();
	}
}, () => {

	QUnit.test( 'calls destroy then apply with the jQuery-wrapped element', ( assert ) => {
		const element = $( '<input id="input_1">' ).appendTo( document.body )[ 0 ];
		const instance = new pageforms.select2.base();
		const destroySpy = sinon.stub( instance, 'destroy' );
		const applySpy = sinon.stub( instance, 'apply' );

		instance.refresh( element );

		assert.true( destroySpy.calledOnce );
		assert.true( applySpy.calledOnce );
		assert.strictEqual( destroySpy.firstCall.args[ 0 ][ 0 ], element );
		assert.strictEqual( applySpy.firstCall.args[ 0 ][ 0 ], element );
	} );

} );

// ===========================================================
// apply
// ===========================================================

QUnit.module( 'pf.select2.base.apply', {
	beforeEach() {
		// jsdom's window.Option is not exposed as a bare global by setup.js;
		// apply() uses the bare `new Option(...)` constructor.
		global.Option = window.Option;
		this.configValues = { wgPageFormsAutocompleteOnAllChars: false };
		mw.config = {
			get: ( key ) => Object.prototype.hasOwnProperty.call( this.configValues, key ) ? this.configValues[ key ] : null
		};
		if ( typeof $.fn.select2 !== 'function' ) {
			$.fn.select2 = function () {
				return this;
			};
		}
	},
	afterEach() {
		sinon.restore();
	}
}, () => {

	function makeInputData() {
		return {
			dropdown: { $searchContainer: $( '<span></span>' ) },
			$results: $( '<ul></ul>' )
		};
	}

	QUnit.test( 'empties the element, applies select2, and restores the original value', ( assert ) => {
		const $element = $( '<select id="input_1" value="Existing" autocompletesettings="Scientists"><option value="Stale">Stale</option></select>' )
			.appendTo( document.body );
		const inputData = makeInputData();

		// apply() only appends an empty placeholder Option itself (no autocompletedatatype
		// branch here); add the "Existing" option up front so $input.val(origValue) has a
		// matching <option> to select — a <select> silently ignores .val() otherwise.
		sinon.stub( $.fn, 'select2' ).callsFake( function () {
			this.append( new Option( 'Existing', 'Existing', false, false ) );
			this.data( 'select2', inputData );
			return this;
		} );

		const instance = new pageforms.select2.base();
		instance.id = 'input_1';
		instance.setOptions = () => ( {} );
		instance.dependentOn = () => null;

		instance.apply( $element );

		assert.strictEqual( $element.find( 'option[value="Stale"]' ).length, 0, 'stale options are removed by empty()' );
		assert.strictEqual( $element.val(), 'Existing', 'original value is restored' );
	} );

	QUnit.test( 'treats an undefined original value as an empty string', ( assert ) => {
		const $element = $( '<select id="input_1" autocompletesettings="Scientists"></select>' ).appendTo( document.body );
		const inputData = makeInputData();

		sinon.stub( $.fn, 'select2' ).callsFake( function () {
			this.data( 'select2', inputData );
			return this;
		} );

		const instance = new pageforms.select2.base();
		instance.id = 'input_1';
		instance.setOptions = () => ( {} );
		instance.dependentOn = () => null;

		instance.apply( $element );

		assert.strictEqual( $element.val(), '', 'missing value attribute defaults to empty string' );
	} );

	QUnit.test( 'appends a placeholder option for remote autocompletion when not dependent on another field', ( assert ) => {
		const $element = $( '<select id="input_1" value="Berlin" autocompletesettings="Cities,external data"></select>' )
			.appendTo( document.body );
		const inputData = makeInputData();

		sinon.stub( $.fn, 'select2' ).callsFake( function () {
			this.data( 'select2', inputData );
			return this;
		} );

		const instance = new pageforms.select2.base();
		instance.id = 'input_1';
		instance.setOptions = () => ( {} );
		instance.dependentOn = () => null;
		instance.getAutocompleteOpts = () => ( { autocompletedatatype: 'category', autocompletesettings: 'Cities,external data' } );

		instance.apply( $element );

		assert.strictEqual( $element.find( 'option[value="Berlin"]' ).length, 1, 'placeholder option restores the remote value' );
	} );

	QUnit.test( 'does not append a placeholder option when the field is dependent on another field', ( assert ) => {
		const $element = $( '<select id="input_1" value="Berlin" autocompletesettings="Cities,external data"></select>' )
			.appendTo( document.body );
		const inputData = makeInputData();

		sinon.stub( $.fn, 'select2' ).callsFake( function () {
			this.data( 'select2', inputData );
			return this;
		} );

		const instance = new pageforms.select2.base();
		instance.id = 'input_1';
		instance.setOptions = () => ( {} );
		instance.dependentOn = () => 'Country';
		instance.getAutocompleteOpts = () => ( { autocompletedatatype: 'category', autocompletesettings: 'Cities,external data' } );

		instance.apply( $element );

		assert.strictEqual( $element.find( 'option[value="Berlin"]' ).length, 0, 'no placeholder option added when dependent' );
	} );

	QUnit.test( 'Tab keydown selects the highlighted result and adds a matching option if missing', ( assert ) => {
		const $element = $( '<select id="input_1" autocompletesettings="Scientists"></select>' ).appendTo( document.body );
		const inputData = makeInputData();
		inputData.$results.append( $( '<li class="select2-results__option--highlighted">Einstein</li>' ) );

		sinon.stub( $.fn, 'select2' ).callsFake( function () {
			this.data( 'select2', inputData );
			return this;
		} );

		const instance = new pageforms.select2.base();
		instance.id = 'input_1';
		instance.setOptions = () => ( {} );
		instance.dependentOn = () => null;

		instance.apply( $element );

		inputData.dropdown.$searchContainer.trigger( $.Event( 'keydown', { keyCode: 9 } ) );

		assert.strictEqual( $element.find( 'option[value="Einstein"]' ).length, 1, 'option is created for the highlighted result' );
		assert.strictEqual( $element.val(), 'Einstein', 'value is set to the highlighted result' );
	} );

	QUnit.test( 'Tab keydown with no highlighted result leaves the value unchanged', ( assert ) => {
		const $element = $( '<select id="input_1" value="" autocompletesettings="Scientists"></select>' ).appendTo( document.body );
		const inputData = makeInputData();

		sinon.stub( $.fn, 'select2' ).callsFake( function () {
			this.data( 'select2', inputData );
			return this;
		} );

		const instance = new pageforms.select2.base();
		instance.id = 'input_1';
		instance.setOptions = () => ( {} );
		instance.dependentOn = () => null;

		instance.apply( $element );

		inputData.dropdown.$searchContainer.trigger( $.Event( 'keydown', { keyCode: 9 } ) );

		assert.strictEqual( $element.val(), '', 'value stays empty when nothing is highlighted' );
	} );

	QUnit.test( 'Tab keydown is a no-op when existingvaluesonly is true', ( assert ) => {
		const $element = $( '<select id="input_1" existingvaluesonly="true" autocompletesettings="Scientists"></select>' )
			.appendTo( document.body );
		const inputData = makeInputData();
		inputData.$results.append( $( '<li class="select2-results__option--highlighted">Einstein</li>' ) );

		sinon.stub( $.fn, 'select2' ).callsFake( function () {
			this.data( 'select2', inputData );
			return this;
		} );

		const instance = new pageforms.select2.base();
		instance.id = 'input_1';
		instance.setOptions = () => ( {} );
		instance.dependentOn = () => null;

		instance.apply( $element );

		inputData.dropdown.$searchContainer.trigger( $.Event( 'keydown', { keyCode: 9 } ) );

		assert.strictEqual( $element.find( 'option[value="Einstein"]' ).length, 0, 'no option added when existingValuesOnly is true' );
	} );

	QUnit.test( 'swallows exceptions from select2() and logs them via console.log', ( assert ) => {
		const $element = $( '<select id="input_1" autocompletesettings="Scientists"></select>' ).appendTo( document.body );
		const thrown = new Error( 'select2 init failed' );
		sinon.stub( $.fn, 'select2' ).throws( thrown );
		const consoleLogSpy = sinon.stub( window.console, 'log' );

		const instance = new pageforms.select2.base();
		instance.id = 'input_1';
		instance.setOptions = () => ( {} );

		let threw = false;
		try {
			instance.apply( $element );
		} catch ( e ) {
			threw = true;
		}
		assert.false( threw, 'exception from select2() does not propagate out of apply()' );
		assert.true( consoleLogSpy.calledWith( thrown ) );
	} );

} );
