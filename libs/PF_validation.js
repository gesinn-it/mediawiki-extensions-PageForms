/**
 * PF_validation.js
 *
 * Field-validation functions for the Page Forms extension, plus the
 * window.validateAll() entry point run on form submission.
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

( function ( $, mw ) {

/*
 * Validation functions
 */

// Set the error message for an input.
$.fn.setErrorMessage = function(msg, val) {
	const container = this.find('.pfErrorMessages');
	container.html($('<div>').addClass( 'errorMessage' ).text( mw.msg( msg, val ) )); // eslint-disable-line mediawiki/msg-doc
};

// Append an error message to the end of an input.
$.fn.addErrorMessage = function(msg, val, $incorrectElements) {
	// Remove error class from all relevant elements first (a previous error may have vanished by now)
	const $relevantElements = this.find('input, select, select2-container');
	$relevantElements.removeClass('inputError');

	// Set input error and show message
	($incorrectElements || $relevantElements).addClass('inputError');
	this.append($('<div>').addClass( 'errorMessage' ).text( mw.msg( msg, val ) )); // eslint-disable-line mediawiki/msg-doc

	// If this is part of a minimized multiple-template instance, add a
	// red border around the instance rectangle to make it easier to find.
	this.parents( '.multipleTemplateInstance.minimized' ).css( 'border', '1px solid red' );
};

$.fn.isAtMaxInstances = function() {
	const numInstances = this.find("div.multipleTemplateInstance").length;
	const maximumInstances = this.attr("maximumInstances");
	if ( numInstances >= maximumInstances ) {
		this.parent().setErrorMessage( 'pf_too_many_instances_error', maximumInstances );
		return true;
	}
	return false;
};

$.fn.validateNumInstances = function() {
	const minimumInstances = this.attr("minimumInstances");
	const maximumInstances = this.attr("maximumInstances");
	const numInstances = this.find("div.multipleTemplateInstance").length;
	if ( numInstances < minimumInstances ) {
		this.parent().addErrorMessage( 'pf_too_few_instances_error', minimumInstances );
		return false;
	} else if ( numInstances > maximumInstances ) {
		this.parent().addErrorMessage( 'pf_too_many_instances_error', maximumInstances );
		return false;
	} else {
		return true;
	}
};

$.fn.validateMandatoryField = function() {
	const fieldVal = this.find(".mandatoryField").val();
	let isEmpty;

	if (fieldVal === null) {
		isEmpty = true;
	} else if ($.isArray(fieldVal)) {
		isEmpty = (fieldVal.length === 0);
	} else {
		isEmpty = (fieldVal.replace(/\s+/, '') === '');
	}
	if (isEmpty) {
		this.addErrorMessage( 'pf_blank_error' );
		return false;
	} else {
		return true;
	}
};

$.fn.validateUniqueField = function() {

	const UNDEFINED = "undefined";
	const field = this.find(".uniqueField");
	const fieldVal = field.val();

	if (typeof fieldVal === UNDEFINED || fieldVal.replace(/\s+/, '') === '') {
		return true;
	}

	const fieldOrigVal = field.prop("defaultValue");
	if (fieldVal === fieldOrigVal) {
		return true;
	}

	const categoryFieldName = field.prop("id") + "_unique_for_category";
	const $categoryField = $("[name=" + categoryFieldName + "]");
	const category = $categoryField.val();

	const namespaceFieldName = field.prop("id") + "_unique_for_namespace";
	const $namespaceField = $("[name=" + namespaceFieldName + "]");
	const namespace = $namespaceField.val();

	let url = mw.config.get( 'wgScriptPath' ) + "/api.php?format=json&action=";

	let query,
		isNotUnique;

	// SMW
	const propertyFieldName = field.prop("id") + "_unique_property",
		$propertyField = $("[name=" + propertyFieldName + "]"),
		property = $propertyField.val();
	if (typeof property !== UNDEFINED && property.replace(/\s+/, '') !== '') {

		query = "[[" + property + "::" + fieldVal + "]]";

		if (typeof category !== UNDEFINED &&
			category.replace(/\s+/, '') !== '') {
			query += "[[Category:" + category + "]]";
		}

		if (typeof namespace !== UNDEFINED) {
			if (namespace.replace(/\s+/, '') !== '') {
				query += "[[:" + namespace + ":+]]";
			} else {
				query += "[[:+]]";
			}
		}

		const conceptFieldName = field.prop("id") + "_unique_for_concept";
		const $conceptField = $("[name=" + conceptFieldName + "]");
		const concept = $conceptField.val();
		if (typeof concept !== UNDEFINED &&
			concept.replace(/\s+/, '') !== '') {
			query += "[[Concept:" + concept + "]]";
		}

		query += "|limit=1";
		query = encodeURIComponent(query);

		url += "ask&query=" + query;
		isNotUnique = true;
		$.ajax({
			url: url,
			dataType: 'json',
			async: false,
			success: function(data) {
				if (data.query.meta.count === 0) {
					isNotUnique = false;
				}
			}
		});
		if (isNotUnique) {
			this.addErrorMessage( 'pf_not_unique_error' );
			return false;
		} else {
			return true;
		}
	}

	return true;

};

$.fn.validateMandatoryComboBox = function() {
	const $combobox = this.find('.mandatoryField');
	if ($combobox.val() === null || $combobox.val() === '') {
		this.addErrorMessage( 'pf_blank_error' );
		return false;
	} else {
		return true;
	}
};

$.fn.validateMandatoryDateField = function() {
	const $year = this.find(".yearInput");
	if ($year.val() === '') {
		this.addErrorMessage( 'pf_blank_error', null, $year );
		return false;
	}

	const $month = this.find(".monthInput");
	const $day = this.find(".dayInput");
	if ($day.val() !== '' && $month.val() === '') {
		this.addErrorMessage( 'pf_blank_error', null, $month );
		return false;
	}

	return true;
};
// ==== GESINN PATCH BEGIN ====
// Added custom validation function for mandatory datetime fields
$.fn.validateMandatoryDateTimeField = function() {
	// validate that all fields are filled in
    const day = this.find(".dayInput").val();
    const month = this.find(".monthInput").val();
    const year = this.find(".yearInput").val();
    const hour = this.find(".hoursInput").val();
    const minute = this.find(".minutesInput").val();
    const second = this.find(".secondsInput").val();

	// if any field is blank, return false and mandatory error
    if (day === "" || month === "" || year === "" || hour === "" || minute === "") {
        this.addErrorMessage('pf_blank_error');
        return false;
    }
    return true;
};
// ==== GESINN PATCH END ====

$.fn.validateMandatoryRadioButton = function() {
	const checkedValue = this.find("input:checked").val();
	if (!checkedValue || checkedValue == '') {
		this.addErrorMessage('pf_blank_error');
		return false;
	} else {
		return true;
	}
};

$.fn.validateMandatoryCheckboxes = function() {
	// Get the number of checked checkboxes within this span - must
	// be at least one.
	const numChecked = this.find("input:checked").length;
	if (numChecked === 0) {
		this.addErrorMessage('pf_blank_error');
		return false;
	} else {
		return true;
	}
};

// ==== GESINN PATCH BEGIN ====
// Added custom validation function for mandatory google maps fields
$.fn.validateMandatoryGeoCoordinatesMaps = function() {
    if ( $(this).val().trim() === '' ) {
        $(this).parent().addErrorMessage('pf_blank_error');
        return false;
    }
    return true;
};
// ==== GESINN PATCH END ====

$.fn.validateMandatoryTree = function() {
	const input_value = this.find( 'input' ).attr( 'value' );
	if ( input_value === undefined || input_value === '' ) {
		this.addErrorMessage( 'pf_blank_error' );
		return false;
	} else {
		return true;
	}
};

$.fn.validateMandatoryDatePicker = function() {
	const input = this.find('input');
	if (input.val() === null || input.val() === '') {
		this.addErrorMessage( 'pf_blank_error' );
		return false;
	} else {
		return true;
	}
};

/*
 * Type-based validation
 */

$.fn.validateURLField = function() {
	const fieldVal = this.find("input").val();
	let url_protocol = mw.config.get( 'wgUrlProtocols' );
	//removing backslash before colon from url_protocol string
	url_protocol = url_protocol.replace( /\\:/, ':' );
	//removing '//' from wgUrlProtocols as this causes to match any protocol in regexp
	url_protocol = url_protocol.replace( /\|\\\/\\\//, '' );
	const url_regexp = new RegExp( '(' + url_protocol + ')' + '(\\w+:{0,1}\\w*@)?(\\S+)(:[0-9]+)?(\/|\/([\\w#!:.?+=&%@!\\-\/]))?' );
	if (fieldVal === "" || url_regexp.test(fieldVal)) {
		return true;
	} else {
		this.addErrorMessage( 'pf_bad_url_error' );
		return false;
	}
};

$.fn.validateEmailField = function() {
	const fieldVal = this.find("input").val();
	// code borrowed from http://javascript.internet.com/forms/email-validation---basic.html
	const email_regexp = /^\s*\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,6})+\s*$/;
	if (fieldVal === '' || email_regexp.test(fieldVal)) {
		return true;
	} else {
		this.addErrorMessage( 'pf_bad_email_error' );
		return false;
	}
};

$.fn.validateNumberField = function() {
	const fieldVal = this.find("input").val();
	// Handle "E notation"/scientific notation ("1.2e-3") in addition
	// to regular numbers
	if (fieldVal === '' ||
	fieldVal.match(/^\s*[\-+]?((\d+[\.,]?\d*)|(\d*[\.,]?\d+))([eE]?[\-\+]?\d+)?\s*$/)) {
		return true;
	} else {
		this.addErrorMessage( 'pf_bad_number_error' );
		return false;
	}
};

$.fn.validateIntegerField = function() {
	const fieldVal = this.find("input").val();
	if ( fieldVal === '' || fieldVal == parseInt( fieldVal, 10 ) ) {
		return true;
	} else {
		this.addErrorMessage( 'pf_bad_integer_error' );
		return false;
	}
};

$.fn.validateDateField = function() {
	// validate only if day and year fields are both filled in
	const dayVal = this.find(".dayInput").val();
	const yearVal = this.find(".yearInput").val();
	if (dayVal === '' || yearVal === '') {
		return true;
	} else if (dayVal.match(/^\d+$/) && dayVal <= 31) {
		// no year validation, since it can also include
		// 'BC' and possibly other non-number strings
		return true;
	} else {
		this.addErrorMessage( 'pf_bad_date_error' );
		return false;
	}
};

// Standalone pipes are not allowed, because they mess up the template
// parsing; unless they're part of a call to a template or a parser function.
$.fn.checkForPipes = function() {
	let fieldVal = this.find("input, textarea").val();
	// We need to check for a few different things because this is
	// called for a variety of different input types.
	if ( fieldVal === undefined || fieldVal === '' ) {
		fieldVal = this.text();
	}
	if ( fieldVal === undefined || fieldVal === '' ) {
		return true;
	}
	if ( !fieldVal.includes('|') ) {
		return true;
	}

	// Also allow pipes within special tags, like <pre> or <syntaxhighlight>.
	// Code copied, more or less, from PFTemplateInForm::escapeNonTemplatePipes().
	const startAndEndTags = [
		[ '<pre', 'pre>' ],
		[ '<syntaxhighlight', 'syntaxhighlight>' ],
		[ '<source', 'source>' ],
		[ '<ref', 'ref>' ]
	];

	for ( const i in startAndEndTags ) {
		const startTag = startAndEndTags[i][0];
		const endTag = startAndEndTags[i][1];
		const pattern = RegExp( "(" + startTag + "[^]*?)\\|([^]*?" + endTag + ")", 'i' );
	let matches;
		while ( ( matches = fieldVal.match( pattern ) ) !== null ) {
			// Special handling, to avoid escaping pipes
			// within a string that looks like:
			// startTag ... endTag | startTag ... endTag
			if ( matches[1].includes( endTag ) &&
				matches[2].includes( startTag ) ) {
				fieldVal = fieldVal.replace( pattern, "$1" + "\u0002" + "$2");
			} else {
				fieldVal = fieldVal.replace( pattern, "$1" + "\u0001" + "$2" );
			}
		}
	}
	fieldVal = fieldVal.replace( "\u0002", '|' );

	// Now check for pipes outside of brackets.
	let nextPipe,
		nextDoubleBracketsStart,
		nextDoubleBracketsEnd;

	// There's at least one pipe - here's where the real work begins.
	// We do a mini-parsing of the string to try to make sure that every
	// pipe is within either double square brackets (links) or double
	// curly brackets (parser functions, template calls).
	// For simplicity's sake, turn all curly brackets into square brackets,
	// so we only have to check for one thing.
	// This will incorrectly allow bad text like "[[a|b}}", but hopefully
	// that's not a major problem.
	fieldVal = fieldVal.replace( /{{/g, '[[' );
	fieldVal = fieldVal.replace( /}}/g, ']]' );
	let curIndex = 0;
	let numUnclosedBrackets = 0;
	while ( true ) {
		nextDoubleBracketsStart = fieldVal.indexOf( '[[', curIndex );

		if ( numUnclosedBrackets === 0 ) {
			nextPipe = fieldVal.indexOf( '|', curIndex );
			if ( nextPipe < 0 ) {
				return true;
			}
			if ( nextDoubleBracketsStart < 0 || nextPipe < nextDoubleBracketsStart ) {
				// There's a pipe where it shouldn't be.
				this.addErrorMessage( 'pf_pipe_error' );
				return false;
			}
		} else {
			if ( nextDoubleBracketsEnd < 0 ) {
				// Something is malformed - might as well throw
				// an error.
				this.addErrorMessage( 'pf_pipe_error' );
				return false;
			}
		}

		nextDoubleBracketsEnd = fieldVal.indexOf( ']]', curIndex );

		if ( nextDoubleBracketsStart >= 0 && nextDoubleBracketsStart < nextDoubleBracketsEnd ) {
			numUnclosedBrackets++;
			curIndex = nextDoubleBracketsStart + 2;
		} else {
			numUnclosedBrackets--;
			curIndex = nextDoubleBracketsEnd + 2;
		}
	}

	// We'll never get here, but let's have this line anyway.
	return true;
};

function leftPad( number, targetLength ) {
	let negative = false;
	if ( number < 0 ) {
		number = number * -1;
		negative = true;
	}
	let output = number + '';
	while ( output.length < targetLength ) {
		output = '0' + output;
	}
	if ( negative ) {
		output = '-' + output
	}
	return output;
}

function validateStartEndDateField( startInput, endInput ) {
	if ( !startInput.length || !endInput.length ) {
		return true;
	}
	const startYearVal = leftPad( startInput.find(".yearInput").val(),4 );
	const startMonthVal = leftPad( startInput.find(".monthInput").val(),2 );
	const startDayVal = leftPad( startInput.find(".dayInput").val(),2 );

	const endYearVal = leftPad( endInput.find(".yearInput").val(),4 );
	const endMonthVal = leftPad( endInput.find(".monthInput").val(),2 );
	const endDayVal = leftPad( endInput.find(".dayInput").val(),2 );

	const startDate = startYearVal + "/" + startMonthVal + "/" + startDayVal;

	const endDate = endYearVal + "/" + endMonthVal + "/" + endDayVal;

	if ( startDate <= endDate || endDate == "0000/00/00") {
		return true;
	} else {
		if ( endInput ) {
			endInput.addErrorMessage( 'pf_start_end_date_error' )
		} else if ( startInput ) {
			startInput.addErrorMessage( 'pf_start_end_date_error' )
		}
		return false;
	}
}

function validateStartEndDateTimeField( startInput, endInput ) {
	if ( !startInput.length || !endInput.length ) {
		return true;
	}
	const startYearVal = leftPad( startInput.find(".yearInput").val(),4 );
	const startMonthVal = leftPad( startInput.find(".monthInput").val(),2 );
	const startDayVal = leftPad( startInput.find(".dayInput").val(),2 );
	const startHoursVal = leftPad( startInput.find(".hoursInput").val(),2 );
	const startMinutesVal = leftPad( startInput.find(".minutesInput").val(),2 );
	const startSecondsVal = leftPad( startInput.find(".secondsInput").val(),2 );
	const startAmPmVal = startInput.find(".ampmInput").val();

	const endYearVal = leftPad( endInput.find(".yearInput").val(),4 );
	const endMonthVal = leftPad( endInput.find(".monthInput").val(),2 );
	const endDayVal = leftPad( endInput.find(".dayInput").val(),2 );
	const endHoursVal = leftPad( endInput.find(".hoursInput").val(),2 );
	const endMinutesVal = leftPad( endInput.find(".minutesInput").val(),2 );
	const endSecondsVal = leftPad( endInput.find(".secondsInput").val(),2 );
	const endAmPmVal = endInput.find(".ampmInput").val();

	const startDateTime = startYearVal + "/" + startMonthVal + "/" + startDayVal + " " +
	startHoursVal + ":" + startMinutesVal + ":" + startSecondsVal + " " + startAmPmVal;

	const endDateTime = endYearVal + "/" + endMonthVal + "/" + endDayVal + " " +
		endHoursVal + ":" + endMinutesVal + ":" + endSecondsVal + " " + endAmPmVal;

	if ( startDateTime <= endDateTime || endDateTime == "0000/00/00 00:00:00 " ) {
		return true;
	} else {
		if ( endInput ) {
			endInput.addErrorMessage( 'pf_start_end_datetime_error' )
		} else if ( startInput ) {
			startInput.addErrorMessage( 'pf_start_end_datetime_error' )
		}
		return false;
	}

}

window.validateAll = function () {

	// Hook that fires on form submission, before the validation.
	mw.hook('pf.formValidationBefore').fire();

	const args = {numErrors: 0};
	mw.hook('pf.formValidation').fire( args );
	let num_errors = args.numErrors;

	// Remove all old error messages.
	$(".errorMessage").remove();

	// Make sure all inputs are ignored in the "starter" instance
	// of any multiple-instance template.
	$(".multipleTemplateStarter").find("span, div").addClass("hiddenByPF");

	$(".multipleTemplateList").each( function() {
		if (! $(this).validateNumInstances() ) {
			num_errors += 1;
		}
	});

	$("span.inputSpan.mandatoryFieldSpan").not(".hiddenByPF").each( function() {
		if (! $(this).validateMandatoryField() ) {
			num_errors += 1;
		}
	});
	$("span.comboboxSpan.mandatoryFieldSpan").not(".hiddenByPF").each( function() {
		if (! $(this).validateMandatoryComboBox() ) {
			num_errors += 1;
		}
	});
	$("span.dateInput.mandatoryFieldSpan").not(".hiddenByPF").each( function() {
		if (! $(this).validateMandatoryDateField() ) {
			num_errors += 1;
		}
	});
	// ==== GESINN PATCH BEGIN ====
	// Added support for mandatory datetime:
	// Iterate over all mandatoryFieldSpan elements
	// that are not hidden by PF and validate them using
	// the custom validateMandatoryDateTimeField() function
	$("span.dateTimeInput.mandatoryFieldSpan").not(".hiddenByPF").each( function() {
		if (! $(this).validateMandatoryDateTimeField() ) {
			num_errors += 1;
		}
	});
	// ==== GESINN PATCH END ====
	$("span.radioButtonSpan.mandatoryFieldSpan").not(".hiddenByPF").each( function() {
		if (! $(this).validateMandatoryRadioButton() ) {
			num_errors += 1;
		}
	});
	$("span.checkboxesSpan.mandatoryFieldSpan").not(".hiddenByPF").each( function() {
		if (! $(this).validateMandatoryCheckboxes() ) {
			num_errors += 1;
		}
	});
	// ==== GESINN PATCH BEGIN ====
	// Added support for mandatory checkbox:
	// Iterate over all span.checkboxInput.mandatoryFieldSpan elements
	// that are not hidden by PF and validate them using
	// the custom validateMandatoryCheckboxes() function
	$("span.checkboxInput.mandatoryFieldSpan").not(".hiddenByPF").each( function() {
		if (! $(this).validateMandatoryCheckboxes() ) {
			num_errors += 1;
		}
	});
	// ==== GESINN PATCH END ====
	$("div.pfTreeInputWrapper.mandatory").not(".hiddenByPF").each( function() {
		if (! $(this).validateMandatoryTree() ) {
			num_errors += 1;
		}
	});
	$("div.pfPickerWrapper.mandatory").not(".hiddenByPF").each( function() {
		if (! $(this).find('.pfPicker').validateMandatoryDatePicker() ) {
			num_errors += 1;
		}
	});
	// ==== GESINN PATCH BEGIN ====
	// Added support for mandatory maps fields:
	// Iterate over all input.pfCoordsInput.mandatoryFieldSpan elements
	// that are not hidden by PF and validate them using
	// the custom validateMandatoryGeoCoordinatesMaps() function
	$("input.pfCoordsInput.mandatoryFieldSpan").not(".hiddenByPF").each( function() {
    	if (! $(this).validateMandatoryGeoCoordinatesMaps() ) {
			num_errors += 1;
		}
	});
	// ==== GESINN PATCH END ====
	$("span.inputSpan.uniqueFieldSpan").not(".hiddenByPF").each( function() {
		if (! $(this).validateUniqueField() ) {
			num_errors += 1;
		}
	});
	$("span.inputSpan, div.pfComboBox").not(".hiddenByPF, .freeText, .pageSection").each( function() {
		if (! $(this).checkForPipes() ) {
			num_errors += 1;
		}
	});
	$("span.URLInput").not(".hiddenByPF").each( function() {
		if (! $(this).validateURLField() ) {
			num_errors += 1;
		}
	});
	$("span.emailInput").not(".hiddenByPF").each( function() {
		if (! $(this).validateEmailField() ) {
			num_errors += 1;
		}
	});
	$("span.numberInput").not(".hiddenByPF").each( function() {
		if (! $(this).validateNumberField() ) {
			num_errors += 1;
		}
	});
	$("span.integerInput").not(".hiddenByPF").each( function() {
		if (! $(this).validateIntegerField() ) {
			num_errors += 1;
		}
	});
	$("span.dateInput").not(".hiddenByPF").each( function() {
		if (! $(this).validateDateField() ) {
			num_errors += 1;
		}
	});
	$("input.modifiedInput").not(".hiddenByPF").each( function() {
		// No separate function needed.
		$(this).parent().addErrorMessage( 'pf_modified_input_error' );
		num_errors += 1;
	});

	const $startDateInput = $("span.startDateInput").not(".hiddenByPF")
	const $endDateInput = $("span.endDateInput").not(".hiddenByPF")

	if ( !validateStartEndDateField( $startDateInput, $endDateInput ) ) {
		num_errors += 1;
	}

	const $startDateTimeInput = $("span.startDateTimeInput").not(".hiddenByPF")
	const $endDateTimeInput = $("span.endDateTimeInput").not(".hiddenByPF")

	if ( !validateStartEndDateTimeField( $startDateTimeInput, $endDateTimeInput ) ) {
		num_errors += 1;
	}
	// call registered validation functions
	const pfdata = $("#pfForm").data('PageForms');

	if ( pfdata && pfdata.validationFunctions.length > 0 ) { // found data object?

		// for every registered input
		for ( let i = 0; i < pfdata.validationFunctions.length; i++ ) {

			// if input is not part of multipleTemplateStarter
			if ( typeof pfdata.validationFunctions[i] !== 'undefined' &&
				$("#" + pfdata.validationFunctions[i].input).closest(".multipleTemplateStarter").length === 0 &&
				$("#" + pfdata.validationFunctions[i].input).closest(".hiddenByPF").length === 0 ) {

				if (! pfdata.validationFunctions[i].valfunction(
						pfdata.validationFunctions[i].input,
						pfdata.validationFunctions[i].parameters)
					) {
					num_errors += 1;
				}
			}
		}
	}

	if (num_errors > 0) {
		// add error header, if it's not there already
		if ($("#form_error_header").length === 0) {
			$("#contentSub").append('<div id="form_error_header" class="errorbox" style="font-size: medium"><img src="' + mw.config.get( 'wgPageFormsScriptPath' ) + '/skins/MW-Icon-AlertMark.png" />&nbsp;' + mw.message( 'pf_formerrors_header' ).escaped() + '</div><br clear="both" />');
		}
		// The "Save page", etc. buttons were disabled to prevent
		// double-clicking; since there has been an error, re-enable
		// them so that the form can be submitted again after the
		// user tries to fix these errors.
		$( '.editButtons > .oo-ui-buttonElement' ).removeClass( 'oo-ui-widget-disabled' ).addClass( 'oo-ui-widget-enabled' );
		// Also undo the indicator that the form was submitted.
		$( '#pfForm' ).data('submitted', false);
		scroll(0, 0);
	} else {
		// Disable inputs hidden due to either "show on select" or
		// because they're part of the "starter" div for
		// multiple-instance templates, so that they aren't
		// submitted by the form.
		$('.hiddenByPF').find("input, select, textarea").not(':disabled')
		.prop('disabled', true)
		.addClass('disabledByPF');
		//remove error box if it exists because there are no errors in the form now
		$("#contentSub").find(".errorbox").remove();
	}

	// Hook that fires on form submission, after the validation.
	mw.hook('pf.formValidationAfter').fire();

	return (num_errors === 0);
};

}( jQuery, mediaWiki ) );
