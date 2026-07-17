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
		PFTargetInputDialog.super.call( this, config );
		this.defaultValue = config.defaultValue || '';
		this.dataType = config.dataType || '';
		this.dataSettings = config.dataSettings || '';
		this.$windowManagerElement = config.$windowManagerElement || null;
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
			const uniqueId = 'pf-target-input-dialog-' + Date.now();
			this.$syntheticSelect = $( '<select>' ).attr( {
				id: uniqueId,
				autocompletesettings: this.dataSettings,
				autocompletedatatype: this.dataType
			} );
			// apply() appends a loading icon to $sel.parent() — give it a temp body wrapper.
			const $wrap = $( '<div>' ).appendTo( document.body ).append( this.$syntheticSelect );

			// Use windowManager.$element as the overlay so the menu lives inside the same
			// stacking context as the dialog. This avoids the offsetParent mismatch that
			// occurs with $overlay: true (global body overlay): FloatableElement would use
			// getRelativePosition($floatableContainer, dialogBody) → left=0, and
			// ClippableElement.clip() would compute maxHeight=0 because getBoundingClientRect
			// sees the container as off-screen relative to the global overlay's scroll origin.
			this.inputWidget = new pf.ComboBoxInput( {
				$overlay: this.$windowManagerElement || true
			} );

			// Install all menu overrides BEFORE apply() so they are active during the
			// apply() call itself (apply() triggers onInputChange → toggle(true)).
			const menu = this.inputWidget.getMenu();

			// Prevent FloatableElement from hiding the menu when the floatable container
			// appears to be outside the viewport (dialog body is used as scroll container).
			menu.hideWhenOutOfView = false;
			// Prevent MenuSelectWidget.toggle() from flipping direction to 'above' based on
			// a viewport check against the dialog body scroll container.
			menu.isFloatableOutOfView = () => false;
			// Prevent ClippableElement.clip() from computing maxHeight=0 when the dialog body
			// is detected as the scroll container and getBoundingClientRect() returns an
			// off-screen rect for the menu's floatable container.
			menu.clip = function () {
				this.$clippable.css( { maxWidth: '', maxHeight: '', overflowX: '', overflowY: '' } );
				return this;
			};

			// Suppress menu opens (apply() init, setValue(), focus(), API responses) until
			// the user types. Flag lives on the menu instance; released by native 'input'.
			menu._pfSuppressOpen = true;
			const origToggle = menu.toggle.bind( menu );
			menu.toggle = ( show ) => {
				if ( show && menu._pfSuppressOpen ) {
					return menu;
				}
				return origToggle( show );
			};

			this.inputWidget.apply( this.$syntheticSelect );
			this.inputWidget.$element.detach();
			this.$syntheticSelect.remove();
			$wrap.remove();
			this.$syntheticSelect = null;

			// Release suppression only on genuine user input (not programmatic setValue).
			this.inputWidget.$input.on( 'input', () => {
				menu._pfSuppressOpen = false;
			} );

			// Fix: PF's blur handler resizes $element to value.length * 11px for short
			// values. Restore 100% width after each blur via rAF so the layout stays full.
			this.inputWidget.$element.css( 'width', '100%' );
			this.inputWidget.$input.on( 'blur', () => {
				requestAnimationFrame( () => {
					this.inputWidget.$element.css( 'width', '100%' );
				} );
			} );
		} else {
			this.inputWidget = new OO.ui.TextInputWidget( {
				value: this.defaultValue,
				placeholder: mw.msg( 'pf-target-input-dialog-title' )
			} );
		}

		const panel = new OO.ui.PanelLayout( { padded: true, expanded: false } );
		panel.$element.append( this.inputWidget.$element );
		this.$body.append( panel.$element );
	};

	PFTargetInputDialog.prototype.getBodyHeight = function () {
		return 100;
	};

	PFTargetInputDialog.prototype.getSetupProcess = function ( data ) {
		return PFTargetInputDialog.super.prototype.getSetupProcess.call( this, data )
			.next( () => {
				if ( this.defaultValue && this.inputWidget.setValue ) {
					if ( this.inputWidget.getMenu ) {
						this.inputWidget.getMenu()._pfSuppressOpen = true;
					}
					this.inputWidget.setValue( this.defaultValue );
				}
			}, this );
	};

	PFTargetInputDialog.prototype.getReadyProcess = function ( data ) {
		return PFTargetInputDialog.super.prototype.getReadyProcess.call( this, data )
			.next( () => {
				if ( this.inputWidget.getMenu ) {
					const menu = this.inputWidget.getMenu();
					// Re-set the floatable container after the dialog animation so
					// computePosition() uses the final viewport-relative coordinates.
					menu.setFloatableContainer( this.inputWidget.$element );
					// Keep suppression active through focus() — focus triggers a second
					// autocomplete query whose API response must also be suppressed.
					if ( this.defaultValue ) {
						menu._pfSuppressOpen = true;
					}
				}
				if ( this.inputWidget.focus ) {
					this.inputWidget.focus();
				}
			}, this );
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

	// Expose pure helpers on the pf namespace so Node QUnit tests can reach them
	// without needing to trigger DOM events.
	pf.pfTargetInputReadConfig = readConfig;
	pf.pfTargetInputBuildTargetUrl = buildTargetUrl;

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
			dataSettings: cfg.dataSettings,
			$windowManagerElement: windowManager.$element
		} );

		windowManager.addWindows( [ dialog ] );
		// Move focus away before opening so WindowManager's aria-hidden on the
		// page content does not trap focus on the trigger element.
		$trigger[ 0 ].blur();
		const instance = windowManager.openWindow( dialog, { title: cfg.dialogTitle || mw.msg( 'pf-target-input-dialog-title' ) } );
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
