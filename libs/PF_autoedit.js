/**
 * Javascript handler for the autoedit parser function
 *
 * @author Stephan Gambke
 */

/*global confirm */

( function ( $, mw ) {

	'use strict';
	function sendData( $trigger ){
		const $autoedit = $trigger.closest( '.autoedit' );
		const $result = $autoedit.find( '.autoedit-result' );
		const reload = $trigger.hasClass( 'reload' );

		$trigger.attr( 'class', 'autoedit-trigger autoedit-trigger-wait' );
		$result.attr( 'class', 'autoedit-result autoedit-result-wait' );

		$result.text( mw.msg( 'pf-autoedit-wait' ) );


		const data = {
			action: 'pfautoedit',
			query: $autoedit.find( 'form.autoedit-data' ).serialize()
		};

		new mw.Api().post( data ).then(
			( result ) => {
				$result.empty().append( result.responseText );

				if ( result.status === 200 ) {
					if ( reload ) {
						window.location.reload();
					}

					$result.removeClass( 'autoedit-result-wait' ).addClass( 'autoedit-result-ok' );
					$trigger.removeClass( 'autoedit-trigger-wait' ).addClass( 'autoedit-trigger-ok' );
				} else {
					$result.removeClass( 'autoedit-result-wait' ).addClass( 'autoedit-result-error' );
					$trigger.removeClass( 'autoedit-trigger-wait' ).addClass( 'autoedit-trigger-error' );
				}
			},
			( code, error ) => {
				// pfautoedit returns HTTP 4xx on error; mw.Api rejects with ('http', {xhr, ...})
				const response = code === 'http' ? JSON.parse( error.xhr.responseText ) : error;
				let text = ( response && response.responseText ) || '';
				const errors = ( response && response.errors ) || [];

				for ( let i = 0; i < errors.length; i++ ) {
					text += ' ' + errors[ i ].message;
				}

				$result.empty().append( text );
				$result.removeClass( 'autoedit-result-wait' ).addClass( 'autoedit-result-error' );
				$trigger.removeClass( 'autoedit-trigger-wait' ).addClass( 'autoedit-trigger-error' );
			}
		);
	}

	const autoEditHandler = function handleAutoEdit( e ){

		// Normalize event
		const event =
			e && typeof e.preventDefault === 'function'
				? e
				: null;

		// No usable event → exit safely
		if (!event) {
			return;
		}

		// Prevent anchor (#) jump
		event.preventDefault();
		if (typeof event.stopPropagation === 'function') {
			event.stopPropagation();
		}

		if ( mw.config.get( 'wgUserName' ) === null &&
			! confirm( mw.msg( 'pf_autoedit_anoneditwarning' ) ) ) {
			return;
		}
		const $trigger = $( this );
		const $autoedit = $trigger.closest( '.autoedit' );
		const $editdata = $autoedit.find( 'form.autoedit-data' );
		const targetpage = $editdata.find( 'input[name=target]' ).val();
		const confirmEdit = $editdata.hasClass( 'confirm-edit' );
		if ( confirmEdit ) {
			OO.ui.confirm( mw.msg( 'pf_autoedit_confirm', targetpage ) ).then( (confirmed) => {
				if ( confirmed ) {
					sendData( $trigger );
				}
			})
		} else {
			sendData( $trigger );
		}
	};

	$( () => {
		$( '.autoedit-trigger' ).click( autoEditHandler );
		$( '.autoedit-trigger-instant' ).each( function() {
			autoEditHandler.call( this, {
				preventDefault: function(){},
				stopPropagation: function(){}
			} );
		} );
	} );

}( jQuery, mediaWiki ) );
