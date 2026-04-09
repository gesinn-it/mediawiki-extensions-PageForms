/**
 * PF_ComboBoxDataSource.js
 *
 * Data-fetching layer for pf.ComboBoxInput. Handles all autocomplete
 * value retrieval: remote API calls, static local values, external data,
 * and dependent-field lookups.
 *
 * Exposes pf.ComboBoxDataSource.
 *
 * @param {jQuery} $
 * @param {mw} mw
 * @param {Object} pf
 *
 * @license GNU GPL v2+
 */

( function ( $, mw, pf ) {

	/**
	 * @constructor
	 * @param {Object} config
	 * @param {string|undefined} config.autocompletedatatype
	 * @param {string|undefined} config.autocompletesettings
	 * @param {string} config.inputId  ID of the input element (without leading '#')
	 */
	pf.ComboBoxDataSource = function ( config ) {
		this.dataType = config.autocompletedatatype;
		this.settings = config.autocompletesettings;
		this.inputId = config.inputId;
		this._pendingRequest = null;
	};

	/**
	 * Fetch autocomplete items for the given search term.
	 *
	 * @param {string}  substr        Current input value (search term)
	 * @param {boolean} [showAllValues=true]
	 *   When true and a local data source is used, all values are returned
	 *   regardless of the search term.  Has no effect on remote sources.
	 * @return {jQuery.Promise.<Array.<{title: string, displaytitle: string}>>}
	 */
	pf.ComboBoxDataSource.prototype.fetch = function ( substr, showAllValues ) {
		if ( this.dataType !== undefined ) {
			return this._fetchRemote( substr );
		}

		const depOn = this.dependentOn();
		// When showAllValues is true (default on focus), local sources return
		// everything; when false (on keyup), they filter by the current value.
		const effectiveSubstr = showAllValues ? '' : substr;

		if ( depOn === null ) {
			if ( this.settings === 'external data' ) {
				return $.when( this._fetchExternalData( effectiveSubstr ) );
			}
			return $.when( this._fetchStaticValues( effectiveSubstr ) );
		}

		// Dependent field: server returns all values for the base value;
		// client-side filtering is applied using the current input value.
		return this._fetchDependentField( depOn, substr );
	};

	/**
	 * Fetch items from the pfautocomplete API for remote data types
	 * (category, namespace, property, concept, cargo field, wikidata).
	 *
	 * @private
	 * @param {string} substr
	 * @return {jQuery.Promise}
	 */
	pf.ComboBoxDataSource.prototype._fetchRemote = function ( substr ) {
		let settings = this.settings;

		// Wikidata: replace [FieldName] placeholders with the current value of
		// the named field in the form.  Do NOT call encodeURIComponent — jQuery's
		// $.ajax data-encoding handles URL encoding.
		if ( this.dataType === 'wikidata' ) {
			settings.split( '&' ).forEach( ( term ) => {
				const parts = term.split( '=' );
				if ( parts.length < 2 ) {
					return;
				}
				const match = parts[ 1 ].match( /\[(.*?)\]/ );
				if ( match ) {
					const depVal = $( '[name="' + parts[ 1 ] + '"]' ).val();
					if ( depVal && depVal.trim().length ) {
						settings = settings.replace( parts[ 1 ], depVal );
					}
				}
			} );
		}

		const params = pf.buildAutocompleteParams( this.dataType, settings, substr );
		const deferred = $.Deferred();

		if ( this._pendingRequest !== null ) {
			this._pendingRequest.abort();
		}

		this._pendingRequest = $.ajax( {
			url: mw.util.wikiScript( 'api' ),
			data: params,
			dataType: 'json',
			success: function ( data ) {
				deferred.resolve( data.pfautocomplete || [] );
			},
			error: function () {
				deferred.resolve( [] );
			}
		} );

		return deferred.promise();
	};

	/**
	 * Fetch items from wgPageFormsAutocompleteValues (local, synchronous).
	 *
	 * @private
	 * @param {string} filterValue  Search term; empty string returns all values.
	 * @return {Array.<{title: string, displaytitle: string}>}
	 */
	pf.ComboBoxDataSource.prototype._fetchStaticValues = function ( filterValue ) {
		const data = ( mw.config.get( 'wgPageFormsAutocompleteValues' ) || {} )[ this.settings ];
		const onAllChars = mw.config.get( 'wgPageFormsAutocompleteOnAllChars' );
		const items = [];

		if ( !data || typeof data !== 'object' ) {
			return items;
		}

		for ( const key in data ) {
			const title = Array.isArray( data ) ? data[ key ] : key;
			const displaytitle = data[ key ];
			if (
				this._matchesFilter( displaytitle, filterValue, onAllChars ) ||
				this._matchesFilter( title, filterValue, onAllChars )
			) {
				items.push( { title: title, displaytitle: displaytitle } );
			}
		}

		return items;
	};

	/**
	 * Fetch items from External Data (edgValues, synchronous).
	 *
	 * @private
	 * @param {string} filterValue  Search term; empty string returns all values.
	 * @return {Array.<{title: string, displaytitle: string}>}
	 */
	pf.ComboBoxDataSource.prototype._fetchExternalData = function ( filterValue ) {
		const inputId = '#' + this.inputId;
		const name = $( inputId ).attr( pf.nameAttr( $( inputId ) ) );
		const edSettings = mw.config.get( 'wgPageFormsEDSettings' ) || {};
		const edValues = mw.config.get( 'edgValues' ) || {};
		const onAllChars = mw.config.get( 'wgPageFormsAutocompleteOnAllChars' );
		const items = [];

		if ( !edSettings[ name ] || !edSettings[ name ].title ) {
			return items;
		}

		const titles = edValues[ edSettings[ name ].title ];
		if ( !titles ) {
			return items;
		}

		titles.forEach( ( title ) => {
			if ( this._matchesFilter( title, filterValue, onAllChars ) ) {
				items.push( { title: title, displaytitle: title } );
			}
		} );

		return items;
	};

	/**
	 * Fetch items via the pfautocomplete API for a dependent field.
	 * The API call does not include a substr — the server returns all values
	 * for the given base value, and client-side filtering is applied.
	 *
	 * @private
	 * @param {string} depOn   Name of the field this field depends on
	 * @param {string} substr  Current input value used for client-side filtering
	 * @return {jQuery.Promise}
	 */
	pf.ComboBoxDataSource.prototype._fetchDependentField = function ( depOn, substr ) {
		const depOpts = this.getDependentFieldOpts( depOn );
		const deferred = $.Deferred();

		if (
			depOpts.prop === undefined ||
			depOpts.base_prop === undefined ||
			depOpts.base_value === undefined
		) {
			deferred.resolve( [] );
			return deferred.promise();
		}

		const params = { action: 'pfautocomplete', format: 'json' };
		if ( !depOpts.prop.includes( '|' ) ) {
			// SMW
			params.property = depOpts.prop;
			params.baseprop = depOpts.base_prop;
			params.basevalue = depOpts.base_value;
		} else {
			// Cargo
			const propParts = depOpts.prop.split( '|' );
			const baseParts = depOpts.base_prop.split( '|' );
			params.cargo_table = propParts[ 0 ];
			params.cargo_field = propParts[ 1 ];
			params.base_cargo_table = baseParts[ 0 ];
			params.base_cargo_field = baseParts[ 1 ];
			params.basevalue = depOpts.base_value;
		}

		const onAllChars = mw.config.get( 'wgPageFormsAutocompleteOnAllChars' );
		const self = this;

		$.ajax( {
			url: mw.config.get( 'wgScriptPath' ) + '/api.php',
			data: params,
			dataType: 'json',
			success: function ( response ) {
				if ( response.error !== undefined || !response.pfautocomplete || response.pfautocomplete.length === 0 ) {
					deferred.resolve( [] );
					return;
				}

				const items = [];
				response.pfautocomplete.forEach( ( item ) => {
					const title = item.title;
					const displaytitle = item.displaytitle !== undefined ? item.displaytitle : item.title;
					if (
						!substr ||
						self._matchesFilter( displaytitle, substr, onAllChars ) ||
						self._matchesFilter( title, substr, onAllChars )
					) {
						items.push( { title: title, displaytitle: displaytitle } );
					}
				} );
				deferred.resolve( items );
			},
			error: function () {
				deferred.resolve( [] );
			}
		} );

		return deferred.promise();
	};

	/**
	 * Test whether a string matches the given filter value.
	 *
	 * @private
	 * @param {string}  string
	 * @param {string}  filterValue  Empty string matches everything
	 * @param {boolean} onAllChars   When true, match anywhere; otherwise match word-start
	 * @return {boolean}
	 */
	pf.ComboBoxDataSource.prototype._matchesFilter = function ( string, filterValue, onAllChars ) {
		if ( !filterValue ) {
			return true;
		}
		if ( onAllChars ) {
			return this.getConditionForAutocompleteOnAllChars( string, filterValue );
		}
		return this.checkIfAnyWordStartsWithInputValue( string, filterValue );
	};

	// ===========================================================
	// DOM helpers — moved from pf.ComboBoxInput
	// pf.nameAttr() and pf.partOfMultiple() live in ext.pf.js (shared with select2)
	// ===========================================================

	/**
	 * Returns the name of the field this input depends on, or null.
	 *
	 * @return {string|null}
	 */
	pf.ComboBoxDataSource.prototype.dependentOn = function () {
		const inputId = '#' + this.inputId;
		const nameAttr = pf.nameAttr( $( inputId ) );
		const name = $( inputId ).attr( nameAttr );
		const deps = mw.config.get( 'wgPageFormsDependentFields' ) || [];
		for ( let i = 0; i < deps.length; i++ ) {
			if ( deps[ i ][ 1 ] === name ) {
				return deps[ i ][ 0 ];
			}
		}
		return null;
	};

	/**
	 * Returns the names of fields that depend on this input.
	 *
	 * @return {Array.<string>}
	 */
	pf.ComboBoxDataSource.prototype.dependentOnMe = function () {
		const inputId = '#' + this.inputId;
		const nameAttr = pf.nameAttr( $( inputId ) );
		const name = $( inputId ).attr( nameAttr );
		const dependent = [];
		const deps = mw.config.get( 'wgPageFormsDependentFields' ) || [];
		for ( let i = 0; i < deps.length; i++ ) {
			if ( deps[ i ][ 0 ] === name ) {
				dependent.push( deps[ i ][ 1 ] );
			}
		}
		return dependent;
	};

	/**
	 * Returns dependent-field options needed to build the API request.
	 *
	 * @param {string} depOn  Name of the field this input depends on
	 * @return {Object} opts with .prop, .base_prop, .base_value
	 */
	pf.ComboBoxDataSource.prototype.getDependentFieldOpts = function ( depOn ) {
		const inputId = '#' + this.inputId;
		const opts = {};
		let $baseElement;

		if ( pf.partOfMultiple( $( inputId ) ) ) {
			$baseElement = $( inputId ).closest( '.multipleTemplateInstance' )
				.find( '[origname="' + depOn + '"]' );
		} else {
			$baseElement = $( '[name="' + depOn + '"]' );
		}

		opts.base_value = $baseElement.attr( 'data-pf-canonical-value' ) || $baseElement.val();
		opts.base_prop = mw.config.get( 'wgPageFormsFieldProperties' )[ depOn ] ||
			( $baseElement.attr( 'autocompletesettings' ) === 'external data'
				? $baseElement.attr( 'data-autocomplete' )
				: $baseElement.attr( 'autocompletesettings' ) );
		opts.prop = $( inputId ).attr( 'autocompletesettings' ).split( ',' )[ 0 ];

		return opts;
	};

	// ===========================================================
	// Filter helpers
	// ===========================================================

	/**
	 * Returns whether any "word" in the string starts with curValue.
	 * Words are delimited by common separators (/, (, ), |, s, -, ', ").
	 *
	 * @param {string} string
	 * @param {string} curValue
	 * @return {boolean}
	 */
	pf.ComboBoxDataSource.prototype.checkIfAnyWordStartsWithInputValue = function ( string, curValue ) {
		const wordSeparators = [
			'/', '(', ')', '|', 's'
		].map( ( p ) => '\\' + p ).concat( '^', '-', "'", '"' );
		const regex = new RegExp( '(' + wordSeparators.join( '|' ) + ')' + curValue.toLowerCase() );
		return string.toLowerCase().match( regex ) !== null;
	};

	/**
	 * Returns whether the string contains curValue anywhere (case-insensitive).
	 *
	 * @param {string} string
	 * @param {string} curValue
	 * @return {boolean}
	 */
	pf.ComboBoxDataSource.prototype.getConditionForAutocompleteOnAllChars = function ( string, curValue ) {
		return string.toLowerCase().includes( curValue.toLowerCase() );
	};

}( jQuery, mediaWiki, pageforms ) );
