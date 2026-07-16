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
