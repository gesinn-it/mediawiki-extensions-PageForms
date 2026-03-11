global.pageforms = global.pageforms || {};
require( '../../libs/PF_ComboBoxInput.js' );
const sinon = require( 'sinon' );

function createComboBoxDouble( overrides ) {
	const combo = {
		config: {},
		itemFound: false,
		getInputId: () => 'input_1',
		_currentValue: '',
		getValue: function() {
			return this._currentValue;
		},
		setValue: function( value ) {
			this._currentValue = value;
			this.$input.val( value );
		},
		setOptions: sinon.spy(),
		dependentOn: () => null,
		getDependentFieldOpts: () => ( {
			prop: 'City',
			base_prop: 'Country',
			base_value: 'Germany'
		} ),
		pushPending: () => {},
		popPending: () => {},
		highlightText: ( text ) => text,
		getConditionForAutocompleteOnAllChars: pageforms.ComboBoxInput.prototype.getConditionForAutocompleteOnAllChars,
		checkIfAnyWordStartsWithInputValue: pageforms.ComboBoxInput.prototype.checkIfAnyWordStartsWithInputValue,
		getCanonicalValueForInput: pageforms.ComboBoxInput.prototype.getCanonicalValueForInput,
		getDisplayValueForCanonicalInput: pageforms.ComboBoxInput.prototype.getDisplayValueForCanonicalInput,
		syncCanonicalValue: pageforms.ComboBoxInput.prototype.syncCanonicalValue,
		syncDisplayValueFromCanonical: pageforms.ComboBoxInput.prototype.syncDisplayValueFromCanonical,
		bindCanonicalSubmitHandler: pageforms.ComboBoxInput.prototype.bindCanonicalSubmitHandler,
		$input: $( '#input_1' ),
		$element: $( '<span>' )
	};

	Object.keys( overrides || {} ).forEach( ( key ) => {
		combo[key] = overrides[key];
	} );
	return combo;
}

QUnit.module( 'PF_ComboBoxInput displaytitle handling', {
	beforeEach: function() {
		this.configValues = {
			wgPageFormsAutocompleteOnAllChars: true,
			wgPageFormsAutocompleteValues: {},
			wgPageFormsFieldProperties: {},
			wgScriptPath: '',
			wgPageFormsScriptPath: ''
		};
		mw.config = {
			get: ( key ) => Object.prototype.hasOwnProperty.call( this.configValues, key ) ? this.configValues[key] : null
		};
		mw.util.wikiScript = () => '/api.php';
		mw.message = ( key ) => ( {
			text: () => key
		} );
		mw.hook = () => ( {
			fire: () => {}
		} );

		$( '<span><input id="input_1"></span>' ).appendTo( document.body );
		$( '<img id="loading-input_1">' ).appendTo( document.body );
	}
} );

QUnit.test( 'remote autocomplete keeps displaytitle visible and tracks canonical title', function( assert ) {
	const combo = createComboBoxDouble( {
		config: {
			autocompletedatatype: 'category',
			autocompletesettings: 'Scientists'
		},
		_currentValue: 'Alb'
	} );

	sinon.replace( $, 'ajax', ( options ) => {
		options.success( {
			pfautocomplete: [
				{
					title: 'Albert_Einstein',
					displaytitle: 'Albert Einstein'
				}
			]
		} );
		if ( options.complete ) {
			options.complete();
		}
		return {
			abort: () => {}
		};
	} );

	pageforms.ComboBoxInput.prototype.setValues.call( combo, false );

	const options = combo.setOptions.firstCall.args[0];
	assert.strictEqual( options[0].data, 'Albert Einstein' );
	assert.strictEqual( options[0].label, 'Albert Einstein' );
	combo.syncCanonicalValue( 'Albert Einstein' );
	assert.strictEqual( combo.$input.attr( 'data-pf-canonical-value' ), 'Albert_Einstein' );
} );

QUnit.test( 'remote autocomplete accepts canonical title for itemFound', function( assert ) {
	const combo = createComboBoxDouble( {
		config: {
			autocompletedatatype: 'category',
			autocompletesettings: 'Scientists'
		},
		_currentValue: 'Albert_Einstein'
	} );

	sinon.replace( $, 'ajax', ( options ) => {
		options.success( {
			pfautocomplete: [
				{
					title: 'Albert_Einstein',
					displaytitle: 'Albert Einstein'
				}
			]
		} );
		if ( options.complete ) {
			options.complete();
		}
		return {
			abort: () => {}
		};
	} );

	pageforms.ComboBoxInput.prototype.setValues.call( combo, false );

	assert.true( combo.itemFound );
} );

QUnit.test( 'local autocomplete map keeps displaytitle visible and tracks canonical title', function( assert ) {
	this.configValues.wgPageFormsAutocompleteValues = {
		Scientists: {
			Albert_Einstein: 'Albert Einstein'
		}
	};
	const combo = createComboBoxDouble( {
		config: {
			autocompletesettings: 'Scientists'
		},
		_currentValue: 'Alb'
	} );

	pageforms.ComboBoxInput.prototype.setValues.call( combo, false );

	const options = combo.setOptions.firstCall.args[0];
	assert.strictEqual( options[0].data, 'Albert Einstein' );
	assert.strictEqual( options[0].label, 'Albert Einstein' );
	combo.syncCanonicalValue( 'Albert Einstein' );
	assert.strictEqual( combo.$input.attr( 'data-pf-canonical-value' ), 'Albert_Einstein' );
} );

QUnit.test( 'dependent autocomplete keeps displaytitle visible and tracks canonical title', function( assert ) {
	const combo = createComboBoxDouble( {
		config: {
			autocompletesettings: 'City'
		},
		dependentOn: () => 'Country',
		_currentValue: 'Ber'
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

	pageforms.ComboBoxInput.prototype.setValues.call( combo, false );

	const options = combo.setOptions.firstCall.args[0];
	assert.strictEqual( options[0].data, 'Berlin' );
	assert.strictEqual( options[0].label, 'Berlin' );
	combo.syncCanonicalValue( 'Berlin' );
	assert.strictEqual( combo.$input.attr( 'data-pf-canonical-value' ), 'Berlin_(DE)' );
} );

QUnit.test( 'dependent field lookup prefers canonical base value attribute', function( assert ) {
	$( '<input name="Country" value="Germany display" data-pf-canonical-value="Germany">' ).appendTo( document.body );
	const combo = createComboBoxDouble( {
		config: {
			autocompletesettings: 'City'
		},
		partOfMultiple: () => false,
		getInputId: () => 'input_1'
	} );
	combo.$input.attr( 'autocompletesettings', 'City' );

	const opts = pageforms.ComboBoxInput.prototype.getDependentFieldOpts.call( combo, 'Country' );

	assert.strictEqual( opts.base_value, 'Germany' );
} );

QUnit.test( 'submit handler rewrites visible displaytitle to canonical title', function( assert ) {
	const combo = createComboBoxDouble( {
		getValue: function() {
			return this.$input.val();
		}
	} );
	combo.$input = $( '<input id="input_1" name="City" value="Albert Einstein">' );
	const $form = $( '<form></form>' ).append( combo.$input ).appendTo( document.body );
	combo.titleByDisplayValue = {
		'Albert Einstein': 'Albert_Einstein'
	};

	combo.bindCanonicalSubmitHandler();
	$form.on( 'submit', ( event ) => {
		event.preventDefault();
	} );
	$form.trigger( 'submit' );

	assert.strictEqual( combo.$input.val(), 'Albert_Einstein' );
} );

QUnit.test( 'existing canonical value is displayed as displaytitle after options load', function( assert ) {
	this.configValues.wgPageFormsAutocompleteValues = {
		Scientists: {
			Albert_Einstein: 'Albert Einstein'
		}
	};
	const combo = createComboBoxDouble( {
		config: {
			autocompletesettings: 'Scientists'
		},
		_currentValue: 'Albert_Einstein'
	} );

	pageforms.ComboBoxInput.prototype.setValues.call( combo, false );

	assert.strictEqual( combo.getValue(), 'Albert Einstein' );
	assert.strictEqual( combo.$input.attr( 'data-pf-canonical-value' ), 'Albert_Einstein' );
} );
