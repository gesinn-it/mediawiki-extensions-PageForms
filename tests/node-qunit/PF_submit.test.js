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
