/**
 * Javascript handler for the autoedit parser function
 *
 * @author Stephan Gambke
 */

/*global confirm */

( function ( $, mw ) {

	'use strict';

	const api = new mw.Api();

	/**
	 * Text describing a failed request: the module's response text followed by each error
	 * message it reported. A response that failed inside the API itself (an "error" member
	 * such as internal_api_error_*) carries neither, so its error info is used instead.
	 *
	 * @param {Object} response
	 * @return {string}
	 */
	function failureText( response ) {
		let text = ( response && response.responseText ) || '';
		const errors = ( response && response.errors ) || [];

		for ( let i = 0; i < errors.length; i++ ) {
			text += ' ' + errors[ i ].message;
		}
		if ( text === '' && response && response.error ) {
			text = response.error.info || response.error.code || '';
		}
		return text;
	}

	/**
	 * Whether a rejected request failed inside the database layer, e.g. because another save
	 * changed a row this one had read. The save was rolled back, so sending it again is safe.
	 *
	 * @param {string} code
	 * @return {boolean}
	 */
	function isTransientDatabaseError( code ) {
		return typeof code === 'string' && /^internal_api_error_DB/.test( code );
	}

	/**
	 * Sends the autoedit request of a trigger.
	 *
	 * @param {jQuery} $trigger
	 * @param {boolean} [retried] Whether this is the second attempt after a transient database error
	 * @return {jQuery.Promise} Settles when the request has finished, whatever its outcome
	 */
	function sendData( $trigger, retried ){
		const finished = $.Deferred();
		const $autoedit = $trigger.closest( '.autoedit' );
		const $result = $autoedit.find( '.autoedit-result' );
		const reload = $trigger[ 0 ].classList.contains( 'reload' );

		$trigger.attr( 'class', 'autoedit-trigger autoedit-trigger-wait' );
		$result.attr( 'class', 'autoedit-result autoedit-result-wait' );

		$result.text( mw.msg( 'pf-autoedit-wait' ) );


		const data = {
			action: 'pfautoedit',
			query: $autoedit.find( 'form.autoedit-data' ).serialize()
		};

		api.post( data ).then(
			( result ) => {
				finished.resolve();
				if ( result.status === 200 ) {
					$result.empty().append( result.responseText );

					if ( reload ) {
						window.location.reload();
					}

					$result.removeClass( 'autoedit-result-wait' ).addClass( 'autoedit-result-ok' );
					$trigger.removeClass( 'autoedit-trigger-wait' ).addClass( 'autoedit-trigger-ok' );
				} else {
					$result.empty().append( failureText( result ) );
					$result.removeClass( 'autoedit-result-wait' ).addClass( 'autoedit-result-error' );
					$trigger.removeClass( 'autoedit-trigger-wait' ).addClass( 'autoedit-trigger-error' );
				}
			},
			( code, error ) => {
				if ( !retried && isTransientDatabaseError( code ) ) {
					setTimeout( () => {
						sendData( $trigger, true ).then( finished.resolve );
					}, 500 );
					return;
				}
				finished.resolve();
				// pfautoedit returns HTTP 4xx on error; mw.Api rejects with ('http', {xhr, ...})
				const response = code === 'http' ? JSON.parse( error.xhr.responseText ) : error;

				$result.empty().append( failureText( response ) );
				$result.removeClass( 'autoedit-result-wait' ).addClass( 'autoedit-result-error' );
				$trigger.removeClass( 'autoedit-trigger-wait' ).addClass( 'autoedit-trigger-error' );
			}
		);
		return finished.promise();
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
		const confirmEdit = $editdata[ 0 ].classList.contains( 'confirm-edit' );
		if ( confirmEdit ) {
			return OO.ui.confirm( mw.msg( 'pf_autoedit_confirm', targetpage ) ).then( (confirmed) => {
				if ( confirmed ) {
					return sendData( $trigger );
				}
			})
		} else {
			return sendData( $trigger );
		}
	};

	$( () => {
		$( '.autoedit-trigger' ).click( autoEditHandler );
		// The instant triggers of a page are sent one after the other: all at once, a page with
		// many of them (e.g. the transfer of 100 samples) exceeds the rate limit and makes the
		// saves compete for the same database rows.
		let queue = $.Deferred().resolve().promise();
		$( '.autoedit-trigger-instant' ).each( function() {
			const trigger = this;
			queue = queue.then( () => autoEditHandler.call( trigger, {
				preventDefault: function(){},
				stopPropagation: function(){}
			} ) ).then( null, () => {} );
		} );
	} );

}( jQuery, mediaWiki ) );
