global.pageforms = global.pageforms || {};
// Minimal stub for pf.buildAutocompleteParams used by DataSource
global.pageforms.buildAutocompleteParams = function ( dataType, settings, substr ) {
	const params = { action: 'pfautocomplete', format: 'json', substr: substr };
	if ( dataType ) {
		params[ dataType ] = settings;
	}
	return params;
};
// Stub for pf.highlightText (shared free function from ext.pf.js)
global.pageforms.highlightText = ( searchTerm, s ) => s;
require( '../../libs/PF_ComboBoxDataSource.js' );
require( '../../libs/PF_ComboBoxInput.js' );
const sinon = require( 'sinon' );

function createComboBoxDouble( overrides ) {
	const combo = {
		config: {},
		itemFound: false,
		titleByDisplayValue: {},
		displayByTitle: {},
		getInputId: () => 'input_1',
		_currentValue: '',
		getValue: function () {
			return this._currentValue;
		},
		setValue: function ( value ) {
			this._currentValue = value;
			this.$input.val( value );
		},
		setOptions: sinon.spy(),
		pushPending: () => {},
		popPending: () => {},
		highlightText: ( text ) => text,
		getCanonicalValueForInput: pageforms.ComboBoxInput.prototype.getCanonicalValueForInput,
		getDisplayValueForCanonicalInput: pageforms.ComboBoxInput.prototype.getDisplayValueForCanonicalInput,
		syncCanonicalValue: pageforms.ComboBoxInput.prototype.syncCanonicalValue,
		syncDisplayValueFromCanonical: pageforms.ComboBoxInput.prototype.syncDisplayValueFromCanonical,
		bindCanonicalSubmitHandler: pageforms.ComboBoxInput.prototype.bindCanonicalSubmitHandler,
		_renderItems: pageforms.ComboBoxInput.prototype._renderItems,
		$input: $( '#input_1' ),
		$element: $( '<span>' ),
		// Default dataSource: no data type, returns nothing
		dataSource: {
			dataType: undefined,
			settings: undefined,
			fetch: sinon.stub().returns( $.Deferred().resolve( [] ).promise() )
		}
	};

	Object.keys( overrides || {} ).forEach( ( key ) => {
		combo[ key ] = overrides[ key ];
	} );
	return combo;
}

QUnit.module( 'PF_ComboBoxInput displaytitle handling', {
	beforeEach: function () {
		this.configValues = {
			wgPageFormsAutocompleteOnAllChars: true,
			wgPageFormsAutocompleteValues: {},
			wgPageFormsFieldProperties: {},
			wgScriptPath: '',
			wgPageFormsScriptPath: ''
		};
		mw.config = {
			get: ( key ) => Object.prototype.hasOwnProperty.call( this.configValues, key ) ? this.configValues[ key ] : null
		};
		mw.util = { wikiScript: () => '/api.php' };
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

QUnit.test( 'remote autocomplete keeps displaytitle visible and tracks canonical title', ( assert ) => {
	const combo = createComboBoxDouble( {
		dataSource: {
			dataType: 'category',
			fetch: sinon.stub().returns( $.Deferred().resolve( [
				{ title: 'Albert_Einstein', displaytitle: 'Albert Einstein' }
			] ).promise() )
		},
		_currentValue: 'Alb'
	} );

	const done = assert.async();
	pageforms.ComboBoxInput.prototype.setValues.call( combo, false );

	setTimeout( () => {
		const options = combo.setOptions.firstCall.args[ 0 ];
		assert.strictEqual( options[ 0 ].data, 'Albert Einstein' );
		assert.strictEqual( options[ 0 ].label, 'Albert Einstein' );
		combo.syncCanonicalValue( 'Albert Einstein' );
		assert.strictEqual( combo.$input.attr( 'data-pf-canonical-value' ), 'Albert_Einstein' );
		done();
	}, 0 );
} );

QUnit.test( 'remote autocomplete accepts canonical title for itemFound', ( assert ) => {
	const combo = createComboBoxDouble( {
		dataSource: {
			dataType: 'category',
			fetch: sinon.stub().returns( $.Deferred().resolve( [
				{ title: 'Albert_Einstein', displaytitle: 'Albert Einstein' }
			] ).promise() )
		},
		_currentValue: 'Albert_Einstein'
	} );

	const done = assert.async();
	pageforms.ComboBoxInput.prototype.setValues.call( combo, false );

	setTimeout( () => {
		assert.true( combo.itemFound );
		done();
	}, 0 );
} );

QUnit.test( 'local autocomplete keeps displaytitle visible and tracks canonical title', ( assert ) => {
	const combo = createComboBoxDouble( {
		dataSource: {
			dataType: undefined,
			fetch: sinon.stub().returns( $.Deferred().resolve( [
				{ title: 'Albert_Einstein', displaytitle: 'Albert Einstein' }
			] ).promise() )
		},
		_currentValue: 'Alb'
	} );

	const done = assert.async();
	pageforms.ComboBoxInput.prototype.setValues.call( combo, false );

	setTimeout( () => {
		const options = combo.setOptions.firstCall.args[ 0 ];
		assert.strictEqual( options[ 0 ].data, 'Albert Einstein' );
		assert.strictEqual( options[ 0 ].label, 'Albert Einstein' );
		combo.syncCanonicalValue( 'Albert Einstein' );
		assert.strictEqual( combo.$input.attr( 'data-pf-canonical-value' ), 'Albert_Einstein' );
		done();
	}, 0 );
} );

QUnit.test( 'dependent autocomplete keeps displaytitle visible and tracks canonical title', ( assert ) => {
	const combo = createComboBoxDouble( {
		dataSource: {
			dataType: undefined,
			fetch: sinon.stub().returns( $.Deferred().resolve( [
				{ title: 'Berlin_(DE)', displaytitle: 'Berlin' }
			] ).promise() )
		},
		_currentValue: 'Ber'
	} );

	const done = assert.async();
	pageforms.ComboBoxInput.prototype.setValues.call( combo, false );

	setTimeout( () => {
		const options = combo.setOptions.firstCall.args[ 0 ];
		assert.strictEqual( options[ 0 ].data, 'Berlin' );
		assert.strictEqual( options[ 0 ].label, 'Berlin' );
		combo.syncCanonicalValue( 'Berlin' );
		assert.strictEqual( combo.$input.attr( 'data-pf-canonical-value' ), 'Berlin_(DE)' );
		done();
	}, 0 );
} );

QUnit.test( 'setValues shows "no matches" when dataSource returns empty array', ( assert ) => {
	const combo = createComboBoxDouble( {
		dataSource: {
			dataType: undefined,
			fetch: sinon.stub().returns( $.Deferred().resolve( [] ).promise() )
		},
		_currentValue: 'xyz'
	} );

	const done = assert.async();
	pageforms.ComboBoxInput.prototype.setValues.call( combo, false );

	setTimeout( () => {
		const options = combo.setOptions.firstCall.args[ 0 ];
		assert.strictEqual( options.length, 1 );
		assert.true( options[ 0 ].disabled );
		done();
	}, 0 );
} );

QUnit.test( 'setValues shows "too short" hint and does not call fetch when remote input is empty', ( assert ) => {
	const fetchSpy = sinon.spy();
	const combo = createComboBoxDouble( {
		dataSource: { dataType: 'category', fetch: fetchSpy },
		_currentValue: ''
	} );

	pageforms.ComboBoxInput.prototype.setValues.call( combo, false );

	const options = combo.setOptions.firstCall.args[ 0 ];
	assert.true( options[ 0 ].disabled );
	assert.false( fetchSpy.called, 'fetch must not be called for empty remote input' );
} );

QUnit.test( 'submit handler rewrites visible displaytitle to canonical title', ( assert ) => {
	const combo = createComboBoxDouble( {
		getValue: function () {
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

QUnit.test( 'existing canonical value is displayed as displaytitle after options load', ( assert ) => {
	const combo = createComboBoxDouble( {
		dataSource: {
			dataType: undefined,
			fetch: sinon.stub().returns( $.Deferred().resolve( [
				{ title: 'Albert_Einstein', displaytitle: 'Albert Einstein' }
			] ).promise() )
		},
		_currentValue: 'Albert_Einstein'
	} );

	const done = assert.async();
	pageforms.ComboBoxInput.prototype.setValues.call( combo, false );

	setTimeout( () => {
		assert.strictEqual( combo.getValue(), 'Albert Einstein' );
		assert.strictEqual( combo.$input.attr( 'data-pf-canonical-value' ), 'Albert_Einstein' );
		done();
	}, 0 );
} );

QUnit.test( 'bootstrapMapsFromElement populates maps from option value/text attributes', ( assert ) => {
	// Simulates a server-rendered <select> where PHP set value=canonical and
	// text=displaytitle on the selected <option> (the new behaviour added to
	// PF_ComboBoxInput.php).  No AJAX call should be needed to resolve the mapping.
	const $select = $( '<select>' )
		.append( $( '<option>' ).attr( 'value', 'Product:Caddy' ).text( 'Caddy' ).prop( 'selected', true ) );

	const combo = createComboBoxDouble( {
		titleByDisplayValue: {},
		displayByTitle: {},
		_currentValue: 'Caddy'
	} );

	pageforms.ComboBoxInput.prototype.bootstrapMapsFromElement.call( combo, $select );

	assert.strictEqual( combo.titleByDisplayValue[ 'Caddy' ], 'Product:Caddy',
		'titleByDisplayValue maps display title to canonical' );
	assert.strictEqual( combo.displayByTitle[ 'Product:Caddy' ], 'Caddy',
		'displayByTitle maps canonical to display title' );
	assert.true( combo.itemFound, 'itemFound is set when current value matches' );
} );

QUnit.test( 'bootstrapMapsFromElement falls back to text when no value attribute', ( assert ) => {
	// When PHP renders <option selected>Caddy</option> without a value attribute
	// (e.g. when possible_values is null), text and canonical are both "Caddy".
	const $select = $( '<select>' )
		.append( $( '<option>' ).text( 'Caddy' ).prop( 'selected', true ) );

	const combo = createComboBoxDouble( {
		titleByDisplayValue: {},
		displayByTitle: {},
		_currentValue: 'Caddy'
	} );

	pageforms.ComboBoxInput.prototype.bootstrapMapsFromElement.call( combo, $select );

	assert.strictEqual( combo.titleByDisplayValue[ 'Caddy' ], 'Caddy',
		'titleByDisplayValue uses text as canonical fallback' );
	assert.strictEqual( combo.displayByTitle[ 'Caddy' ], 'Caddy',
		'displayByTitle identity mapping for no-value option' );
	assert.true( combo.itemFound, 'itemFound is set when current value matches' );
} );
