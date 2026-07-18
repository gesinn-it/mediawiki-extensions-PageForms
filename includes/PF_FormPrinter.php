<?php
/**
 * Handles the creation and running of a user-created form.
 *
 * @author Yaron Koren
 * @author Nils Oppermann
 * @author Jeffrey Stuckman
 * @author Harold Solbrig
 * @author Daniel Hansch
 * @author Stephan Gambke
 * @author LY Meng
 * @ingroup PF
 */

use MediaWiki\Extension\PageForms\CalendarHtmlBuilder;
use MediaWiki\Extension\PageForms\FieldValueResolver;
use MediaWiki\Extension\PageForms\FormCounters;
use MediaWiki\Extension\PageForms\FormDefParser;
use MediaWiki\Extension\PageForms\FormFieldHtmlBuilder;
use MediaWiki\Extension\PageForms\FormPlaceholder;
use MediaWiki\Extension\PageForms\FormSectionHtmlBuilder;
use MediaWiki\Extension\PageForms\InputTypeRegistry;
use MediaWiki\Extension\PageForms\MultipleTemplateHtmlBuilder;
use MediaWiki\Extension\PageForms\SpreadsheetHtmlBuilder;
use MediaWiki\Extension\PageForms\StandardInputHtmlBuilder;
use MediaWiki\MediaWikiServices;

class PFFormPrinter {

	/**
	 * This property stores mSemanticTypeHooks values
	 *
	 * @var array
	 */
	public $mSemanticTypeHooks;
	/**
	 * This property stores mInputTypeHooks values
	 *
	 * @var array
	 */
	public $mInputTypeHooks;
	/**
	 * This property stores standardInputsIncluded values
	 *
	 * @var array
	 */
	public $standardInputsIncluded;
	/**
	 * This property stores mPageTitle values
	 *
	 * @var array
	 */
	public $mPageTitle;

	/** Owned by InputTypeRegistry; FormPrinter delegates to it for all input-type lookups. */
	private InputTypeRegistry $inputTypeRegistry;

	private CalendarHtmlBuilder $calendarHtmlBuilder;

	private SpreadsheetHtmlBuilder $spreadsheetHtmlBuilder;

	private MultipleTemplateHtmlBuilder $multipleTemplateHtmlBuilder;

	private FormFieldHtmlBuilder $formFieldHtmlBuilder;

	private FormDefParser $formDefParser;

	private StandardInputHtmlBuilder $standardInputHtmlBuilder;

	private FormSectionHtmlBuilder $formSectionHtmlBuilder;

	private FieldValueResolver $fieldValueResolver;

	private ?FormCounters $counters = null;

	/** Set by the {{{info|query form at top}}} tag; returned from formHTML(). */
	private bool $runQueryFormAtTop = false;

	public function __construct() {
		global $wgPageFormsDisableOutsideServices;
		// Initialize variables.
		$this->mSemanticTypeHooks = [];
		$this->mInputTypeHooks = [];
		$this->inputTypeRegistry = new InputTypeRegistry();
		$this->calendarHtmlBuilder = new CalendarHtmlBuilder();
		$this->multipleTemplateHtmlBuilder = new MultipleTemplateHtmlBuilder();
		$this->spreadsheetHtmlBuilder = new SpreadsheetHtmlBuilder();
		$this->standardInputHtmlBuilder = new StandardInputHtmlBuilder();
		$this->formSectionHtmlBuilder = new FormSectionHtmlBuilder();
		$this->fieldValueResolver = new FieldValueResolver();

		$this->standardInputsIncluded = false;

		$this->registerInputType( 'PFTextInput' );
		$this->registerInputType( 'PFTextWithAutocompleteInput' );
		$this->registerInputType( 'PFTextAreaInput' );
		$this->registerInputType( 'PFTextAreaWithAutocompleteInput' );
		$this->registerInputType( 'PFDateInput' );
		$this->registerInputType( 'PFStartDateInput' );
		$this->registerInputType( 'PFEndDateInput' );
		$this->registerInputType( 'PFDatePickerInput' );
		$this->registerInputType( 'PFDateTimePicker' );
		$this->registerInputType( 'PFDateTimeInput' );
		$this->registerInputType( 'PFStartDateTimeInput' );
		$this->registerInputType( 'PFEndDateTimeInput' );
		$this->registerInputType( 'PFYearInput' );
		$this->registerInputType( 'PFCheckboxInput' );
		$this->registerInputType( 'PFDropdownInput' );
		$this->registerInputType( 'PFRadioButtonInput' );
		$this->registerInputType( 'PFCheckboxesInput' );
		$this->registerInputType( 'PFListBoxInput' );
		$this->registerInputType( 'PFComboBoxInput' );
		$this->registerInputType( 'PFTreeInput' );
		$this->registerInputType( 'PFTokensInput' );
		$this->registerInputType( 'PFRegExpInput' );
		$this->registerInputType( 'PFRatingInput' );
		$this->registerInputType( 'PFSFSelectInput' );
		// Add this if the Semantic Maps extension is not
		// included, or if it's SM (really Maps) v4.0 or higher.
		if ( !$wgPageFormsDisableOutsideServices ) {
			if ( !defined( 'SM_VERSION' ) || version_compare( SM_VERSION, '4.0', '>=' ) ) {
				$this->registerInputType( 'PFGoogleMapsInput' );
			}
			$this->registerInputType( 'PFOpenLayersInput' );
			$this->registerInputType( 'PFLeafletInput' );
		}

		// All-purpose setup hook.
		// Avoid PHP 7.1 warning from passing $this by reference.
		$formPrinterRef = $this;
		MediaWikiServices::getInstance()->getHookContainer()->run(
			'PageForms::FormPrinterSetup', [ &$formPrinterRef ]
		);

		// Build after all hooks are registered so the builder sees the full type maps.
		$this->formFieldHtmlBuilder = new FormFieldHtmlBuilder( $this->mInputTypeHooks, $this->mSemanticTypeHooks );
		$this->formDefParser = new FormDefParser( MediaWikiServices::getInstance()->getParserFactory() );
	}

	public function setSemanticTypeHook( $type, $is_list, $class_name, $default_args ) {
		$this->mSemanticTypeHooks[$type][$is_list] = [ $class_name, $default_args ];
	}

	public function setInputTypeHook( $input_type, $class_name, $default_args ) {
		$this->mInputTypeHooks[$input_type] = [ $class_name, $default_args ];
	}

	/**
	 * Register all information about the passed-in form input class.
	 *
	 * @param string $inputTypeClass The full qualified class name representing the new input.
	 * Must be derived from PFFormInput.
	 */
	public function registerInputType( $inputTypeClass ) {
		// Delegate the five private lookup tables to InputTypeRegistry.
		$this->inputTypeRegistry->register( $inputTypeClass );

		// Keep $mInputTypeHooks and $mSemanticTypeHooks in sync on FormPrinter
		// for backward compatibility with external code that reads them directly.
		$inputTypeName = call_user_func( [ $inputTypeClass, 'getName' ] );
		$this->setInputTypeHook( $inputTypeName, $inputTypeClass, [] );

		$defaultProperties = call_user_func( [ $inputTypeClass, 'getDefaultPropTypes' ] );
		foreach ( $defaultProperties as $propertyType => $additionalValues ) {
			$this->setSemanticTypeHook( $propertyType, false, $inputTypeClass, $additionalValues );
		}
		$defaultPropertyLists = call_user_func( [ $inputTypeClass, 'getDefaultPropTypeLists' ] );
		foreach ( $defaultPropertyLists as $propertyType => $additionalValues ) {
			$this->setSemanticTypeHook( $propertyType, true, $inputTypeClass, $additionalValues );
		}
	}

	public function getInputType( $inputTypeName ) {
		return $this->inputTypeRegistry->getClass( $inputTypeName );
	}

	public function getDefaultInputTypeSMW( $isList, $propertyType ) {
		return $this->inputTypeRegistry->getDefaultInputType( (bool)$isList, $propertyType );
	}

	public function getPossibleInputTypesSMW( $isList, $propertyType ) {
		return $this->inputTypeRegistry->getPossibleInputTypes( (bool)$isList, $propertyType );
	}

	public function getAllInputTypes() {
		return $this->inputTypeRegistry->getAllTypeNames();
	}

	/**
	 * Show the set of previous deletions for the page being edited.
	 * @param OutputPage $out
	 * @return true
	 */
	public function showDeletionLog( $out ) {
		LogEventsList::showLogExtract( $out, 'delete', $this->mPageTitle->getPrefixedText(),
			'', [ 'lim' => 10,
				'conds' => [ "log_action != 'revision'" ],
				'showIfEmpty' => false,
				'msgKey' => [ 'moveddeleted-notice' ] ]
		);
		return true;
	}

	/**
	 * @deprecated since PageForms 5.x — use PFUtils::strReplaceFirst() instead.
	 * @param string $search
	 * @param string $replace
	 * @param string $subject
	 * @return string
	 */
	public function strReplaceFirst( $search, $replace, $subject ) {
		return PFUtils::strReplaceFirst( $search, $replace, $subject );
	}

	public static function placeholderFormat( $templateName, $fieldName ) {
		return FormPlaceholder::format( $templateName, $fieldName );
	}

	public static function makePlaceholderInFormHTML( $str ) {
		return FormPlaceholder::toHtmlMarker( $str );
	}

	public function multipleTemplateStartHTML( $tif ) {
		return $this->multipleTemplateHtmlBuilder->multipleTemplateStartHTML( $tif );
	}

	/**
	 * Creates the HTML for the inner table for every instance of a
	 * multiple-instance template in the form.
	 * @param bool $form_is_disabled
	 * @param string $mainText
	 * @return string
	 */
	public function multipleTemplateInstanceTableHTML( $form_is_disabled, $mainText ) {
		return $this->multipleTemplateHtmlBuilder->multipleTemplateInstanceTableHTML( $form_is_disabled, $mainText );
	}

	/**
	 * Creates the HTML for a single instance of a multiple-instance
	 * template.
	 * @param PFTemplateInForm $template_in_form
	 * @param bool $form_is_disabled
	 * @param string &$section
	 * @return string
	 */
	public function multipleTemplateInstanceHTML( $template_in_form, $form_is_disabled, &$section ) {
		return $this->multipleTemplateHtmlBuilder->multipleTemplateInstanceHTML(
			$template_in_form, $form_is_disabled, $section
		);
	}

	/**
	 * Creates the end of the HTML for a multiple-instance template -
	 * including the sections necessary for adding additional instances.
	 * @param PFTemplateInForm $template_in_form
	 * @param bool $form_is_disabled
	 * @param string $section
	 * @return string
	 */
	public function multipleTemplateEndHTML( $template_in_form, $form_is_disabled, $section ) {
		return $this->multipleTemplateHtmlBuilder->multipleTemplateEndHTML(
			$template_in_form, $form_is_disabled, $section, $this->counters
		);
	}

	public function tableHTML( $tif, $instanceNum ) {
		return $this->spreadsheetHtmlBuilder->tableHTML(
			$tif, $instanceNum, [ $this, 'formFieldHTML' ], $this->counters
		);
	}

	public function getSpreadsheetAutocompleteAttributes( $formFieldArgs ) {
		return $this->spreadsheetHtmlBuilder->getSpreadsheetAutocompleteAttributes( $formFieldArgs );
	}

	public function spreadsheetHTML( $tif ) {
		global $wgOut, $wgPageFormsScriptPath;
		return $this->spreadsheetHtmlBuilder->spreadsheetHTML( $tif, $wgOut, $wgPageFormsScriptPath );
	}

	public function calendarHTML( $tif ) {
		global $wgPageFormsScriptPath;
		return $this->calendarHtmlBuilder->calendarHTML( $tif, $wgPageFormsScriptPath );
	}

	/**
	 * Extract preloaded field values from an existing page's wikitext using
	 * the form definition, without generating any HTML.
	 *
	 * This is a stripped-down version of formHTML() for the $source_is_page=true
	 * path: it sets up a parser, normalises the form definition, walks the
	 * {{{for template}}} / {{{field}}} / {{{end template}}} sections, calls
	 * PFTemplateInForm::setFieldValuesFromPage() for each template found in
	 * the page, and returns the collected values as a nested array.
	 *
	 * The returned keys use the same underscore-normalisation as
	 * HtmlFormDataExtractor::extract(), so the result can be merged directly
	 * into PFAutoeditAPI::$mOptions without further transformation.
	 *
	 * **Limitation**: Only the first occurrence of each template is read; multiple-instance
	 * templates (those with the `multiple` attribute) are not supported — subsequent
	 * instances on the page are silently ignored.
	 *
	 * @param string $form_def Form definition wikitext (noinclude already stripped)
	 * @param string $existing_page_content Wikitext of the existing page to preload from
	 * @param int|null $form_id Form article ID (used for parser cache, may be null)
	 * @return array<string, string|array<string, string>> Template field values keyed by template name, plus
	 *   optionally 'pf_free_text' => string for any remaining page content outside templates.
	 */
	public function preparePreloadData( string $form_def, string $existing_page_content, ?int $form_id = null ): array {
		return $this->formDefParser->preparePreloadData( $form_def, $existing_page_content, $form_id );
	}

	/**
	 * Split a form definition string into sections on {{{for template}}} / {{{end template}}}
	 * boundaries.
	 *
	 * @param string $form_def Form definition wikitext.
	 * @return list<string>
	 */
	private function splitFormDefIntoSections( string $form_def ): array {
		$form_def_sections = [];
		$start_position = 0;
		$section_start = 0;
		$brackets_loc = strpos( $form_def, '{{{', $start_position );
		while ( $brackets_loc !== false ) {
			$brackets_end_loc = strpos( $form_def, '}}}', $brackets_loc );
			$bracketed_string = substr( $form_def, $brackets_loc + 3, $brackets_end_loc - ( $brackets_loc + 3 ) );
			$tag_components = PFUtils::getFormTagComponents( $bracketed_string );
			if ( count( $tag_components ) > 0 ) {
				$tag_title = trim( $tag_components[0] );
				if ( $tag_title === 'for template' || $tag_title === 'end template' ) {
					$form_def_sections[] = substr( $form_def, $section_start, $brackets_loc - $section_start );
					$section_start = $brackets_loc;
				}
			}
			$start_position = $brackets_loc + 1;
			$brackets_loc = strpos( $form_def, '{{{', $start_position );
		}
		$form_def_sections[] = trim( substr( $form_def, $section_start ) );
		return $form_def_sections;
	}

	/**
	 * This function is the real heart of the entire Page Forms
	 * extension. It handles two main actions: (1) displaying a form on the
	 * screen, given a form definition and possibly page contents (if an
	 * existing page is being edited); and (2) creating actual page
	 * contents, if the form was already submitted by the user.
	 *
	 * It also does some related tasks, like figuring out the page name (if
	 * only a page formula exists).
	 * @param string $form_def
	 * @param bool $form_submitted
	 * @param bool $source_is_page
	 * @param string|null $form_id
	 * @param string|null $existing_page_content
	 * @param string|null $page_name
	 * @param string|null $page_name_formula
	 * @param bool $is_query
	 * @param bool $is_embedded
	 * @param bool $is_autocreate true when called by #formredlink with "create page"
	 * @param array $autocreate_query query parameters from #formredlink
	 * @param User|null $user
	 * @param WebRequest|null $request
	 * @return array [ $form_text, $page_text, $form_page_title, $generated_page_name,
	 *   $parserOutput, $runQueryFormAtTop ]
	 * @throws FatalError
	 * @throws MWException
	 */
	public function formHTML(
		$form_def,
		$form_submitted,
		$source_is_page,
		$form_id = null,
		$existing_page_content = null,
		$page_name = null,
		$page_name_formula = null,
		$is_query = false,
		$is_embedded = false,
		$is_autocreate = false,
		$autocreate_query = [],
		$user = null,
		$request = null
	) {
		if ( $request === null ) {
			$request = RequestContext::getMain()->getRequest();
		}
		// used to represent the current tab index in the form
		global $wgPageFormsTabIndex;
		// used for setting various HTML IDs
		global $wgPageFormsFieldNum;
		global $wgPageFormsShowExpandAllLink;

		// Initialize some variables.
		$wiki_page = new PFWikiPage();
		$wgPageFormsTabIndex = 0;
		$wgPageFormsFieldNum = 0;
		$this->counters = new FormCounters();
		$this->runQueryFormAtTop = false;
		$source_page_matches_this_form = false;
		$form_page_title = null;
		$generated_page_name = $page_name_formula;
		$new_text = "";
		$original_page_content = $existing_page_content;

		// Disable all form elements if user doesn't have edit
		// permission - two different checks are needed, because
		// editing permissions can be set in different ways.
		// HACK - sometimes we don't know the page name in advance, but
		// we still need to set a title here for testing permissions.
		if ( $is_embedded || $is_query ) {
			// If this is an embedded form (probably a 'RunQuery') or we're in Special:RunQuery,
			// just use the name of the actual page we're on.
			$titleGlobal = RequestContext::getMain()->getTitle();
			$this->mPageTitle = $titleGlobal;
		} elseif ( $page_name === '' || $page_name === null ) {
			$this->mPageTitle = Title::newFromText(
				$request->getVal( 'namespace' ) . ":Page Forms permissions test" );
		} else {
			// $page_name may not be a syntactically valid title (e.g. it was
			// generated from a page name formula, or came from an untrusted
			// request value); fall back to the same placeholder title used
			// above for permission-testing purposes rather than leaving
			// $this->mPageTitle null, which fatals in getPermissionErrors()
			// and other unguarded uses below.
			$this->mPageTitle = Title::newFromText( $page_name ) ?? Title::newFromText(
				$request->getVal( 'namespace' ) . ":Page Forms permissions test" );
		}

		if ( $user === null ) {
			$user = RequestContext::getMain()->getUser();
		}

		global $wgOut;
		// Show previous set of deletions for this page, if it's been
		// deleted before.
		if ( !$form_submitted &&
			( $this->mPageTitle && !$this->mPageTitle->exists() &&
			$page_name_formula === null )
		) {
			$this->showDeletionLog( $wgOut );
		}
		// Unfortunately, we can't just call userCan() or its
		// equivalent here because it seems to ignore the setting
		// "$wgEmailConfirmToEdit = true;". Instead, we'll just get the
		// permission errors from the start, and use those to determine
		// whether the page is editable.
		if ( !$is_query ) {
			$permissionErrors = MediaWikiServices::getInstance()->getPermissionManager()
					->getPermissionErrors( 'edit', $user, $this->mPageTitle );
			if ( MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly() ) {
				$permissionErrors = [ [ 'readonlytext',
					[ MediaWikiServices::getInstance()->getReadOnlyMode()->getReason() ] ] ];
			}
			$userCanEditPage = count( $permissionErrors ) == 0;
			MediaWikiServices::getInstance()->getHookContainer()->run(
				'PageForms::UserCanEditPage', [ $this->mPageTitle, &$userCanEditPage ]
			);
		}

		// Start off with a loading spinner - this will be removed by
		// the JavaScript once everything has finished loading.
		$form_text = PFFormUtils::displayLoadingImage();
		if ( $is_query || $userCanEditPage ) {
			$form_is_disabled = false;
			// Show "Your IP address will be recorded" warning if
			// user is anonymous, and it's not a query.
			if ( $user->isAnon() && !$is_query ) {
				// Based on code in MediaWiki's EditPage.php.
				$anonEditWarning = wfMessage( 'anoneditwarning',
					// Log-in link
					'{{fullurl:Special:UserLogin|returnto={{FULLPAGENAMEE}}}}',
					// Sign-up link
					'{{fullurl:Special:UserLogin/signup|returnto={{FULLPAGENAMEE}}}}' )->parse();
				$form_text .= Html::rawElement(
					'div', [ 'id' => 'mw-anon-edit-warning', 'class' => 'warningbox' ], $anonEditWarning
				);
			}
		} else {
			$form_is_disabled = true;
			if ( $wgOut->getTitle() != null ) {
				$wgOut->setPageTitle( wfMessage( 'badaccess' )->text() );
				$wgOut->addWikiTextAsInterface( $wgOut->formatPermissionsErrorMessage( $permissionErrors, 'edit' ) );
				$wgOut->addHTML( "\n<hr />\n" );
			}
		}

		if ( $wgPageFormsShowExpandAllLink ) {
			$form_text .= Html::rawElement( 'p', [ 'id' => 'pf-expand-all' ],
				// @TODO - add an i18n message for this.
				Html::element( 'a', [ 'href' => '#' ], 'Expand all collapsed parts of the form' ) ) . "\n";
		}

		// getFreshParser() was removed in MW 1.43; use the factory on newer versions.
		$globalParser = PFUtils::getParser();
		if ( method_exists( $globalParser, 'getFreshParser' ) ) {
			// MW < 1.43: reset the global parser instance in-place
			$parser = $globalParser->getFreshParser();
			if ( !$parser->getOptions() ) {
				$parser->setOptions( ParserOptions::newFromUser( $user ) );
			}
		} else {
			// MW 1.43+: create a fresh parser via the factory
			$parser = MediaWikiServices::getInstance()->getParserFactory()->create();
			$parser->setOptions( ParserOptions::newFromUser( $user ) );
		}
		$parser->setTitle( $this->mPageTitle );
		// This is needed in order to make sure $parser->mLinkHolders
		// is set.
		$parser->clearState();

		$form_def = PFFormCache::getFormDefinition( $parser, $form_def, $form_id );
		// Snapshot RL modules registered by parser tag hooks during form-definition
		// parsing. PFFormField calls $parser->clearState() during field rendering,
		// which resets $parser->mOutput and discards these modules. We save them
		// here and merge them back into the final ParserOutput before returning.
		$formDefParserModules = $parser->getOutput()->getModules();
		$formDefParserModuleStyles = $parser->getOutput()->getModuleStyles();

		$free_text_was_included = false;
		$preloaded_free_text = null;
		// @HACK - replace the 'free text' standard input with a
		// field declaration to get it to be handled as a field.
		$form_def = str_replace( 'standard input|free text', 'field|#freetext#', $form_def );
		$form_def_sections = $this->splitFormDefIntoSections( $form_def );

		// Cycle through the form definition file, and possibly an
		// existing article as well, finding template and field
		// declarations and replacing them with form elements, either
		// blank or pre-populated, as appropriate.
		$template_name = null;
		$template = null;
		$tif = null;
		// This array will keep track of all the replaced @<name>@ strings
		$placeholderFields = [];
		$info_tag_seen = false;

		for ( $section_num = 0; $section_num < count( $form_def_sections ); $section_num++ ) {
			$start_position = 0;
			// the append is there to ensure that the original
			// array doesn't get modified; is it necessary?
			$section = " " . $form_def_sections[$section_num];

			while ( true ) {
				$brackets_loc = strpos( $section, '{{{', $start_position );
				if ( $brackets_loc === false ) {
					break;
				}
				$brackets_end_loc = strpos( $section, "}}}", $brackets_loc );
				if ( $brackets_end_loc === false ) {
					throw new MWException(
						'<div class="error">Error in form definition!'
						. ' The following tag is missing its closing \'}}}\':</div>'
						. "\n<pre>" . htmlspecialchars( substr( $section, $brackets_loc ) ) . "</pre>"
					);
				}
				// For cases with more than 3 ending brackets,
				// take the last 3 ones as the tag end.
				while ( isset( $section[$brackets_end_loc + 3] ) && $section[$brackets_end_loc + 3] == "}" ) {
					$brackets_end_loc++;
				}
				$bracketed_string = substr( $section, $brackets_loc + 3, $brackets_end_loc - ( $brackets_loc + 3 ) );
				$tag_components = PFUtils::getFormTagComponents( $bracketed_string );
				if ( count( $tag_components ) == 0 ) {
					break;
				}
				$tag_title = trim( $tag_components[0] );
				// Checks for forbidden characters
				if ( $tag_title != 'info' ) {
					foreach ( $tag_components as $tag_component ) {
						// Angled brackets could cause a security leak (and should not be necessary).
						// Allow them in "default filename", though.
						$tagParts = explode( '=', $tag_component, 2 );
						if ( count( $tagParts ) == 2 && $tagParts[0] == 'default filename' ) {
							continue;
						}
						if ( str_contains( $tag_component, '<' ) && str_contains( $tag_component, '>' ) ) {
							throw new MWException(
								'<div class="error">Error in form definition!' .
						' The following field tag contains forbidden characters:</div>' .
								"\n<pre>" . htmlspecialchars( $tag_component ) . "</pre>"
							);
						}
					}
				}
				// =====================================================
				// for template processing
				// =====================================================
				if ( $tag_title == 'for template' ) {
					if ( count( $tag_components ) < 2 ) {
						throw new MWException(
							'<div class="error">Error in form definition:' .
							' \'for template\' tag is missing the template name.</div>'
						);
					}
					if ( $tif ) {
						$previous_template_name = $tif->getTemplateName();
					} else {
						$previous_template_name = '';
					}
					$template_name = str_replace( '_', ' ', $parser->recursiveTagParse( $tag_components[1] ) );
					$is_new_template = ( $template_name != $previous_template_name );
					if ( $is_new_template ) {
						$template = PFTemplate::newFromName( $template_name );
						$tif = PFTemplateInForm::newFromFormTag( $tag_components, $parser );
					}
					// Remove template tag.
					$section = substr_replace( $section, '', $brackets_loc, $brackets_end_loc + 3 - $brackets_loc );
					// If we are editing a page, and this
					// template can be found more than
					// once in that page, and multiple
					// values are allowed, repeat this
					// section.
					if ( $source_is_page ) {
						$tif->setPageRelatedInfo( $existing_page_content );
						// Get the first instance of
						// this template on the page
						// being edited, even if there
						// are more.
						if ( $tif->pageCallsThisTemplate() ) {
							$tif->setFieldValuesFromPage( $existing_page_content );
							$existing_template_text = $tif->getFullTextInPage();
							// Now remove this template from the text being edited.
							$existing_page_content = PFUtils::strReplaceFirst(
								$existing_template_text, '', $existing_page_content
							);
							// If we've found a match in the source
							// page, there's a good chance that this
							// page was created with this form - note
							// that, so we don't send the user a warning.
							$source_page_matches_this_form = true;
						}
					}

					// We get values from the request,
					// regardless of whether the source is the
					// page or a form submit, because even if
					// the source is a page, values can still
					// come from a query string.
					// (Unless it's called from #formredlink.)
					if ( !$is_autocreate ) {
						$tif->setFieldValuesFromSubmit( $request );
					}

					$tif->checkIfAllInstancesPrinted( $form_submitted, $source_is_page );

					if ( !$tif->allInstancesPrinted() ) {
						$wiki_page->addTemplate( $tif );
					}

				// =====================================================
				// end template processing
				// =====================================================
				} elseif ( $tag_title == 'end template' ) {
					if ( count( $tag_components ) > 1 ) {
						throw new MWException(
							'<div class="error">Error in form definition:' .
							' \'end template\' tag cannot contain any additional parameters.</div>'
						);
					}
					if ( $source_is_page ) {
						// Add any unhandled template fields
						// in the page as hidden variables.
						$form_text .= PFFormUtils::unhandledFieldsHTML( $tif );
					}
					// Remove this tag from the $section variable.
					$section = substr_replace( $section, '', $brackets_loc, $brackets_end_loc + 3 - $brackets_loc );
					$template = null;
					$tif = null;
				// =====================================================
				// field processing
				// =====================================================
				} elseif ( $tag_title == 'field' ) {
					// If the template is null, that (hopefully)
					// means we're handling the free text field.
					// Make the template a dummy variable.
					if ( $tif == null ) {
						$template = new PFTemplate( null, [] );
						// Get free text from the query string, if it was set.
						if ( $request->getCheck( 'free_text' ) ) {
							$standard_input = $request->getArray( 'standard_input', [] );
							$standard_input['#freetext#'] = $request->getVal( 'free_text' );
							$request->setVal( 'standard_input', $standard_input );
						}
						$tif = PFTemplateInForm::create( 'standard_input', null, null, null, [] );
						$tif->setFieldValuesFromSubmit( $request );
					}
					// We get the field name both here
					// and in the PFFormField constructor,
					// because PFFormField isn't equipped
					// to deal with the #freetext# hack,
					// among others.
					if ( count( $tag_components ) < 2 ) {
						throw new MWException(
							'<div class="error">Error in form definition:' .
							' \'field\' tag is missing the field name.</div>'
						);
					}
					$field_name = trim( $tag_components[1] );
					$form_field = PFFormField::newFromFormFieldTag(
						$tag_components, $template, $tif, $form_is_disabled, $user
					);
					// For special displays, add in the
					// form fields, so we know the data
					// structure.
					if ( ( $tif->getDisplay() == 'table'
							&& ( !$tif->allowsMultiple() || $tif->getInstanceNum() == 0 ) ) ||
						( $tif->getDisplay() == 'spreadsheet'
							&& $tif->allowsMultiple() && $tif->getInstanceNum() == 0 ) ||
						( $tif->getDisplay() == 'calendar'
							&& $tif->allowsMultiple() && $tif->getInstanceNum() == 0 ) ) {
						$tif->addField( $form_field );
					}
					$val_modifier = null;
					if ( $is_autocreate ) {
						$values_from_query = $autocreate_query[$tif->getTemplateName()] ?? [];
						$cur_value = $form_field->getCurrentValue(
							$values_from_query, $form_submitted, $source_is_page,
							$tif->allInstancesPrinted(), $val_modifier
						);
					} else {
						$cur_value = $form_field->getCurrentValue(
							$tif->getValuesFromSubmit(), $form_submitted, $source_is_page,
							$tif->allInstancesPrinted(), $val_modifier
						);
					}
					$delimiter = $form_field->getFieldArg( 'delimiter' );
					if ( $form_field->holdsTemplate() ) {
						$placeholderFields[] = self::placeholderFormat( $tif->getTemplateName(), $field_name );
					}

					if ( $val_modifier !== null ) {
						$page_value = $tif->getValuesFromPage()[$field_name] ?? '';
						$cur_value = $this->fieldValueResolver->applyValModifier(
							(string)$cur_value, $val_modifier, (string)$page_value, $delimiter
						);
						$tif->changeFieldValues( $field_name, $cur_value, $delimiter );
					}
					// If the user is editing a page, and that page contains a call to
					// the template being processed, get the current field's value
					// from the template call
					if ( $source_is_page && ( $tif->getFullTextInPage() != '' ) && !$form_submitted ) {
						if ( $tif->hasValueFromPageForField( $field_name ) ) {
							// Get value, and remove it,
							// so that at the end we
							// can have a list of all
							// the fields that weren't
							// handled by the form.
							$cur_value = $tif->getAndRemoveValueFromPageForField( $field_name );

							// If the field is a placeholder, the contents of this template
							// parameter should be treated as elements parsed by an another
							// multiple template form.
							// By putting that at the very end of the parsed string, we'll
							// have it processed as a regular multiple template form.
							if ( $form_field->holdsTemplate() ) {
								$existing_page_content .= $cur_value;
							}
						} elseif ( $cur_value !== '' ) {
							// Do nothing.
						} else {
							$cur_value = '';
						}
					}

					// Handle the free text field.
					if ( $field_name == '#freetext#' ) {
						// If there was no preloading, this will just be blank.
						$preloaded_free_text = $cur_value;
						// Add placeholders for the free text in both the form and
						// the page, using <free_text> tags - once all the free text
						// is known (at the end), it will get substituted in.
						if ( $form_field->isHidden() ) {
							$new_text = Html::hidden( 'pf_free_text', '!free_text!' );
						} else {
							$wgPageFormsTabIndex++;
							$wgPageFormsFieldNum++;
							$this->counters->tabIndex = $wgPageFormsTabIndex;
							$this->counters->fieldNum = $wgPageFormsFieldNum;
							if ( $cur_value === '' || $cur_value === null ) {
								$default_value = '!free_text!';
							} else {
								$default_value = $cur_value;
							}
							$freeTextInput = new PFTextAreaInput(
								$input_number = null, $default_value, 'pf_free_text',
								( $form_is_disabled || $form_field->isRestricted() ),
								$form_field->getFieldArgs()
							);
							$freeTextInput->addJavaScript();
							$new_text = $freeTextInput->getHtmlText();
							if ( $form_field->hasFieldArg( 'edittools' ) ) {
								// borrowed from EditPage::showEditTools()
								$edittools_text = $parser->recursiveTagParse(
									wfMessage( 'edittools', [ 'content' ] )->text()
								);

								$new_text .= <<<END
		<div class="mw-editTools">
		$edittools_text
		</div>

END;
							}
						}
						$free_text_was_included = true;
						$wiki_page->addFreeTextSection();
					}

					if ( $tif->getTemplateName() === '' || $field_name == '#freetext#' ) {
						$section = substr_replace(
							$section, $new_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc
						);
					} else {
						if ( $form_field->holdsTemplate() ) {
							// If this field holds an embedded template and the value is not
							// an array, there are no instances of the template — set the value
							// to null to avoid carrying over whatever is currently on the page.
							$cur_value_in_template = null;
						} else {
							$cur_value_in_template = $cur_value;
						}

						// If we're creating the page name from a formula based on
						// form values, see if the current input is part of that formula,
						// and if so, substitute in the actual value.
						if ( $form_submitted && $generated_page_name !== '' ) {
							// This line appears to be unnecessary.
							// $generated_page_name = str_replace('.', '_', $generated_page_name);
							$generated_page_name = str_replace( ' ', '_', $generated_page_name ?? '' );
							$escaped_input_name = str_replace( ' ', '_', $form_field->getInputName() ?? '' );
							$generated_page_name = str_ireplace(
								"<$escaped_input_name>", $cur_value_in_template ?? '', $generated_page_name
							);
							// Once the substitution is done, replace underlines back
							// with spaces.
							$generated_page_name = str_replace( '_', ' ', $generated_page_name );
						}
						if ( $cur_value !== '' &&
							( $form_field->hasFieldArg( 'mapping template' ) ||
							$form_field->hasFieldArg( 'mapping property' ) ||
							$form_field->getUseDisplayTitle() ) ) {
							// If the input type is "tokens', the value is not
							// an array, but the delimiter still needs to be set.
							if ( !is_array( $cur_value ) ) {
								if ( $form_field->isList() ) {
									$delimiter = $form_field->getFieldArg( 'delimiter' );
								} else {
									$delimiter = null;
								}
							}
							$cur_value = $form_field->valueStringToLabels( $cur_value, $delimiter );
						}

						// Call hooks - unfortunately this has to be split into two
						// separate calls, because of the different variable names in
						// each case.
						// @TODO - should it be $cur_value for both cases? Or should the
						// hook perhaps modify both variables?
						if ( $form_submitted ) {
							MediaWikiServices::getInstance()->getHookContainer()->run(
								'PageForms::CreateFormField', [ &$form_field, &$cur_value_in_template, true ]
							);
						} else {
							$this->createFormFieldTranslateTag( $template, $tif, $form_field, $cur_value );
							MediaWikiServices::getInstance()->getHookContainer()->run(
								'PageForms::CreateFormField', [ &$form_field, &$cur_value, false ]
							);
						}
						// if this is not part of a 'multiple' template, increment the
						// global tab index (used for correct tabbing)
						if ( !$form_field->hasFieldArg( 'part_of_multiple' ) ) {
							$wgPageFormsTabIndex++;
						}
						// increment the global field number regardless
						$wgPageFormsFieldNum++;
						$this->counters->tabIndex = $wgPageFormsTabIndex;
						$this->counters->fieldNum = $wgPageFormsFieldNum;
						if ( $source_is_page && !$tif->allInstancesPrinted() ) {
							// If the source is a page, don't use the default
							// values - except for newly-added instances of a
							// multiple-instance template.
						} elseif ( $form_field->getDefaultValue() !== null ) {
							[ $cur_value, $cur_value_in_template ] = $this->fieldValueResolver->resolveDefaultValue(
								$form_field, (string)$cur_value, (string)$cur_value_in_template,
								(bool)$tif->allowsMultiple(), (bool)$form_submitted, $user
							);
						}

						// If all instances have been
						// printed, that means we're
						// now printing a "starter"
						// div - set the current value
						// to null, unless it's the
						// default value.
						// (Ideally it wouldn't get
						// set at all, but that seems a
						// little harder.)
						if ( $tif->allInstancesPrinted() && $form_field->getDefaultValue() == null ) {
							$cur_value = null;
						}

						$new_text = $this->formFieldHTML( $form_field, $cur_value );
						$new_text .= $form_field->additionalHTMLForInput(
							$cur_value, $field_name, $tif->getTemplateName()
						);

						if ( $new_text ) {
							$wiki_page->addTemplateParam(
								$template_name, $tif->getInstanceNum(), $field_name, $cur_value_in_template
							);
							$section = substr_replace(
								$section, $new_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc
							);
							$start_position = $brackets_loc + strlen( $new_text );
						} else {
							$start_position = $brackets_end_loc;
						}
					}

					if ( $tif->allowsMultiple() && !$tif->allInstancesPrinted() ) {
						$wordForYes = PFUtils::getWordForYesOrNo( true );
						if ( $form_field->getInputType() == 'checkbox' ) {
							if ( strtolower( $cur_value ) == strtolower( $wordForYes )
								|| strtolower( $cur_value ) == 'yes' || $cur_value == '1' ) {
								$cur_value = true;
							} else {
								$cur_value = false;
							}
						}
					}

					if ( $tif->getDisplay() != null
						&& ( !$tif->allowsMultiple() || !$tif->allInstancesPrinted() ) ) {
						$tif->addGridValue( $field_name, $cur_value );
					}

				// =====================================================
				// standard input processing
				// =====================================================
				} elseif ( $tag_title == 'standard input' ) {
					if ( count( $tag_components ) < 2 ) {
						throw new MWException(
							'<div class="error">Error in form definition:' .
							' \'standard input\' tag is missing the input name.</div>'
						);
					}
					$input_name = $tag_components[1];

					// if it's a query, ignore all standard inputs except run query
					if ( ( $is_query && $input_name != 'run query' )
						|| ( !$is_query && $input_name == 'run query' ) ) {
						$section = substr_replace(
							$section, "", $brackets_loc, $brackets_end_loc + 3 - $brackets_loc
						);
						continue;
					}
					// set a flag so that the standard 'form bottom' won't get displayed
					$this->standardInputsIncluded = true;

					$new_text = $this->standardInputHtmlBuilder->buildHtml(
						$input_name,
						$tag_components,
						$form_is_disabled,
						(bool)$form_submitted,
						$request,
						$parser,
						$this->mPageTitle,
						$page_name
					);
					$section = substr_replace(
						$section, $new_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc
					);
				// =====================================================
				// for section processing
				// =====================================================
				} elseif ( $tag_title == 'section' ) {
					$wgPageFormsFieldNum++;
					$wgPageFormsTabIndex++;
					$this->counters->fieldNum = $wgPageFormsFieldNum;
					$this->counters->tabIndex = $wgPageFormsTabIndex;

					$form_section_text = $this->formSectionHtmlBuilder->buildHtml(
						$tag_components,
						$section,
						$brackets_end_loc,
						$source_is_page,
						$existing_page_content,
						$request,
						$wiki_page,
						$form_is_disabled,
						$user,
						$this->counters
					);

					$section = substr_replace(
						$section, $form_section_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc
					);
				// =====================================================
				// page info processing
				// =====================================================
				} elseif ( $tag_title == 'info' ) {
					if ( $info_tag_seen ) {
						throw new MWException(
							'<div class="error">Error in form definition:'
							. ' only one \'info\' tag is allowed per form.</div>'
						);
					}
					$info_tag_seen = true;
					foreach ( array_slice( $tag_components, 1 ) as $component ) {
						$sub_components = array_map( 'trim', explode( '=', $component, 2 ) );
						// Tag names are case-insensitive
						$tag = strtolower( $sub_components[0] );
						if ( $tag == 'create title' || $tag == 'add title' ) {
							// Handle this only if
							// we're adding a page.
							if ( !$is_query && !$this->mPageTitle->exists() ) {
								$form_page_title = $sub_components[1];
							}
						} elseif ( $tag == 'edit title' ) {
							// Handle this only if
							// we're editing a page.
							if ( !$is_query && $this->mPageTitle->exists() ) {
								$form_page_title = $sub_components[1];
							}
						} elseif ( $tag == 'query title' ) {
							// Handle this only if
							// we're in 'RunQuery'.
							if ( $is_query ) {
								$form_page_title = $sub_components[1];
							}
						} elseif ( $tag == 'includeonly free text' || $tag == 'onlyinclude free text' ) {
							$wiki_page->makeFreeTextOnlyInclude();
						} elseif ( $tag == 'query form at top' ) {
							$this->runQueryFormAtTop = true;
						}
					}
					// Replace the {{{info}}} tag with a hidden span, instead of a blank, to avoid a
					// potential security issue.
					$section = substr_replace(
					$section, '<span style="visibility: hidden;"></span>',
					$brackets_loc, $brackets_end_loc + 3 - $brackets_loc
					);
				// =====================================================
				// default outer level processing
				// =====================================================
				} else {
					// Tag is not one of the allowed values -
					// ignore it, other than to HTML-escape it.
					$form_section_text = htmlspecialchars(
						substr( $section, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc )
					);
					$section = substr_replace(
						$section, $form_section_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc
					);
					$start_position = $brackets_end_loc;
				}
				// end if
			}
			// end while

			if ( $tif && ( !$tif->allowsMultiple() || $tif->allInstancesPrinted() ) ) {
				$template_text = $wiki_page->createTemplateCallsForTemplateName( $tif->getTemplateName(), $request );
				// Escape the '$' characters for the preg_replace() call.
				$template_text = str_replace( '$', '\$', $template_text );

				// If there is a placeholder in the text, we
				// know that we are doing a replace.
				if ( $existing_page_content && str_contains( $existing_page_content, '{{{insertionpoint}}}' ) ) {
					$existing_page_content = preg_replace( '/\{\{\{insertionpoint\}\}\}(\r?\n?)/',
						preg_replace( '/\}\}/m', '}�',
							preg_replace( '/\{\{/m', '�{', $template_text ) ) .
						"{{{insertionpoint}}}",
						$existing_page_content );
				}
			}

			$multipleTemplateHTML = '';
			if ( $tif ) {
				if ( $tif->getLabel() != null ) {
					$fieldsetStartHTML = "<fieldset>\n" . Html::element( 'legend', null, $tif->getLabel() ) . "\n";
					$fieldsetStartHTML .= $tif->getIntro();
					if ( !$tif->allowsMultiple() ) {
						$form_text .= $fieldsetStartHTML;
					} elseif ( $tif->allowsMultiple() && $tif->getInstanceNum() == 0 ) {
						$multipleTemplateHTML .= $fieldsetStartHTML;
					}
				} else {
					if ( !$tif->allowsMultiple() ) {
						$form_text .= $tif->getIntro();
					}
					if ( $tif->allowsMultiple() && $tif->getInstanceNum() == 0 ) {
						$multipleTemplateHTML .= $tif->getIntro();
					}
				}
			}
			if ( $tif && $tif->allowsMultiple() ) {
				if ( $tif->getDisplay() == 'spreadsheet' ) {
					if ( $tif->allInstancesPrinted() ) {
						$multipleTemplateHTML .= $this->spreadsheetHTML( $tif );
						// For spreadsheets, this needs
						// to be specially inserted.
						if ( $tif->getLabel() != null ) {
							$multipleTemplateHTML .= "</fieldset>\n";
						}
					}
				} elseif ( $tif->getDisplay() == 'calendar' ) {
					if ( $tif->allInstancesPrinted() ) {
						$multipleTemplateHTML .= $this->calendarHTML( $tif );
						$multipleTemplateHTML .= "</fieldset>\n";
					}
				} else {
					if ( $tif->getDisplay() == 'table' ) {
						$section = $this->tableHTML( $tif, $tif->getInstanceNum() );
					}
					if ( $tif->getInstanceNum() == 0 ) {
						$multipleTemplateHTML .= $this->multipleTemplateStartHTML( $tif );
					}
					if ( !$tif->allInstancesPrinted() ) {
						$multipleTemplateHTML .= $this->multipleTemplateInstanceHTML(
							$tif, $form_is_disabled, $section
						);
					} else {
						$multipleTemplateHTML .= $this->multipleTemplateEndHTML( $tif, $form_is_disabled, $section );
					}
				}
				$placeholder = $tif->getPlaceholder();
				if ( $placeholder == null ) {
					// The normal process.
					$form_text .= $multipleTemplateHTML;
				} else {
					// The template text won't be appended
					// at the end of the template like for
					// usual multiple template forms.
					// The HTML text will instead be stored in
					// the $multipleTemplateHTML variable,
					// and then added in the right
					// @insertHTML_".$placeHolderField."@"; position
					// Optimization: actually, instead of
					// separating the processes, the usual
					// multiple template forms could also be
					// handled this way if a fitting
					// placeholder tag was added.
					// We replace the HTML into the current
					// placeholder tag, but also add another
					// placeholder tag, to keep track of it.
					$multipleTemplateHTML .= self::makePlaceholderInFormHTML( $placeholder );
					$form_text = str_replace(
						self::makePlaceholderInFormHTML( $placeholder ), $multipleTemplateHTML, $form_text
					);
				}
				if ( !$tif->allInstancesPrinted() ) {
					// This will cause the section to be
					// re-parsed on the next go.
					$section_num--;
					$tif->incrementInstanceNum();
				}
			} elseif ( $tif && $tif->getDisplay() == 'table' ) {
				$form_text .= $this->tableHTML( $tif, 0 );
			} elseif ( $tif && !$tif->allowsMultiple() && $tif->getLabel() != null ) {
				$form_text .= $section . "\n</fieldset>";
			} else {
				$form_text .= $section;
			}
		}
		// end for

		// Cleanup - everything has been browsed.
		// Remove all the remaining placeholder
		// tags in the HTML and wiki-text.
		foreach ( $placeholderFields as $stringToReplace ) {
			// Remove the @<insertHTML>@ tags from the generated
			// HTML form.
			$form_text = str_replace( self::makePlaceholderInFormHTML( $stringToReplace ), '', $form_text );
		}

		// If it wasn't included in the form definition, add the
		// 'free text' input as a hidden field at the bottom.
		if ( !$free_text_was_included ) {
			$form_text .= Html::hidden( 'pf_free_text', '!free_text!' );
		}
		// Get free text, and add to page data, as well as retroactively
		// inserting it into the form.

		if ( $source_is_page ) {
			// If the page is the source, free_text will just be
			// whatever in the page hasn't already been inserted
			// into the form.
			$free_text = trim( $existing_page_content );
		// ...or get it from the form submission, if it's not called from #formredlink
		} elseif ( !$is_autocreate && $request->getCheck( 'pf_free_text' ) ) {
			$free_text = $request->getVal( 'pf_free_text' );
			if ( !$free_text_was_included ) {
				$wiki_page->addFreeTextSection();
			}
		} elseif ( $preloaded_free_text != null ) {
			$free_text = $preloaded_free_text;
		} else {
			$free_text = null;
		}

		if ( $wiki_page->freeTextOnlyInclude() ) {
			$free_text = str_replace( "<onlyinclude>", '', $free_text );
			$free_text = str_replace( "</onlyinclude>", '', $free_text );
			$free_text = trim( $free_text );
		}

		$page_text = '';

		MediaWikiServices::getInstance()->getHookContainer()->run( 'PageForms::BeforeFreeTextSubst',
			[ &$free_text, $existing_page_content, &$page_text ] );

		// Now that we have the free text, we can create the full page
		// text.
		// The page text needs to be created whether or not the form
		// was submitted, in case this is called from #formredlink.
		$wiki_page->setFreeText( $free_text );
		$page_text = $wiki_page->createPageText( $request );

		// Also substitute the free text into the form.
		$escaped_free_text = Sanitizer::safeEncodeAttribute( $free_text ?? '' );
		$form_text = str_replace( '!free_text!', $escaped_free_text, $form_text );

		// Add a warning in, if we're editing an existing page and that
		// page appears to not have been created with this form.
		if ( !$is_query && $page_name_formula === null &&
			$this->mPageTitle->exists() && $existing_page_content !== ''
			&& !$source_page_matches_this_form ) {
			$form_text = "\t" . '<div class="warningbox">' .
				// Prepend with a colon in case it's a file or category page.
				wfMessage( 'pf_formedit_formwarning', ':' . $page_name )->parse() .
				"</div>\n<br clear=\"both\" />\n" . $form_text;
		}

		// Add form bottom, if no custom "standard inputs" have been defined.
		if ( !$this->standardInputsIncluded ) {
			if ( $is_query ) {
				$form_text .= PFFormUtils::queryFormBottom();
			} else {
				$form_text .= PFFormUtils::formBottom( $form_submitted, $form_is_disabled );
			}
		}

		if ( !$is_query ) {
			$form_text .= Html::hidden( 'wpStarttime', wfTimestampNow() );
			// This variable is called $mwWikiPage and not
			// something simpler, to avoid confusion with the
			// variable $wiki_page, which is of type PFWikiPage.
			$mwWikiPage = PFUtils::newWikiPageFromTitle( $this->mPageTitle );
			$form_text .= Html::hidden( 'wpEdittime', $mwWikiPage->getTimestamp() );
			$form_text .= Html::hidden( 'editRevId', 0 );
			$form_text .= Html::hidden( 'wpEditToken', $user->getEditToken() );
			$form_text .= Html::hidden( 'wpUnicodeCheck', EditPage::UNICODE_CHECK );
			$form_text .= Html::hidden( 'wpUltimateParam', true );
		}

		$form_text .= "\t</form>\n";
		$parser->replaceLinkHolders( $form_text );
		MediaWikiServices::getInstance()->getHookContainer()->run( 'PageForms::RenderingEnd', [ &$form_text ] );

		// Capture the internal parser's output so callers can forward
		// ResourceLoader modules (and other metadata) registered by parser
		// tag hooks (e.g. <headertabs />) to the real OutputPage via
		// addParserOutputMetadata(). This must be done by the caller because
		// formHTML() has no handle on the caller's OutputPage instance.
		$parserOutput = $parser->getOutput();
		// Restore modules that were registered during form-definition parsing
		// but cleared by PFFormField::clearState() during field rendering.
		if ( $formDefParserModules ) {
			$parserOutput->addModules( $formDefParserModules );
		}
		if ( $formDefParserModuleStyles ) {
			$parserOutput->addModuleStyles( $formDefParserModuleStyles );
		}

		// Send the autocomplete values to the browser, along with the
		// mappings of which values should apply to which fields.
		// If doing a replace, the page text is actually the modified
		// original page.
		if ( !$is_embedded ) {
			$form_page_title = $parser->recursiveTagParse( str_replace( "{{!}}", "|", $form_page_title ?? '' ) );
		} else {
			$form_page_title = null;
		}

		return [
			$form_text, $page_text, $form_page_title, $generated_page_name, $parserOutput, $this->runQueryFormAtTop
		];
	}

	/**
	 * Create the HTML to display this field within a form.
	 */
	public function formFieldHTML( PFFormField $form_field, ?string $cur_value ): string {
		return $this->formFieldHtmlBuilder->formFieldHTML( $form_field, $cur_value, $this->counters );
	}

	private function createFormFieldTranslateTag(
		&$template, &$tif, PFFormField &$form_field, ?string &$cur_value
	): void {
		$this->formFieldHtmlBuilder->createFormFieldTranslateTag( $template, $tif, $form_field, $cur_value );
	}

}
