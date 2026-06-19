/**
 * PF_FormLinkTargetInput.js
 *
 * OOUI dialog that lets the user enter (or autocomplete) a target page name
 * before following a #formlink. Activated by the `target input` parameter.
 *
 * @param {jQuery} $
 * @param {mw} mw
 * @param {OO} OO
 * @param {Object} pf
 *
 * @license GNU GPL v2+
 */
( function ( $, mw, OO, pf ) {

	/**
	 * Dialog subclass that hosts the page-name input.
	 *
	 * @class
	 * @extends OO.ui.ProcessDialog
	 * @param {Object} config
	 * @param {string} config.dialogTitle   Rendered title string (may contain HTML from i18n templates)
	 * @param {string} config.defaultValue  Pre-filled value for the input
	 * @param {string} [config.dataType]    pfautocomplete data type (e.g. 'category')
	 * @param {string} [config.dataSettings] autocomplete settings string (e.g. 'MyCategory')
	 */
	function PFTargetInputDialog( config ) {
		config.title = config.dialogTitle || mw.msg( 'pf-target-input-dialog-title' );
		PFTargetInputDialog.super.call( this, config );
		this.defaultValue = config.defaultValue || '';
		this.dataType = config.dataType || '';
		this.dataSettings = config.dataSettings || '';
	}
	OO.inheritClass( PFTargetInputDialog, OO.ui.ProcessDialog );

	PFTargetInputDialog.static.name = 'pfTargetInput';
	PFTargetInputDialog.static.actions = [
		{
			action: 'confirm',
			label: mw.msg( 'pf-target-input-confirm' ),
			flags: [ 'primary', 'progressive' ]
		},
		{
			action: 'cancel',
			label: mw.msg( 'pf-target-input-cancel' ),
			flags: 'safe'
		}
	];

	PFTargetInputDialog.prototype.initialize = function () {
		PFTargetInputDialog.super.prototype.initialize.call( this );

		if ( this.dataType ) {
			// Reuse pf.ComboBoxInput with a synthetic DOM element so we can
			// leverage the full autocomplete stack without a real form field.
			const uniqueId = 'pf-target-input-dialog-' + Date.now();
			// Build the minimal <select> that pf.ComboBoxInput.apply() expects.
			this.$syntheticSelect = $( '<select>' ).attr( {
				id: uniqueId,
				autocompletesettings: this.dataSettings,
				autocompletedatatype: this.dataType
			} );
			// ComboBoxInput.apply() replaces the element in the DOM; we hide it
			// in an off-screen container so OOUI can measure/render it properly.
			this.$syntheticContainer = $( '<div>' )
				.css( { position: 'absolute', left: '-9999px' } )
				.append( this.$syntheticSelect );
			$( document.body ).append( this.$syntheticContainer );

			// $overlay: true renders the dropdown menu in the document body
			// overlay instead of inside the dialog, preventing clipping by
			// the dialog's overflow:hidden container.
			this.inputWidget = new pf.ComboBoxInput( { $overlay: true } );
			this.inputWidget.apply( this.$syntheticSelect );
			this.inputWidget.setValue( this.defaultValue );
		} else {
			this.inputWidget = new OO.ui.TextInputWidget( {
				value: this.defaultValue,
				placeholder: mw.msg( 'pf-target-input-dialog-title' )
			} );
		}

		this.$body.append(
			new OO.ui.FieldLayout( this.inputWidget, { align: 'top' } ).$element
		);
	};

	PFTargetInputDialog.prototype.getBodyHeight = function () {
		return 120;
	};

	PFTargetInputDialog.prototype.getActionProcess = function ( action ) {
		if ( action === 'confirm' ) {
			return new OO.ui.Process( () => {
				const rawValue = this.inputWidget.getValue().trim();
				if ( rawValue === '' ) {
					return new OO.ui.Error( mw.msg( 'pf-target-input-error-empty' ) );
				}
				// Resolve display value → canonical title when ComboBoxInput is used.
				let canonicalValue = rawValue;
				if ( this.inputWidget.getCanonicalValueForInput ) {
					canonicalValue = this.inputWidget.getCanonicalValueForInput( rawValue ) || rawValue;
				}
				this.close( { action: action, value: canonicalValue } );
			} );
		}
		if ( action === 'cancel' ) {
			return new OO.ui.Process( () => {
				this.close( { action: action } );
			} );
		}
		return PFTargetInputDialog.super.prototype.getActionProcess.call( this, action );
	};

	PFTargetInputDialog.prototype.teardown = function ( data ) {
		if ( this.$syntheticContainer ) {
			this.$syntheticContainer.remove();
			this.$syntheticContainer = null;
		}
		return PFTargetInputDialog.super.prototype.teardown.call( this, data );
	};

	// -----------------------------------------------------------------------
	// Click handler
	// -----------------------------------------------------------------------

	/**
	 * Read data-pf-* attributes from either a <form> or an <a> element.
	 *
	 * @param {jQuery} $el
	 * @return {Object}
	 */
	function readConfig( $el ) {
		return {
			baseUrl: $el.is( 'form' ) ? $el.attr( 'action' ) : $el.attr( 'href' ),
			isForm: $el.is( 'form' ),
			isPost: $el.is( 'form' ) && $el.attr( 'method' ) === 'post',
			dialogTitle: $el.attr( 'data-pf-target-dialog-title' ) || '',
			defaultValue: $el.attr( 'data-pf-target-default' ) || '',
			dataType: $el.attr( 'data-pf-autocomplete-datatype' ) || '',
			dataSettings: $el.attr( 'data-pf-autocomplete-settings' ) || ''
		};
	}

	/**
	 * Build the final URL from the base URL and the user-entered page name.
	 * The target is appended as a path segment (matching PF_FormLink.php convention).
	 *
	 * @param {string} baseUrl  URL without trailing target segment
	 * @param {string} value    Page name entered by the user
	 * @return {string}
	 */
	function buildTargetUrl( baseUrl, value ) {
		const normalised = value.replace( / /g, '_' );
		// Strip a trailing '/' if the base URL already has a form path segment
		// so we don't get double slashes.
		const separator = baseUrl.endsWith( '/' ) ? '' : '/';
		return baseUrl + separator + encodeURIComponent( normalised );
	}

	$( document ).on( 'click', '[data-pf-target-input]', function ( e ) {
		const $trigger = $( this );

		// For <form> elements: only intercept clicks that originate from the
		// OOUI submit button (type=submit), not from arbitrary child elements
		// (e.g. cancel links in a surrounding nav bar that bubble up to the form).
		if ( $trigger.is( 'form' ) ) {
			const $origin = $( e.target ).closest( '[type="submit"]' );
			if ( $origin.length === 0 ) {
				return;
			}
		}

		e.preventDefault();
		const cfg = readConfig( $trigger );

		const windowManager = new OO.ui.WindowManager();
		$( document.body ).append( windowManager.$element );

		const dialog = new PFTargetInputDialog( {
			dialogTitle: cfg.dialogTitle,
			defaultValue: cfg.defaultValue,
			dataType: cfg.dataType,
			dataSettings: cfg.dataSettings
		} );

		windowManager.addWindows( [ dialog ] );
		// Move focus away before opening so WindowManager's aria-hidden on the
		// page content does not trap focus on the trigger element.
		$trigger[ 0 ].blur();
		const instance = windowManager.openWindow( dialog );
		instance.opening.then( () => {
			instance.closed.then( ( data ) => {
				windowManager.destroy();
				if ( !data || data.action !== 'confirm' ) {
					return;
				}
				const value = data.value;
				if ( cfg.isForm ) {
					if ( cfg.isPost ) {
						// POST form: inject hidden inputs for target and returnto, then submit.
						$trigger.find( 'input[name="target"]' ).remove();
						$trigger.find( 'input[name="returnto"]' ).remove();
						$trigger.append( $( '<input>' ).attr( { type: 'hidden', name: 'target', value: value } ) );
						$trigger.append( $( '<input>' ).attr( { type: 'hidden', name: 'returnto', value: mw.config.get( 'wgPageName' ) } ) );
						$trigger[ 0 ].submit();
					} else {
						// GET form: update action URL with target path segment and inject
						// returnto as a hidden input (query string on action is discarded
						// by the browser on GET submit).
						$trigger.find( 'input[name="returnto"]' ).remove();
						$trigger.attr( 'action', buildTargetUrl( cfg.baseUrl, value ) );
						$trigger.append( $( '<input>' ).attr( { type: 'hidden', name: 'returnto', value: mw.config.get( 'wgPageName' ) } ) );
						$trigger[ 0 ].submit();
					}
				} else {
					// Plain link: append returnto as query parameter.
					const returnto = mw.config.get( 'wgPageName' );
					window.location.href = buildTargetUrl( cfg.baseUrl, value ) +
						'?returnto=' + encodeURIComponent( returnto );
				}
			} );
		} );
	} );

}( jQuery, mediaWiki, OO, pageforms ) );
