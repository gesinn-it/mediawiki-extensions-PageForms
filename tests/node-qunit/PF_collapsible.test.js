'use strict';

require( '../../libs/PF_collapsible.js' );

function createFieldset() {
	return $( '<fieldset>' )
		.append( $( '<legend>' ).text( 'Section' ) )
		.append( $( '<div>' ).text( 'Content' ) )
		.appendTo( document.body );
}

QUnit.module( 'PF_collapsible', {
	beforeEach() {
		// jsdom has no CSS engine — stub jQuery animations to return `this` synchronously
		$.fn.slideUp = function () {
			return this;
		};
		$.fn.slideDown = function () {
			return this;
		};
	}
} );

QUnit.test( 'pfMakeCollapsible collapses fieldset on init', ( assert ) => {
	const $fieldset = createFieldset();

	$fieldset.pfMakeCollapsible();

	assert.true( $fieldset[ 0 ].classList.contains( 'pfCollapsedFieldset' ), 'pfCollapsedFieldset added' );
	assert.false( $fieldset[ 0 ].classList.contains( 'pfExpandedFieldset' ), 'pfExpandedFieldset absent' );
} );

QUnit.test( 'clicking legend expands a collapsed fieldset', ( assert ) => {
	const $fieldset = createFieldset();
	$fieldset.pfMakeCollapsible();

	$fieldset.children( 'legend' ).trigger( 'click' );

	assert.true( $fieldset[ 0 ].classList.contains( 'pfExpandedFieldset' ), 'pfExpandedFieldset added' );
	assert.false( $fieldset[ 0 ].classList.contains( 'pfCollapsedFieldset' ), 'pfCollapsedFieldset removed' );
} );

QUnit.test( 'clicking legend twice collapses the fieldset again', ( assert ) => {
	const $fieldset = createFieldset();
	$fieldset.pfMakeCollapsible();
	const $legend = $fieldset.children( 'legend' );

	$legend.trigger( 'click' ); // expand
	$legend.trigger( 'click' ); // collapse

	assert.true( $fieldset[ 0 ].classList.contains( 'pfCollapsedFieldset' ), 'collapses again' );
	assert.false( $fieldset[ 0 ].classList.contains( 'pfExpandedFieldset' ), 'not expanded' );
} );

QUnit.test( 'pfMakeCollapsible works on multiple fieldsets independently', ( assert ) => {
	const $a = createFieldset();
	const $b = createFieldset();

	$a.add( $b ).pfMakeCollapsible();

	assert.true( $a[ 0 ].classList.contains( 'pfCollapsedFieldset' ), 'first fieldset collapsed' );
	assert.true( $b[ 0 ].classList.contains( 'pfCollapsedFieldset' ), 'second fieldset collapsed' );
} );

QUnit.test( 'expanding one fieldset does not affect another', ( assert ) => {
	const $a = createFieldset();
	const $b = createFieldset();

	$a.add( $b ).pfMakeCollapsible();
	$a.children( 'legend' ).trigger( 'click' ); // expand only $a

	assert.true( $a[ 0 ].classList.contains( 'pfExpandedFieldset' ), '$a expanded' );
	assert.true( $b[ 0 ].classList.contains( 'pfCollapsedFieldset' ), '$b still collapsed' );
} );
