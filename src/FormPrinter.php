<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use EditPage;
use FatalError;
use Html;
use LogEventsList;
use MediaWiki\Extension\PageForms\FormDefinition\EndTemplateSpec;
use MediaWiki\Extension\PageForms\FormDefinition\FieldSpec;
use MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionReader;
use MediaWiki\Extension\PageForms\FormDefinition\FormElement;
use MediaWiki\Extension\PageForms\FormDefinition\InfoSpec;
use MediaWiki\Extension\PageForms\FormDefinition\SectionSpec;
use MediaWiki\Extension\PageForms\FormDefinition\StandardInputSpec;
use MediaWiki\Extension\PageForms\FormDefinition\TagSpec;
use MediaWiki\Extension\PageForms\FormDefinition\TemplateSpec;
use MediaWiki\Extension\PageForms\FormDefinition\TextSpec;
use MediaWiki\Extension\PageForms\FormDefinition\UnknownTagSpec;
use MediaWiki\Extension\PageForms\FormRender\ElementHandler;
use MediaWiki\Extension\PageForms\FormRender\EndTemplateHandler;
use MediaWiki\Extension\PageForms\FormRender\FieldHandler;
use MediaWiki\Extension\PageForms\FormRender\InfoHandler;
use MediaWiki\Extension\PageForms\FormRender\SectionHandler;
use MediaWiki\Extension\PageForms\FormRender\StandardInputHandler;
use MediaWiki\Extension\PageForms\FormRender\TemplateHandler;
use MediaWiki\Extension\PageForms\FormRender\TextHandler;
use MediaWiki\Extension\PageForms\FormRender\UnknownTagHandler;
use MediaWiki\MediaWikiServices;
use MWException;
use OutputPage;
use Parser;
use ParserOptions;
use PFUtils;
use RequestContext;
use Sanitizer;
use Title;
use User;
use WebRequest;

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
class FormPrinter {

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
	/** Owned by InputTypeRegistry; FormPrinter delegates to it for all input-type lookups. */
	private InputTypeRegistry $inputTypeRegistry;

	private CalendarHtmlBuilder $calendarHtmlBuilder;

	private SpreadsheetHtmlBuilder $spreadsheetHtmlBuilder;

	private MultipleTemplateHtmlBuilder $multipleTemplateHtmlBuilder;

	private FormFieldHtmlBuilder $formFieldHtmlBuilder;

	private FormDefParser $formDefParser;

	private FormDefinitionReader $formDefReader;

	private FieldValueResolver $fieldValueResolver;

	/** @var array<class-string, ElementHandler> The handler of each type of form definition element */
	private array $elementHandlers;

	private MappingLabels $mappingLabels;

	public function __construct() {
		global $wgPageFormsDisableOutsideServices;
		$this->mappingLabels = new MappingLabels();
		// Initialize variables.
		$this->mSemanticTypeHooks = [];
		$this->mInputTypeHooks = [];
		$this->inputTypeRegistry = new InputTypeRegistry();
		$this->calendarHtmlBuilder = new CalendarHtmlBuilder();
		$this->multipleTemplateHtmlBuilder = new MultipleTemplateHtmlBuilder();
		$this->spreadsheetHtmlBuilder = new SpreadsheetHtmlBuilder();
		$this->formDefReader = new FormDefinitionReader();
		$this->fieldValueResolver = new FieldValueResolver();

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
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullableInternal SM_VERSION guarded by defined()
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
		$this->formDefParser = new FormDefParser(
			MediaWikiServices::getInstance()->getParserFactory(), $this->formDefReader
		);
		$this->elementHandlers = [
			FieldSpec::class => new FieldHandler(
				$this->formFieldHtmlBuilder, $this->mappingLabels, $this->fieldValueResolver
			),
			TextSpec::class => new TextHandler(),
			UnknownTagSpec::class => new UnknownTagHandler(),
			TemplateSpec::class => new TemplateHandler(),
			EndTemplateSpec::class => new EndTemplateHandler(),
			InfoSpec::class => new InfoHandler(),
			StandardInputSpec::class => new StandardInputHandler(),
			SectionSpec::class => new SectionHandler(),
		];
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
	 * @param Title|null $title
	 * @return bool
	 */
	public function showDeletionLog( $out, ?Title $title = null ) {
		if ( $title === null ) {
			return false;
		}
		LogEventsList::showLogExtract( $out, 'delete', $title->getPrefixedText(),
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
	 * @param TemplateInForm $template_in_form
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
	 * @param TemplateInForm $template_in_form
	 * @param bool $form_is_disabled
	 * @param string $section
	 * @param FormCounters|null $counters
	 * @return string
	 */
	public function multipleTemplateEndHTML(
		$template_in_form, $form_is_disabled, $section, ?FormCounters $counters = null
	) {
		return $this->multipleTemplateHtmlBuilder->multipleTemplateEndHTML(
			$template_in_form, $form_is_disabled, $section, $counters
		);
	}

	public function tableHTML( $tif, $instanceNum, Parser $parser, ?FormCounters $counters = null ) {
		return $this->spreadsheetHtmlBuilder->tableHTML(
			$tif, $instanceNum,
			fn ( $formField, $curValue ) => $this->formFieldHTML( $formField, $curValue, $parser, $counters ),
			$counters
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
	 * TemplateInForm::setFieldValuesFromPage() for each template found in
	 * the page, and returns the collected values as a nested array.
	 *
	 * The returned keys use the same underscore-normalisation as
	 * HtmlFormDataExtractor::extract(), so the result can be merged directly
	 * into PFAutoeditAPI::$mOptions without further transformation.
	 *
	 * Every instance of a multiple-instance template (one with the `multiple` attribute) is
	 * read and keyed "0a", "1a", ... as HtmlFormDataExtractor::extract() names them. For all
	 * other templates only the first occurrence on the page is read.
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
	 * Read the values the form's fields have on an existing page, apart from the form's structure.
	 *
	 * @param string $form_def Form definition wikitext.
	 * @param string $existing_page_content Wikitext of the page being edited.
	 * @param int|null $form_id Page ID of the form page (used by FormCache).
	 * @return FormValues
	 */
	public function readPageValues(
		string $form_def, string $existing_page_content, ?int $form_id = null
	): FormValues {
		return $this->formDefParser->readPageValues( $form_def, $existing_page_content, $form_id );
	}

	/**
	 * The inputs of the form that $user may not edit because they are marked "restricted".
	 *
	 * @param string $form_def Form definition wikitext.
	 * @param int|null $form_id Page ID of the form page (used by FormCache).
	 * @param User $user
	 * @return RestrictedInputs
	 */
	public function getRestrictedInputs( string $form_def, ?int $form_id, User $user ): RestrictedInputs {
		return $this->formDefParser->getRestrictedInputs( $form_def, $form_id, $user );
	}

	/**
	 * Resolve $context->pageTitle (needed for permission testing even when the real
	 * page name isn't known yet) and compute the edit-permission errors for formHTML().
	 *
	 * Also shows the page's previous deletion log, as a side effect, matching the
	 * original inline behavior in formHTML().
	 *
	 * @param bool $is_embedded
	 * @param bool $is_query
	 * @param string|null $page_name
	 * @param string|null $page_name_formula
	 * @param WebRequest $request
	 * @param User $user
	 * @param bool $form_submitted
	 * @param FormRenderContext $context
	 * @return array [ array $permissionErrors, bool $userCanEditPage ]
	 */
	private function resolvePageTitleAndPermissions(
		$is_embedded, $is_query, $page_name, $page_name_formula, $request, $user, $form_submitted,
		FormRenderContext $context
	): array {
		// Disable all form elements if user doesn't have edit
		// permission - two different checks are needed, because
		// editing permissions can be set in different ways.
		// HACK - sometimes we don't know the page name in advance, but
		// we still need to set a title here for testing permissions.
		if ( $is_embedded || $is_query ) {
			// If this is an embedded form (probably a 'RunQuery') or we're in Special:RunQuery,
			// just use the name of the actual page we're on.
			$titleGlobal = RequestContext::getMain()->getTitle();
			$context->pageTitle = $titleGlobal;
		} elseif ( $page_name === '' || $page_name === null ) {
			$context->pageTitle = Title::newFromText(
				$request->getVal( 'namespace' ) . ":Page Forms permissions test" );
		} else {
			// $page_name may not be a syntactically valid title (e.g. it was
			// generated from a page name formula, or came from an untrusted
			// request value); fall back to the same placeholder title used
			// above for permission-testing purposes rather than leaving
			// $context->pageTitle null, which fatals in getPermissionErrors()
			// and other unguarded uses below.
			$context->pageTitle = Title::newFromText( $page_name ) ?? Title::newFromText(
				$request->getVal( 'namespace' ) . ":Page Forms permissions test" );
		}

		global $wgOut;
		// Show previous set of deletions for this page, if it's been
		// deleted before.
		if ( !$form_submitted &&
			( $context->pageTitle && !$context->pageTitle->exists() &&
			$page_name_formula === null )
		) {
			$this->showDeletionLog( $wgOut, $context->pageTitle );
		}

		$permissionErrors = [];
		$userCanEditPage = true;
		// Unfortunately, we can't just call userCan() or its
		// equivalent here because it seems to ignore the setting
		// "$wgEmailConfirmToEdit = true;". Instead, we'll just get the
		// permission errors from the start, and use those to determine
		// whether the page is editable.
		if ( !$is_query ) {
			$permissionErrors = MediaWikiServices::getInstance()->getPermissionManager()
					->getPermissionErrors( 'edit', $user, $context->pageTitle );
			if ( MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly() ) {
				$permissionErrors = [ [ 'readonlytext',
					[ MediaWikiServices::getInstance()->getReadOnlyMode()->getReason() ] ] ];
			}
			$userCanEditPage = count( $permissionErrors ) == 0;
			MediaWikiServices::getInstance()->getHookContainer()->run(
				'PageForms::UserCanEditPage', [ $context->pageTitle, &$userCanEditPage ]
			);
		}

		return [ $permissionErrors, $userCanEditPage ];
	}

	/**
	 * Finish assembling $form_text and $page_text after the per-section tag-dispatch
	 * loop in formHTML() completes: resolve free text, substitute it into both the
	 * form and the page text, add the warning/form-bottom/hidden-fields boilerplate,
	 * and finalize the ParserOutput to return to the caller.
	 *
	 * @param FormRenderContext $context
	 * @return array [ string $form_text, string $page_text, string|null $form_page_title, ParserOutput $parserOutput ]
	 */
	private function finalizeFormAndPageText( FormRenderContext $context ): array {
		$form_text = $context->formText;
		$existing_page_content = $context->existingPageContent;
		$request = $context->request;
		$wiki_page = $context->wikiPage;
		$user = $context->user;
		$parser = $context->parser;
		$form_page_title = $context->formPageTitle;

		// Cleanup - everything has been browsed.
		// Remove all the remaining placeholder
		// tags in the HTML and wiki-text.
		foreach ( $context->placeholderFields as $stringToReplace ) {
			// Remove the @<insertHTML>@ tags from the generated
			// HTML form.
			$form_text = str_replace( self::makePlaceholderInFormHTML( $stringToReplace ), '', $form_text );
		}

		// If it wasn't included in the form definition, add the
		// 'free text' input as a hidden field at the bottom.
		if ( !$context->freeTextWasIncluded ) {
			$form_text .= Html::hidden( 'pf_free_text', '!free_text!' );
		}
		// Get free text, and add to page data, as well as retroactively
		// inserting it into the form.

		if ( $context->sourceIsPage ) {
			// If the page is the source, free_text will just be
			// whatever in the page hasn't already been inserted
			// into the form.
			$free_text = trim( $existing_page_content );
		// ...or get it from the form submission, if it's not called from #formredlink
		} elseif ( !$context->isAutocreate && $request->getCheck( 'pf_free_text' ) ) {
			$free_text = $request->getVal( 'pf_free_text' );
			if ( !$context->freeTextWasIncluded ) {
				$wiki_page->addFreeTextSection();
			}
		} elseif ( $context->preloadedFreeText != null ) {
			$free_text = $context->preloadedFreeText;
		} else {
			$free_text = null;
		}

		if ( $free_text !== null && $wiki_page->freeTextOnlyInclude() ) {
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
		if ( !$context->isQuery && $context->pageNameFormula === null &&
			$context->pageTitle->exists() && $existing_page_content !== ''
			&& !$context->sourcePageMatchesThisForm ) {
			$form_text = "\t" . '<div class="warningbox">' .
				// Prepend with a colon in case it's a file or category page.
				wfMessage( 'pf_formedit_formwarning', ':' . $context->pageName )->parse() .
				"</div>\n<br clear=\"both\" />\n" . $form_text;
		}

		// Add form bottom, if no custom "standard inputs" have been defined.
		if ( !$context->standardInputsIncluded ) {
			if ( $context->isQuery ) {
				$form_text .= FormUtils::queryFormBottom();
			} else {
				$form_text .= FormUtils::formBottom( $context->formSubmitted, $context->formIsDisabled );
			}
		}

		if ( !$context->isQuery ) {
			$form_text .= Html::hidden( 'wpStarttime', wfTimestampNow() );
			// This variable is called $mwWikiPage and not
			// something simpler, to avoid confusion with the
			// variable $wiki_page, which is of type PFWikiPage.
			$mwWikiPage = PFUtils::newWikiPageFromTitle( $context->pageTitle );
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
		// but cleared by FormField::clearState() during field rendering.
		if ( $context->formDefParserModules ) {
			$parserOutput->addModules( $context->formDefParserModules );
		}
		if ( $context->formDefParserModuleStyles ) {
			$parserOutput->addModuleStyles( $context->formDefParserModuleStyles );
		}

		// Send the autocomplete values to the browser, along with the
		// mappings of which values should apply to which fields.
		// If doing a replace, the page text is actually the modified
		// original page.
		if ( !$context->isEmbedded ) {
			$form_page_title = $parser->recursiveTagParse( str_replace( "{{!}}", "|", $form_page_title ?? '' ) );
		} else {
			$form_page_title = null;
		}

		return [ $form_text, $page_text, $form_page_title, $parserOutput ];
	}

	/**
	 * Angled brackets in a tag could cause a security leak (and should not be necessary).
	 *
	 * @param TagSpec $tag
	 * @throws MWException if a component of the tag contains both < and >
	 */
	private function assertNoForbiddenCharacters( TagSpec $tag ): void {
		foreach ( $tag->getComponents() as $tag_component ) {
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

	/**
	 * Replace the 'free text' standard input with a field declaration
	 * to get it to be handled as a field (a hack).
	 *
	 * @param FormElement $element
	 * @return FormElement
	 */
	private function freeTextAsField( FormElement $element ): FormElement {
		if ( $element instanceof StandardInputSpec && $element->isFreeText() ) {
			$args = array_slice( $element->getComponents(), 2 );
			return new FieldSpec( array_merge( [ 'field', '#freetext#' ], $args ) );
		}
		return $element;
	}

	/**
	 * Create a fresh Parser instance for use by formHTML(), titled at $context->pageTitle.
	 *
	 * @param User $user
	 * @param FormRenderContext $context
	 * @return Parser
	 */
	private function createFreshParser( $user, FormRenderContext $context ) {
		// getFreshParser() was removed in MW 1.43; use the factory on newer versions.
		$globalParser = PFUtils::getParser();
		if ( method_exists( $globalParser, 'getFreshParser' ) ) {
			// MW < 1.43: reset the global parser instance in-place
			// @phan-suppress-next-line PhanUndeclaredMethod -- getFreshParser() removed in MW 1.43, guarded above
			$parser = $globalParser->getFreshParser();
			if ( !$parser->getOptions() ) {
				$parser->setOptions( ParserOptions::newFromUser( $user ) );
			}
		} else {
			// MW 1.43+: create a fresh parser via the factory
			$parser = MediaWikiServices::getInstance()->getParserFactory()->create();
			$parser->setOptions( ParserOptions::newFromUser( $user ) );
		}
		$parser->setTitle( $context->pageTitle );
		// This is needed in order to make sure $parser->mLinkHolders
		// is set.
		$parser->clearState();
		return $parser;
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
	 * @param int|null $form_id
	 * @param string|null $existing_page_content
	 * @param string|null $page_name
	 * @param string|null $page_name_formula
	 * @param bool $is_query
	 * @param bool $is_embedded
	 * @param bool $is_autocreate true when called by #formredlink with "create page"
	 * @param array $autocreate_query query parameters from #formredlink
	 * @param User|null $user
	 * @param WebRequest|null $request
	 * @return FormRenderResult
	 * @throws FatalError
	 * @throws MWException
	 */
	public function render(
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
	): FormRenderResult {
		$context = new FormRenderContext();
		FormCounters::begin( $context->counters );
		try {
			return $this->renderInContext(
				$form_def, $form_submitted, $source_is_page, $form_id, $existing_page_content, $page_name,
				$page_name_formula, $is_query, $is_embedded, $is_autocreate, $autocreate_query, $user, $request,
				$context
			);
		} finally {
			$context->counters->mirrorToGlobals();
			FormCounters::end();
		}
	}

	/**
	 * The body of render(), run with the counters of $context as the current ones.
	 *
	 * @param string $form_def
	 * @param bool $form_submitted
	 * @param bool $source_is_page
	 * @param int|null $form_id
	 * @param string|null $existing_page_content
	 * @param string|null $page_name
	 * @param string|null $page_name_formula
	 * @param bool $is_query
	 * @param bool $is_embedded
	 * @param bool $is_autocreate
	 * @param array $autocreate_query
	 * @param User|null $user
	 * @param WebRequest|null $request
	 * @param FormRenderContext $context
	 * @return FormRenderResult
	 */
	private function renderInContext(
		$form_def,
		$form_submitted,
		$source_is_page,
		$form_id,
		$existing_page_content,
		$page_name,
		$page_name_formula,
		$is_query,
		$is_embedded,
		$is_autocreate,
		$autocreate_query,
		$user,
		$request,
		FormRenderContext $context
	): FormRenderResult {
		global $wgPageFormsShowExpandAllLink;
		global $wgOut;

		$context->formSubmitted = (bool)$form_submitted;
		$context->sourceIsPage = (bool)$source_is_page;
		$context->isQuery = (bool)$is_query;
		$context->isEmbedded = (bool)$is_embedded;
		$context->isAutocreate = (bool)$is_autocreate;
		$context->autocreateQuery = $autocreate_query;
		$context->pageName = $page_name;
		$context->pageNameFormula = $page_name_formula;
		$context->formId = $form_id !== null ? (int)$form_id : null;
		$context->existingPageContent = $existing_page_content;
		$context->generatedPageName = $page_name_formula;
		$context->request = $request ?? RequestContext::getMain()->getRequest();
		$context->user = $user ?? RequestContext::getMain()->getUser();

		// Disable all form elements if user doesn't have edit permission.
		// Also resolves $context->pageTitle as a side effect (needed below).
		[ $permissionErrors, $userCanEditPage ] = $this->resolvePageTitleAndPermissions(
			$context->isEmbedded, $context->isQuery, $context->pageName, $context->pageNameFormula,
			$context->request, $context->user, $context->formSubmitted, $context
		);

		// Start off with a loading spinner - this will be removed by
		// the JavaScript once everything has finished loading.
		$context->formText = FormUtils::displayLoadingImage();
		if ( $context->isQuery || $userCanEditPage ) {
			$context->formIsDisabled = false;
			// Show "Your IP address will be recorded" warning if
			// user is anonymous, and it's not a query.
			if ( $context->user->isAnon() && !$context->isQuery ) {
				// Based on code in MediaWiki's EditPage.php.
				$anonEditWarning = wfMessage( 'anoneditwarning',
					// Log-in link
					'{{fullurl:Special:UserLogin|returnto={{FULLPAGENAMEE}}}}',
					// Sign-up link
					'{{fullurl:Special:UserLogin/signup|returnto={{FULLPAGENAMEE}}}}' )->parse();
				$context->formText .= Html::rawElement(
					'div', [ 'id' => 'mw-anon-edit-warning', 'class' => 'warningbox' ], $anonEditWarning
				);
			}
		} else {
			$context->formIsDisabled = true;
			if ( $wgOut->getTitle() != null ) {
				$wgOut->setPageTitle( wfMessage( 'badaccess' )->text() );
				$wgOut->addWikiTextAsInterface( $wgOut->formatPermissionsErrorMessage( $permissionErrors, 'edit' ) );
				$wgOut->addHTML( "\n<hr />\n" );
			}
		}

		if ( $wgPageFormsShowExpandAllLink ) {
			$context->formText .= Html::rawElement( 'p', [ 'id' => 'pf-expand-all' ],
				// @TODO - add an i18n message for this.
				Html::element( 'a', [ 'href' => '#' ], 'Expand all collapsed parts of the form' ) ) . "\n";
		}

		$context->parser = $this->createFreshParser( $context->user, $context );

		$form_definition = FormCache::getFormDefinitionModel( $context->parser, $form_def, $context->formId );
		// Snapshot RL modules registered by parser tag hooks during form-definition
		// parsing. FormField calls $context->parser->clearState() during field rendering,
		// which resets $context->parser->mOutput and discards these modules. We save them
		// here and merge them back into the final ParserOutput before returning.
		$context->formDefParserModules = $context->parser->getOutput()->getModules();
		$context->formDefParserModuleStyles = $context->parser->getOutput()->getModuleStyles();

		$context->freeTextWasIncluded = false;
		$context->preloadedFreeText = null;
		$form_def_sections = $this->formDefParser->splitIntoSections( $form_definition );

		// Cycle through the form definition file, and possibly an
		// existing article as well, finding template and field
		// declarations and replacing them with form elements, either
		// blank or pre-populated, as appropriate.
		$new_text = '';
		$context->template = null;
		$context->tif = null;
		// This array will keep track of all the replaced @<name>@ strings
		$context->placeholderFields = [];
		$context->infoTagSeen = false;

		for ( $section_num = 0; $section_num < count( $form_def_sections ); $section_num++ ) {
			// The section's text and tags, in order. The HTML for the section is
			// assembled from them in $context->section.
			$section_elements = $context->sectionElements = array_map(
				[ $this, 'freeTextAsField' ], $form_def_sections[$section_num]
			);
			$context->section = ' ';

			foreach ( $section_elements as $element_num => $element ) {
				$context->elementNum = $element_num;
				if ( $element instanceof TagSpec && !$element instanceof InfoSpec ) {
					$this->assertNoForbiddenCharacters( $element );
				}
				$handler = $this->elementHandlers[get_class( $element )] ?? null;
				if ( $handler !== null ) {
					$handler->handle( $element, $context );
					continue;
				}
			}
			// end foreach

			$tif = $context->tif;
			if ( $tif && ( !$tif->allowsMultiple() || $tif->allInstancesPrinted() ) ) {
				$template_text = $context->wikiPage->createTemplateCallsForTemplateName(
					$tif->getTemplateName(), $context->request
				);
				// Escape the '$' characters for the preg_replace() call.
				$template_text = str_replace( '$', '\$', $template_text );

				// If there is a placeholder in the text, we
				// know that we are doing a replace.
				if ( $context->existingPageContent
					&& str_contains( $context->existingPageContent, '{{{insertionpoint}}}' ) ) {
					$context->existingPageContent = preg_replace( '/\{\{\{insertionpoint\}\}\}(\r?\n?)/',
						preg_replace( '/\}\}/m', '}�',
							preg_replace( '/\{\{/m', '�{', $template_text ) ) .
						"{{{insertionpoint}}}",
						$context->existingPageContent );
				}
			}

			if ( $context->sourceIsPage && $tif && $tif->allowsMultiple()
				&& !$tif->allInstancesPrinted() ) {
				// The parameters of this instance's template call that the form does not define,
				// as hidden inputs of the instance. (The "end template" tag is only handled once
				// for all instances, so it cannot do this.)
				$context->section .= FormUtils::unhandledFieldsHTML( $tif );
			}

			$multipleTemplateHTML = '';
			if ( $tif ) {
				if ( $tif->getLabel() != null ) {
					$fieldsetStartHTML = "<fieldset>\n"
						. Html::element( 'legend', [], $tif->getLabel() ) . "\n";
					$fieldsetStartHTML .= $tif->getIntro();
					if ( !$tif->allowsMultiple() ) {
						$context->formText .= $fieldsetStartHTML;
					} elseif ( $tif->allowsMultiple() && $tif->getInstanceNum() == 0 ) {
						$multipleTemplateHTML .= $fieldsetStartHTML;
					}
				} else {
					if ( !$tif->allowsMultiple() ) {
						$context->formText .= $tif->getIntro();
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
						$context->section = $this->tableHTML(
							$tif, $tif->getInstanceNum(), $context->parser, $context->counters
						);
					}
					if ( $tif->getInstanceNum() == 0 ) {
						$multipleTemplateHTML .= $this->multipleTemplateStartHTML( $tif );
					}
					if ( !$tif->allInstancesPrinted() ) {
						$multipleTemplateHTML .= $this->multipleTemplateInstanceHTML(
							$tif, $context->formIsDisabled, $context->section
						);
					} else {
						$multipleTemplateHTML .= $this->multipleTemplateEndHTML(
							$tif, $context->formIsDisabled, $context->section, $context->counters
						);
					}
				}
				$placeholder = $tif->getPlaceholder();
				if ( $placeholder == null ) {
					// The normal process.
					$context->formText .= $multipleTemplateHTML;
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
					$context->formText = str_replace(
						self::makePlaceholderInFormHTML( $placeholder ), $multipleTemplateHTML, $context->formText
					);
				}
				if ( !$tif->allInstancesPrinted() ) {
					// This will cause the section to be
					// re-parsed on the next go.
					$section_num--;
					$tif->incrementInstanceNum();
				}
			} elseif ( $tif && $tif->getDisplay() == 'table' ) {
				$context->formText .= $this->tableHTML( $tif, 0, $context->parser, $context->counters );
			} elseif ( $tif && !$tif->allowsMultiple() && $tif->getLabel() != null ) {
				$context->formText .= $context->section . "\n</fieldset>";
			} else {
				$context->formText .= $context->section;
			}
		}
		// end for

		[ $context->formText, $page_text, $context->formPageTitle, $parserOutput ] =
			$this->finalizeFormAndPageText( $context );

		return new FormRenderResult(
			$context->formText, $page_text, $context->formPageTitle, $context->generatedPageName, $parserOutput,
			$context->runQueryFormAtTop
		);
	}

	/**
	 * Same as render(), with the result as a list.
	 *
	 * @deprecated use render(), which returns a named result.
	 * @param string $form_def
	 * @param bool $form_submitted
	 * @param bool $source_is_page
	 * @param int|null $form_id
	 * @param string|null $existing_page_content
	 * @param string|null $page_name
	 * @param string|null $page_name_formula
	 * @param bool $is_query
	 * @param bool $is_embedded
	 * @param bool $is_autocreate
	 * @param array $autocreate_query
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
		return $this->render(
			$form_def, $form_submitted, $source_is_page, $form_id, $existing_page_content, $page_name,
			$page_name_formula, $is_query, $is_embedded, $is_autocreate, $autocreate_query, $user, $request
		)->toArray();
	}

	/**
	 * Create the HTML to display this field within a form.
	 */
	public function formFieldHTML(
		FormField $form_field, ?string $cur_value, Parser $parser, ?FormCounters $counters = null
	): string {
		return $this->formFieldHtmlBuilder->formFieldHTML( $form_field, $cur_value, $parser, $counters );
	}

}
