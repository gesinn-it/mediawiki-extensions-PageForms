global.pageforms = global.pageforms || {};
global.pageforms.buildAutocompleteParams = function ( dataType, settings, substr ) {
	const params = { action: 'pfautocomplete', format: 'json', substr: substr };
	if ( dataType ) {
		params[ dataType ] = settings;
	}
	return params;
};
global.pageforms.highlightText = ( searchTerm, s ) => s;
global.pageforms.nameAttr = () => 'name';
require( '../../libs/PF_ComboBoxDataSource.js' );
require( '../../libs/PF_ComboBoxInput.js' );
const sinon = require( 'sinon' );

QUnit.module( 'PF_ComboBoxInput initial-load redlink marker', {
	beforeEach: function () {
		this.configValues = {
			wgPageFormsAutocompleteOnAllChars: true,
			wgPageFormsAutocompleteValues: {},
			wgPageFormsFieldProperties: {},
			wgScriptPath: '',
			wgPageFormsScriptPath: ''
		};
		mw.config = {
			get: ( key ) => Object.prototype.hasOwnProperty.call( this.configValues, key )
				? this.configValues[ key ]
				: null
		};
		mw.util = { wikiScript: () => '/api.php' };
		mw.message = ( key ) => ( { text: () => key } );
		mw.hook = () => ( { fire: () => {} } );
	},
	afterEach: function () {
		sinon.restore();
	}
} );

// Instantiate a real pf.ComboBoxInput and call apply() on it so the redlink
// marker block (inside apply()) runs exactly as it does in production.
function buildRealCombo( selectAttrs, selectedValue ) {
	const id = 'pf_redlink_test_input';
	$( '#' + id ).parent().remove();
	$( '#loading-' + id ).remove();

	const $select = $( '<select>' ).attr( Object.assign( { id: id, name: 'test' }, selectAttrs ) );
	$( '<option>' ).attr( 'value', selectedValue ).text( selectedValue ).prop( 'selected', true ).appendTo( $select );
	$( '<span>' ).append( $select ).appendTo( document.body );
	$( '<img id="loading-' + id + '">' ).appendTo( document.body );

	const widget = new pageforms.ComboBoxInput( {} );
	widget.apply( $select );
	return widget;
}

QUnit.test( 'local source: value not found in static values gets marked as redlink', function ( assert ) {
	this.configValues.wgPageFormsAutocompleteValues = {
		Scientists: { Albert_Einstein: 'Albert Einstein' }
	};
	const widget = buildRealCombo( {
		existingvaluesonly: 'true',
		autocompletesettings: 'Scientists'
	}, 'Deleted_Page' );

	const done = assert.async();
	setTimeout( () => {
		assert.true( widget.$input.hasClass( 'pfComboBoxNewValue' ),
			'stored value with no matching local static value is marked as redlink' );
		done();
	}, 0 );
} );

QUnit.test( 'local source: value found in static values is not marked as redlink', function ( assert ) {
	this.configValues.wgPageFormsAutocompleteValues = {
		Scientists: { Albert_Einstein: 'Albert Einstein' }
	};
	const widget = buildRealCombo( {
		existingvaluesonly: 'true',
		autocompletesettings: 'Scientists'
	}, 'Albert_Einstein' );

	const done = assert.async();
	setTimeout( () => {
		assert.false( widget.$input.hasClass( 'pfComboBoxNewValue' ),
			'stored value matching a local static value is not marked as redlink' );
		done();
	}, 0 );
} );

QUnit.test( 'remote source: value not resolved by the API is marked as redlink', ( assert ) => {
	sinon.replace( $, 'ajax', ( opts ) => {
		opts.success( { pfautocomplete: [] } );
		return { abort: () => {} };
	} );
	const widget = buildRealCombo( {
		existingvaluesonly: 'true',
		autocompletedatatype: 'category',
		autocompletesettings: 'Scientists'
	}, 'Deleted_Page' );

	const done = assert.async();
	setTimeout( () => {
		assert.true( widget.$input.hasClass( 'pfComboBoxNewValue' ),
			'stored value not returned by the remote API is marked as redlink' );
		done();
	}, 0 );
} );

QUnit.test( 'remote source: value resolved by the API is not marked as redlink', ( assert ) => {
	sinon.replace( $, 'ajax', ( opts ) => {
		opts.success( { pfautocomplete: [ { title: 'Albert_Einstein', displaytitle: 'Albert Einstein' } ] } );
		return { abort: () => {} };
	} );
	const widget = buildRealCombo( {
		existingvaluesonly: 'true',
		autocompletedatatype: 'category',
		autocompletesettings: 'Scientists'
	}, 'Albert_Einstein' );

	const done = assert.async();
	setTimeout( () => {
		assert.false( widget.$input.hasClass( 'pfComboBoxNewValue' ),
			'stored value returned by the remote API is not marked as redlink' );
		done();
	}, 0 );
} );

QUnit.test( 'existingvaluesonly unset: no redlink check is performed regardless of data source', ( assert ) => {
	const fetchSpy = sinon.spy( pageforms.ComboBoxDataSource.prototype, 'fetch' );
	const widget = buildRealCombo( {
		autocompletesettings: 'Scientists'
	}, 'Deleted_Page' );

	assert.false( widget.$input.hasClass( 'pfComboBoxNewValue' ),
		'no redlink class is applied when existingvaluesonly is not set' );
	assert.false( fetchSpy.called, 'dataSource.fetch is not called for the redlink check when existingvaluesonly is unset' );
} );
