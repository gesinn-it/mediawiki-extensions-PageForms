/**
 * PF_multipleInstance.js
 *
 * Multiple-instance-template handling for the Page Forms extension:
 * minimizing open instances, wizard-screen navigation, and adding/
 * removing template instances.
 *
 * @author Yaron Koren
 * @author Sanyam Goyal
 * @author Stephan Gambke
 * @author Jeffrey Stuckman
 * @author Harold Solbrig
 * @author Eugene Mednikov
 * @param {jQuery} $
 * @param {mw} mw
 */
/*global wgPageFormsShowOnSelect*/

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
			label: backText,
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


}( jQuery, mediaWiki ) );
