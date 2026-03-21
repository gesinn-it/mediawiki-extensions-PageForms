/**
 * SF_Select input type for Page Forms.
 *
 * Dynamically populates a <select> element with values retrieved from an
 * SMW #ask query or a parser function, via the `sformsselect` API.
 *
 * Originally maintained as the SemanticFormsSelect extension (res/sfs.js).
 *
 * @author Jason Zhang
 * @author Toni Hermoso Pulido
 * @author Alexander Gesinn
 * @file
 */

/* global mediaWiki */

( function ( $, mw, pf ) {
	'use strict';

	/**
	 * Reads all SF_Select field configs from the `sf_select` mw.config variable,
	 * registers a change handler on the form, and triggers initial population.
	 *
	 * Config object shape per field:
	 *   sep:              string   — value separator
	 *   selectquery:      string?  — SMW #ask query with @@@@  placeholder
	 *   selectfunction:   string?  — parser function with @@@@ placeholder
	 *   label:            boolean  — trailing (...) is the display label
	 *   valuetemplate:    string   — template name of the driving field
	 *   valuefield:       string   — field name of the driving field
	 *   selecttemplate:   string   — template name of the dependent select
	 *   selectfield:      string   — field name of the dependent select
	 *   selectismultiple: boolean  — whether selecttemplate is a multiple template
	 *   selectrm:         boolean  — remove instance div when value becomes invalid
	 */
	function initialize() {
		$( () => {
			const sfsObjects = getSfsObjects();
			registerChangeHandlers( sfsObjects );
			populateSelectFields( sfsObjects );
		} );
	}

	function registerChangeHandlers( sfsObjects ) {
		$( 'form#pfForm' ).on( 'change pf-combobox-choose', ( event ) => {
			handleChange( event.target, sfsObjects );
		} );
	}

	function populateSelectFields( sfsObjects ) {
		for ( let i = 0; i < sfsObjects.length; i++ ) {
			const sfsObject = sfsObjects[ i ];
			// Support multiple-instance templates: select all inputs starting
			// with valuetemplate and containing valuefield, but skip hidden
			// [map_field] inputs.
			const $objs = $( '[name^="' + sfsObject.valuetemplate + '"][name*="[' + sfsObject.valuefield + ']"]' )
				.not( '[name*="[map_field]"]' );
			$objs.trigger( 'change' );
		}
	}

	/**
	 * Read and deduplicate SF_Select configs from mw.config.
	 *
	 * For multiple-instance templates, only one entry per (selecttemplate,
	 * selectfield) pair is kept — the JS handles all instances via name
	 * pattern matching in getSelectFieldPat().
	 *
	 * @return {Array} deduplicated SF_Select config objects
	 */
	function getSfsObjects() {
		const sfSelectConfig = mw.config.get( 'sf_select' );
		if ( !sfSelectConfig ) {
			return [];
		}
		const objects = JSON.parse( sfSelectConfig );
		const distinctObjects = [];
		for ( let i = 0; i < objects.length; i++ ) {
			let found = false;
			const of = objects[ i ];
			if ( !of.selectismultiple ) {
				distinctObjects.push( of );
				continue;
			}
			for ( let j = 0; j < distinctObjects.length; j++ ) {
				const nf = distinctObjects[ j ];
				if ( of.selecttemplate === nf.selecttemplate && of.selectfield === nf.selectfield ) {
					found = true;
					break;
				}
			}
			if ( !found ) {
				distinctObjects.push( of );
			}
		}
		return distinctObjects;
	}

	/**
	 * Extract the raw field name and current values from a form element.
	 *
	 * For checkboxes the name has a trailing `[value]` component that is
	 * stripped, and the value is normalised to `'true'`/`'false'`.
	 *
	 * @param {jQuery} $element the form element
	 * @return {{name: string, values: string[]}}
	 */
	const getRawNameAndValues = ( $element ) => {
		let name = $element.attr( 'name' );
		let values;
		if ( $element.attr( 'type' ) === 'checkbox' ) {
			name = name.slice( 0, name.indexOf( '[value]' ) );
			values = $element.is( ':checked' ) ? [ 'true' ] : [ 'false' ];
		} else {
			const selectedValue = $element.val();
			if ( selectedValue ) {
				if ( Array.isArray( selectedValue ) ) {
					values = selectedValue;
				} else {
					values = $.map( selectedValue.split( ';' ), $.trim );
				}
			} else {
				values = [];
			}
		}
		return { name, values };
	};

	/**
	 * @param {EventTarget} src
	 * @param {Array} sfsObjects
	 */
	function handleChange( src, sfsObjects ) {
		if ( src.tagName.toLowerCase() !== 'select' && src.tagName.toLowerCase() !== 'input' ) {
			return;
		}
		const $src = $( src );
		const raw = getRawNameAndValues( $src );
		if ( !raw.name ) {
			return;
		}
		const lookupOriginalValue = pf.originalValueLookup( $src );
		const originalValues = raw.values.map( lookupOriginalValue );
		const srcName = parseFieldIdentifier( raw.name );

		for ( let i = 0; i < sfsObjects.length; i++ ) {
			if ( sfsObjects[ i ].hasOwnProperty( 'staticvalue' ) && sfsObjects[ i ].staticvalue ) {
				changeSelected( sfsObjects[ i ], srcName );
			} else {
				executeQuery( sfsObjects[ i ], srcName, originalValues );
			}
		}
	}

	/**
	 * Parse a field name of the form `TEMPLATE[INDEX][PROPERTY][]?` into an
	 * object `{ template, index, property, isList }`.
	 *
	 * @param {string} name
	 * @return {{ template: string, index: string|null, property: string, isList: boolean }}
	 */
	function parseFieldIdentifier( name ) {
		const names = name.split( '[' );
		const nameObj = { template: names[ 0 ] };
		if ( names[ names.length - 1 ] === ']' ) {
			nameObj.isList = true;
			let property = names[ names.length - 2 ];
			property = property.slice( 0, property.length - 1 );
			nameObj.property = property;
			if ( names.length === 4 ) {
				let index = names[ 1 ];
				index = index.slice( 0, index.length - 1 );
				nameObj.index = index;
			} else {
				nameObj.index = null;
			}
		} else {
			nameObj.isList = false;
			let property = names[ names.length - 1 ];
			property = property.slice( 0, property.length - 1 );
			nameObj.property = property;
			if ( names.length === 3 ) {
				let index = names[ 1 ];
				index = index.slice( 0, index.length - 1 );
				nameObj.index = index;
			} else {
				nameObj.index = null;
			}
		}
		return nameObj;
	}

	function changeSelected( sfsObject, nameobj ) {
		const selectPat = getSelectFieldPat( nameobj, sfsObject );
		$( selectPat ).each( ( index, element ) => {
			let selectedValues = $( element ).val();
			if ( !selectedValues && sfsObject.hasOwnProperty( 'curvalues' ) ) {
				selectedValues = sfsObject.curvalues;
			}
			if ( !selectedValues ) {
				selectedValues = [];
			} else if ( !$.isArray( selectedValues ) ) {
				selectedValues = [ selectedValues ];
			}
			if ( element.options && element.options.length > 0 ) {
				const options = $.map( element.options, ( option ) => option.value );
				for ( let c = 0; c < selectedValues.length; c++ ) {
					if ( $.inArray( selectedValues[ c ], options ) ) {
						const changed = $( element ).attr( 'data-changed' );
						if ( changed ) {
							$( element ).val( selectedValues[ c ] ).trigger( 'change' );
						}
					}
				}
			}
		} );
	}

	function executeQuery( sfsObject, srcName, v ) {
		if ( srcName.template === sfsObject.valuetemplate && srcName.property === sfsObject.valuefield ) {
			if ( v.length === 0 || v[ 0 ] === '' ) {
				setDependentValues( srcName, sfsObject, [] );
			} else {
				const param = {};
				param.action = 'sformsselect';
				param.format = 'json';
				param.sep = sfsObject.sep;
				if ( sfsObject.selectquery ) {
					param.query = sfsObject.selectquery.replace( '@@@@', v.join( '||' ) );
					param.approach = 'smw';
				} else {
					param.query = sfsObject.selectfunction.replace( '@@@@', v.join( ',' ) );
					param.approach = 'function';
				}
			$.get( mw.config.get( 'wgScriptPath' ) + '/api.php', param )
				.then( ( data ) => {
					setDependentValues( srcName, sfsObject, data.sformsselect.values );
				}, () => {
					// eslint-disable-next-line no-console
					console.log( 'SF_Select: API call failed.' );
				} );
			}
		}
	}

	function setDependentValues( nameobj, sfsObject, values ) {
		const selectPat = getSelectFieldPat( nameobj, sfsObject );
		$( selectPat ).each( ( index, element ) => {
			let selectedValues = $( element ).val();
			if ( !selectedValues && sfsObject.hasOwnProperty( 'curvalues' ) ) {
				selectedValues = sfsObject.curvalues;
			}
			if ( !selectedValues ) {
				selectedValues = [];
			} else if ( !$.isArray( selectedValues ) ) {
				selectedValues = [ selectedValues ];
			}
			element.options.length = values.length;
			const newselected = [];
			if ( sfsObject.label ) {
				const namevalues = parsePlainlistQueryResult( values );
				for ( let i = 0; i < namevalues.length; i++ ) {
					element.options[ i ] = new Option( namevalues[ i ][ 1 ], namevalues[ i ][ 0 ] );
					if ( $.inArray( namevalues[ i ][ 0 ], selectedValues ) !== -1 ) {
						element.options[ i ].selected = true;
						newselected.push( namevalues[ i ][ 0 ] );
					}
				}
			} else {
				for ( let i = 0; i < values.length; i++ ) {
					element.options[ i ] = new Option( values[ i ] );
					if ( $.inArray( values[ i ], selectedValues ) !== -1 ) {
						element.options[ i ].selected = true;
						newselected.push( values[ i ] );
					}
				}
			}
			if ( newselected.length === 0 ) {
				if ( sfsObject.selectrm && sfsObject.selecttemplate !== sfsObject.valuetemplate && sfsObject.selectismultiple ) {
					$( element ).closest( 'div.multipleTemplateInstance' ).remove();
				} else {
					if ( selectedValues.length !== 0 || values.length === 1 ) {
						$( element ).trigger( 'change' );
					}
				}
			} else if ( !arrayEqual( newselected, selectedValues ) ) {
				$( element ).trigger( 'change' );
			}
		} );
	}

	/**
	 * Build a jQuery selector pattern matching all dependent select elements.
	 * @param {Object} nameObj
	 * @param {Object} f sfsObject config
	 * @return {string} jQuery selector string
	 */
	function getSelectFieldPat( nameObj, f ) {
		let selectpat;
		if ( f.selectismultiple ) {
			if ( f.selecttemplate === f.valuetemplate ) {
				const pat = "select[name='" + f.selecttemplate + '[' + nameObj.index + '][' + f.selectfield + "]']";
				const pat1 = "select[name='" + f.selecttemplate + '[' + nameObj.index + '][' + f.selectfield + "][]']";
				selectpat = pat + ',' + pat1;
			} else {
				selectpat = "select[name^='" + f.selecttemplate + "'][name$='[" + f.selectfield + "]'], " +
					"select[name^='" + f.selecttemplate + "'][name$='[" + f.selectfield + "][]']";
			}
		} else {
			selectpat = "select[name='" + f.selecttemplate + '[' + f.selectfield + "]'], " +
				"select[name='" + f.selecttemplate + '[' + f.selectfield + "][]']";
		}
		return selectpat;
	}

	/**
	 * Parse an API result in plainlist format into `[value, label]` pairs.
	 *
	 * Input format: `"Page Title (Display Label)"` → `["Page Title", "Display Label"]`
	 *
	 * @param {string[]} values
	 * @return {Array<Array<string>>} array of [value, label] pairs
	 */
	function parsePlainlistQueryResult( values ) {
		const lastToplevelBracketPair = ( text ) => {
			let depth = 0;
			let right;
			for ( let i = text.length - 1; i >= 0; i-- ) {
				if ( text[ i ] === ')' ) {
					depth += 1;
					if ( depth === 1 ) {
						right = i;
					}
				} else if ( text[ i ] === '(' ) {
					if ( depth > 0 ) {
						depth -= 1;
						if ( depth === 0 ) {
							return { left: i, right };
						}
					}
				}
			}
		};

		return values.map( ( value ) => {
			value = value || '';
			const pair = lastToplevelBracketPair( value );
			if ( pair === undefined ) {
				return [ value, value ];
			}
			return [
				value.slice( 0, pair.left - 1 ),
				value.slice( pair.left + 1, pair.right )
			].map( ( s ) => s.trim() );
		} );
	}

	function arrayEqual( a, b ) {
		if ( a.length !== b.length ) {
			return false;
		}
		a = a.sort();
		b = b.sort();
		for ( let i = 0; i < a.length; i++ ) {
			if ( a[ i ] !== b[ i ] ) {
				return false;
			}
		}
		return true;
	}

	initialize();

}( jQuery, mediaWiki, window.pageforms ) );
