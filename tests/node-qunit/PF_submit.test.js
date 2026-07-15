const sinon = require( 'sinon' );

function loadSubmitScript() {
	const scriptPath = '../../libs/PF_submit.js';
	delete require.cache[ require.resolve( scriptPath ) ];
	require( scriptPath );
}

QUnit.module( 'PF_submit', {
	beforeEach() {
		// PF_submit.js registers a document-level VEForAllLoaded handler on every
		// loadSubmitScript(); without this, handlers from earlier tests pile up on the
		// (never-reset) document and all fire together in later tests.
		$( document ).off( 'VEForAllLoaded' );
		global.validateAll = () => true;
		this.configValues = {
			wgAction: 'formedit',
			wgCanonicalSpecialPageName: null,
			wgPageName: 'Main_Page',
			wgPageFormsScriptPath: '/w/extensions/PageForms'
		};
		mw.config = {
			get: ( key ) => Object.prototype.hasOwnProperty.call( this.configValues, key ) ? this.configValues[ key ] : null
		};
		mw.msg = ( key ) => key;
		mw.util.wikiScript = () => '/api.php';
		sinon.stub( mw.Api.prototype, 'post' ).returns( $.Deferred().resolve() );
		$.fn.getVEInstances = () => [];

		$(
			'<div id="contentSub"></div>' +
			'<form id="pfForm">' +
				'<div class="editButtons"><span class="oo-ui-buttonElement oo-ui-widget-enabled"></span></div>' +
				'<input id="wpSummary" value="" />' +
				'<span class="pf-save_and_continue oo-ui-widget-disabled"><button disabled="disabled">Save and continue</button></span>' +
			'</form>'
		).appendTo( document.body );
	},
	afterEach() {
		delete $.fn.getVEInstances;
	}
} );

QUnit.test( 'stores target returned by API in hidden target input', ( assert ) => {
	const done = assert.async();

	mw.Api.prototype.post.returns( $.Deferred().resolve( {
		target: 'My Target',
		form: { title: 'MyForm' }
	} ) );

	loadSubmitScript();

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );
		setTimeout( () => {
			const $target = $( '#pfForm input[name="target"]' );
			assert.strictEqual( $target.attr( 'value' ), 'My Target' );
			done();
		}, 0 );
	}, 0 );
} );

QUnit.test( 'renders server error message as text, not HTML', ( assert ) => {
	const done = assert.async();
	const payload = '<img id="pf-evil" src="x">';

	mw.Api.prototype.post.returns( $.Deferred().reject( 'http', {
		xhr: {
			responseText: JSON.stringify( {
				errors: [ { level: 1, message: payload } ]
			} )
		}
	} ) );

	loadSubmitScript();

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );
		setTimeout( () => {
			assert.strictEqual( $( '#pf-evil' ).length, 0, 'no injected DOM node created from message' );
			assert.strictEqual( $( '#contentSub .errorbox img' ).length, 1, 'only built-in icon image is present' );
			assert.true(
				$( '#contentSub .errorbox' ).text().includes( payload ),
				'payload appears as plain text'
			);
			done();
		}, 0 );
	}, 0 );
} );

QUnit.test( 'falls back to result.$target when result.target is undefined', ( assert ) => {
	const done = assert.async();

	mw.Api.prototype.post.returns( $.Deferred().resolve( {
		$target: 'Fallback Target',
		form: { title: 'MyForm' }
	} ) );

	loadSubmitScript();

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );
		setTimeout( () => {
			const $target = $( '#pfForm input[name="target"]' );
			assert.strictEqual( $target.attr( 'value' ), 'Fallback Target', 'fallback target stored' );
			done();
		}, 0 );
	}, 0 );
} );

QUnit.test( 'does not call ajax when validateAll returns false', ( assert ) => {
	const done = assert.async();
	global.validateAll = () => false;

	loadSubmitScript();

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );
		// mw.Api.prototype.post is already stubbed in beforeEach — just verify it's not called
		assert.false( mw.Api.prototype.post.called, 'post was not called when validateAll returns false' );
		done();
	}, 0 );
} );

QUnit.test( 'error response with empty errors array adds no errorbox', ( assert ) => {
	const done = assert.async();

	mw.Api.prototype.post.returns( $.Deferred().reject( 'http', {
		xhr: { responseText: JSON.stringify( { errors: [] } ) }
	} ) );

	loadSubmitScript();

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );
		setTimeout( () => {
			assert.strictEqual( $( '#contentSub .errorbox' ).length, 0, 'no errorbox added for empty errors' );
			done();
		}, 0 );
	}, 0 );
} );

QUnit.test( 'error with level >= 2 is not shown in DOM', ( assert ) => {
	const done = assert.async();

	mw.Api.prototype.post.returns( $.Deferred().reject( 'http', {
		xhr: {
			responseText: JSON.stringify( {
				errors: [ { level: 2, message: 'High-severity — invisible' } ]
			} )
		}
	} ) );

	loadSubmitScript();

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );
		setTimeout( () => {
			assert.strictEqual( $( '#contentSub .errorbox' ).length, 0, 'errorbox not shown for level >= 2' );
			done();
		}, 0 );
	}, 0 );
} );

QUnit.test( 'non-empty summary is appended then restored after serialization', ( assert ) => {
	const done = assert.async();

	mw.Api.prototype.post.returns( $.Deferred().resolve( { target: 'T', form: { title: 'F' } } ) );

	loadSubmitScript();
	// Set the attribute after script load but before the click fires (next tick).
	$( '#wpSummary' ).attr( 'value', 'Existing summary' );

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );
		setTimeout( () => {
			assert.strictEqual(
				$( '#wpSummary' ).attr( 'value' ),
				'Existing summary',
				'original summary attribute is restored after serialization'
			);
			done();
		}, 0 );
	}, 0 );
} );

QUnit.test( 'does not set wpMinoredit when minor edit checkbox is unchecked', ( assert ) => {
	const done = assert.async();

	mw.Api.prototype.post.returns( $.Deferred().resolve( { target: 'T', form: { title: 'F' } } ) );

	loadSubmitScript();

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );
		setTimeout( () => {
			const query = mw.Api.prototype.post.args[ 0 ][ 0 ].query;
			assert.false( query.includes( 'wpMinoredit=1' ), 'wpMinoredit=1 not sent when checkbox is unchecked' );
			done();
		}, 0 );
	}, 0 );
} );

QUnit.test( 'sets wpMinoredit when minor edit checkbox is checked', ( assert ) => {
	const done = assert.async();

	mw.Api.prototype.post.returns( $.Deferred().resolve( { target: 'T', form: { title: 'F' } } ) );

	$( '#pfForm' ).append( '<input type="checkbox" id="wpMinoredit" checked />' );

	loadSubmitScript();

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );
		setTimeout( () => {
			const query = mw.Api.prototype.post.args[ 0 ][ 0 ].query;
			assert.true( query.includes( 'wpMinoredit=1' ), 'wpMinoredit=1 sent when checkbox is checked' );
			done();
		}, 0 );
	}, 0 );
} );

// ── collectData subpage-splitting (Special:FormEdit) ────────────────────────

QUnit.test( 'FormEdit subpage with only a form part appends form= only', function ( assert ) {
	const done = assert.async();

	this.configValues.wgAction = null;
	this.configValues.wgCanonicalSpecialPageName = 'FormEdit';
	// No slash at all: the whole subpage name is used as the form name, no target.
	this.configValues.wgPageName = 'MyForm';

	mw.Api.prototype.post.returns( $.Deferred().resolve( { target: 'T', form: { title: 'F' } } ) );

	loadSubmitScript();

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );
		setTimeout( () => {
			const query = mw.Api.prototype.post.args[ 0 ][ 0 ].query;
			assert.true( query.includes( 'form=MyForm' ), 'form= set from single subpage segment' );
			assert.false( query.includes( 'target=' ), 'no target= appended when there is no second slash' );
			done();
		}, 0 );
	}, 0 );
} );

QUnit.test( 'FormEdit subpage with form and target parts appends both, preserving inner slashes', function ( assert ) {
	const done = assert.async();

	this.configValues.wgAction = null;
	this.configValues.wgCanonicalSpecialPageName = 'FormEdit';
	// First path segment is discarded (everything before the first slash); the form
	// name is the segment between the first and second slash, and the target is
	// everything after that, with inner slashes preserved.
	this.configValues.wgPageName = 'MyForm/Some/Deep/Page';

	mw.Api.prototype.post.returns( $.Deferred().resolve( { target: 'T', form: { title: 'F' } } ) );

	loadSubmitScript();

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );
		setTimeout( () => {
			const query = mw.Api.prototype.post.args[ 0 ][ 0 ].query;
			assert.true( query.includes( 'form=Some' ), 'form= set from segment between first and second slash' );
			assert.true(
				query.includes( 'target=' + encodeURIComponent( 'Deep/Page' ) ),
				'target= re-joins remaining segments with slashes preserved'
			);
			done();
		}, 0 );
	}, 0 );
} );

// ── preventDoubleSubmission ──────────────────────────────────────────────────

QUnit.test( 'preventDoubleSubmission allows the first submit and blocks the second', ( assert ) => {
	const done = assert.async();

	loadSubmitScript();

	// Wait for the module's document.ready block, which assigns the
	// module-level $form that preventDoubleSubmission's handler reads.
	setTimeout( () => {
		const $form = $( '#pfForm' );

		const firstEvent = $.Event( 'submit' );
		$form.trigger( firstEvent );

		assert.strictEqual( $form.data( 'submitted' ), true, 'form is marked as submitted after first submit' );
		assert.true(
			$( '.editButtons > .oo-ui-buttonElement' ).hasClass( 'oo-ui-widget-disabled' ),
			'edit buttons disabled after first submit'
		);
		assert.false( firstEvent.isDefaultPrevented(), 'first submit is not prevented' );

		const secondEvent = $.Event( 'submit' );
		$form.trigger( secondEvent );

		assert.true( secondEvent.isDefaultPrevented(), 'second submit is prevented' );
		done();
	}, 0 );
} );

// ── setChanged ────────────────────────────────────────────────────────────────

QUnit.test( 'setChanged marks save-and-continue buttons as changed on qualifying events', ( assert ) => {
	const done = assert.async();

	$( '#pfForm' ).append( '<input type="text" id="someField" />' );
	$( '#pfForm' ).append( '<span class="rearrangerImage"></span>' );

	loadSubmitScript();

	setTimeout( () => {
		const $sac = $( '.pf-save_and_continue' );

		assert.true( $sac.hasClass( 'oo-ui-widget-disabled' ), 'sanity: starts disabled' );

		$( '#someField' ).trigger( $.Event( 'change' ) );

		assert.true( $sac.hasClass( 'pf-save_and_continue-changed' ), 'changed class added' );
		assert.true( $sac.hasClass( 'oo-ui-widget-enabled' ), 'enabled class added' );
		assert.false( $sac.hasClass( 'oo-ui-widget-disabled' ), 'disabled class removed' );
		assert.false( $sac.children( 'button' ).prop( 'disabled' ), 'child button is enabled' );

		done();
	}, 0 );
} );

QUnit.test( 'setChanged fires on click of rearranger/remove/add controls and mousedown on rearranger', ( assert ) => {
	const done = assert.async();

	$( '#pfForm' ).append( '<span class="rearrangerImage"></span>' );

	loadSubmitScript();

	setTimeout( () => {
		const $sac = $( '.pf-save_and_continue' );

		$( '.rearrangerImage' ).trigger( 'click' );
		assert.true( $sac.hasClass( 'pf-save_and_continue-changed' ), 'click on rearrangerImage triggers setChanged' );

		$sac.removeClass( 'pf-save_and_continue-changed oo-ui-widget-enabled' ).addClass( 'oo-ui-widget-disabled' );
		$( '.rearrangerImage' ).trigger( 'mousedown' );
		assert.true( $sac.hasClass( 'pf-save_and_continue-changed' ), 'mousedown on rearrangerImage triggers setChanged' );

		done();
	}, 0 );
} );

QUnit.test( 'setChanged is not triggered by keyup with a non-printable key', ( assert ) => {
	const done = assert.async();

	$( '#pfForm' ).append( '<input type="text" id="someField" />' );

	loadSubmitScript();

	setTimeout( () => {
		const $sac = $( '.pf-save_and_continue' );

		$( '#someField' ).trigger( $.Event( 'keyup', { which: 9 } ) );

		assert.false( $sac.hasClass( 'pf-save_and_continue-changed' ), 'non-printable keyup does not mark as changed' );

		done();
	}, 0 );
} );

QUnit.test( 'setChanged is triggered by keyup with a printable key', ( assert ) => {
	const done = assert.async();

	$( '#pfForm' ).append( '<input type="text" id="someField" />' );

	loadSubmitScript();

	setTimeout( () => {
		const $sac = $( '.pf-save_and_continue' );

		$( '#someField' ).trigger( $.Event( 'keyup', { which: 65 } ) );

		assert.true( $sac.hasClass( 'pf-save_and_continue-changed' ), 'printable keyup marks as changed' );

		done();
	}, 0 );
} );

// ── VEForAllLoaded click interception ───────────────────────────────────────

QUnit.test( 'VEForAllLoaded intercepts Save page click, actualizes VE fields, then re-clicks submit', ( assert ) => {
	const done = assert.async();

	$( '#pfForm' ).append(
		'<span id="wpSave"><button type="submit">Save</button></span>' +
		'<div class="visualeditor"></div>'
	);

	const veInstance = { target: { updateContent: () => $.Deferred().resolve().promise() } };
	$.fn.getVEInstances = () => [ veInstance ];

	loadSubmitScript();

	setTimeout( () => {
		$( document ).trigger( 'VEForAllLoaded' );

		const $submitButton = $( '#wpSave [type="submit"]' );
		const clickSpy = sinon.spy( $submitButton[ 0 ], 'click' );

		$( '#wpSave' ).trigger( 'click' );

		setTimeout( () => {
			assert.true( clickSpy.called, 'inner submit button is clicked after VE fields are actualized and validation passes' );
			done();
		}, 0 );
	}, 0 );
} );

QUnit.test( 'VEForAllLoaded intercepts Save-and-continue click and actualizes VE fields first', ( assert ) => {
	const done = assert.async();

	$( '#pfForm' ).append( '<div class="visualeditor"></div>' );

	let actualizeCalled = false;
	const veInstance = { target: { updateContent: () => {
		actualizeCalled = true;
		return $.Deferred().resolve().promise();
	} } };
	$.fn.getVEInstances = () => [ veInstance ];

	mw.Api.prototype.post.returns( $.Deferred().resolve( { target: 'T', form: { title: 'F' } } ) );

	loadSubmitScript();

	setTimeout( () => {
		$( document ).trigger( 'VEForAllLoaded' );

		$( '.pf-save_and_continue' ).trigger( 'click' );

		setTimeout( () => {
			assert.true( actualizeCalled, 'VE fields are actualized before save-and-continue proceeds' );
			assert.true( mw.Api.prototype.post.called, 'save-and-continue still submits after VE actualization' );
			done();
		}, 0 );
	}, 0 );
} );

QUnit.test( 'VEForAllLoaded does not intercept clicks when no .visualeditor is present', ( assert ) => {
	const done = assert.async();

	let veCalled = false;
	$.fn.getVEInstances = () => {
		veCalled = true;
		return [];
	};

	mw.Api.prototype.post.returns( $.Deferred().resolve( { target: 'T', form: { title: 'F' } } ) );

	loadSubmitScript();

	setTimeout( () => {
		$( document ).trigger( 'VEForAllLoaded' );

		$( '.pf-save_and_continue' ).trigger( 'click' );

		setTimeout( () => {
			assert.false( veCalled, 'VE fields are not actualized when no .visualeditor element is present' );
			assert.true( mw.Api.prototype.post.called, 'save-and-continue still submits normally' );
			done();
		}, 0 );
	}, 0 );
} );
