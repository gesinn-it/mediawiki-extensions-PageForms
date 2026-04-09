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
    let apiRequest = null;
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
            const name = $(input_id).attr(this.nameAttr($(input_id)));
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
        this.$element.mouseup( () => {
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
        const input_id = "#" + this.getInputId();
        const values = [];
        let data,
            i,
            curValue,
            my_server;
        const dep_on = this.dependentOn();
        const self = this;
        const wgPageFormsAutocompleteOnAllChars = mw.config.get( 'wgPageFormsAutocompleteOnAllChars' );
        this.titleByDisplayValue = {};
        this.displayByTitle = {};

        // First, handle "show on select" stuff.
        const $parentSpan = $(input_id).closest('span');
        if ( $parentSpan.hasClass('pfShowIfSelected') ) {
            mw.hook('pf.comboboxChange').fire($parentSpan);
        }

        this.itemFound = false;
        if (this.config.autocompletedatatype !== undefined) {
            let data_source = this.config.autocompletesettings;
            const data_type = this.config.autocompletedatatype;
            curValue = this.getValue();
            if (curValue.length == 0) {
                values.push({
                    data:self.getValue(), label: mw.message('pf-autocomplete-input-too-short',1).text(), disabled: true
                });
                this.setOptions(values);
                return;
            }

		    my_server = mw.util.wikiScript( 'api' );

            if (data_type === 'cargo field') {
                const table_and_field = data_source.split('|');
                my_server += "?action=pfautocomplete&format=json&cargo_table=" + table_and_field[0] + "&cargo_field=" + table_and_field[1] + "&substr=" + curValue;
                if ( table_and_field.length > 2 ) {
                    my_server += '&cargo_where=' + table_and_field[2];
                }
            } else {
                if ( data_type === 'wikidata' ) {
                    // Support for getting query values from an existing field in the form
                    const terms = data_source.split( "&" );
                    terms.forEach( (element) => {
                        const subTerms = element.split( "=" );
                        const matches = subTerms[1].match( /\[(.*?)\]/ );
                        if ( matches ) {
                            const dep_value = $( '[name="' + subTerms[1] + '"]' ).val();
                            if ( dep_value && dep_value.trim().length ) {
                                data_source = data_source.replace( subTerms[1], dep_value );
                            }
                            return;
                        }
                    } );
                    data_source = encodeURIComponent( data_source );
                }
                my_server += "?action=pfautocomplete&format=json&" + data_type + "=" + data_source + "&substr=" + curValue;
            }
            self.pushPending();
            apiRequest = $.ajax({
                url: my_server,
                dataType: 'json',
                beforeSend: () => {
                    if ( apiRequest !== null ) {
                        apiRequest.abort();
                    }
                    $( '#loading-' + input_id.replace( '#', '' ) ).show();
                },
                success: function (Data) {
                    $( '#loading-' + input_id.replace( '#', '' ) ).hide();
                    if (Data.pfautocomplete !== undefined) {
                        Data = Data.pfautocomplete;
                        if (Data.length === 0) {
                            values.push({
                                data:self.getValue(), label: mw.message('pf-autocomplete-no-matches').text(), disabled: true
                            });
                        } else {
                            for ( i = 0; i < Data.length; i++ ) {
                                const optionTitle = Data[i].title;
                                const optionLabel = Data[i].displaytitle || Data[i].title;
                                self.titleByDisplayValue[optionLabel] = optionTitle;
                                self.displayByTitle[optionTitle] = optionLabel;
                                if ( optionLabel === self.getValue() || optionTitle === self.getValue() ) {
                                    self.itemFound = true;
                                }
                                values.push({
                                    data: optionLabel, label: self.highlightText(optionLabel)
                                });
                            }
                        }
                    } else {
                        values.push({
                            data:self.getValue(), label: mw.message('pf-autocomplete-no-matches').text(), disabled: true
                        });
                    }
                    self.setOptions(values);
                    self.syncDisplayValueFromCanonical();
                    self.syncCanonicalValue();
                },
                complete: function() {
                    self.popPending();
                }
            });
        } else {
            if (dep_on === null) {
                if (this.config['autocompletesettings'] === 'external data') {
                    curValue = this.getValue();
                    if ( showAllValues ) {
                        curValue = "";
                    }
                    const name = $(input_id).attr(this.nameAttr($(input_id)));
                    const wgPageFormsEDSettings = mw.config.get('wgPageFormsEDSettings');
                    const edgValues = mw.config.get('edgValues');
                    data = {};
                    if (wgPageFormsEDSettings[name].title !== undefined && wgPageFormsEDSettings[name].title !== "") {
                        data.title = edgValues[wgPageFormsEDSettings[name].title];
                        if (data.title !== undefined && data.title !== null) {
                            i = 0;
                            data.title.forEach(() => {
                                if (data.title[i] == curValue ){
                                    self.itemFound = true;
                                }
                                self.titleByDisplayValue[data.title[i]] = data.title[i];
                                self.displayByTitle[data.title[i]] = data.title[i];
                                if (wgPageFormsAutocompleteOnAllChars) {
                                    if (self.getConditionForAutocompleteOnAllChars(data.title[i], curValue)) {
                                        values.push({
                                            data: data.title[i], label: self.highlightText(data.title[i])
                                        });
                                    }
                                } else {
                                    if (self.checkIfAnyWordStartsWithInputValue(data.title[i], curValue)) {
                                        values.push({
                                            data: data.title[i], label: self.highlightText(data.title[i])
                                        });
                                    }
                                }
                                i++;
                            });
                        }
                    }
                } else {
                    const wgPageFormsAutocompleteValues = mw.config.get('wgPageFormsAutocompleteValues');
                    data = wgPageFormsAutocompleteValues[this.config['autocompletesettings']];
                    curValue = this.getValue();
                    if ( showAllValues ) {
                        curValue = "";
                    }
                    if (Array.isArray(data) || typeof data == 'object') {
                        if (wgPageFormsAutocompleteOnAllChars) {
                            for (const key in data) {
                                const optionData = Array.isArray(data) ? data[key] : key;
                                const optionLabel = data[key];
                                this.titleByDisplayValue[optionLabel] = optionData;
                                this.displayByTitle[optionData] = optionLabel;
                                if ( optionData == curValue || optionLabel == curValue ) {
                                    self.itemFound = true;
                                }
                                if (
                                    this.getConditionForAutocompleteOnAllChars(optionLabel, curValue ) ||
                                    this.getConditionForAutocompleteOnAllChars(optionData, curValue )
                                ) {
                                    values.push({
                                        data: optionLabel, label: this.highlightText(optionLabel)
                                    });
                                }
                            }
                        } else {
                            for (const key in data) {
                                const optionData = Array.isArray(data) ? data[key] : key;
                                const optionLabel = data[key];
                                this.titleByDisplayValue[optionLabel] = optionData;
                                this.displayByTitle[optionData] = optionLabel;
                                if ( optionData == curValue || optionLabel == curValue ) {
                                    self.itemFound = true;
                                }
                                if (
                                    this.checkIfAnyWordStartsWithInputValue(optionLabel, curValue) ||
                                    this.checkIfAnyWordStartsWithInputValue(optionData, curValue)
                                ) {
                                    values.push({
                                        data: optionLabel, label: this.highlightText(optionLabel)
                                    });
                                }
                            }
                        }
                    }
                }
            } else { // Dependent field autocompletion
                const dep_field_opts = this.getDependentFieldOpts(dep_on);
                my_server = mw.config.get('wgScriptPath') + "/api.php";
                my_server += "?action=pfautocomplete&format=json";
                // URL depends on whether Cargo or Semantic MediaWiki
                // is being used.
                if (dep_field_opts.prop !== undefined && dep_field_opts.base_prop !== undefined && dep_field_opts.base_value !== undefined) {
                    if (!dep_field_opts.prop.includes('|')) {
                        // SMW
                        my_server += "&property=" + dep_field_opts.prop + "&baseprop=" + dep_field_opts.base_prop + "&basevalue=" + dep_field_opts.base_value;
                    } else {
                        // Cargo
                        const cargoTableAndFieldStr = dep_field_opts.prop;
                        const cargoTableAndField = cargoTableAndFieldStr.split('|');
                        const cargoTable = cargoTableAndField[0];
                        const cargoField = cargoTableAndField[1];
                        const baseCargoTableAndFieldStr = dep_field_opts.base_prop;
                        const baseCargoTableAndField = baseCargoTableAndFieldStr.split('|');
                        const baseCargoTable = baseCargoTableAndField[0];
                        const baseCargoField = baseCargoTableAndField[1];
                        my_server += "&cargo_table=" + cargoTable + "&cargo_field=" + cargoField + "&base_cargo_table=" + baseCargoTable + "&base_cargo_field=" + baseCargoField + "&basevalue=" + dep_field_opts.base_value;
                    }
                    $.ajax({
                        url: my_server,
                        dataType: 'json',
                        async: false,
                        success: function (response) {
                            if ( response.error !== undefined || response.pfautocomplete.length == 0 ) {
                                values.push({
                                    data:self.getValue(), label: mw.message('pf-autocomplete-no-matches').text(), disabled: true
                                });
                                return values;
                            }
                            response.pfautocomplete.forEach((item) => {
                                curValue = self.getValue();
                                const optionTitle = item.title;
                                const optionLabel = item.displaytitle !== undefined ? item.displaytitle : item.title;
                                self.titleByDisplayValue[optionLabel] = optionTitle;
                                self.displayByTitle[optionTitle] = optionLabel;
                                if ( optionLabel == curValue || optionTitle == curValue ) {
                                    self.itemFound = true;
                                }
                                if (wgPageFormsAutocompleteOnAllChars) {
                                    if (
                                        self.getConditionForAutocompleteOnAllChars(optionLabel, curValue) ||
                                        self.getConditionForAutocompleteOnAllChars(optionTitle, curValue)
                                    ) {
                                        values.push({
                                            data: optionLabel, label: self.highlightText(optionLabel)
                                        });
                                    }
                                } else {
                                    if (
                                        self.checkIfAnyWordStartsWithInputValue(optionLabel, curValue) ||
                                        self.checkIfAnyWordStartsWithInputValue(optionTitle, curValue)
                                    ) {
                                        values.push({
                                            data: optionLabel, label: self.highlightText(optionLabel)
                                        });
                                    }
                                }
                            });
                            return values;
                        }
                    });
                } else {
                    // this condition will come when the wrong parameters are used in form definition
                    values.push({
                        data:self.getValue(), label: mw.message('pf-autocomplete-no-matches').text(), disabled: true
                    });
                }
            }
            if (values.length == 0) {
                values.push({
                    data:self.getValue(), label: mw.message('pf-autocomplete-no-matches').text(), disabled: true
                });
            }
            this.setOptions(values);
            this.syncDisplayValueFromCanonical();
            this.syncCanonicalValue();
        }
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
    /**
     * Returns the name attribute of the field depending on
     * whether it is a part of a multiple instance template or not
     *
     * @param {HTMLElement} element
     *
     * @return {string}
     */
    pf.ComboBoxInput.prototype.nameAttr = function (element) {
        return this.partOfMultiple(element) ? "origname" : "name";
    };
    /**
     * Checks whether the field is part of a multiple instance template or not
     *
     * @param {HTMLElement} element
     *
     * @return {boolean}
     */
    pf.ComboBoxInput.prototype.partOfMultiple = function (element) {
        return element.attr("origname") !== undefined ? true : false;
    };
    /**
     * If a field is dependent on some other field in the form
     * then it returns its name.
     *
     * @return {string}
     */
    pf.ComboBoxInput.prototype.dependentOn = function () {
        const input_id = "#" + this.getInputId();
        const name_attr = this.nameAttr($(input_id));
        const name = $(input_id).attr(name_attr);

        const wgPageFormsDependentFields = mw.config.get('wgPageFormsDependentFields');
        for (let i = 0; i < wgPageFormsDependentFields.length; i++) {
            const dependentFieldPair = wgPageFormsDependentFields[i];
            if (dependentFieldPair[1] === name) {
                return dependentFieldPair[0];
            }
        }
        return null;
    };
    /**
     * Gives dependent field options which include
     * property, base property and base value
     *
     * @param {string} dep_on
     *
     * @return {Object} dep_field_opts
     */
    pf.ComboBoxInput.prototype.getDependentFieldOpts = function (dep_on) {
        const input_id = "#" + this.getInputId();
        const dep_field_opts = {};
        let $baseElement;
        if (this.partOfMultiple($(input_id))) {
            $baseElement = $(input_id).closest(".multipleTemplateInstance")
                .find('[origname ="' + dep_on + '" ]');
        } else {
            $baseElement = $('[name ="' + dep_on + '" ]');
        }
        dep_field_opts.base_value = $baseElement.attr( 'data-pf-canonical-value' ) || $baseElement.val();
        dep_field_opts.base_prop = mw.config.get('wgPageFormsFieldProperties')[dep_on] ||
            $baseElement.attr("autocompletesettings") == 'external data' ?
            $baseElement.attr("data-autocomplete") : $baseElement.attr("autocompletesettings");
        dep_field_opts.prop = $(input_id).attr("autocompletesettings").split(",")[0];

        return dep_field_opts;
    };
    /**
     * Returns the array of names of fields in the form which are dependent
     * on the field passed as a param to this function,
     *
     * @param {HTMLElement} element
     *
     * @return {Array} dependent_on_me (associative array)
     */
    pf.ComboBoxInput.prototype.dependentOnMe = function () {
        const input_id = "#" + this.getInputId();
        const name_attr = this.nameAttr($(input_id));
        const name = $(input_id).attr(name_attr);
        const dependent_on_me = [];
        const wgPageFormsDependentFields = mw.config.get('wgPageFormsDependentFields');
        for (let i = 0; i < wgPageFormsDependentFields.length; i++) {
            const dependentFieldPair = wgPageFormsDependentFields[i];
            if (dependentFieldPair[0] === name) {
                dependent_on_me.push(dependentFieldPair[1]);
            }
        }

        return dependent_on_me;
    };

    pf.ComboBoxInput.prototype.highlightText = function (suggestion) {
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
        return new OO.ui.HtmlSnippet(t);
    };

    pf.ComboBoxInput.prototype.checkIfAnyWordStartsWithInputValue = function(string, curValue) {
        const wordSeparators = [
            '/', '(', ')', '|', 's'
        ].map( (p) => "\\" + p).concat('^', '-', "'",'"');
        const regex = new RegExp('(' + wordSeparators.join('|') + ')' + curValue.toLowerCase());
        return string.toLowerCase().match(regex) !== null;
    }

    pf.ComboBoxInput.prototype.getConditionForAutocompleteOnAllChars = function(string, curValue) {
        return string.toLowerCase().includes(curValue.toLowerCase())
    }

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
