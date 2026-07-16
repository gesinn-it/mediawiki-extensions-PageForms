/**
 * PF_showOnSelect.js
 *
 * "Show on select" conditional-visibility functions for the Page Forms
 * extension, plus the input-init/validation registration registry
 * (PageForms_registerInputInit/Validation) that show-on-select relies on.
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
/*global wgPageFormsShowOnSelect, alert*/

( function ( $, mw ) {

/*
 * Functions to register/unregister methods for the initialization and
 * validation of inputs.
 */

// Initialize data object to hold initialization and validation data
function setupPF() {

	$("#pfForm").data("PageForms",{
		initFunctions : [],
		validationFunctions : []
	});

}

// Register a validation method
//
// More than one method may be registered for one input by subsequent calls to
// PageForms_registerInputValidation.
//
// Validation functions and their data are stored in a numbered array
//
// @param valfunction The validation functions. Must take a string (the input's id) and an object as parameters
// @param param The parameter object given to the validation function
$.fn.PageForms_registerInputValidation = function(valfunction, param) {

	if ( ! this.attr("id") ) {
		return this;
	}

	if ( ! $("#pfForm").data("PageForms") ) {
		setupPF();
	}

	$("#pfForm").data("PageForms").validationFunctions.push({
		input : this.attr("id"),
		valfunction : valfunction,
		parameters : param
	});

	return this;
};

// Register an initialization method
//
// More than one method may be registered for one input by subsequent calls to
// PageForms_registerInputInit. This method also executes the initFunction
// if the element referenced by /this/ is not part of a multipleTemplateStarter.
//
// Initialization functions and their data are stored in a associative array
//
// @param initFunction The initialization function. Must take a string (the input's id) and an object as parameters
// @param param The parameter object given to the initialization function
// @param noexecute If set, the initialization method will not be executed here
$.fn.PageForms_registerInputInit = function( initFunction, param, noexecute ) {

	// return if element has no id
	if ( ! this.attr("id") ) {
		return this;
	}

	// setup data structure if necessary
	if ( ! $("#pfForm").data("PageForms") ) {
		setupPF();
	}

	// if no initialization function for this input was registered yet,
	// create entry
	if ( ! $("#pfForm").data("PageForms").initFunctions[this.attr("id")] ) {
		$("#pfForm").data("PageForms").initFunctions[this.attr("id")] = [];
	}

	// record initialization function
	$("#pfForm").data("PageForms").initFunctions[this.attr("id")].push({
		initFunction : initFunction,
		parameters : param
	});

	// execute initialization if input is not part of multipleTemplateStarter
	// and if not forbidden
	if ( this.closest(".multipleTemplateStarter").length === 0 && !noexecute) {
		const $input = this;
		// ensure initFunction is only executed after doc structure is complete
		$(() => {
			if ( initFunction !== undefined ) {
				initFunction ( $input.attr("id"), param );
			}
		});
	}

	return this;
};

// Unregister all validation methods for the element referenced by /this/
$.fn.PageForms_unregisterInputValidation = function() {

	const pfdata = $("#pfForm").data("PageForms");

	if ( this.attr("id") && pfdata ) {
		// delete every validation method for this input
		for ( let i = 0; i < pfdata.validationFunctions.length; i++ ) {
			if ( typeof pfdata.validationFunctions[i] !== 'undefined' &&
				pfdata.validationFunctions[i].input === this.attr("id") ) {
				delete pfdata.validationFunctions[i];
			}
		}
	}

	return this;
};

// Unregister all initialization methods for the element referenced by /this/
$.fn.PageForms_unregisterInputInit = function() {

	if ( this.attr("id") && $("#pfForm").data("PageForms") ) {
		delete $("#pfForm").data("PageForms").initFunctions[this.attr("id")];
	}

	return this;
};

// Called from within PF_ComboBoxInput.php.
mw.hook('pf.comboboxChange').add( ( $parentSpan ) => {
	const initPage = $parentSpan.find('select').length > 0;
	const partOfMultiple = $parentSpan.attr('data-origid') !== undefined;
	$parentSpan.showIfSelected( partOfMultiple, initPage );
});

/*
 * Functions for handling 'show on select'
 */

// Display a div that would otherwise be hidden by "show on select".
function showDiv( div_id, $instanceWrapperDiv, initPage ) {
	const speed = initPage ? 0 : 'fast';
	let $elem;
	if ( $instanceWrapperDiv !== null ) {
		$elem = $('[data-origID="' + div_id + '"]', $instanceWrapperDiv);
	} else {
		$elem = $('#' + div_id);
	}

	$elem
	.addClass('shownByPF')

	.find(".hiddenByPF")
	.removeClass('hiddenByPF')
	.addClass('shownByPF')

	.find(".disabledByPF")
	.prop('disabled', false)
	.removeClass('disabledByPF');

	$elem.each( function() {
		if ( $(this).css('display') === 'none' ) {

			$(this).slideDown(speed, function() {
				$(this).fadeTo(speed,1);
			});

		}
	});

	// Now re-show any form elements that are meant to be shown due
	// to the current value of form inputs in this div that are now
	// being uncovered.
	const wgPageFormsShowOnSelect = mw.config.get( 'wgPageFormsShowOnSelect' );
	$elem.find(".pfShowIfSelected, .pfShowIfChecked").each( function() {
		const $uncoveredInput = $(this);
		let uncoveredInputID = null;
		if ( $instanceWrapperDiv === null ) {
			uncoveredInputID = $uncoveredInput.attr("id");
		} else {
			uncoveredInputID = $uncoveredInput.attr("data-origID");
		}
		const showOnSelectVals = wgPageFormsShowOnSelect[uncoveredInputID];

		if ( showOnSelectVals !== undefined ) {
			const inputVal = $uncoveredInput.val();
			for ( let i = 0; i < showOnSelectVals.length; i++ ) {
				const options = showOnSelectVals[i][0];
				const div_id2 = showOnSelectVals[i][1];
				if ( $uncoveredInput[0].classList.contains( 'pfShowIfSelected' ) ) {
					showDivIfSelected( options, div_id2, inputVal, $instanceWrapperDiv, initPage );
				} else {
					$uncoveredInput.showDivIfChecked( options, div_id2, $instanceWrapperDiv, initPage );
				}
			}
		}
	});
}

// Hide a div due to "show on select". The CSS class is there so that PF can
// ignore the div's contents when the form is submitted.
function hideDiv( div_id, $instanceWrapperDiv, initPage ) {
	const speed = initPage ? 0 : 'fast';
	let $elem;
	// IDs can't contain spaces, and jQuery won't work with such IDs - if
	// this one has a space, display an alert.
	if ( div_id.includes(' ') ) {
		// TODO - this should probably be a language value, instead of
		// hardcoded in English.
		alert( "Warning: this form has \"show on select\" pointing to an invalid element ID (\"" + div_id + "\") - IDs in HTML cannot contain spaces." );
	}

	if ( $instanceWrapperDiv !== null ) {
		$elem = $instanceWrapperDiv.find('[data-origID=' + div_id + ']');
	} else {
		$elem = $('#' + div_id);
	}

	// If we're just setting up the page, and this element has already
	// been marked to be shown by some other input, don't hide it.
	if ( initPage && $elem[0].classList.contains('shownByPF') ) {
		return;
	}

	$elem.find("span, div").addClass('hiddenByPF');

	$elem.each( function() {
		if ( $(this).css('display') !== 'none' ) {

			// if 'display' is not 'hidden', but the element is hidden otherwise
			// (e.g. by having height = 0), just hide it, else animate the hiding
			if ( this.offsetWidth === 0 || this.offsetHeight === 0 ) {
				$(this).hide();
			} else {
				$(this).fadeTo(speed, 0, function() {
					$(this).slideUp(speed);
				});
			}
		}
	});

	// Also, recursively hide further elements that are only shown because
	// inputs within this now-hidden div were checked/selected.
	const wgPageFormsShowOnSelect = mw.config.get( 'wgPageFormsShowOnSelect' );
	$elem.find(".pfShowIfSelected, .pfShowIfChecked").each( function() {
		let showOnSelectVals;
		if ( $instanceWrapperDiv === null ) {
			showOnSelectVals = wgPageFormsShowOnSelect[$(this).attr("id")];
		} else {
			showOnSelectVals = wgPageFormsShowOnSelect[$(this).attr("data-origID")];
		}

		if ( showOnSelectVals !== undefined ) {
			for ( let i = 0; i < showOnSelectVals.length; i++ ) {
				//var options = showOnSelectVals[i][0];
				const div_id2 = showOnSelectVals[i][1];
				hideDiv( div_id2, $instanceWrapperDiv, initPage );
			}
		}
	});
}

// Show this div if the current value is any of the relevant options -
// otherwise, hide it.
function showDivIfSelected(options, div_id, inputVal, $instanceWrapperDiv, initPage) {
	for ( let i = 0; i < options.length; i++ ) {
		// If it's a listbox and the user has selected more than one
		// value, it'll be an array - handle either case.
		if (($.isArray(inputVal) && $.inArray(options[i], inputVal) >= 0) ||
			(!$.isArray(inputVal) && (inputVal === options[i]))) {
			showDiv( div_id, $instanceWrapperDiv, initPage );
			return;
		}
	}
	hideDiv( div_id, $instanceWrapperDiv, initPage );
}

// Used for handling 'show on select' for the 'dropdown', 'listbox',
// 'combobox' and 'tokens' input types.
$.fn.showIfSelected = function(partOfMultiple, initPage) {
	let inputVal,
		showOnSelectVals,
		$instanceWrapperDiv;
	const wgPageFormsShowOnSelect = mw.config.get( 'wgPageFormsShowOnSelect' );

		if ( this.attr( 'data-input-type' ) == 'combobox' ) {
			if ( initPage ) {
				inputVal = $(this).find('select').val();
			} else {
				inputVal = $(this).find('input').val();
			}
		} else if ( this.attr( 'data-input-type' ) == 'tokens' ) {
			if ( initPage ) {
				inputVal = $(this).find('select').val();
			} else {
				inputVal = [];
				$(this).find('li.select2-selection__choice').each( function() {
					inputVal.push( $(this).attr('title') );
				});
			}
		} else {
			inputVal = this.val();
		}

	if ( partOfMultiple ) {
		showOnSelectVals = wgPageFormsShowOnSelect[this.attr("data-origID")];
		$instanceWrapperDiv = this.closest('.multipleTemplateInstance');
	} else {
		showOnSelectVals = wgPageFormsShowOnSelect[this.attr("id")];
		$instanceWrapperDiv = null;
	}

	if ( showOnSelectVals !== undefined ) {
		for ( let i = 0; i < showOnSelectVals.length; i++ ) {
			const options = showOnSelectVals[i][0];
			const div_id = showOnSelectVals[i][1];
			showDivIfSelected( options, div_id, inputVal, $instanceWrapperDiv, initPage );
		}
	}

	return this;
};

// Show this div if any of the relevant selections are checked -
// otherwise, hide it.
$.fn.showDivIfChecked = function(options, div_id, $instanceWrapperDiv, initPage ) {
	for ( let i = 0; i < options.length; i++ ) {
		if ($(this).find('[value="' + options[i] + '"]').is(":checked")) {
			showDiv( div_id, $instanceWrapperDiv, initPage );
			return this;
		}
	}
	hideDiv( div_id, $instanceWrapperDiv, initPage );

	return this;
};

// Used for handling 'show on select' for the 'checkboxes' and 'radiobutton'
// inputs.
$.fn.showIfChecked = function(partOfMultiple, initPage) {
	const wgPageFormsShowOnSelect = mw.config.get( 'wgPageFormsShowOnSelect' );
	let showOnSelectVals,
		$instanceWrapperDiv,
		i;

	if ( partOfMultiple ) {
		showOnSelectVals = wgPageFormsShowOnSelect[this.attr("data-origID")];
		$instanceWrapperDiv = this.closest('.multipleTemplateInstance');
	} else {
		showOnSelectVals = wgPageFormsShowOnSelect[this.attr("id")];
		$instanceWrapperDiv = null;
	}

	if ( showOnSelectVals !== undefined ) {
		for ( i = 0; i < showOnSelectVals.length; i++ ) {
			const options = showOnSelectVals[i][0];
			const div_id = showOnSelectVals[i][1];
			this.showDivIfChecked( options, div_id, $instanceWrapperDiv, initPage );
		}
	}

	return this;
};

// Used for handling 'show on select' for the 'checkbox' input.
$.fn.showIfCheckedCheckbox = function( partOfMultiple, initPage ) {
	const wgPageFormsShowOnSelect = mw.config.get( 'wgPageFormsShowOnSelect' );
	let divIDs,
		$instanceWrapperDiv = null,
		i;
	if ( partOfMultiple ) {
		divIDs = wgPageFormsShowOnSelect[this.attr( "data-origID" )];
		$instanceWrapperDiv = this.closest( ".multipleTemplateInstance" );
	}
	if ( divIDs === undefined ) {
		divIDs = wgPageFormsShowOnSelect[this.attr( "id" )];
	}
	for ( i = 0; i < divIDs.length; i++ ) {
		const divID = divIDs[i];
		if ( $( this ).find( '[value]' ).is( ":checked" ) ) {
			showDiv( divID, $instanceWrapperDiv, initPage );
		} else {
			hideDiv( divID, $instanceWrapperDiv, initPage );
		}
	}

	return this;
};

}( jQuery, mediaWiki ) );
