/**
 * An OOUI-based widget for an autocompleting text input that uses the
 * Page Forms 'pfautocomplete' API.
 *
 * @class
 * @extends OO.ui.TextInputWidget
 *
 * @constructor
 * @param {Object} config Configuration options
 * @author Yaron Koren
 * @author Sahaj Khandelwal
 * @author Yash Varshney
 */

pf.AutocompleteWidget = function( config ) {
	// Parent constructor
	const textInputConfig = {
		name: 'page_name',
		// The following classes are used here:
		// * pfPageNameWithNamespace
		// * pfPageNameWithoutNamespace
		classes: config.classes,
		// This turns off the local, browser-based autocompletion,
		// which would normally suggest values that the user has
		// typed before on that computer.
		autocomplete: false
	};
	if ( config.value !== undefined ) {
		textInputConfig.value = config.value;
	}
	if ( config.placeholder !== undefined ) {
		textInputConfig.placeholder = config.placeholder;
	}
	if ( config.autofocus !== undefined ) {
		textInputConfig.autofocus = config.autofocus;
	}
	OO.ui.TextInputWidget.call( this, textInputConfig );
	// Mixin constructors
	if ( config.autocompletedatatype !== undefined ) {
		OO.ui.mixin.LookupElement.call( this, { highlightFirst: false } );
	}

	this.config = config;

	// Initialization
	if ( config.size !== undefined && config.size !== '' ) {
		this.$element.css('width', 'initial');
		this.$input.css('width', 'initial');
		this.$input.attr('size', config.size);
	}

	// dataCache will temporarily store entity id => entity data mappings of
	// entities, so that if we somehow then alter the text (add characters,
	// remove some) and then adjust our typing to form a known item,
	// it'll recognize it and know what the id was, without us having to
	// select it anew
	this.dataCache = {};
};

OO.inheritClass( pf.AutocompleteWidget, OO.ui.TextInputWidget );
OO.mixinClass( pf.AutocompleteWidget, OO.ui.mixin.LookupElement );

/**
 * @inheritdoc
 */
pf.AutocompleteWidget.prototype.getLookupRequest = function () {
	const value = this.getValue();
	const deferred = $.Deferred();
	const api = new mw.Api();
	const requestParams = {
		action: 'pfautocomplete',
		format: 'json',
		substr: value
	};

	if ( this.config.autocompletedatatype == 'category' ) {
		requestParams.category = this.config.autocompletesettings;
	} else if ( this.config.autocompletedatatype == 'namespace' ) {
		requestParams.namespace = this.config.autocompletesettings;
	}

	return api.get( requestParams );
};
/**
 * @inheritdoc
 */
pf.AutocompleteWidget.prototype.getLookupCacheDataFromResponse = function ( response ) {
	return response || [];
};
/**
 * @inheritdoc
 */
pf.AutocompleteWidget.prototype.getLookupMenuOptionsFromData = function ( data ) {
	let i,
		item;
	const items = [];

	data = data.pfautocomplete;
	if ( this.maxSuggestions !== undefined ) {
		data = data.slice( 0, this.maxSuggestions - 1 );
	}
	if ( !data ) {
		return [];
	} else if ( data.length === 0 ) {
		// Generate a disabled option with a helpful message in case no results are found.
		return [
			new OO.ui.MenuOptionWidget( {
				disabled: true,
				label: mw.message( 'pf-autocomplete-no-matches' ).text()
			} )
		];
	}
	for ( i = 0; i < data.length; i++ ) {
		item = new OO.ui.MenuOptionWidget( {
			// this data will be passed to onLookupMenuChoose when item is selected
			data: data[ i ].title,
			label: this.highlightText( data[ i ].title )
		} );
		items.push( item );
	}
	return items;
};

/**
 * Returns a disabled OOUI MenuOptionWidget with a "No Matches" label.
 *
 * @return {OO.ui.MenuOptionWidget[]}
 */
pf.AutocompleteWidget.prototype.getNoMatchesOOUIMenuOptionWidget = function () {
	return [
		new OO.ui.MenuOptionWidget( {
			data: this.getValue(),
			label: mw.message( 'pf-autocomplete-no-matches' ).text(),
			disabled: true
		} )
	];
};

/**
 * Checks if any word in the given string starts with the given search term.
 *
 * @param {string} string
 * @param {string} curValue
 * @return {boolean}
 */
pf.AutocompleteWidget.prototype.checkIfAnyWordStartsWithInputValue = function ( string, curValue ) {
	const regex = new RegExp( '\\b' + curValue.toLowerCase() );
	return string.toLowerCase().match( regex ) !== null;
};

pf.AutocompleteWidget.prototype.highlightText = function ( suggestion ) {
	const searchTerm = this.getValue();
	const searchRegexp = new RegExp("(?![^&;]+;)(?!<[^<>]*)(" +
		searchTerm.replace(/([\^\$\(\)\[\]\{\}\*\.\+\?\|\\])/gi, "\\$1") +
		")(?![^<>]*>)(?![^&;]+;)", "gi");
	const itemLabel = suggestion;
	const loc = itemLabel.search(searchRegexp);
	let t;

	if (loc >= 0) {
		t = itemLabel.slice(0, Math.max(0, loc)) +
			 '<strong>' + itemLabel.slice(loc, loc + searchTerm.length) + '</strong>' +
			itemLabel.slice(loc + searchTerm.length);
	} else {
		t = itemLabel;
	}

	return new OO.ui.HtmlSnippet( t );
};

/**
 * Returns a single-item array containing a disabled "No matches" MenuOptionWidget.
 * Shared by all autocomplete widgets so subclasses do not need to duplicate it.
 *
 * @return {OO.ui.MenuOptionWidget[]}
 */
pf.AutocompleteWidget.prototype.getNoMatchesOOUIMenuOptionWidget = function () {
	return [
		new OO.ui.MenuOptionWidget( {
			data: this.getValue(),
			label: mw.message( 'pf-autocomplete-no-matches' ).text(),
			disabled: true
		} )
	];
};

/**
 * Checks if any word in the given string starts with the given search term.
 * Used for non-"all-chars" autocomplete filtering.
 *
 * @param {string} string
 * @param {string} curValue
 * @return {boolean}
 */
pf.AutocompleteWidget.prototype.checkIfAnyWordStartsWithInputValue = function ( string, curValue ) {
	const regex = new RegExp( '\\b' + curValue.toLowerCase() );
	return string.toLowerCase().match( regex ) !== null;
};
