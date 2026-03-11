global.pageforms = global.pageforms || {};
require( '../../libs/PF_ComboBoxInput.js' );
const sinon = require( 'sinon' );

function createComboBoxDouble( overrides ) {
	const combo = {
		config: {},
		itemFound: false,
		getInputId: () => 'input_1',
		getValue: () => '',
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

QUnit.test( 'remote autocomplete stores title while showing displaytitle', function( assert ) {
	const combo = createComboBoxDouble( {
		config: {
			autocompletedatatype: 'category',
			autocompletesettings: 'Scientists'
		},
		getValue: () => 'Alb'
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
	assert.strictEqual( options[0].data, 'Albert_Einstein' );
	assert.strictEqual( options[0].label, 'Albert Einstein' );
} );

QUnit.test( 'remote autocomplete accepts canonical title for itemFound', function( assert ) {
	const combo = createComboBoxDouble( {
		config: {
			autocompletedatatype: 'category',
			autocompletesettings: 'Scientists'
		},
		getValue: () => 'Albert_Einstein'
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

QUnit.test( 'local autocomplete map uses title as data and displaytitle as label', function( assert ) {
	this.configValues.wgPageFormsAutocompleteValues = {
		Scientists: {
			Albert_Einstein: 'Albert Einstein'
		}
	};
	const combo = createComboBoxDouble( {
		config: {
			autocompletesettings: 'Scientists'
		},
		getValue: () => 'Alb'
	} );

	pageforms.ComboBoxInput.prototype.setValues.call( combo, false );

	const options = combo.setOptions.firstCall.args[0];
	assert.strictEqual( options[0].data, 'Albert_Einstein' );
	assert.strictEqual( options[0].label, 'Albert Einstein' );
} );

QUnit.test( 'dependent autocomplete uses title as data and displaytitle as label', function( assert ) {
	const combo = createComboBoxDouble( {
		config: {
			autocompletesettings: 'City'
		},
		dependentOn: () => 'Country',
		getValue: () => 'Ber'
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
	assert.strictEqual( options[0].data, 'Berlin_(DE)' );
	assert.strictEqual( options[0].label, 'Berlin' );
} );
