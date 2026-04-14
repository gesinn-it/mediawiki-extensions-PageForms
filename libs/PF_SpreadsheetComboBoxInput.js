
/**
 * An OOUI-based widget for an autocompleting ComboBox input
 * within the spreadsheet-style display that uses the
 * Page Forms 'pfautocomplete' API.
 *
 * @class
 * @extends OO.ui.ComboBoxInputWidget
 * @param {Object} config Configuration Options
 * @author Yash Varshney
 */
 pf.SpreadsheetComboBoxInput = function( config ) {
	this.config = config || {};
	OO.ui.ComboBoxInputWidget.call( this, config );
	this.$input.focus( () => {
		this.setValues();
	});
	this.$input.keyup( (event) => {
		if (event.keyCode !== 38 && event.keyCode !== 40 && event.keyCode !== 37 && event.keyCode !== 39) {
			this.setValues();
		}
	});
}
OO.inheritClass( pf.SpreadsheetComboBoxInput, OO.ui.ComboBoxInputWidget );
/**
 *
 * A function for setting the options for combobox whenever something is typed
 */
pf.SpreadsheetComboBoxInput.prototype.setValues = function() {
	const data_source = this.config.autocompletesettings,
		data_type = this.config.autocompletedatatype,
		self = this,
		values = [];
	let curValue = this.getValue();
	// sometimes it happens that on double clicking the cell
	// a space is automatically added to the value inside the
	// editor and hence we get "No Matches found" so we can
	// simply remove that space.
	if ( curValue[0] == ' ' ) {
		curValue = curValue.slice(1);
	}
	if ( data_type == 'external data' ) { // External Data Autocompletion
		const	wgPageFormsEDSettings = mw.config.get( 'wgPageFormsEDSettings' ),
			name = data_source,
			edgValues = mw.config.get( 'edgValues' ),
			data = {};
		if ( wgPageFormsEDSettings !== null && wgPageFormsEDSettings[name].title !== undefined && wgPageFormsEDSettings[name].title !== "" ) {
			data.title = edgValues[ wgPageFormsEDSettings[ name ].title ];
			if ( data.title !== undefined && data.title !== null ) {
				let i = 0;
				data.title.forEach( () => {
					const wgPageFormsAutocompleteOnAllChars = mw.config.get( 'wgPageFormsAutocompleteOnAllChars' );
				let valueFilter;
				if ( wgPageFormsAutocompleteOnAllChars ) {
					valueFilter = self.getConditionForAutocompleteOnAllChars( data.title[i], curValue.toLowerCase() )
				} else {
					valueFilter = self.checkIfAnyWordStartsWithInputValue( data.title[i], curValue );
					}
					if ( valueFilter ) {
						values.push( {
							data: data.title[i], label: self.highlightText( data.title[i] )
						} );
					}
					i++;
				} );
				if( values.length == 0 ) {
					values.push( self.getNoMatchesOption() );
				}
			}
		} else {
			// this case will possibly come when the external data can't be fetched due to wrong parameters provided
			values.push( this.getNoMatchesOption() );
		}
		this.setOptions( values );
	} else {
		if ( curValue.length == 0 ) {
			values.push({
				label: mw.message('pf-autocomplete-input-too-short',1).text(), disabled: true
			});
			this.setOptions(values);
			return;
		}
		let my_server = mw.util.wikiScript( 'api' );
		my_server += "?action=pfautocomplete&format=json";
		if ( data_type == 'dep_on' ) {
			const dep_field_opts = this.getDependentFieldOpts( this.config.data_y, this.config.dep_on_field );
			my_server += "&property=" + dep_field_opts.prop + "&baseprop=" + dep_field_opts.base_prop + "&basevalue=" + dep_field_opts.base_value + "&substr=" + curValue;
		} else {
			my_server += "&" + data_type + "=" + data_source + "&substr=" + curValue;
		}
		$.ajax( {
			url: my_server,
			dataType: 'json',
			success: function ( data ) {
				if ( data.pfautocomplete !== undefined ) {
					data = data.pfautocomplete;
					if ( data.length == 0 ) {
						values.push( self.getNoMatchesOption() )
					} else {
						for ( let i = 0; i < data.length; i++ ) {
							values.push( {
								data: data[i].title, label: self.highlightText( data[i].title )
							} );
						}
					}
				} else {
					values.push( self.getNoMatchesOption() )
				}
				self.setOptions( values );
			}
		} );
	}
}
/**
 * @param {string} suggestion
 * @return {OO.ui.HtmlSnippet}
 */
pf.SpreadsheetComboBoxInput.prototype.highlightText = function ( suggestion ) {
	let searchTerm = this.getValue();
	if ( searchTerm[ 0 ] === ' ' ) {
		searchTerm = searchTerm.slice( 1 );
	}
	return pf.highlightText( searchTerm, suggestion );
};
/**
 * Provides an option with "No Matches" label
 *
 * @return {Object}
 */
pf.SpreadsheetComboBoxInput.prototype.getNoMatchesOption = function() {
	return {
			data: this.getValue(),
			label: mw.message('pf-autocomplete-no-matches').text(),
			disabled: true
		}
}
/**
 * Checks if any "word" in the given string starts with the given search term.
 *
 * @param {string} string
 * @param {string} curValue
 * @return {boolean}
 */
pf.SpreadsheetComboBoxInput.prototype.checkIfAnyWordStartsWithInputValue = function( string, curValue ) {
	const regex = new RegExp('\\b' + curValue.toLowerCase());
	return string.toLowerCase().match(regex) !== null;
}
/**
 * Checks if the given string contains the word or not
 *
 * @param {string} string
 * @param {string} curValue
 * @return {boolean}
 */
pf.SpreadsheetComboBoxInput.prototype.getConditionForAutocompleteOnAllChars = function(string, curValue) {
	return string.toLowerCase().includes(curValue.toLowerCase())
}
/**
 * Gives dependent field options which include
 * property, base property and base value
 *
 * @param {number} data_y
 * @param {string} dep_on_field
 * @return {Object} dep_field_opts
 */
pf.SpreadsheetComboBoxInput.prototype.getDependentFieldOpts = function( data_y, dep_on_field ) {
	const dep_field_opts = {};
	const $baseElement = $('td[data-y="'+data_y+'"][origname="'+dep_on_field+'"]');
	dep_field_opts.base_value = $baseElement.html();
	dep_field_opts.base_prop = mw.config.get('wgPageFormsFieldProperties')[dep_on_field] ||
		$baseElement.attr('name');
	dep_field_opts.prop = this.config['autocompletesettings'];
	return dep_field_opts;
}
