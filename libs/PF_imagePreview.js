/**
 * JavaScript for the Page Forms MediaWiki extension.
 *
 * @param $
 * @param mw
 * @license GNU GPL v3+
 * @author Jeroen De Dauw <jeroendedauw at gmail dot com>
 */

( function ( $, mw ) {
	const _this = this;

	this.getPreviewImage = function( args, callback ) {
		$.getJSON(
			mw.config.get( 'wgScriptPath' ) + '/api.php',
			{
				'action': 'query',
				'format': 'json',
				'prop': 'imageinfo',
				'iiprop': 'url',
				'titles': 'File:' + args.title,
				'iiurlwidth': args.width
			},
			( data ) => {
				if ( data.query && data.query.pages ) {
					const pages = data.query.pages;

					for ( const p in pages ) { // object, not an array
						const info = pages[p].imageinfo;
						if ( info.length > 0 ) {
							callback( info[0].thumburl );
							return;
						}
					}
				}
				callback( false );
			}
		);
	};

	$( document ).ready( () => {
		$( '.pfImagePreview' ).each( ( index, domElement ) => {
			const $uploadLink = $( domElement );
			const inputId = $uploadLink.attr( 'data-input-id' );
			const $input = $( '#' + inputId );
			const $previewDiv = $( '#' + inputId + '_imagepreview' );

			const showPreview = function() {
				_this.getPreviewImage(
					{
						'title': $input.val(),
						'width': 200
					},
					( url ) => {
						if ( url === false ) {
							$previewDiv.html( '' );
						} else {
							$previewDiv.html( $( '<img />' ).attr( { 'src': url } ) );
						}
					}
				);
			};

			$input.change( showPreview );
		} );
	} );
}( jQuery, mediaWiki ) );
