const sinon = require( 'sinon' );

function loadSubmitScript() {
	const scriptPath = '../../libs/PF_submit.js';
	delete require.cache[ require.resolve( scriptPath ) ];
	require( scriptPath );
}

QUnit.module( 'PF_submit', {
	beforeEach: () => {
		global.validateAll = () => true;
		mw.config = {
			get: ( key ) => {
				const values = {
					wgAction: 'formedit',
					wgCanonicalSpecialPageName: null,
					wgPageName: 'Main_Page',
					wgPageFormsScriptPath: '/w/extensions/PageForms'
				};
				return Object.prototype.hasOwnProperty.call( values, key ) ? values[ key ] : null;
			}
		};
		mw.msg = ( key ) => key;
		mw.util.wikiScript = () => '/api.php';

		$(
			'<div id="contentSub"></div>' +
			'<form id="pfForm">' +
				'<input id="wpSummary" value="" />' +
				'<input type="button" class="pf-save_and_continue" value="Save page" />' +
			'</form>'
		).appendTo( document.body );
	}
} );

QUnit.test( 'stores target returned by API in hidden target input', ( assert ) => {
	const done = assert.async();

	sinon.replace( $, 'ajax', ( opts ) => {
		opts.success( {
			target: 'My Target',
			form: { title: 'MyForm' }
		} );
		return {};
	} );

	loadSubmitScript();

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );

		const $target = $( '#pfForm input[name="target"]' );
		assert.strictEqual( $target.attr( 'value' ), 'My Target' );
		done();
	}, 0 );
} );

QUnit.test( 'renders server error message as text, not HTML', ( assert ) => {
	const done = assert.async();
	const payload = '<img id="pf-evil" src="x">';

	sinon.replace( $, 'ajax', ( opts ) => {
		opts.error( {
			responseText: JSON.stringify( {
				errors: [ { level: 1, message: payload } ]
			} )
		} );
		return {};
	} );

	loadSubmitScript();

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );

		assert.strictEqual( $( '#pf-evil' ).length, 0, 'no injected DOM node created from message' );
		assert.strictEqual( $( '#contentSub .errorbox img' ).length, 1, 'only built-in icon image is present' );
		assert.true(
			$( '#contentSub .errorbox' ).text().includes( payload ),
			'payload appears as plain text'
		);
		done();
	}, 0 );
} );

QUnit.test( 'falls back to result.$target when result.target is undefined', ( assert ) => {
	const done = assert.async();

	sinon.replace( $, 'ajax', ( opts ) => {
		opts.success( {
			$target: 'Fallback Target',
			form: { title: 'MyForm' }
		} );
		return {};
	} );

	loadSubmitScript();

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );

		const $target = $( '#pfForm input[name="target"]' );
		assert.strictEqual( $target.attr( 'value' ), 'Fallback Target', 'fallback target stored' );
		done();
	}, 0 );
} );

QUnit.test( 'does not call ajax when validateAll returns false', ( assert ) => {
	const done = assert.async();
	global.validateAll = () => false;

	let ajaxCalled = false;
	sinon.replace( $, 'ajax', () => {
		ajaxCalled = true;
		return {};
	} );

	loadSubmitScript();

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );
		assert.false( ajaxCalled, 'ajax was not called when validateAll returns false' );
		done();
	}, 0 );
} );

QUnit.test( 'error response with empty errors array adds no errorbox', ( assert ) => {
	const done = assert.async();

	sinon.replace( $, 'ajax', ( opts ) => {
		opts.error( {
			responseText: JSON.stringify( { errors: [] } )
		} );
		return {};
	} );

	loadSubmitScript();

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );
		assert.strictEqual( $( '#contentSub .errorbox' ).length, 0, 'no errorbox added for empty errors' );
		done();
	}, 0 );
} );

QUnit.test( 'error with level >= 2 is not shown in DOM', ( assert ) => {
	const done = assert.async();

	sinon.replace( $, 'ajax', ( opts ) => {
		opts.error( {
			responseText: JSON.stringify( {
				errors: [ { level: 2, message: 'High-severity — invisible' } ]
			} )
		} );
		return {};
	} );

	loadSubmitScript();

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );
		assert.strictEqual( $( '#contentSub .errorbox' ).length, 0, 'errorbox not shown for level >= 2' );
		done();
	}, 0 );
} );

QUnit.test( 'non-empty summary is appended then restored after serialization', ( assert ) => {
	const done = assert.async();

	sinon.replace( $, 'ajax', ( opts ) => {
		opts.success( { target: 'T', form: { title: 'F' } } );
		return {};
	} );

	loadSubmitScript();
	// Set the attribute after script load but before the click fires (next tick).
	$( '#wpSummary' ).attr( 'value', 'Existing summary' );

	setTimeout( () => {
		$( '.pf-save_and_continue' ).trigger( 'click' );
		assert.strictEqual(
			$( '#wpSummary' ).attr( 'value' ),
			'Existing summary',
			'original summary attribute is restored after serialization'
		);
		done();
	}, 0 );
} );
