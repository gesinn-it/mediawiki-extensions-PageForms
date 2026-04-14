global.pageforms = global.pageforms || {};
// Minimal stub for pf.buildAutocompleteParams used inside _fetchRemote
global.pageforms.buildAutocompleteParams = function ( dataType, settings, substr ) {
	const params = { action: 'pfautocomplete', format: 'json', substr: substr };
	if ( dataType ) {
		params[ dataType ] = settings;
	}
	return params;
};
// Stubs for pf.nameAttr / pf.partOfMultiple (shared free functions from ext.pf.js)
global.pageforms.partOfMultiple = function ( element ) {
	return element.attr( 'origname' ) !== undefined;
};
global.pageforms.nameAttr = function ( element ) {
	return global.pageforms.partOfMultiple( element ) ? 'origname' : 'name';
};

require( '../../libs/PF_ComboBoxDataSource.js' );
const sinon = require( 'sinon' );

function createDataSource( overrides ) {
	const ds = new pageforms.ComboBoxDataSource( Object.assign( {
		autocompletedatatype: undefined,
		autocompletesettings: undefined,
		inputId: 'input_1'
	}, overrides ) );
	return ds;
}

QUnit.module( 'PF_ComboBoxDataSource', {
	beforeEach: function () {
		this.configValues = {
			wgPageFormsAutocompleteOnAllChars: false,
			wgPageFormsAutocompleteValues: {},
			wgPageFormsDependentFields: [],
			wgPageFormsFieldProperties: {},
			wgPageFormsEDSettings: {},
			edgValues: {},
			wgScriptPath: ''
		};
		mw.config = {
			get: ( key ) => Object.prototype.hasOwnProperty.call( this.configValues, key )
				? this.configValues[ key ]
				: null
		};
		mw.util = { wikiScript: () => '/api.php' };
		$( '<span><input id="input_1" name="City"></span>' ).appendTo( document.body );
	}
} );

// ===========================================================
// _fetchStaticValues
// ===========================================================

QUnit.test( '_fetchStaticValues returns item matching word-start (onAllChars=false)', function ( assert ) {
	this.configValues.wgPageFormsAutocompleteValues = {
		Scientists: { Albert_Einstein: 'Albert Einstein' }
	};
	const ds = createDataSource( { autocompletesettings: 'Scientists' } );

	const items = ds._fetchStaticValues( 'Alb' );

	assert.strictEqual( items.length, 1 );
	assert.strictEqual( items[ 0 ].title, 'Albert_Einstein' );
	assert.strictEqual( items[ 0 ].displaytitle, 'Albert Einstein' );
} );

QUnit.test( '_fetchStaticValues excludes non-matching values (onAllChars=false)', function ( assert ) {
	this.configValues.wgPageFormsAutocompleteValues = {
		Scientists: { Albert_Einstein: 'Albert Einstein', Nikola_Tesla: 'Nikola Tesla' }
	};
	const ds = createDataSource( { autocompletesettings: 'Scientists' } );

	const items = ds._fetchStaticValues( 'Alb' );

	assert.strictEqual( items.length, 1 );
	assert.strictEqual( items[ 0 ].title, 'Albert_Einstein' );
} );

QUnit.test( '_fetchStaticValues matches anywhere when onAllChars=true', function ( assert ) {
	this.configValues.wgPageFormsAutocompleteOnAllChars = true;
	this.configValues.wgPageFormsAutocompleteValues = {
		Scientists: { Albert_Einstein: 'Albert Einstein', Nikola_Tesla: 'Nikola Tesla' }
	};
	const ds = createDataSource( { autocompletesettings: 'Scientists' } );

	const items = ds._fetchStaticValues( 'ein' );

	assert.strictEqual( items.length, 1 );
	assert.strictEqual( items[ 0 ].title, 'Albert_Einstein' );
} );

QUnit.test( '_fetchStaticValues returns all values when filterValue is empty', function ( assert ) {
	this.configValues.wgPageFormsAutocompleteValues = {
		Scientists: { Albert_Einstein: 'Albert Einstein', Nikola_Tesla: 'Nikola Tesla' }
	};
	const ds = createDataSource( { autocompletesettings: 'Scientists' } );

	const items = ds._fetchStaticValues( '' );

	assert.strictEqual( items.length, 2 );
} );

QUnit.test( '_fetchStaticValues returns empty array for unknown settings key', ( assert ) => {
	const ds = createDataSource( { autocompletesettings: 'UnknownList' } );

	const items = ds._fetchStaticValues( 'foo' );

	assert.deepEqual( items, [] );
} );

// ===========================================================
// _fetchExternalData
// ===========================================================

QUnit.test( '_fetchExternalData returns filtered items from edgValues', function ( assert ) {
	$( '#input_1' ).attr( 'name', 'City' );
	this.configValues.wgPageFormsEDSettings = { City: { title: 'cities' } };
	this.configValues.edgValues = { cities: [ 'Berlin', 'Barcelona', 'Munich' ] };
	const ds = createDataSource( { autocompletesettings: 'external data' } );

	const items = ds._fetchExternalData( 'Ber' );

	assert.strictEqual( items.length, 1 );
	assert.strictEqual( items[ 0 ].title, 'Berlin' );
} );

QUnit.test( '_fetchExternalData returns all items when filterValue is empty', function ( assert ) {
	$( '#input_1' ).attr( 'name', 'City' );
	this.configValues.wgPageFormsEDSettings = { City: { title: 'cities' } };
	this.configValues.edgValues = { cities: [ 'Berlin', 'Barcelona' ] };
	const ds = createDataSource( { autocompletesettings: 'external data' } );

	const items = ds._fetchExternalData( '' );

	assert.strictEqual( items.length, 2 );
} );

QUnit.test( '_fetchExternalData returns empty array when ED settings missing', ( assert ) => {
	const ds = createDataSource( { autocompletesettings: 'external data' } );

	const items = ds._fetchExternalData( 'foo' );

	assert.deepEqual( items, [] );
} );

// ===========================================================
// _fetchDependentField
// ===========================================================

QUnit.test( '_fetchDependentField issues correct SMW API params', ( assert ) => {
	$( '<input name="Country" value="Germany" autocompletesettings="Country">' ).appendTo( document.body );
	$( '#input_1' ).attr( 'autocompletesettings', 'City' );

	const ds = createDataSource( { autocompletesettings: 'City' } );

	let receivedData;
	sinon.replace( $, 'ajax', ( opts ) => {
		receivedData = opts.data;
		opts.success( {
			pfautocomplete: [ { title: 'Berlin_(DE)', displaytitle: 'Berlin' } ]
		} );
		return {};
	} );

	const depOpts = {
		prop: 'City',
		base_prop: 'Country',
		base_value: 'Germany'
	};
	ds.getDependentFieldOpts = () => depOpts;

	const done = assert.async();
	ds._fetchDependentField( 'Country', '' ).then( ( items ) => {
		assert.strictEqual( receivedData.property, 'City', 'property param set' );
		assert.strictEqual( receivedData.baseprop, 'Country', 'baseprop param set' );
		assert.strictEqual( receivedData.basevalue, 'Germany', 'basevalue param set' );
		assert.strictEqual( items[ 0 ].title, 'Berlin_(DE)' );
		assert.strictEqual( items[ 0 ].displaytitle, 'Berlin' );
		done();
	} );
} );

QUnit.test( '_fetchDependentField filters items by word-start when substr given', ( assert ) => {
	const ds = createDataSource( {} );
	ds.getDependentFieldOpts = () => ( { prop: 'City', base_prop: 'Country', base_value: 'Germany' } );

	sinon.replace( $, 'ajax', ( opts ) => {
		opts.success( {
			pfautocomplete: [
				{ title: 'Berlin_(DE)', displaytitle: 'Berlin' },
				{ title: 'Munich', displaytitle: 'Munich' }
			]
		} );
		return {};
	} );

	const done = assert.async();
	ds._fetchDependentField( 'Country', 'Ber' ).then( ( items ) => {
		assert.strictEqual( items.length, 1 );
		assert.strictEqual( items[ 0 ].title, 'Berlin_(DE)' );
		done();
	} );
} );

QUnit.test( '_fetchDependentField resolves empty array on error', ( assert ) => {
	const ds = createDataSource( {} );
	ds.getDependentFieldOpts = () => ( { prop: 'City', base_prop: 'Country', base_value: 'Germany' } );

	sinon.replace( $, 'ajax', ( opts ) => {
		opts.error();
		return {};
	} );

	const done = assert.async();
	ds._fetchDependentField( 'Country', '' ).then( ( items ) => {
		assert.deepEqual( items, [] );
		done();
	} );
} );

QUnit.test( '_fetchDependentField resolves empty array when opts incomplete', ( assert ) => {
	const ds = createDataSource( {} );
	ds.getDependentFieldOpts = () => ( {} );

	const done = assert.async();
	ds._fetchDependentField( 'Country', '' ).then( ( items ) => {
		assert.deepEqual( items, [] );
		done();
	} );
} );

// ===========================================================
// _fetchRemote
// ===========================================================

QUnit.test( '_fetchRemote passes buildAutocompleteParams result to $.ajax', ( assert ) => {
	const ds = createDataSource( {
		autocompletedatatype: 'category',
		autocompletesettings: 'Scientists'
	} );

	let receivedData;
	sinon.replace( $, 'ajax', ( opts ) => {
		receivedData = opts.data;
		opts.success( { pfautocomplete: [] } );
		return { abort: () => {} };
	} );

	ds._fetchRemote( 'Alb' );

	assert.strictEqual( receivedData.action, 'pfautocomplete' );
	assert.strictEqual( receivedData.category, 'Scientists' );
	assert.strictEqual( receivedData.substr, 'Alb' );
} );

QUnit.test( '_fetchRemote aborts previous pending request', ( assert ) => {
	const ds = createDataSource( {
		autocompletedatatype: 'category',
		autocompletesettings: 'Scientists'
	} );

	const abortSpy = sinon.spy();
	ds._pendingRequest = { abort: abortSpy };

	sinon.replace( $, 'ajax', ( opts ) => {
		opts.success( { pfautocomplete: [] } );
		return { abort: () => {} };
	} );

	ds._fetchRemote( 'Alb' );

	assert.ok( abortSpy.calledOnce, 'previous request was aborted' );
} );

QUnit.test( '_fetchRemote resolves empty array on AJAX error', ( assert ) => {
	const ds = createDataSource( {
		autocompletedatatype: 'category',
		autocompletesettings: 'Scientists'
	} );

	sinon.replace( $, 'ajax', ( opts ) => {
		opts.error();
		return { abort: () => {} };
	} );

	const done = assert.async();
	ds._fetchRemote( 'X' ).then( ( items ) => {
		assert.deepEqual( items, [] );
		done();
	} );
} );

// ===========================================================
// dependentOn
// ===========================================================

QUnit.test( 'dependentOn returns null when field has no dependency', function ( assert ) {
	this.configValues.wgPageFormsDependentFields = [ [ 'Country', 'SomeOtherField' ] ];
	const ds = createDataSource( {} );

	assert.strictEqual( ds.dependentOn(), null );
} );

QUnit.test( 'dependentOn returns source field name when dependency exists', function ( assert ) {
	$( '#input_1' ).attr( 'name', 'City' );
	this.configValues.wgPageFormsDependentFields = [ [ 'Country', 'City' ] ];
	const ds = createDataSource( {} );

	assert.strictEqual( ds.dependentOn(), 'Country' );
} );

// ===========================================================
// getDependentFieldOpts
// ===========================================================

QUnit.test( 'getDependentFieldOpts prefers canonical base value attribute', ( assert ) => {
	$( '<input name="Country" value="Germany display" data-pf-canonical-value="Germany" autocompletesettings="Country">' )
		.appendTo( document.body );
	$( '#input_1' ).attr( 'autocompletesettings', 'City' );
	const ds = createDataSource( {} );

	const opts = ds.getDependentFieldOpts( 'Country' );

	assert.strictEqual( opts.base_value, 'Germany' );
} );

QUnit.test( 'getDependentFieldOpts falls back to element value when no canonical attr', ( assert ) => {
	$( '<input name="Country" value="Germany" autocompletesettings="Country">' ).appendTo( document.body );
	$( '#input_1' ).attr( 'autocompletesettings', 'City' );
	const ds = createDataSource( {} );

	const opts = ds.getDependentFieldOpts( 'Country' );

	assert.strictEqual( opts.base_value, 'Germany' );
} );

// ===========================================================
// fetch integration
// ===========================================================

QUnit.test( 'fetch delegates to _fetchStaticValues for local settings', function ( assert ) {
	this.configValues.wgPageFormsAutocompleteValues = {
		Fruits: { apple: 'Apple', apricot: 'Apricot', banana: 'Banana' }
	};
	const ds = createDataSource( { autocompletesettings: 'Fruits' } );
	ds.dependentOn = () => null;

	const done = assert.async();
	ds.fetch( 'ap', false ).then( ( items ) => {
		assert.strictEqual( items.length, 2, 'both apple and apricot match "ap"' );
		done();
	} );
} );

QUnit.test( 'fetch with showAllValues=true returns all local items', function ( assert ) {
	this.configValues.wgPageFormsAutocompleteValues = {
		Fruits: { apple: 'Apple', banana: 'Banana' }
	};
	const ds = createDataSource( { autocompletesettings: 'Fruits' } );
	ds.dependentOn = () => null;

	const done = assert.async();
	ds.fetch( 'xyz', true ).then( ( items ) => {
		assert.strictEqual( items.length, 2, 'showAllValues=true ignores filter term' );
		done();
	} );
} );
