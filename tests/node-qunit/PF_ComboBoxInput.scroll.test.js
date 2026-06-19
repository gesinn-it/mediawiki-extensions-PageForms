global.pageforms = global.pageforms || {};
global.pageforms.buildAutocompleteParams = function ( dataType, settings, substr ) {
	const params = { action: 'pfautocomplete', format: 'json', substr: substr };
	if ( dataType ) {
		params[ dataType ] = settings;
	}
	return params;
};
global.pageforms.highlightText = ( searchTerm, s ) => s;
global.pageforms.nameAttr = () => 'name';
require( '../../libs/PF_ComboBoxDataSource.js' );
require( '../../libs/PF_ComboBoxInput.js' );
const sinon = require( 'sinon' );

QUnit.module( 'PF_ComboBoxInput scroll drag guard', {
	beforeEach: function () {
		mw.config = {
			get: ( key ) => {
				const cfg = {
					wgPageFormsAutocompleteOnAllChars: true,
					wgPageFormsAutocompleteValues: {},
					wgPageFormsFieldProperties: {},
					wgScriptPath: '',
					wgPageFormsScriptPath: ''
				};
				return Object.prototype.hasOwnProperty.call( cfg, key ) ? cfg[ key ] : null;
			}
		};
		mw.util = { wikiScript: () => '/api.php' };
		mw.message = ( key ) => ( { text: () => key } );
		mw.hook = () => ( { fire: () => {} } );

		// Mount a select element that apply() needs
		$( '<span id="pf_scroll_test_wrap"><select id="pf_scroll_test_input" name="test"><option value="Foo">Foo</option></select></span>' )
			.appendTo( document.body );
		$( '<img id="loading-pf_scroll_test_input">' ).appendTo( document.body );
	}
} );

// Instantiate a real pf.ComboBoxInput and call apply() on it so that the
// production event handlers from apply() are wired up.  We then stub
// setValues to observe whether it fires.
function buildRealCombo() {
	const widget = new pageforms.ComboBoxInput( {} );
	const $select = $( '#pf_scroll_test_input' );
	widget.apply( $select );
	// Stub setValues AFTER apply() so the apply() bootstrap doesn't count.
	widget.setValues = sinon.spy();
	return widget;
}

QUnit.test( 'mouseup from outside menu calls setValues (normal click on widget)', ( assert ) => {
	const widget = buildRealCombo();

	// Simulate a mouseup on the outer element whose target is a node outside the menu
	const $outside = $( '<div>' ).appendTo( document.body );
	widget.$element.trigger( $.Event( 'mouseup', { target: $outside[ 0 ] } ) );

	assert.true( widget.setValues.calledOnce, 'setValues is called for mouseup outside menu' );
} );

QUnit.test( 'mouseup from inside menu element does not call setValues', ( assert ) => {
	const widget = buildRealCombo();

	// Target is a child of the menu element
	const $menuChild = $( '<li>' ).appendTo( widget.menu.$element );
	widget.$element.trigger( $.Event( 'mouseup', { target: $menuChild[ 0 ] } ) );

	assert.false( widget.setValues.called, 'setValues is not called when mouseup target is inside menu' );
} );

QUnit.test( 'mouseup after scrollbar drag (target outside menu) does not call setValues', ( assert ) => {
	// This is the bug: user drags the native scrollbar in the dropdown.
	// On mousedown the target is inside the menu; on mouseup (after drag) the
	// mouse may have drifted outside — e.target fails the contains() check —
	// so the old guard lets setValues() through and the scroll position resets.
	//
	// The fix adds a mousedown listener on menu.$element that sets
	// _menuScrollDragging=true; the mouseup handler also bails when that flag is set.
	const widget = buildRealCombo();

	// Step 1 – mousedown starts inside the menu (scrollbar drag begins)
	widget.menu.$element.trigger( 'mousedown' );
	assert.true( widget._menuScrollDragging, '_menuScrollDragging is set after mousedown on menu' );

	// Step 2 – mouseup fires on $element with a target OUTSIDE the menu
	const $outside = $( '<div>' ).appendTo( document.body );
	widget.$element.trigger( $.Event( 'mouseup', { target: $outside[ 0 ] } ) );

	assert.false( widget.setValues.called, 'setValues is NOT called after scrollbar drag release outside menu' );
	assert.false( widget._menuScrollDragging, '_menuScrollDragging is reset after mouseup' );
} );

QUnit.test( 'setValues is called on the next normal mouseup after a scroll drag', ( assert ) => {
	// After the scroll drag guard fires once, the flag is cleared.
	// The very next mouseup that does NOT start with a menu mousedown must still call setValues.
	const widget = buildRealCombo();

	const $outside = $( '<div>' ).appendTo( document.body );

	// Scroll drag sequence (suppressed)
	widget.menu.$element.trigger( 'mousedown' );
	widget.$element.trigger( $.Event( 'mouseup', { target: $outside[ 0 ] } ) );
	assert.false( widget.setValues.called, 'setValues not called during scroll drag' );

	// Normal mouseup (no prior menu mousedown) — must call setValues
	widget.$element.trigger( $.Event( 'mouseup', { target: $outside[ 0 ] } ) );
	assert.true( widget.setValues.calledOnce, 'setValues is called on the next normal mouseup' );
} );
