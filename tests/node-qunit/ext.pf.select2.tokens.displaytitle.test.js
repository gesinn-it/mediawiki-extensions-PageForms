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

QUnit.test( 'dependent autocomplete uses title as id and displaytitle as text', function( assert ) {
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

QUnit.test( 'remote autocomplete processResults uses title as id and displaytitle as text', function( assert ) {
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

QUnit.test( 'insertTag skips duplicate free tag for existing displaytitle result', function( assert ) {
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
