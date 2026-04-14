( function( $, mw ) {
	'use strict';

	function sendData( $trigger ) {
		const $autoedit = $trigger.closest( '.autoedit' );
		const $result = $autoedit.find( '.autoedit-result' );

		$result.attr( 'class', 'autoedit-result autoedit-result-wait' );
		$result.text( mw.msg( 'pf-autoedit-wait' ) );

		const data = {
			action: 'pfautoedit',
			query: $autoedit.find( 'form.autoedit-data' ).serialize()
		};

		new mw.Api().post( data ).then(
			( result ) => {
				$result.empty()
					.append( result.responseText );
				if ( result.status === 200 ) {
					$result.removeClass( 'autoedit-result-wait' )
						.addClass( 'autoedit-result-ok' );
				} else {
					$result.removeClass( 'autoedit-result-wait' )
						.addClass( 'autoedit-result-error' );
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

				$result.empty()
					.append( text );
				$result.removeClass( 'autoedit-result-wait' )
					.addClass( 'autoedit-result-error' );
			}
		);
	}

	function handleAutoEditRating( $trigger, value ) {
		if ( mw.config.get( 'wgUserName' ) === null &&
			!confirm( mw.msg( 'pf_autoedit_anoneditwarning' ) ) ) {
			return;
		}
		const $autoedit = $trigger.closest( '.autoedit' );
		const $editdata = $autoedit.find( 'form.autoedit-data' );
		$editdata.find( '#ratingInput' )
			.attr( 'value', value );
		const targetpage = $editdata.find( 'input[name=target]' )
			.val();
		const confirmEdit = $editdata.hasClass( 'confirm-edit' );
		if ( confirmEdit ) {
			OO.ui.confirm( mw.msg( 'pf_autoedit_confirm', targetpage ) )
				.then( (confirmed) => {
					if ( confirmed ) {
						sendData( $trigger );
					}
				} )
		} else {
			sendData( $trigger );
		}
	};

	$.fn.applyRatingInput = function( fromCalendar ) {
		const starWidth = $( this )
			.attr( 'data-starwidth' );
		let curValue = $( this )
			.attr( 'data-curvalue' );
		const numStars = $( this )
			.attr( 'data-numstars' );
		const allowsHalf = $( this )
			.attr( 'data-allows-half' );

		if ( curValue === '' || curValue === undefined ) {
			curValue = 0;
		}

		const ratingsSettings = {
			normalFill: '#ddd',
			starWidth: starWidth,
			numStars: numStars,
			maxValue: numStars,
			rating: curValue
		};

		if ( allowsHalf === undefined ) {
			ratingsSettings.fullStar = true;
		} else {
			ratingsSettings.halfStar = true;
		}

		$( this )
			.rateYo( ratingsSettings )
			.on( 'rateyo.set', function( e, data ) {
				handleAutoEditRating( $( this )
					.parent(), data.rating );
			} );
	};

	$( () => {
		$( document )
			.find( '.pfRating' )
			.each( function() {
				$( this ).applyRatingInput();
			} );
	} );

}( jQuery, mediaWiki ) );
