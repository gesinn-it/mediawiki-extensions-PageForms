/**
 * Javascript handler for the autoedit parser function
 *
 * @author Stephan Gambke
 */

/*global confirm */

( function ( $, mw ) {

	'use strict';
	function sendData( $jtrigger ){
		const $jautoedit = $jtrigger.closest( '.autoedit' );
		const $jresult = $jautoedit.find( '.autoedit-result' );
		const reload = $jtrigger.hasClass( 'reload' );

		$jtrigger.attr( 'class', 'autoedit-trigger autoedit-trigger-wait' );
		$jresult.attr( 'class', 'autoedit-result autoedit-result-wait' );

		$jresult.text( mw.msg( 'pf-autoedit-wait' ) );


		// data array to be sent to the server
		const data = {
			action: 'pfautoedit',
			format: 'json'
		};

		// add form values to the data
		data.query = $jautoedit.find( 'form.autoedit-data' ).serialize();

		$.ajax( {

			type:     'POST', // request type ( GET or POST )
			url:      mw.util.wikiScript( 'api' ), // URL to which the request is sent
			data:     data, // data to be sent to the server
			dataType: 'json', // type of data expected back from the server
			success:  function ( result ){
				$jresult.empty().append( result.responseText );

				if ( result.status === 200 ) {

					if ( reload ) {
						window.location.reload();
					}

					$jresult.removeClass( 'autoedit-result-wait' ).addClass( 'autoedit-result-ok' );
					$jtrigger.removeClass( 'autoedit-trigger-wait' ).addClass( 'autoedit-trigger-ok' );
				} else {
					$jresult.removeClass( 'autoedit-result-wait' ).addClass( 'autoedit-result-error' );
					$jtrigger.removeClass( 'autoedit-trigger-wait' ).addClass( 'autoedit-trigger-error' );
				}
			}, // function to be called if the request succeeds
			error:  function ( jqXHR, textStatus, errorThrown ) {
				const result = jQuery.parseJSON(jqXHR.responseText);
				let text = result.responseText;

				for ( let i = 0; i < result.errors.length; i++ ) {
					text += ' ' + result.errors[i].message;
				}

				$jresult.empty().append( text );
				$jresult.removeClass( 'autoedit-result-wait' ).addClass( 'autoedit-result-error' );
				$jtrigger.removeClass( 'autoedit-trigger-wait' ).addClass( 'autoedit-trigger-error' );
			} // function to be called if the request fails
		} );
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
		const $jtrigger = jQuery( this );
		const $jautoedit = $jtrigger.closest( '.autoedit' );
		const $jeditdata = $jautoedit.find( 'form.autoedit-data' );
		const targetpage = $jeditdata.find( 'input[name=target]' ).val();
		const confirmEdit = $jeditdata.hasClass( 'confirm-edit' );
		if ( confirmEdit ) {
			OO.ui.confirm( mw.msg( 'pf_autoedit_confirm', targetpage ) ).then( (confirmed) => {
				if ( confirmed ) {
					sendData( $jtrigger );
				}
			})
		} else {
			sendData( $jtrigger );
		}
	};

	JQuery( document ).ready( () => {
		$( '.autoedit-trigger' ).click( autoEditHandler );
		$( '.autoedit-trigger-instant' ).each( function() {
			autoEditHandler.call( this, {
				preventDefault: function(){},
				stopPropagation: function(){}
			} );
		});
	} );

}( jQuery, mediaWiki ) );
