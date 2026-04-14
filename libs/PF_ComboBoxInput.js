/**
 * PF_ComboBoxInput.js
 *
 * JavaScript code to use OOUI ComboBoxInput widget for comboBox.
 *
 * @param {jQuery} $
 * @param {mw} mw
 * @param {Object} pf
 * @class
 * @extends OO.ui.ComboBoxInputWidget
 * @mixin OO.ui.mixin.PendingElement
 *
 * @license GNU GPL v2+
 * @author Jatin Mehta
 * @author Priyanshu Varshney
 * @author Yaron Koren
 * @author Sahaj Khandelwal
 * @author Yash Varshney
 */

(function ($, mw, pf) {
	pf.ComboBoxInput = function (config) {
		this.config = config || {}
		this.titleByDisplayValue = {};
		this.displayByTitle = {};
		OO.ui.ComboBoxInputWidget.call(this, config);
		OO.ui.mixin.PendingElement.call(this, { $pending: this.$input });
	};
	OO.inheritClass(pf.ComboBoxInput, OO.ui.ComboBoxInputWidget);
	OO.mixinClass(pf.ComboBoxInput, OO.ui.mixin.PendingElement);

	// Override onMenuChoose to fire a targeted jQuery event on the native
	// input element when the user confirms a dropdown selection.  The OOUI
	// 'change' event cannot be used for this purpose because it also fires on
	// every keystroke (setValue() is called by onEdit during typing).
	pf.ComboBoxInput.prototype.onMenuChoose = function ( item ) {
		pf.ComboBoxInput.super.prototype.onMenuChoose.call( this, item );
		// Mark that the user explicitly picked an existing value from the list.
		// Without this, the blur handler (which fires on TAB / click-away) clears
		// the input for fields with existingvaluesonly=true, because setValues /
		// _renderItems only set itemFound=true when the in-progress search term
		// exactly matches a result label — the chosen label (e.g. "Daimler Actros")
		// never equals the typed search term (e.g. "C").
		this.itemFound = true;
		// Abort any in-flight remote fetch so it cannot land after the choose event
		// and reset itemFound back to false inside _renderItems.
		if ( this.dataSource && this.dataSource._pendingRequest ) {
			this.dataSource._pendingRequest.abort();
			this.dataSource._pendingRequest = null;
		}
		this.$input.trigger( 'pf-combobox-choose' );
	};

	pf.ComboBoxInput.prototype.apply = function (element) {
		// Apply ComboBoxInput to the element
		this.setInputAttribute('name', element.attr('name'));
		this.setInputAttribute('origname', element.attr('origname'));
		this.setInputId(element.attr('id'));
		this.setValue(element.val());
		// ==== GESINN PATCH BEGIN ====
		// https://github.com/gesinn-it-pub/mediawiki-extensions-PageForms/commit/1f556e83ee2e806e243eb08d91226a9ea6842f81
		// pass the class attribute to the OOUI widget
		this.appendToInputAttribute('class', element.attr('class'));
		// ==== GESINN PATCH END ====
		this.config['autocompletesettings'] = element.attr('autocompletesettings');
		this.config['autocompletedatatype'] = element.attr('autocompletedatatype');
		this.config['existingvaluesonly'] = element.attr('existingvaluesonly');
		// Create the DataSource now that config and inputId are both available.
		this.dataSource = new pf.ComboBoxDataSource( {
			autocompletedatatype: this.config['autocompletedatatype'],
			autocompletesettings: this.config['autocompletesettings'],
			inputId: this.getInputId()
		} );
		this.setInputAttribute('autocompletesettings', this.config['autocompletesettings']);
		this.setInputAttribute('placeholder', element.attr('placeholder'));
		this.setInputAttribute('tabIndex', element.attr('tabindex'));
		// ==== GESINN PATCH BEGIN ====
		// handle disabled state when the field is disabled in the form definition
		if ( element.is(':disabled') ) {
			this.setDisabled( true );
		}
		// ==== GESINN PATCH END ====

		// Bootstrap the DisplayTitle↔canonical maps from the server-rendered
		// <option> elements. The server encodes the canonical page title in the
		// option's value attribute and the display title as its text content.
		// This avoids an AJAX call on init: the only purpose of the old
		// this.setValues() call here was to populate these maps so that
		// syncCanonicalValue() could set data-pf-canonical-value correctly.
		// The maps are still refreshed via AJAX on the first user interaction
		// (focus/keyup/mouseup) through the normal setValues() path.
		//
		// Note: element.val() now returns the canonical title (value attribute),
		// so maps must be bootstrapped before syncDisplayValueFromCanonical() can
		// resolve it to the display title. setOptions() is called with the display
		// title to ensure OOUI renders its dropdown indicator button; the menu is
		// immediately closed so it does not auto-open on init.
		this.bootstrapMapsFromElement( element );
		this.syncDisplayValueFromCanonical();
		this.syncCanonicalValue();
		const initDisplayVal = this.getValue();
		this.setOptions( [ {
			data: initDisplayVal || '',
			label: initDisplayVal || mw.message( 'pf-autocomplete-input-too-short', 1 ).text(),
			disabled: !initDisplayVal
		} ] );
		this.getMenu().toggle( false );

		if (this.config.autocompletesettings == 'external data') {
			// this is especially set for dependent on settings
			// when the source field has external data autocompletion
			const input_id = "#" + this.getInputId();
			const name = $(input_id).attr(pf.nameAttr($(input_id)));
			const positionOfBracket = name.indexOf('[');
			const data_autocomplete = name.slice(0,Math.max(0, positionOfBracket))+'|'+name.slice(positionOfBracket+1,name.length-1);
			this.setInputAttribute('data-autocomplete',data_autocomplete);
		}
		// Bind the blur event to resize input according to the value
		this.$input.blur( () => {
			if ( !this.itemFound && this.config['existingvaluesonly'] ){
				this.setValue("");
				this.syncCanonicalValue( "" );
			} else {
				this.syncCanonicalValue();
				this.$element.css("width", this.getValue().length * 11);
			}
		});
		this.$input.focus( () => {
			this.setValues();
		});
		this.$input.keyup( (event) => {
			this.syncCanonicalValue();
			if (event.keyCode !== 38 && event.keyCode !== 40 && event.keyCode !== 37 && event.keyCode !== 39) {
				this.setValues(false);
			}
		});
		this.$element.mouseup( ( e ) => {
			// Skip re-fetching when the mouseup originated from inside the
			// dropdown menu (e.g. releasing the native scrollbar at the end of
			// a scroll drag). Without this guard the event bubbles from the
			// menu, triggers setValues(), rebuilds the option list via
			// _renderItems() → setOptions() and resets the scroll position to
			// the top, while also firing an unnecessary API call.
			if ( this.menu.$element[ 0 ].contains( e.target ) ) {
				return;
			}
			this.setValues(false);
		})
		this.$element.focusout( () => {
			this.syncCanonicalValue();
			$( '.combobox_map_feed' ).val( this.$input.val() );
		});

		this.bindCanonicalSubmitHandler();

		const $loadingIcon = $( '<img src = "' + mw.config.get( 'wgPageFormsScriptPath' ) + '/skins/loading.gif'
			+ '" id="loading-' + this.getInputId() + '">' );
		$loadingIcon.hide();
		$( '#' + element.attr('id') ).parent().append( $loadingIcon );
	};
	/**
	 * Sets the values for combobox
	 *
	 * @param {boolean} [showAllValues=true]
	 */
	pf.ComboBoxInput.prototype.setValues = function ( showAllValues = true ) {
		const input_id = '#' + this.getInputId();
		const self = this;

		// First, handle "show on select" stuff.
		const $parentSpan = $( input_id ).closest( 'span' );
		if ( $parentSpan.hasClass( 'pfShowIfSelected' ) ) {
			mw.hook( 'pf.comboboxChange' ).fire( $parentSpan );
		}

		this.itemFound = false;
		this.titleByDisplayValue = {};
		this.displayByTitle = {};

		const curValue = this.getValue();

		// For remote data types, show a hint when the input is empty.
		if ( this.dataSource.dataType !== undefined && curValue.length === 0 ) {
			this.setOptions( [ {
				data: curValue,
				label: mw.message( 'pf-autocomplete-input-too-short', 1 ).text(),
				disabled: true
			} ] );
			return;
		}

		const isRemote = this.dataSource.dataType !== undefined;
		if ( isRemote ) {
			this.pushPending();
			$( '#loading-' + this.getInputId() ).show();
		}

		this.dataSource.fetch( curValue, showAllValues )
			.then( ( items ) => {
				self._renderItems( items, curValue );
				if ( isRemote ) {
					self.popPending();
					$( '#loading-' + self.getInputId() ).hide();
				}
			}, () => {
				if ( isRemote ) {
					self.popPending();
					$( '#loading-' + self.getInputId() ).hide();
				}
			} );
	};

	/**
	 * Render autocomplete items into the dropdown.
	 *
	 * Populates the displaytitle↔canonical maps, tracks itemFound,
	 * and calls setOptions() followed by the canonical-value sync methods.
	 *
	 * @param {Array.<{title: string, displaytitle: string}>} items
	 * @param {string} curValue  Input value at the time the fetch was initiated
	 */
	pf.ComboBoxInput.prototype._renderItems = function ( items, curValue ) {
		const values = [];

		if ( items.length === 0 ) {
			values.push( {
				data: curValue,
				label: mw.message( 'pf-autocomplete-no-matches' ).text(),
				disabled: true
			} );
		} else {
			items.forEach( ( item ) => {
				const optionTitle = item.title;
				const optionLabel = item.displaytitle || item.title;
				this.titleByDisplayValue[ optionLabel ] = optionTitle;
				this.displayByTitle[ optionTitle ] = optionLabel;
				if ( optionLabel === curValue || optionTitle === curValue ) {
					this.itemFound = true;
				}
				values.push( { data: optionLabel, label: pf.highlightText( this.getValue(), optionLabel ) } );
			} );
		}

		this.setOptions( values );
		this.syncDisplayValueFromCanonical();
		this.syncCanonicalValue();
	};

	/**
	 * Populate titleByDisplayValue / displayByTitle maps from the <option>
	 * elements of the original server-rendered <select>. The server stores
	 * the canonical page title in option.value and the display title as
	 * option.text (for remote fields with UseDisplayTitle). If the value
	 * attribute is absent the two are identical.
	 *
	 * @param {jQuery} element  The original <select> element passed to apply()
	 */
	pf.ComboBoxInput.prototype.bootstrapMapsFromElement = function ( element ) {
		element.find( 'option' ).each( ( i, opt ) => {
			const $opt = $( opt );
			const displayTitle = $opt.text();
			const canonicalTitle = $opt.attr( 'value' ) !== undefined
				? $opt.attr( 'value' )
				: displayTitle;
			if ( displayTitle ) {
				this.titleByDisplayValue[ displayTitle ] = canonicalTitle;
				this.displayByTitle[ canonicalTitle ] = displayTitle;
				if ( canonicalTitle === this.getValue() || displayTitle === this.getValue() ) {
					this.itemFound = true;
				}
			}
		} );
	};

	pf.ComboBoxInput.prototype.getCanonicalValueForInput = function ( value ) {
		if ( value === undefined || value === null ) {
			return value;
		}
		if ( this.titleByDisplayValue !== undefined && this.titleByDisplayValue[value] !== undefined ) {
			return this.titleByDisplayValue[value];
		}
		return value;
	};

	pf.ComboBoxInput.prototype.getDisplayValueForCanonicalInput = function ( value ) {
		if ( value === undefined || value === null ) {
			return value;
		}
		if ( this.displayByTitle !== undefined && this.displayByTitle[value] !== undefined ) {
			return this.displayByTitle[value];
		}
		return value;
	};

	pf.ComboBoxInput.prototype.syncDisplayValueFromCanonical = function ( inputValue ) {
		let valueToSync = inputValue;
		if ( valueToSync === undefined ) {
			valueToSync = this.getValue();
		}
		const displayValue = this.getDisplayValueForCanonicalInput( valueToSync );
		if ( displayValue !== undefined && displayValue !== null && displayValue !== valueToSync ) {
			this.setValue( displayValue );
		}
	};

	pf.ComboBoxInput.prototype.syncCanonicalValue = function ( inputValue ) {
		let valueToSync = inputValue;
		if ( valueToSync === undefined ) {
			valueToSync = this.getValue();
		}
		this.$input.attr( 'data-pf-canonical-value', this.getCanonicalValueForInput( valueToSync ) );
	};

	pf.ComboBoxInput.prototype.bindCanonicalSubmitHandler = function () {
		const eventNamespace = '.pfCanonicalSubmit-' + this.getInputId();
		$( document ).off( 'submit' + eventNamespace ).on( 'submit' + eventNamespace, 'form', ( event ) => {
			const $form = $( event.currentTarget );
			if ( $form.find( this.$input ).length === 0 ) {
				return;
			}
			this.syncCanonicalValue();
			this.$input.val( this.$input.attr( 'data-pf-canonical-value' ) || this.$input.val() );
		} );
	};
	pf.ComboBoxInput.prototype.setInputAttribute = function (attr, value) {
		this.$input.attr(attr, value);
	};

	// ==== GESINN PATCH BEGIN ====
	// https://github.com/gesinn-it-pub/mediawiki-extensions-PageForms/commit/1f556e83ee2e806e243eb08d91226a9ea6842f81
	// pass the class attribute to the OOUI widget
	pf.ComboBoxInput.prototype.appendToInputAttribute = function (attr, value) {
		this.$input.attr(attr, this.$input.attr(attr) + ' ' + value);
	};
	// ==== GESINN PATCH END ====
}(jQuery, mediaWiki, pageforms));
