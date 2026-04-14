'use strict';

// PF_checkboxes.js reads mw.message() for the switch labels
// and calls $.fn.appendSelectionSwitches on .checkboxesSpan.select-all elements.

require( '../../libs/PF_checkboxes.js' );

function createCheckboxGroup( checked ) {
	const ids = ( checked ? 'checked' : '' );
	const $span = $( `
		<span class="checkboxesSpan select-all">
			<span>
				<input type="checkbox" value="A" ${ ids }>
				<input type="checkbox" value="B">
				<input type="checkbox" value="C" checked>
			</span>
		</span>
	` ).appendTo( document.body );
	return $span;
}

QUnit.module( 'PF_checkboxes appendSelectionSwitches', {
	beforeEach() {
		mw.message = ( key ) => ( { escaped: () => key } );
	}
} );

QUnit.test( 'Select All link checks all checkboxes', ( assert ) => {
	const $span = createCheckboxGroup();

	// Simulate what document.ready does
	$span.appendSelectionSwitches();

	const $selectAllLink = $span.find( 'a' ).first();
	$selectAllLink.trigger( 'click' );

	const allChecked = $span.find( 'input[type="checkbox"]' ).toArray().every( ( cb ) => cb.checked );
	assert.true( allChecked, 'all checkboxes are checked' );
} );

QUnit.test( 'Select None link unchecks all checkboxes', ( assert ) => {
	const $span = createCheckboxGroup();
	$span.appendSelectionSwitches();

	const $selectNoneLink = $span.find( 'a' ).last();
	$selectNoneLink.trigger( 'click' );

	const noneChecked = $span.find( 'input[type="checkbox"]' ).toArray().every( ( cb ) => !cb.checked );
	assert.true( noneChecked, 'all checkboxes are unchecked' );
} );

QUnit.test( 'appendSelectionSwitches inserts two switch links', ( assert ) => {
	const $span = createCheckboxGroup();
	$span.appendSelectionSwitches();

	assert.strictEqual( $span.find( '.checkboxSwitch' ).length, 2 );
} );

QUnit.test( 'switch labels use mw.message keys', ( assert ) => {
	const $span = createCheckboxGroup();
	$span.appendSelectionSwitches();

	const labels = $span.find( '.checkboxSwitch a' ).map( ( i, el ) => $( el ).text() ).toArray();
	assert.ok( labels[ 0 ].includes( 'pf_forminputs_checkboxes_select_all' ), 'first link uses select_all message' );
	assert.ok( labels[ 1 ].includes( 'pf_forminputs_checkboxes_select_none' ), 'second link uses select_none message' );
} );

QUnit.test( 'click event default is prevented', ( assert ) => {
	const $span = createCheckboxGroup();
	$span.appendSelectionSwitches();

	let prevented = false;
	$span.find( 'a' ).first().on( 'click', ( e ) => {
		prevented = e.isDefaultPrevented();
	} );
	$span.find( 'a' ).first().trigger( 'click' );
	assert.true( prevented );
} );
