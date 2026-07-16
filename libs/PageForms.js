/**
 * PageForms.js
 *
 * Javascript utility functions for the Page Forms extension.
 *
 * @author Yaron Koren
 * @author Sanyam Goyal
 * @author Stephan Gambke
 * @author Jeffrey Stuckman
 * @author Harold Solbrig
 * @author Eugene Mednikov
 */
/*global wgPageFormsShowOnSelect, wgPageFormsFieldProperties, wgPageFormsDependentFields, validateAll, alert, mwTinyMCEInit, pf, Sortable*/

( function ( $, mw ) {

/**
 * Minimize all instances if the total height of all the instances
 * is over 800 pixels - to allow for easier navigation and sorting.
 */
$.fn.possiblyMinimizeAllOpenInstances = function() {
	if ( ! this.hasClass( 'minimizeAll' ) ) {
		return;
	}

	const displayedFieldsWhenMinimized = this.attr('data-displayed-fields-when-minimized');
	let allDisplayedFields = null;
	if ( displayedFieldsWhenMinimized ) {
		allDisplayedFields = displayedFieldsWhenMinimized.split(',').map((item) => item.trim().toLowerCase());
	}

	this.find('.multipleTemplateInstance').not('.minimized').each( function() {
		const $instance = $(this);
		$instance.addClass('minimized');
		let valuesStr = '';
		$instance.find( "input[type != 'hidden'][type != 'button'], select, textarea, div.ve-ce-surface" ).each( function() {
			// If the set of fields to be displayed was specified in
			// the form definition, check against that list.
			if ( allDisplayedFields !== null ) {
				const fieldFullName = $(this).attr('name');
				if ( !fieldFullName ) {
					return;
				}
				const matches = fieldFullName.match(/.*\[.*\]\[(.*)\]/);
				const fieldRealName = matches[1].toLowerCase();
				if ( !allDisplayedFields.includes( fieldRealName ) ) {
					return;
				}
			}

			let curVal = $(this).val();
			if ( this.classList.contains('ve-ce-surface') ) {
				// Special handling for VisualEditor/VEForAll textareas.
				curVal = $(this).text();
			}
			if ( typeof curVal !== 'string' || curVal === '' ) {
				return;
			}
			const inputType = $(this).attr('type');
			if ( inputType === 'checkbox' || inputType === 'radio' ) {
				if ( ! $(this).is(':checked') ) {
					return;
				}
			}
			if ( curVal.length > 70 ) {
				curVal = curVal.slice(0, 70) + "...";
			}
			if ( valuesStr !== '' ) {
				valuesStr += ' &middot; ';
			}
			valuesStr += curVal;
		});
		if ( valuesStr === '' ) {
			valuesStr = '<em>No data</em>';
		}
		$instance.find('.instanceMain').fadeOut( "medium", () => {
			$instance.find('.instanceRearranger').after('<td class="fieldValuesDisplay">' + valuesStr + '</td>');
		});
	});
};

$.fn.displayWizardScreen = function( screenNum, $wizardNav ) {
	const $wizardScreens = $(this);
	let $curScreen;

	$wizardScreens.each( function(i) {
		// screenNum starts at 1, not 0.
		if ( i + 1 == screenNum ) {
			$curScreen = $(this);
			$(this).show();
		} else {
			$(this).hide();
		}
	});

	// The rest of this function is taken up with displaying the
	// navigation to the next and previous wizard screens.
	const numScreens = $wizardScreens.length;

	$wizardNav.empty();

	const $navButtons = $('<div class="pf-wizard-buttons"></div>');

	if ( screenNum > 1 ) {
		let backText = $curScreen.attr('data-back-text');
		if ( backText == undefined ) {
			backText = mw.msg('pf-wizard-back');
		}
		const prevButton = new OO.ui.ButtonWidget( {
			label: 'backText',
			icon: 'previous',
			classes: [ 'pf-wizard-back-button' ]
		} );
		prevButton.$element.click( () => {
			$wizardScreens.displayWizardScreen( screenNum - 1, $wizardNav );
		});
		$navButtons.append( prevButton.$element );
	}

	if ( screenNum < numScreens ) {
		let continueText = $curScreen.attr('data-continue-text');
		if ( continueText == undefined ) {
			continueText = mw.msg('pf-wizard-continue');
		}
		const continueButton = new OO.ui.ButtonWidget( {
			label: continueText,
			flags: [
				'primary',
				'progressive'
			],
			icon: 'next',
			classes: [ 'pf-wizard-continue-button' ]
		} );
		continueButton.$element.click( () => {
			$wizardScreens.displayWizardScreen( screenNum + 1, $wizardNav );
		});
		$navButtons.append( continueButton.$element );
	}
	$wizardNav.append( $navButtons );

	// We need this in order to clear the float from the "previous" button.
	$wizardNav.append('<br style="clear: both;" />');

	// Use progress bar if the number of screens is greater than 10 and circles in the other case
	if ( numScreens > 10 ) {
		const progressBar = new OO.ui.ProgressBarWidget( {
			progress: 100 * screenNum / numScreens
		} );
		const progressBarLayout = new OO.ui.FieldLayout(
			progressBar,
			{
				label: 'Step ' + screenNum + ' of ' + numScreens,
				align: 'inline'
			}
		);
		$wizardNav.append( progressBarLayout.$element );
	} else {
		$( '.pf-wizard-buttons' ).addClass( 'pf-wizard-buttons-circle' );
		const $progressCiclesUL = $( '<ul class="pfWizardCircles"></ul>' );
		for( let i = 1; i <= numScreens; i++ ) {
			let circle = '<li>' + i + '</li>';
			if ( i == screenNum ) {
				circle = '<li class="active">' + i + '</li>';
			}
			$progressCiclesUL.append( $( circle ) );
		}
		$wizardNav.append( $progressCiclesUL );
	}
};

let num_elements = 0;

/**
 * Functions for multiple-instance templates.
 *
 * @param {Mixed} addAboveCurInstance
 * @return {boolean|undefined}
 */
$.fn.addInstance = function( addAboveCurInstance ) {
	const wgPageFormsShowOnSelect = mw.config.get( 'wgPageFormsShowOnSelect' );
	const wgPageFormsHeightForMinimizingInstances = mw.config.get( 'wgPageFormsHeightForMinimizingInstances' );
	const $wrapper = this.closest(".multipleTemplateWrapper");
	const $multipleTemplateList = $wrapper.find('.multipleTemplateList');

	// If the nubmer of instances is already at the maximum allowed,
	// exit here.
	if ( $multipleTemplateList.isAtMaxInstances() ) {
		return false;
	}

	if ( wgPageFormsHeightForMinimizingInstances >= 0 ) {
		if ( ! $multipleTemplateList[0].classList.contains('minimizeAll') &&
			$multipleTemplateList.height() >= wgPageFormsHeightForMinimizingInstances ) {
			$multipleTemplateList.addClass('minimizeAll');
		}
		if ( $multipleTemplateList[0].classList.contains('minimizeAll') ) {
			$multipleTemplateList
				.addClass('currentFocus')
				.possiblyMinimizeAllOpenInstances();
		}
	}

	// Global variable.
	num_elements++;
	// Create the new instance
	const $new_div = $wrapper
		.find(".multipleTemplateStarter")
		.clone()
		.removeClass('multipleTemplateStarter')
		.addClass('multipleTemplateInstance')
		.addClass('multipleTemplate') // backwards compatibility
		.removeAttr("id")
		.fadeTo(0,0)
		.slideDown('fast', function() {
			$(this).fadeTo('fast', 1);
		});

	// Add on a new attribute, "data-origID", representing the ID of all
	// HTML elements that had an ID; and delete the actual ID attribute
	// of any divs and spans (presumably, these exist only for the
	// sake of "show on select"). We do the deletions because no two
	// elements on the page are allowed to have the same ID.
	$new_div.find('[id!=""]').attr('data-origID', function() {
 return this.id;
});
	$new_div.find('div[id!=""], span[id!=""]').removeAttr('id');

	$new_div.find('.hiddenByPF')
	.removeClass('hiddenByPF')

	.find('.disabledByPF')
	.prop('disabled', false)
	.removeClass('disabledByPF');

	// Make internal ID unique for the relevant form elements, and replace
	// the [num] index in the element names with an actual unique index
	$new_div.find("input, select, textarea").each(
		function() {
			// Add in a 'b' at the end of the name to reduce the
			// chance of name collision with another field
			if (this.name) {
				const old_name = this.name.replace(/\[num\]/g, '');
				$(this).attr('origName', old_name);
				this.name = this.name.replace(/\[num\]/g, '[' + num_elements + 'b]');
			}

			// Do the same thing with "feeds to map", which also
			// needs to be modified for each instance.
			const feedsToMap = $(this).attr('data-feeds-to-map');
			if ( feedsToMap !== undefined && feedsToMap !== false ) {
				$(this).attr('data-feeds-to-map', feedsToMap.replace(/\[num\]/g, '[' + num_elements + 'b]') );
			}

			if (this.id) {

				const old_id = this.id;

				this.id = this.id.replace(/input_/g, 'input_' + num_elements + '_');

				// TODO: Data in wgPageFormsShowOnSelect should probably be stored in
				// $("#pfForm").data('PageForms')
				if ( wgPageFormsShowOnSelect[ old_id ] ) {
					wgPageFormsShowOnSelect[ this.id ] = wgPageFormsShowOnSelect[ old_id ];
				}

				// register initialization and validation methods for new inputs

				const pfdata = $("#pfForm").data('PageForms');
				if ( pfdata ) { // found data object?
					let i;
					if ( pfdata.initFunctions[old_id] ) {

						// For every initialization method for
						// input with id old_id, register the
						// method for the new input.
						for ( i = 0; i < pfdata.initFunctions[old_id].length; i++ ) {

							$(this).PageForms_registerInputInit(
								pfdata.initFunctions[old_id][i].initFunction,
								pfdata.initFunctions[old_id][i].parameters,
								true //do not yet execute
								);
						}
					}

					// For every validation method for the
					// input with ID old_id, register it
					// for the new input.
					for ( i = 0; i < pfdata.validationFunctions.length; i++ ) {

						if ( typeof pfdata.validationFunctions[i] !== 'undefined' &&
							pfdata.validationFunctions[i].input === old_id ) {

							$(this).PageForms_registerInputValidation(
								pfdata.validationFunctions[i].valfunction,
								pfdata.validationFunctions[i].parameters
								);
						}
					}
				}
			}
		}
	);

	// datepicker and datetimepicker inputs require special handling.
	$new_div.find("div.pfPicker").attr('data-ooui', function() {
		return $(this).attr('data-ooui').replace(/\[num\]/g, '[' + num_elements + 'b]');
	});

	$new_div.find('a').attr('href', function() {
		// Make sure not to add a valid "href" attribute to <a> tags that don't have it.
		if ( this.href == undefined || this.href == false ) {
			return null;
		}
		return this.href.replace(/input_/g, 'input_' + num_elements + '_');
	});

	$new_div.find('span').attr('id', function() {
		return this.id.replace(/span_/g, 'span_' + num_elements + '_');
	});

	// Add the new instance.
	if ( addAboveCurInstance ) {
		$new_div.insertBefore(this.closest(".multipleTemplateInstance"))
			.hide().fadeIn();
	} else {
		this.closest(".multipleTemplateWrapper")
			.find(".multipleTemplateList")
			.append($new_div.hide().fadeIn());
	}

	$new_div.initializeJSElements(true);

	// Initialize new inputs.
	$new_div.find("input, select, textarea").each( function() {
		if ( ! this.id ) {
			return;
		}

		const pfdata = $("#pfForm").data('PageForms');
		if ( ! pfdata ) {
			return;
		}

		// have to store data array: the id attribute
		// of 'this' might be changed in the init function
		const thatData = pfdata.initFunctions[this.id] ;
		if ( !thatData ) {
			return;
		}

		// Call every initialization method for this input.
		for ( let i = 0; i < thatData.length; i++ ) {
			let initFunction = thatData[i].initFunction;
			if ( initFunction === undefined ) {
				continue;
			}
			// If the code attempted to store this function before
			// it was defined, only its name was stored. In that
			// case, get the function now.
			// @TODO - move getFunctionFromName() so that it can be
			// called from here, which would be better than
			// window[].
			if ( typeof initFunction === 'string' ) {
				initFunction = window[initFunction];
			}
			initFunction(
				this.id,
				thatData[i].parameters
			);
		}
	});

	// Hook that fires each time a new template instance is added.
	// The first parameter is a jQuery selection of the newly created instance div.
	mw.hook('pf.addTemplateInstance').fire($new_div);
};

// The first argument is needed, even though it's an attribute of the element
// on which this function is called, because it's the 'name' attribute for
// regular inputs, and the 'origName' attribute for inputs in multiple-instance
// templates.
$.fn.setDependentAutocompletion = function( dependentField, baseField, baseValue ) {
	// Get data from Semantic MediaWiki.
	let myServer = mw.config.get( 'wgScriptPath' ) + "/api.php";
	const wgPageFormsFieldProperties = mw.config.get( 'wgPageFormsFieldProperties' );
	myServer += "?action=pfautocomplete&format=json";
	const propName = wgPageFormsFieldProperties[dependentField];
	const baseProp = wgPageFormsFieldProperties[baseField];
	myServer += "&property=" + propName + "&baseprop=" + baseProp + "&basevalue=" + baseValue;
	const dependentValues = [];
	const $thisInput = $(this);
	// We use $.ajax() here instead of $.getJSON() so that the
	// 'async' parameter can be set. That, in turn, is set because
	// if the 2nd, "dependent" field is a combo box, it can have weird
	// behavior: clicking on the down arrow for the combo box leads to a
	// "blur" event for the base field, which causes the possible
	// values to get recalculated, but not in time for the dropdown to
	// change values - it still shows the old values. By setting
	// "async: false", we guarantee that old values won't be shown - if
	// the values haven't been recalculated yet, the dropdown won't
	// appear at all.
	// @TODO - handle this the right way, by having special behavior for
	// the dropdown - it should get delayed until the values are
	// calculated, then appear.
	$.ajax({
		url: myServer,
		dataType: 'json',
		async: false,
		success: function(data) {
			const realData = data.pfautocomplete;
			$.each(realData, (key, val) => {
				dependentValues.push(val.title);
			});
			$thisInput.data('autocompletevalues', dependentValues);
		}
	});
};

/**
 * Called on a 'base' field (e.g., for a country) - sets the autocompletion
 * for its 'dependent' field (e.g., for a city).
 *
 * @param {Mixed} partOfMultiple
 * @return {Mixed}
 */
$.fn.setAutocompleteForDependentField = function( partOfMultiple ) {
	const curValue = $(this).val();
	if ( curValue === null ) {
 return this;
}

	const nameAttr = partOfMultiple ? 'origName' : 'name';
	const name = $(this).attr(nameAttr);
	const wgPageFormsDependentFields = mw.config.get( 'wgPageFormsDependentFields' );
	let dependent_on_me = [];
	for ( let i = 0; i < wgPageFormsDependentFields.length; i++ ) {
		const dependentFieldPair = wgPageFormsDependentFields[i];
		if ( dependentFieldPair[0] === name ) {
			dependent_on_me.push(dependentFieldPair[1]);
		}
	}
	dependent_on_me = $.uniqueSort(dependent_on_me);

	const self = this;
	$.each( dependent_on_me, function() {
		let $element, cmbox, tokens;
		const dependentField = this;

		if ( partOfMultiple ) {
			$element = $( self ).closest( '.multipleTemplateInstance' )
				.find('[origName="' + dependentField + '"]');
		} else {
			$element = $('[name="' + dependentField + '"]');
		}

		if ( $element[0].classList.contains( 'pfTokens' ) ) {
			tokens = new pf.select2.tokens();
			tokens.refresh($element);
		} else {
			$element.setDependentAutocompletion(dependentField, name, curValue);
		}
	});


	return this;
};

/**
 * Initialize all the JS-using elements contained within this block - can be
 * called for either the entire HTML body, or for a div representing an
 * instance of a multiple-instance template.
 *
 * @param {Mixed} partOfMultiple
 */
$.fn.initializeJSElements = function( partOfMultiple ) {
	this.find(".pfShowIfSelected").each( function() {
		// Avoid duplicate calls on any one element.
		if ( !partOfMultiple && $(this).parents('.multipleTemplateWrapper').length > 0 ) {
			return;
		}

		// Don't call this for combobox inputs, except when a new
		// multiple-instance template instance is created - in all
		// other cases, their "show on select" is triggered separately.
		if ( $(this).attr( 'data-input-type' ) == 'combobox' ) {
			if ( partOfMultiple ) {
				$(this).showIfSelected(true, true)
			}
			return;
		}

		$(this)
		.showIfSelected(partOfMultiple, true)
		.change( function() {
			$(this).showIfSelected(partOfMultiple, false);
		});
	});

	this.find(".pfShowIfChecked").each( function() {
		// Avoid duplicate calls on any one element.
		if ( !partOfMultiple && $(this).parents('.multipleTemplateWrapper').length > 0 ) {
			return;
		}
		$(this)
		.showIfChecked(partOfMultiple, true)
		.click( function() {
			$(this).showIfChecked(partOfMultiple, false);
		});
	});

	this.find(".pfShowIfCheckedCheckbox").each( function() {
		// Avoid duplicate calls on any one element.
		if ( !partOfMultiple && $(this).parents('.multipleTemplateWrapper').length > 0 ) {
			return;
		}
		$(this)
		.showIfCheckedCheckbox(partOfMultiple, true)
		.click( function() {
			$(this).showIfCheckedCheckbox(partOfMultiple, false);
		});
	});

	if ( partOfMultiple ) {
		// Enable the new remove button
		this.find(".removeButton").click( function() {

			// Unregister initialization and validation for deleted inputs
			$(this).parentsUntil( '.multipleTemplateInstance' ).last().parent().find("input, select, textarea").each( function() {
				$(this).PageForms_unregisterInputInit();
				$(this).PageForms_unregisterInputValidation();
			});

			// Remove the encompassing div for this instance.
			$(this).closest(".multipleTemplateInstance")
			.fadeTo('fast', 0, function() {
				$(this).slideUp('fast', function() {
					$(this).remove();
				});
			});
			return false;
		});

		// ...and the new adder
		this.find('.addAboveButton').click( function() {
			$(this).addInstance( true );
			return false; // needed to disable <a> behavior
		});
	}

	this.find('.pfComboBox').not('.multipleTemplateStarter .pfComboBox').each(function(){
		const min_width = $(this).data('size');
		//check min_witdth - issue #53 -comboBox with two dropdowns if value present
		//link: https://github.com/gesinn-it-pub/mediawiki-extensions-PageForms/issues/53
		if (min_width == null) {
			return;
		}
		const input_width = $(this).val().length*11;
		const inputType = new pf.ComboBoxInput({});
		inputType.apply($(this));
		inputType.$element.css("width", input_width > min_width ? input_width : min_width);
		inputType.$element.css("min-width", min_width);
		inputType.$element.find("a").css("margin-left", "-1px");
		$(this).after(inputType.$element);
		$(this).remove();
	});

	const tokens = new pf.select2.tokens();
	this.find('.pfTokens').not('.multipleTemplateStarter .pfTokens, .select2-container').each( function() {
		tokens.apply($(this));
	});

	// Set the end date input to the value selected in start date
	this.find("span.startDateInput").not(".hiddenByPF").find("input").last().blur( () => {
		const $endInput = $(this).find("span.endDateInput").not(".hiddenByPF");
		const $endYearInput = $endInput.find(".yearInput");
		const $endMonthInput = $endInput.find(".monthInput");
		const $endDayInput = $endInput.find(".dayInput");

		// Update end date value only if it is not set
		if ($endYearInput.val() == '' && $endMonthInput.val() == '' && $endDayInput.val() == ''){
			const $startInput = $(this);
			const startYearVal = $startInput.find(".yearInput").val();
			const startMonthVal = $startInput.find(".monthInput").val();
			const startDayVal = $startInput.find(".dayInput").val();

			$endYearInput.val(startYearVal);
			$endMonthInput.val(startMonthVal);
			$endDayInput.val(startDayVal);
		}
	});

	const fancyBoxSettings = {
		toolbar : false,
		smallBtn : true,
		iframe : {
			preload : false,
			css : {
				width : '75%',
				height : '75%'
			}
		},
		animationEffect : false
	};

	if ( partOfMultiple ) {
		this.find('.pfFancyBox').fancybox(fancyBoxSettings);
		this.find('.autoGrow').autoGrow();
		this.find(".pfRating").each( function() {
			$(this).applyRatingInput();
		});
		this.find(".pfTreeInput").each( function() {
			$(this).applyJSTree();
		});
		this.find('.pfDatePicker').applyDatePicker();
		this.find('.pfDateTimePicker').applyDateTimePicker();
		// Only defined if $wgPageFormsSimpleUpload == true.
		if ( typeof this.initializeSimpleUpload === 'function' ) {
			this.find(".simpleUploadInterface").each( function() {
				$(this).initializeSimpleUpload();
			});
		}

		// Also add support in new template instances to any non-Page
		// Forms classes that require special JS handling.
		this.find('.mw-collapsible').makeCollapsible();
	} else {
		this.find('.pfFancyBox').not('multipleTemplateWrapper .pfFancyBox').fancybox(fancyBoxSettings);
		this.find('.autoGrow').not('.multipleTemplateWrapper .autoGrow').autoGrow();
		this.find(".pfRating").not(".multipleTemplateWrapper .pfRating").each( function() {
			$(this).applyRatingInput();
		});
		this.find(".pfTreeInput").not(".multipleTemplateWrapper .pfTreeInput").each( function() {
			$(this).applyJSTree();
		});
		this.find('.pfDatePicker').not(".multipleTemplateWrapper .pfDatePicker").applyDatePicker();
		this.find('.pfDateTimePicker').not(".multipleTemplateWrapper .pfDateTimePicker").applyDateTimePicker();
		// Only defined if $wgPageFormsSimpleUpload == true.
		if ( typeof this.initializeSimpleUpload === 'function' ) {
			this.find(".simpleUploadInterface").not(".multipleTemplateWrapper .simpleUploadInterface").each( function() {
				$(this).initializeSimpleUpload();
			});
		}
	}

	// @TODO - this should ideally be called only for inputs that have
	// a dependent field - which might involve changing the storage of
	// "dependent fields" information from a global variable to a
	// per-input HTML attribute.
	this.find('input, select').each( function() {
		$(this)
		.setAutocompleteForDependentField( partOfMultiple )
		.blur( function() {
			$(this).setAutocompleteForDependentField( partOfMultiple );
		});
	});
	// The 'blur' event doesn't get triggered for radio buttons for
	// Chrome and Safari (the WebKit-based browsers) so use the 'change'
	// event in addition.
	// @TODO - blur() shuldn't be called at all for radio buttons.
	this.find('input:radio')
		.change( function() {
			$(this).setAutocompleteForDependentField( partOfMultiple );
		});

	this.find('.new-uuid').each( function() {
		$(this).val(window.pfGenerateUUID());
	});

	this.find('[data-tooltip]').not('.multipleTemplateStarter [data-tooltip]').each( function() {
		// Even if it's within a <th>, display the text unbolded.
		const tooltipText = '<p style="font-weight: normal;">' + $(this).attr('data-tooltip') + '</p>';
		const tooltip = new OO.ui.PopupButtonWidget( {
			icon: 'info',
			label: mw.msg( 'pf-field-info-button' ),
			invisibleLabel: true,
			framed: false,
			popup: {
				padded: true,
				$content: $(tooltipText)
			}
		} );
		$(this).append( tooltip.$element )
	});

	const $myThis = this;
	if ( $.fn.applyVisualEditor ) {
		if ( partOfMultiple ) {
			$myThis.find(".visualeditor").applyVisualEditor();
		} else {
			$myThis.find(".visualeditor").not(".multipleTemplateWrapper .visualeditor").applyVisualEditor();
		}
	} else {
		$(document).on('VEForAllLoaded', (e) => {
			if ( partOfMultiple ) {
				$myThis.find(".visualeditor").applyVisualEditor();
			} else {
				$myThis.find(".visualeditor").not(".multipleTemplateWrapper .visualeditor").applyVisualEditor();
			}
		});
	}

	// @TODO - this should be in the TinyMCE extension, and use a hook.
	if ( typeof( mwTinyMCEInit ) === 'function' ) {
		if ( partOfMultiple ) {
			$myThis.find(".tinymce").each( function() {
				mwTinyMCEInit( '#' + $(this).attr('id') );
			});
		} else {
			$myThis.find(".tinymce").not(".multipleTemplateWrapper .tinymce").each( function() {
				mwTinyMCEInit( '#' + $(this).attr('id') );
			});
		}
	} else {
		$(document).on('TinyMCELoaded', (e) => {
			if ( partOfMultiple ) {
				$myThis.find(".tinymce").each( function() {
					mwTinyMCEInit( '#' + $(this).attr('id') );
				});
			} else {
				$myThis.find(".tinymce").not(".multipleTemplateWrapper .tinymce").each( function() {
					mwTinyMCEInit( '#' + $(this).attr('id') );
				});
			}
		});
	}

};

// Copied from https://stackoverflow.com/a/8809472
// License: public domain/MIT
window.pfGenerateUUID = function() {
	let d = Date.now();
	let d2 = (performance && performance.now && (performance.now() * 1000)) || 0; // Time in microseconds since page-load or 0 if unsupported
	return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
		let r = Math.random() * 16; // random number between 0 and 16
		if (d > 0) { // Use timestamp until depleted
			r = (d + r) % 16 | 0; // eslint-disable-line no-bitwise
			d = Math.floor(d / 16);
		} else { // Use microseconds since page-load if supported
			r = (d2 + r) % 16 | 0; // eslint-disable-line no-bitwise
			d2 = Math.floor(d2 / 16);
		}
		return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16); // eslint-disable-line no-bitwise
	});
}

// Once the document has finished loading, set up everything!
$(document).ready( () => {
	let i,
		inputID,
		validationFunctionData;

	function getFunctionFromName( functionName ) {
		let func = window;
		const namespaces = functionName.split( "." );
		for ( let nsNum = 0; nsNum < namespaces.length; nsNum++ ) {
			func = func[ namespaces[ nsNum ] ];
		}
		// If this gets called before the function is defined, just
		// store the function name instead, for later lookup.
		if ( func === null ) {
			return functionName;
		}
		return func;
	}

	// Exit now if a Page Forms form is not present.
	if ( $('#pfForm').length === 0 ) {
		return;
	}

	// jQuery's .ready() function is being called before the resource was actually loaded.
	// This is a workaround for https://phabricator.wikimedia.org/T216805.
	setTimeout( () => {
		// "Mask" to prevent users from clicking while form is still loading.
		$('#loadingMask').css({'width': $(document).width(),'height': $(document).height()});

		// register init functions
		const initFunctionData = mw.config.get( 'ext.pf.initFunctionData' );
		for ( inputID in initFunctionData ) {
			for ( i in initFunctionData[inputID] ) {
				/*jshint -W069 */
				$( '#' + inputID ).PageForms_registerInputInit( getFunctionFromName( initFunctionData[ inputID ][ i ][ 'name' ] ), initFunctionData[ inputID ][ i ][ 'param' ] );
				/*jshint +W069 */
			}
		}

		// register validation functions
		validationFunctionData = mw.config.get( 'ext.pf.validationFunctionData' );
		for ( inputID in validationFunctionData ) {
			for ( i in validationFunctionData[inputID] ) {
				/*jshint -W069 */
				$( '#' + inputID ).PageForms_registerInputValidation( getFunctionFromName( validationFunctionData[ inputID ][ i ][ 'name' ] ), validationFunctionData[ inputID ][ i ][ 'param' ] );
				/*jshint +W069 */
			}
		}

		$( 'body' ).initializeJSElements(false);
		$('.multipleTemplateInstance').each( function() {
				$(this).initializeJSElements(true);
		});
		$('.multipleTemplateAdder').click( function() {
			$(this).addInstance( false );
		});
		const wgPageFormsHeightForMinimizingInstances = mw.config.get( 'wgPageFormsHeightForMinimizingInstances' );
		if ( wgPageFormsHeightForMinimizingInstances >= 0) {
			$('.multipleTemplateList').each( function() {
				if ( $(this).height() > wgPageFormsHeightForMinimizingInstances ) {
					$(this).addClass('minimizeAll');
					$(this).possiblyMinimizeAllOpenInstances();
				}
			});
		}
		$('.multipleTemplateList').each( function() {
			const $list = $(this);
			const sortable = Sortable.create($list[0], {
				handle: '.instanceRearranger',
				onStart: function (/**Event*/evt) {
					$list.possiblyMinimizeAllOpenInstances();
				}
			});
		});

		// If there are any "wizard screen" elements defined in the
		// form, turn the whole form into a wizard, with successive
		// screens for each element.
		const $wizardScreens = $('form#pfForm').find('div.pf-wizard-screen');
		if ( $wizardScreens.length > 0 ) {
			const $wizardNav = $('<div class="pf-wizard-navigation"></div>');
			$('form#pfForm').append( $wizardNav );
			$wizardScreens.displayWizardScreen( 1, $wizardNav );
		}

		// If the form is submitted, validate everything!
		$('#pfForm').submit( () => validateAll() );

		// We are all done with synchronous init. Reveal the form once the DOM
		// has settled after all async post-init work (e.g. VEForAll's Parsoid
		// API call).  MutationObserver + debounce handles any async initializer
		// without requiring explicit per-extension checks: each DOM mutation
		// resets the debounce timer, so the form is revealed only after the DOM
		// is quiet.  On simple forms with no async mutations the initial
		// setTimeout(0) fires immediately.
		const form = document.getElementById( 'pfForm' );
		const revealForm = () => {
			$( '#pfForm' ).css( 'visibility', 'visible' );
			$( '.loadingImage' ).remove();
		};
		let pfInitDebounce;
		// Declared before doReveal so it can be const.  doReveal is only called
		// later; the closure captures pfInitSafety / doReveal by binding, so
		// forward references are resolved at call-time, not at definition-time.
		const pfInitObserver = new MutationObserver( () => {
			clearTimeout( pfInitDebounce );
			pfInitDebounce = setTimeout( doReveal, 150 );
		} );
		const doReveal = () => {
			clearTimeout( pfInitDebounce );
			clearTimeout( pfInitSafety );
			pfInitObserver.disconnect();
			revealForm();
		};
		// Absolute fallback: reveal after 3 s even if mutations never stop
		// (e.g. a broken extension keeps mutating the DOM indefinitely).
		const pfInitSafety = setTimeout( doReveal, 3000 );
		pfInitObserver.observe( form, { childList: true, subtree: true, attributes: true } );
		// Fast path: no mutations → reveal on next event-loop tick.
		pfInitDebounce = setTimeout( doReveal, 0 );
	}, 0 );

	mw.hook('pf.formSetupAfter').fire();
});

// If some part of the form is clicked, minimize any multiple-instance
// template instances that need minimizing, and move the "focus" to the current
// instance list, if one is being clicked and it's different from the
// previous one.
// We make only the form itself clickable, instead of the whole screen, to
// try to avoid a click on a popup, like the "Upload file" window, minimizing
// the current open instance.
$('form#pfForm').click( (e) => {
	const $target = $(e.target);
	// Ignore the "add instance" buttons - those get handling of their own.
	const clickedOnAddAnother = $target.parents('.multipleTemplateAdder').length > 0;
	if ( clickedOnAddAnother || $target[0].classList.contains('addAboveButton') ) {
		return;
	}

	const $instance = $target.closest('.multipleTemplateInstance');
	if ( $instance === null ) {
		$('.multipleTemplateList.currentFocus')
			.removeClass('currentFocus')
			.possiblyMinimizeAllOpenInstances();
		return;
	}

	const $instancesList = $instance.closest('.multipleTemplateList');
	if ( !$instancesList[0].classList.contains('currentFocus') ) {
		$('.multipleTemplateList.currentFocus')
			.removeClass('currentFocus')
			.possiblyMinimizeAllOpenInstances();
		if ( $instancesList[0].classList.contains('minimizeAll') ) {
			$instancesList.addClass('currentFocus');
		}
	}

	if ( $instance[0].classList.contains('minimized') ) {
		$instancesList.possiblyMinimizeAllOpenInstances();
		$instance.removeClass('minimized');
		$instance.find('.fieldValuesDisplay').html('');
		$instance.find('.instanceMain').fadeIn();
		$instance.find('.fieldValuesDisplay').remove();
		// Remove unhelpful styling added by VisualEditor.
		$instance.find('div.oo-ui-toolbar-bar').css('left', null);
		$instance.find('div.oo-ui-toolbar-bar').css('right', null);
	}
});

$('#pf-expand-all a').click(( event ) => {
	event.preventDefault();

	// Page Forms minimized template instances.
	$('.minimized').each( function() {
		$(this).removeClass('minimized');
		$(this).find('.fieldValuesDisplay').html('');
		$(this).find('.instanceMain').fadeIn();
		$(this).find('.fieldValuesDisplay').remove();
		// Remove unhelpful styling added by VisualEditor.
		$(this).find('div.oo-ui-toolbar-bar').css('left', null);
		$(this).find('div.oo-ui-toolbar-bar').css('right', null);
	});

	// Standard MediaWiki "collapsible" sections.
	$('div.mw-collapsed a.mw-collapsible-text').click();
});

$('.pfSendBack').click( () => {
	window.history.back();
});

}( jQuery, mediaWiki ) );
