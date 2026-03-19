/**
 * JavaScript code to be used with input type datetimepicker.
 *
 * @param {jQuery} $
 * @param {OO} oo
 * @param {mw} mw
 * @param {Object} pf
 * @author Sam Wilson
 * @author Yaron Koren
 */

( function( $, oo, mw, pf ) {
	'use strict';

	jQuery.fn.applyDateTimePicker = function() {
		return this.each(function() {
			oo.ui.infuse( this );
		});
	};

} )( jQuery, OO, mediaWiki, pf )
