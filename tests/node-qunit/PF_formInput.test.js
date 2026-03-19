'use strict';

global.pf = global.pf || {};
require( '../../libs/PF_AutocompleteWidget.js' );
require( '../../libs/PF_formInput.js' );

QUnit.module( 'PF_formInput', {
	beforeEach() {
		mw.message = () => ( { text: () => '' } );
	}
} );

// Regression test: the form chooser DropdownInputWidget must render its menu in
// OO.ui.getDefaultOverlay() so that Vector's skin styles (z-index: 101) place it
// above #mw-head (z-index: 100) on short pages like Special:FormStart.
QUnit.test( 'form chooser DropdownInputWidget menu uses the OOUI default overlay', ( assert ) => {
	const $overlay = OO.ui.getDefaultOverlay();
	const menusBefore = $overlay.children( '.oo-ui-menuSelectWidget' ).length;

	const $wrapper = $( '<div>' )
		.addClass( 'pfFormInputWrapper' )
		.attr( 'data-possible-forms', 'Form A|Form B' )
		.attr( 'data-button-label', 'Go' )
		.appendTo( document.body );

	$wrapper.displayPFFormInput();

	assert.strictEqual(
		$overlay.children( '.oo-ui-menuSelectWidget' ).length,
		menusBefore + 1,
		'form chooser dropdown menu is appended to OO.ui.getDefaultOverlay()'
	);
} );
