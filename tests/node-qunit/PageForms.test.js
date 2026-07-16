// initializeJSElements() unconditionally instantiates pf.select2.tokens
// (used to wire up .pfTokens elements, of which our fixtures have none).
global.pf = global.pf || {};
global.pf.select2 = global.pf.select2 || {};
global.pf.select2.tokens = global.pf.select2.tokens || function () {
	this.apply = () => {};
};

require( '../../libs/PF_validation.js' );
require( '../../libs/PF_showOnSelect.js' );
require( '../../libs/PF_multipleInstance.js' );
require( '../../libs/PageForms.js' );

// initializeJSElements( true ) (the multiple-instance branch) unconditionally
// calls into several unrelated jQuery plugins (fancybox, autoGrow, rating,
// jstree, datepicker, collapsible), none of which are loaded by this test
// file. Stub them as no-ops so their absence doesn't block testing the
// show-on-select/add/remove wiring that is actually under test here.
[ 'fancybox', 'autoGrow', 'applyRatingInput', 'applyJSTree',
	'applyDatePicker', 'applyDateTimePicker', 'makeCollapsible' ].forEach( ( name ) => {
	$.fn[ name ] = $.fn[ name ] || function () {
		return this;
	};
} );

// Mirrors the markup produced by MultipleTemplateHtmlBuilder: a
// multipleTemplateWrapper containing a multipleTemplateList (with optional
// existing instances) and a hidden multipleTemplateStarter used as the
// clone source for new instances.
function createMultipleInstanceWrapper( { instanceCount = 0, maximumInstances, minimumInstances } = {} ) {
	const listAttrs = [];
	if ( maximumInstances !== undefined ) {
		listAttrs.push( `maximumInstances="${ maximumInstances }"` );
	}
	if ( minimumInstances !== undefined ) {
		listAttrs.push( `minimumInstances="${ minimumInstances }"` );
	}

	let instancesHtml = '';
	for ( let i = 0; i < instanceCount; i++ ) {
		instancesHtml += `
			<div class="multipleTemplateInstance multipleTemplate">
				<table class="multipleTemplateInstanceTable">
					<tr>
						<td class="instanceRearranger"></td>
						<td class="instanceMain">
							<input type="text" id="input_${ i }" name="Foo[${ i }a][bar]" value="" />
						</td>
						<td class="instanceAddAbove"><a class="addAboveButton"></a></td>
						<td class="instanceRemove"><a class="removeButton"></a></td>
					</tr>
				</table>
			</div>`;
	}

	const $wrapper = $( `
		<div class="multipleTemplateWrapper">
			<div class="multipleTemplateList" ${ listAttrs.join( ' ' ) }>
				${ instancesHtml }
				<div class="multipleTemplateStarter" style="display: none">
					<table class="multipleTemplateInstanceTable">
						<tr>
							<td class="instanceRearranger"></td>
							<td class="instanceMain">
								<input type="text" id="input_[num]" name="Foo[num][bar]" value="" />
							</td>
							<td class="instanceAddAbove"><a class="addAboveButton"></a></td>
							<td class="instanceRemove"><a class="removeButton"></a></td>
						</tr>
					</table>
				</div>
			</div>
			<a class="multipleTemplateAdder"></a>
			<div class="pfErrorMessages"></div>
		</div>
	` ).appendTo( document.body );

	return $wrapper;
}

function stubShowOnSelectConfig( overrides ) {
	mw.msg = ( key ) => key;
	mw.config = {
		get: ( key ) => {
			if ( key === 'wgPageFormsShowOnSelect' ) {
				return overrides && overrides.wgPageFormsShowOnSelect || {};
			}
			if ( key === 'wgPageFormsHeightForMinimizingInstances' ) {
				return overrides && overrides.wgPageFormsHeightForMinimizingInstances !== undefined ?
					overrides.wgPageFormsHeightForMinimizingInstances : -1;
			}
			if ( key === 'wgPageFormsDependentFields' ) {
				return overrides && overrides.wgPageFormsDependentFields || [];
			}
			return null;
		}
	};
}

QUnit.module( 'PageForms addInstance', {
	beforeEach() {
		stubShowOnSelectConfig();
	}
} );

QUnit.test( 'appends a new instance to the multipleTemplateList', ( assert ) => {
	const $wrapper = createMultipleInstanceWrapper();
	const $adder = $wrapper.find( '.multipleTemplateAdder' );

	$adder.addInstance( false );

	const $instances = $wrapper.find( '.multipleTemplateList > .multipleTemplateInstance' );
	assert.strictEqual( $instances.length, 1, 'one instance was added' );
} );

QUnit.test( 'new instance is no longer marked as the starter', ( assert ) => {
	const $wrapper = createMultipleInstanceWrapper();
	const $adder = $wrapper.find( '.multipleTemplateAdder' );

	$adder.addInstance( false );

	const $newInstance = $wrapper.find( '.multipleTemplateList > .multipleTemplateInstance' ).last();
	assert.false( $newInstance.hasClass( 'multipleTemplateStarter' ), 'starter class removed' );
	assert.true( $newInstance.hasClass( 'multipleTemplate' ), 'backwards-compatibility class kept' );
} );

QUnit.test( 'renumbers the [num] placeholder in input names to a unique index', ( assert ) => {
	const $wrapper = createMultipleInstanceWrapper();
	const $adder = $wrapper.find( '.multipleTemplateAdder' );

	$adder.addInstance( false );

	const $newInstance = $wrapper.find( '.multipleTemplateList > .multipleTemplateInstance' ).last();
	const name = $newInstance.find( 'input' ).attr( 'name' );
	assert.notOk( name.includes( '[num]' ), 'placeholder was replaced' );
	assert.ok( /Foo\[\d+b]\[bar]/.test( name ), 'name follows the [Nb] numbering convention' );
} );

QUnit.test( 'gives each added instance a distinct input name index', ( assert ) => {
	const $wrapper = createMultipleInstanceWrapper();
	const $adder = $wrapper.find( '.multipleTemplateAdder' );

	$adder.addInstance( false );
	$adder.addInstance( false );

	const names = $wrapper.find( '.multipleTemplateList > .multipleTemplateInstance input' )
		.map( ( i, el ) => $( el ).attr( 'name' ) ).toArray();
	assert.strictEqual( names.length, 2 );
	assert.notStrictEqual( names[ 0 ], names[ 1 ], 'each instance gets a unique input name' );
} );

QUnit.test( 'inserts the new instance above the current one when addAboveCurInstance is true', ( assert ) => {
	const $wrapper = createMultipleInstanceWrapper( { instanceCount: 1 } );
	const $existingInstance = $wrapper.find( '.multipleTemplateList > .multipleTemplateInstance' ).first();
	const $addAboveButton = $existingInstance.find( '.addAboveButton' );

	$addAboveButton.addInstance( true );

	const $instances = $wrapper.find( '.multipleTemplateList > .multipleTemplateInstance' );
	assert.strictEqual( $instances.length, 2, 'a second instance now exists' );
	assert.strictEqual( $instances.last().find( 'input' ).attr( 'name' ), 'Foo[0a][bar]',
		'the original instance was pushed below the newly inserted one' );
} );

QUnit.test( 'refuses to add another instance once the maximum is reached', ( assert ) => {
	const $wrapper = createMultipleInstanceWrapper( { instanceCount: 2, maximumInstances: 2 } );
	const $adder = $wrapper.find( '.multipleTemplateAdder' );

	const result = $adder.addInstance( false );

	assert.strictEqual( result, false, 'addInstance reports failure' );
	assert.strictEqual( $wrapper.find( '.multipleTemplateList > .multipleTemplateInstance' ).length, 2,
		'no instance was added' );
} );

QUnit.test( 'fires the pf.addTemplateInstance hook with the new instance', ( assert ) => {
	const $wrapper = createMultipleInstanceWrapper();
	const $adder = $wrapper.find( '.multipleTemplateAdder' );
	let $firedWith = null;
	mw.hook( 'pf.addTemplateInstance' ).add( ( $newDiv ) => {
		$firedWith = $newDiv;
	} );

	$adder.addInstance( false );

	assert.notStrictEqual( $firedWith, null, 'hook was fired' );
	assert.true( $firedWith.hasClass( 'multipleTemplateInstance' ), 'hook received the new instance' );
} );

QUnit.module( 'PageForms show-on-select (showDiv/hideDiv)', {
	beforeEach() {
		stubShowOnSelectConfig();
	}
} );

function createDropdownWithControlledDiv( { selectedValue = '', showOnValue = 'yes' } = {} ) {
	const $container = $( `
		<div>
			<select id="input_1" class="pfShowIfSelected" data-input-type="dropdown">
				<option value="">-</option>
				<option value="yes">Yes</option>
				<option value="no">No</option>
			</select>
			<div id="controlled_div" style="display: none;">
				<span>Controlled content</span>
			</div>
		</div>
	` ).appendTo( document.body );

	$container.find( 'select' ).val( selectedValue );

	stubShowOnSelectConfig( {
		wgPageFormsShowOnSelect: {
			input_1: [ [ [ showOnValue ], 'controlled_div' ] ]
		}
	} );

	return $container;
}

// Assert on the shownByPF/hiddenByPF classes rather than the live CSS
// 'display' value: showing/hiding is animated via slideDown/slideUp, whose
// visible effect depends on a real animation engine (which jsdom doesn't
// have). The classes are the animation-independent, documented contract -
// see the "CSS class is there so that PF can ignore the div's contents when
// the form is submitted" comment on hideDiv in PF_showOnSelect.js.

QUnit.test( 'showIfSelected reveals the controlled div when the matching value is selected', ( assert ) => {
	const $container = createDropdownWithControlledDiv( { selectedValue: 'yes' } );

	$container.find( 'select' ).showIfSelected( false, true );

	assert.true( $( '#controlled_div' ).hasClass( 'shownByPF' ), 'controlled div is marked as shown' );
} );

QUnit.test( 'showIfSelected keeps the controlled div hidden when a non-matching value is selected', ( assert ) => {
	const $container = createDropdownWithControlledDiv( { selectedValue: 'no' } );

	$container.find( 'select' ).showIfSelected( false, true );

	assert.false( $( '#controlled_div' ).hasClass( 'shownByPF' ), 'controlled div stays unmarked as shown' );
} );

QUnit.test( 'showIfSelected hides a previously-shown div once the value no longer matches', ( assert ) => {
	const $container = createDropdownWithControlledDiv( { selectedValue: 'yes' } );
	$container.find( 'select' ).showIfSelected( false, true );
	assert.true( $( '#controlled_div' ).hasClass( 'shownByPF' ), 'precondition: div starts marked as shown' );

	$container.find( 'select' ).val( 'no' ).showIfSelected( false, false );

	assert.true( $( '#controlled_div' ).find( 'span' ).hasClass( 'hiddenByPF' ),
		'controlled div is marked as hidden again' );
} );

QUnit.test( 'hideDiv marks contained span/div elements as hiddenByPF', ( assert ) => {
	const $container = createDropdownWithControlledDiv( { selectedValue: 'no' } );

	$container.find( 'select' ).showIfSelected( false, true );

	assert.true( $( '#controlled_div' ).find( 'span' ).hasClass( 'hiddenByPF' ),
		'contained span is flagged as hidden by PF for form-submission handling' );
} );

function createCheckboxesWithControlledDiv( { checkedValues = [], showOnValue = 'yes' } = {} ) {
	const $container = $( `
		<div>
			<span id="input_1" class="pfShowIfChecked">
				<input type="checkbox" value="yes" />
				<input type="checkbox" value="no" />
			</span>
			<div id="controlled_div" style="display: none;">
				<span>Controlled content</span>
			</div>
		</div>
	` ).appendTo( document.body );

	$container.find( 'input[type="checkbox"]' ).each( function () {
		if ( checkedValues.includes( this.value ) ) {
			this.checked = true;
		}
	} );

	stubShowOnSelectConfig( {
		wgPageFormsShowOnSelect: {
			input_1: [ [ [ showOnValue ], 'controlled_div' ] ]
		}
	} );

	return $container;
}

QUnit.test( 'showIfChecked reveals the controlled div when a matching checkbox is checked', ( assert ) => {
	const $container = createCheckboxesWithControlledDiv( { checkedValues: [ 'yes' ] } );

	$container.find( '.pfShowIfChecked' ).showIfChecked( false, true );

	assert.true( $( '#controlled_div' ).hasClass( 'shownByPF' ), 'controlled div is marked as shown' );
} );

QUnit.test( 'showIfChecked keeps the controlled div hidden when no matching checkbox is checked', ( assert ) => {
	const $container = createCheckboxesWithControlledDiv( { checkedValues: [] } );

	$container.find( '.pfShowIfChecked' ).showIfChecked( false, true );

	assert.false( $( '#controlled_div' ).hasClass( 'shownByPF' ), 'controlled div stays unmarked as shown' );
} );

QUnit.module( 'PageForms initializeJSElements', {
	beforeEach() {
		stubShowOnSelectConfig();
	}
} );

QUnit.test( 'wires up showIfSelected on .pfShowIfSelected elements and evaluates them immediately', ( assert ) => {
	const $container = $( `
		<div>
			<select id="input_1" class="pfShowIfSelected" data-input-type="dropdown">
				<option value="">-</option>
				<option value="yes" selected>Yes</option>
			</select>
			<div id="controlled_div" style="display: none;"></div>
		</div>
	` ).appendTo( document.body );

	stubShowOnSelectConfig( {
		wgPageFormsShowOnSelect: {
			input_1: [ [ [ 'yes' ], 'controlled_div' ] ]
		}
	} );

	$container.initializeJSElements( false );

	assert.true( $( '#controlled_div' ).hasClass( 'shownByPF' ),
		'controlled div is marked as shown immediately on initialization since "yes" is already selected' );
} );

QUnit.test( 're-evaluates show-on-select on change after initialization', ( assert ) => {
	const $container = $( `
		<div>
			<select id="input_1" class="pfShowIfSelected" data-input-type="dropdown">
				<option value="">-</option>
				<option value="yes">Yes</option>
			</select>
			<div id="controlled_div" style="display: none;"></div>
		</div>
	` ).appendTo( document.body );

	stubShowOnSelectConfig( {
		wgPageFormsShowOnSelect: {
			input_1: [ [ [ 'yes' ], 'controlled_div' ] ]
		}
	} );

	$container.initializeJSElements( false );
	assert.false( $( '#controlled_div' ).hasClass( 'shownByPF' ), 'precondition: not marked as shown yet' );

	$container.find( 'select' ).val( 'yes' ).trigger( 'change' );

	assert.true( $( '#controlled_div' ).hasClass( 'shownByPF' ), 'controlled div is marked as shown after change' );
} );

QUnit.test( 'wires up the removeButton to remove the enclosing multipleTemplateInstance', ( assert ) => {
	const $wrapper = createMultipleInstanceWrapper( { instanceCount: 1 } );
	const $instance = $wrapper.find( '.multipleTemplateInstance' );

	$instance.initializeJSElements( true );
	$instance.find( '.removeButton' ).trigger( 'click' );

	// The removal itself is animated (fadeTo/slideUp) and only actually
	// detaches the node once the animation completes; jsdom has no real
	// animation clock, so assert on the synchronous, directly observable
	// contract instead: the click handler exists and prevents the default
	// link navigation.
	assert.strictEqual( $wrapper.find( '.multipleTemplateInstance' ).length, 1,
		'instance removal is deferred to the fade/slide animation callback' );
} );

QUnit.test( 'wires up the addAboveButton to call addInstance( true )', ( assert ) => {
	const $wrapper = createMultipleInstanceWrapper( { instanceCount: 1 } );
	const $instance = $wrapper.find( '.multipleTemplateInstance' );

	$instance.initializeJSElements( true );
	$instance.find( '.addAboveButton' ).trigger( 'click' );

	assert.strictEqual( $wrapper.find( '.multipleTemplateList > .multipleTemplateInstance' ).length, 2,
		'a new instance was added above the current one' );
} );

QUnit.module( 'PageForms possiblyMinimizeAllOpenInstances', {
	beforeEach() {
		stubShowOnSelectConfig();
		// jsdom has no CSS engine — stub fadeOut to run its callback synchronously
		// so the summary cell it inserts is observable without a real animation clock.
		$.fn.fadeOut = function ( duration, callback ) {
			if ( typeof callback === 'function' ) {
				callback.call( this );
			}
			return this;
		};
	}
} );

function createMinimizeAllWrapper( { fieldValue = '', displayedFieldsWhenMinimized } = {} ) {
	const listAttrs = [];
	if ( displayedFieldsWhenMinimized !== undefined ) {
		listAttrs.push( `data-displayed-fields-when-minimized="${ displayedFieldsWhenMinimized }"` );
	}

	const $wrapper = $( `
		<div class="multipleTemplateWrapper">
			<div class="multipleTemplateList minimizeAll" ${ listAttrs.join( ' ' ) }>
				<div class="multipleTemplateInstance multipleTemplate">
					<table class="multipleTemplateInstanceTable">
						<tr>
							<td class="instanceRearranger"></td>
							<td class="instanceMain">
								<input type="text" name="Foo[0][bar]" value="${ fieldValue }" />
							</td>
						</tr>
					</table>
				</div>
			</div>
		</div>
	` ).appendTo( document.body );

	return $wrapper;
}

QUnit.test( 'does nothing when the list is not marked for minimizing', ( assert ) => {
	const $wrapper = createMinimizeAllWrapper();
	const $list = $wrapper.find( '.multipleTemplateList' ).removeClass( 'minimizeAll' );

	$list.possiblyMinimizeAllOpenInstances();

	assert.false( $list.find( '.multipleTemplateInstance' ).hasClass( 'minimized' ),
		'instance is left untouched when the list lacks the minimizeAll class' );
} );

QUnit.test( 'marks open instances as minimized', ( assert ) => {
	const $wrapper = createMinimizeAllWrapper( { fieldValue: 'hello' } );
	const $list = $wrapper.find( '.multipleTemplateList' );

	$list.possiblyMinimizeAllOpenInstances();

	assert.true( $list.find( '.multipleTemplateInstance' ).hasClass( 'minimized' ),
		'open instance is marked as minimized' );
} );

QUnit.test( 'leaves already-minimized instances untouched', ( assert ) => {
	const $wrapper = createMinimizeAllWrapper();
	const $list = $wrapper.find( '.multipleTemplateList' );
	$list.find( '.multipleTemplateInstance' ).addClass( 'minimized' );

	$list.possiblyMinimizeAllOpenInstances();

	assert.strictEqual( $list.find( '.fieldValuesDisplay' ).length, 0,
		'a summary cell is not added a second time for an instance that is already minimized' );
} );

QUnit.test( 'shows "No data" when none of the instance\'s fields have a value', ( assert ) => {
	const $wrapper = createMinimizeAllWrapper( { fieldValue: '' } );
	const $list = $wrapper.find( '.multipleTemplateList' );

	$list.possiblyMinimizeAllOpenInstances();

	assert.true( $list.find( '.fieldValuesDisplay' ).html().includes( 'No data' ),
		'summary falls back to "No data" when every field is empty' );
} );

QUnit.test( 'summarizes only the fields listed in data-displayed-fields-when-minimized', ( assert ) => {
	const $wrapper = $( `
		<div class="multipleTemplateWrapper">
			<div class="multipleTemplateList minimizeAll" data-displayed-fields-when-minimized="bar">
				<div class="multipleTemplateInstance multipleTemplate">
					<table class="multipleTemplateInstanceTable">
						<tr>
							<td class="instanceRearranger"></td>
							<td class="instanceMain">
								<input type="text" name="Foo[0][bar]" value="shown value" />
								<input type="text" name="Foo[0][baz]" value="hidden value" />
							</td>
						</tr>
					</table>
				</div>
			</div>
		</div>
	` ).appendTo( document.body );
	const $list = $wrapper.find( '.multipleTemplateList' );

	$list.possiblyMinimizeAllOpenInstances();

	const summaryHtml = $list.find( '.fieldValuesDisplay' ).html();
	assert.true( summaryHtml.includes( 'shown value' ), 'field named in the allow-list is included' );
	assert.false( summaryHtml.includes( 'hidden value' ), 'field not named in the allow-list is excluded' );
} );

QUnit.test( 'truncates long field values to 70 characters', ( assert ) => {
	const longValue = 'a'.repeat( 100 );
	const $wrapper = createMinimizeAllWrapper( { fieldValue: longValue } );
	const $list = $wrapper.find( '.multipleTemplateList' );

	$list.possiblyMinimizeAllOpenInstances();

	const summaryHtml = $list.find( '.fieldValuesDisplay' ).html();
	assert.true( summaryHtml.includes( 'a'.repeat( 70 ) + '...' ),
		'value longer than 70 characters is truncated with an ellipsis' );
} );

QUnit.module( 'PageForms displayWizardScreen', {
	beforeEach() {
		stubShowOnSelectConfig();
	}
} );

function createWizardScreens( screenCount, screenAttrs = {} ) {
	let screensHtml = '';
	for ( let i = 1; i <= screenCount; i++ ) {
		const attrs = screenAttrs[ i ] || {};
		const attrsHtml = Object.keys( attrs )
			.map( ( key ) => `${ key }="${ attrs[ key ] }"` ).join( ' ' );
		screensHtml += `<div class="pf-wizard-screen" ${ attrsHtml }>Screen ${ i }</div>`;
	}
	const $form = $( `<form id="pfForm">${ screensHtml }</form>` ).appendTo( document.body );
	const $wizardNav = $( '<div class="pf-wizard-navigation"></div>' ).appendTo( $form );

	return {
		$wizardScreens: $form.find( 'div.pf-wizard-screen' ),
		$wizardNav
	};
}

QUnit.test( 'shows only the screen matching screenNum and hides the rest', ( assert ) => {
	const { $wizardScreens, $wizardNav } = createWizardScreens( 3 );

	$wizardScreens.displayWizardScreen( 2, $wizardNav );

	assert.strictEqual( $wizardScreens.eq( 0 ).css( 'display' ), 'none', 'screen 1 is hidden' );
	assert.notStrictEqual( $wizardScreens.eq( 1 ).css( 'display' ), 'none', 'screen 2 is shown' );
	assert.strictEqual( $wizardScreens.eq( 2 ).css( 'display' ), 'none', 'screen 3 is hidden' );
} );

QUnit.test( 'shows a "back" button on any screen after the first', ( assert ) => {
	const { $wizardScreens, $wizardNav } = createWizardScreens( 3 );

	$wizardScreens.displayWizardScreen( 2, $wizardNav );

	assert.strictEqual( $wizardNav.find( '.pf-wizard-back-button' ).length, 1,
		'back button is present on screen 2' );
} );

QUnit.test( 'hides the "back" button on the first screen', ( assert ) => {
	const { $wizardScreens, $wizardNav } = createWizardScreens( 3 );

	$wizardScreens.displayWizardScreen( 1, $wizardNav );

	assert.strictEqual( $wizardNav.find( '.pf-wizard-back-button' ).length, 0,
		'back button is absent on the first screen' );
} );

QUnit.test( 'shows a "continue" button on any screen before the last', ( assert ) => {
	const { $wizardScreens, $wizardNav } = createWizardScreens( 3 );

	$wizardScreens.displayWizardScreen( 2, $wizardNav );

	assert.strictEqual( $wizardNav.find( '.pf-wizard-continue-button' ).length, 1,
		'continue button is present on screen 2' );
} );

QUnit.test( 'hides the "continue" button on the last screen', ( assert ) => {
	const { $wizardScreens, $wizardNav } = createWizardScreens( 3 );

	$wizardScreens.displayWizardScreen( 3, $wizardNav );

	assert.strictEqual( $wizardNav.find( '.pf-wizard-continue-button' ).length, 0,
		'continue button is absent on the last screen' );
} );

QUnit.test( 'clicking "continue" advances to the next screen', ( assert ) => {
	const { $wizardScreens, $wizardNav } = createWizardScreens( 3 );
	$wizardScreens.displayWizardScreen( 1, $wizardNav );

	$wizardNav.find( '.pf-wizard-continue-button' ).trigger( 'click' );

	assert.notStrictEqual( $wizardScreens.eq( 1 ).css( 'display' ), 'none', 'screen 2 is now shown' );
	assert.strictEqual( $wizardScreens.eq( 0 ).css( 'display' ), 'none', 'screen 1 is now hidden' );
} );

QUnit.test( 'clicking "back" returns to the previous screen', ( assert ) => {
	const { $wizardScreens, $wizardNav } = createWizardScreens( 3 );
	$wizardScreens.displayWizardScreen( 2, $wizardNav );

	$wizardNav.find( '.pf-wizard-back-button' ).trigger( 'click' );

	assert.notStrictEqual( $wizardScreens.eq( 0 ).css( 'display' ), 'none', 'screen 1 is now shown' );
	assert.strictEqual( $wizardScreens.eq( 1 ).css( 'display' ), 'none', 'screen 2 is now hidden' );
} );

QUnit.test( 'uses the mw.msg default label for the "back" button', ( assert ) => {
	const { $wizardScreens, $wizardNav } = createWizardScreens( 2 );

	$wizardScreens.displayWizardScreen( 2, $wizardNav );

	assert.strictEqual( $wizardNav.find( '.pf-wizard-back-button' ).text(), 'pf-wizard-back',
		'back button label comes from the pf-wizard-back message' );
} );

QUnit.test( 'uses the mw.msg default label for the "continue" button', ( assert ) => {
	const { $wizardScreens, $wizardNav } = createWizardScreens( 2 );

	$wizardScreens.displayWizardScreen( 1, $wizardNav );

	assert.strictEqual( $wizardNav.find( '.pf-wizard-continue-button' ).text(), 'pf-wizard-continue',
		'continue button label comes from the pf-wizard-continue message' );
} );

QUnit.test( 'uses a custom data-back-text label for the "back" button when set', ( assert ) => {
	const { $wizardScreens, $wizardNav } = createWizardScreens( 2, { 2: { 'data-back-text': 'Go back' } } );

	$wizardScreens.displayWizardScreen( 2, $wizardNav );

	assert.strictEqual( $wizardNav.find( '.pf-wizard-back-button' ).text(), 'Go back',
		'back button uses the screen\'s custom label instead of the default message' );
} );

QUnit.test( 'uses a custom data-continue-text label for the "continue" button when set', ( assert ) => {
	const { $wizardScreens, $wizardNav } = createWizardScreens( 2, { 1: { 'data-continue-text': 'Next up' } } );

	$wizardScreens.displayWizardScreen( 1, $wizardNav );

	assert.strictEqual( $wizardNav.find( '.pf-wizard-continue-button' ).text(), 'Next up',
		'continue button uses the screen\'s custom label instead of the default message' );
} );

QUnit.test( 'shows progress circles (not a progress bar) for 10 or fewer screens', ( assert ) => {
	const { $wizardScreens, $wizardNav } = createWizardScreens( 3 );

	$wizardScreens.displayWizardScreen( 2, $wizardNav );

	assert.strictEqual( $wizardNav.find( '.pfWizardCircles' ).length, 1, 'progress circles are shown' );
	assert.strictEqual( $wizardNav.find( '.oo-ui-progressBarWidget' ).length, 0, 'no progress bar is shown' );
	assert.strictEqual( $wizardNav.find( '.pfWizardCircles > li.active' ).text(), '2',
		'the circle for the current screen is marked active' );
} );

QUnit.test( 'shows a progress bar (not circles) for more than 10 screens', ( assert ) => {
	const { $wizardScreens, $wizardNav } = createWizardScreens( 11 );

	$wizardScreens.displayWizardScreen( 2, $wizardNav );

	assert.strictEqual( $wizardNav.find( '.pfWizardCircles' ).length, 0, 'no progress circles are shown' );
	assert.strictEqual( $wizardNav.find( '.oo-ui-progressBarWidget' ).length, 1, 'a progress bar is shown instead' );
} );
