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
use MediaWiki\Extension\PageForms\FormDefinition\TemplateSpec;
use MediaWiki\Extension\PageForms\FormDefinition\TextSpec;
use MediaWiki\Extension\PageForms\FormDefinition\UnknownTagSpec;
use MediaWiki\Extension\PageForms\FormRender\ElementHandler;
use MediaWiki\Extension\PageForms\FormRender\ElementHandlerException;
use MediaWiki\Extension\PageForms\FormRender\EndTemplateHandler;
use MediaWiki\Extension\PageForms\FormRender\FieldHandler;
use MediaWiki\Extension\PageForms\FormRender\InfoHandler;
use MediaWiki\Extension\PageForms\FormRender\PageTextAssembler;
use MediaWiki\Extension\PageForms\FormRender\SectionHandler;
use MediaWiki\Extension\PageForms\FormRender\SectionLayout;
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

	/** Owned by InputTypeRegistry; FormPrinter delegates to it for all input-type lookups. */
	private InputTypeRegistry $inputTypeRegistry;

	private CalendarHtmlBuilder $calendarHtmlBuilder;

	private SpreadsheetHtmlBuilder $spreadsheetHtmlBuilder;

	private MultipleTemplateHtmlBuilder $multipleTemplateHtmlBuilder;

	private FormFieldHtmlBuilder $formFieldHtmlBuilder;

	private FormDefParser $formDefParser;

	private FormDefinitionReader $formDefReader;

	private FieldValueResolver $fieldValueResolver;

	private SectionLayout $sectionLayout;

	private PageTextAssembler $pageTextAssembler;

	/** @var array<class-string, ElementHandler> The handler of each type of form definition element */
	private array $elementHandlers;

	private MappingLabels $mappingLabels;

	public function __construct() {
		$this->mappingLabels = new MappingLabels();
		$this->inputTypeRegistry = InputTypeRegistry::newWithBuiltInTypes();
		$this->calendarHtmlBuilder = new CalendarHtmlBuilder();
		$this->multipleTemplateHtmlBuilder = new MultipleTemplateHtmlBuilder();
		$this->spreadsheetHtmlBuilder = new SpreadsheetHtmlBuilder();
		$this->formDefReader = new FormDefinitionReader();
		$this->fieldValueResolver = new FieldValueResolver();

		// All-purpose setup hook.
		// Avoid PHP 7.1 warning from passing $this by reference.
		$formPrinterRef = $this;
		MediaWikiServices::getInstance()->getHookContainer()->run(
			'PageForms::FormPrinterSetup', [ &$formPrinterRef ]
		);

		$this->formFieldHtmlBuilder = new FormFieldHtmlBuilder( $this->inputTypeRegistry );
		$this->formDefParser = new FormDefParser(
			MediaWikiServices::getInstance()->getParserFactory(), $this->formDefReader
		);
		$this->sectionLayout = new SectionLayout(
			$this->multipleTemplateHtmlBuilder, $this->spreadsheetHtmlBuilder, $this->calendarHtmlBuilder,
			$this->formFieldHtmlBuilder
		);
		$this->pageTextAssembler = new PageTextAssembler();
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

	/**
	 * The deprecated properties $mInputTypeHooks and $mSemanticTypeHooks, which read and write the
	 * input type registry.
	 *
	 * @deprecated use registerInputType()
	 * @param string $name
	 * @return DeprecatedHookTable|null
	 */
	public function __get( $name ) {
		$table = $this->deprecatedHookTable( $name );
		if ( $table === null ) {
			trigger_error( "Undefined property: " . static::class . "::\$$name", E_USER_NOTICE );
			return null;
		}
		return $table;
	}

	/**
	 * @deprecated use registerInputType()
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set( $name, $value ) {
		$table = $this->deprecatedHookTable( $name );
		if ( $table === null ) {
			$this->$name = $value;
			return;
		}
		$table->replaceWith( $value instanceof DeprecatedHookTable ? $value->toArray() : (array)$value );
	}

	public function __isset( $name ) {
		return $this->deprecatedHookTable( $name ) !== null;
	}

	private function deprecatedHookTable( string $name ): ?DeprecatedHookTable {
		if ( $name !== 'mInputTypeHooks' && $name !== 'mSemanticTypeHooks' ) {
			return null;
		}
		wfDeprecated( __CLASS__ . "::\$$name", '2.3.0' );
		return new DeprecatedHookTable( $this->inputTypeRegistry, $name === 'mSemanticTypeHooks' );
	}

	/**
	 * @deprecated use registerInputType(), which fills all lookup tables from the input class.
	 * @param string $type
	 * @param bool $is_list
	 * @param string $class_name
	 * @param array $default_args
	 */
	public function setSemanticTypeHook( $type, $is_list, $class_name, $default_args ) {
		$this->inputTypeRegistry->setSemanticTypeHook( $type, (bool)$is_list, $class_name, $default_args );
	}

	/**
	 * @deprecated use registerInputType(), which fills all lookup tables from the input class.
	 * @param string $input_type
	 * @param string $class_name
	 * @param array $default_args
	 */
	public function setInputTypeHook( $input_type, $class_name, $default_args ) {
		$this->inputTypeRegistry->setInputTypeHook( $input_type, $class_name, $default_args );
	}

	/**
	 * Register all information about the passed-in form input class.
	 *
	 * @param string $inputTypeClass The full qualified class name representing the new input.
	 * Must be derived from PFFormInput.
	 */
	public function registerInputType( $inputTypeClass ) {
		$this->inputTypeRegistry->register( $inputTypeClass );
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
	 * @deprecated use FormPlaceholder::format()
	 * @param string $templateName
	 * @param string $fieldName
	 * @return string
	 */
	public static function placeholderFormat( $templateName, $fieldName ) {
		return FormPlaceholder::format( $templateName, $fieldName );
	}

	/**
	 * @deprecated use FormPlaceholder::toHtmlMarker()
	 * @param string $str
	 * @return string
	 */
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
	 * HtmlFormDataExtractor::addToArray(), so the result can be merged directly
	 * into PFAutoeditAPI::$mOptions without further transformation.
	 *
	 * Every instance of a multiple-instance template (one with the `multiple` attribute) is
	 * read and keyed "0a", "1a", ... as HtmlFormDataExtractor::addToArray() names them. For all
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
	 * Resolve the title of the page (needed for permission testing even when the real
	 * page name isn't known yet) and compute the edit-permission errors for formHTML().
	 *
	 * Also shows the page's previous deletion log, as a side effect, matching the
	 * original inline behavior in formHTML().
	 *
	 * @param FormRenderRequest $request
	 * @return array [ Title $pageTitle, array $permissionErrors, bool $userCanEditPage ]
	 */
	private function resolvePageTitleAndPermissions( FormRenderRequest $request ): array {
		// Disable all form elements if user doesn't have edit
		// permission - two different checks are needed, because
		// editing permissions can be set in different ways.
		// HACK - sometimes we don't know the page name in advance, but
		// we still need to set a title here for testing permissions.
		$placeholderTitle = static fn (): Title => Title::newFromText(
			$request->webRequest->getVal( 'namespace' ) . ":Page Forms permissions test"
		) ?? Title::makeTitle( NS_MAIN, 'Page Forms permissions test' );
		if ( $request->isEmbedded || $request->isQuery ) {
			// If this is an embedded form (probably a 'RunQuery') or we're in Special:RunQuery,
			// just use the name of the actual page we're on.
			$pageTitle = RequestContext::getMain()->getTitle() ?? $placeholderTitle();
		} elseif ( $request->pageName === '' || $request->pageName === null ) {
			$pageTitle = $placeholderTitle();
		} else {
			// $request->pageName may not be a syntactically valid title (e.g. it was
			// generated from a page name formula, or came from an untrusted
			// request value); fall back to the placeholder title used above for
			// permission-testing purposes, which would otherwise fatal in
			// getPermissionErrors() and other unguarded uses below.
			$pageTitle = Title::newFromText( $request->pageName ) ?? $placeholderTitle();
		}

		global $wgOut;
		// Show previous set of deletions for this page, if it's been
		// deleted before.
		if ( !$request->formSubmitted &&
			( $pageTitle && !$pageTitle->exists() &&
			$request->pageNameFormula === null )
		) {
			$this->showDeletionLog( $wgOut, $pageTitle );
		}

		$permissionErrors = [];
		$userCanEditPage = true;
		// Unfortunately, we can't just call userCan() or its
		// equivalent here because it seems to ignore the setting
		// "$wgEmailConfirmToEdit = true;". Instead, we'll just get the
		// permission errors from the start, and use those to determine
		// whether the page is editable.
		if ( !$request->isQuery ) {
			$permissionErrors = MediaWikiServices::getInstance()->getPermissionManager()
					->getPermissionErrors( 'edit', $request->user, $pageTitle );
			if ( MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly() ) {
				$permissionErrors = [ [ 'readonlytext',
					[ MediaWikiServices::getInstance()->getReadOnlyMode()->getReason() ] ] ];
			}
			$userCanEditPage = count( $permissionErrors ) == 0;
			MediaWikiServices::getInstance()->getHookContainer()->run(
				'PageForms::UserCanEditPage', [ $pageTitle, &$userCanEditPage ]
			);
		}

		return [ $pageTitle, $permissionErrors, $userCanEditPage ];
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
		$user = $request->user;
		$parser = $context->parser;
		$form_page_title = $context->formPageTitle;

		// Cleanup - everything has been browsed.
		// Remove all the remaining placeholder
		// tags in the HTML and wiki-text.
		foreach ( $context->placeholderFields as $stringToReplace ) {
			// Remove the @<insertHTML>@ tags from the generated
			// HTML form.
			$form_text = str_replace( FormPlaceholder::toHtmlMarker( $stringToReplace ), '', $form_text );
		}

		// If it wasn't included in the form definition, add the
		// 'free text' input as a hidden field at the bottom.
		if ( !$context->freeTextWasIncluded ) {
			$form_text .= Html::hidden( 'pf_free_text', '!free_text!' );
		}
		// Get the free text and the page text. The free text is also inserted into the form.
		[ $free_text, $page_text ] = $this->pageTextAssembler->createPageText( $context );

		// Also substitute the free text into the form.
		$escaped_free_text = Sanitizer::safeEncodeAttribute( $free_text ?? '' );
		$form_text = str_replace( '!free_text!', $escaped_free_text, $form_text );

		// Add a warning in, if we're editing an existing page and that
		// page appears to not have been created with this form.
		if ( !$request->isQuery && $request->pageNameFormula === null &&
			$context->pageTitle->exists() && $existing_page_content !== ''
			&& !$context->sourcePageMatchesThisForm ) {
			$form_text = "\t" . '<div class="warningbox">' .
				// Prepend with a colon in case it's a file or category page.
				wfMessage( 'pf_formedit_formwarning', ':' . $request->pageName )->parse() .
				"</div>\n<br clear=\"both\" />\n" . $form_text;
		}

		// Add form bottom, if no custom "standard inputs" have been defined.
		if ( !$context->standardInputsIncluded ) {
			if ( $request->isQuery ) {
				$form_text .= FormButtons::queryFormBottom();
			} else {
				$form_text .= FormButtons::formBottom( $request->formSubmitted, $context->formIsDisabled );
			}
		}

		if ( !$request->isQuery ) {
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
		if ( !$request->isEmbedded ) {
			$form_page_title = $parser->recursiveTagParse( str_replace( "{{!}}", "|", $form_page_title ?? '' ) );
		} else {
			$form_page_title = null;
		}

		return [ $form_text, $page_text, $form_page_title, $parserOutput ];
	}

	/**
	 * Start the HTML of the form: the loading spinner and the warnings for the user. If the user
	 * may not edit the page, the reason is shown above the form.
	 *
	 * @param FormRenderRequest $request
	 * @param array $permissionErrors
	 * @param bool $formIsDisabled
	 * @return string The start of the HTML of the form.
	 */
	private function openForm( FormRenderRequest $request, array $permissionErrors, bool $formIsDisabled ): string {
		global $wgPageFormsShowExpandAllLink;

		// Start off with a loading spinner - this will be removed by
		// the JavaScript once everything has finished loading.
		$formText = FormMarkup::displayLoadingImage();
		if ( !$formIsDisabled ) {
			// Show "Your IP address will be recorded" warning if
			// user is anonymous, and it's not a query.
			if ( $request->user->isAnon() && !$request->isQuery ) {
				// Based on code in MediaWiki's EditPage.php.
				$anonEditWarning = wfMessage( 'anoneditwarning',
					// Log-in link
					'{{fullurl:Special:UserLogin|returnto={{FULLPAGENAMEE}}}}',
					// Sign-up link
					'{{fullurl:Special:UserLogin/signup|returnto={{FULLPAGENAMEE}}}}' )->parse();
				$formText .= Html::rawElement(
					'div', [ 'id' => 'mw-anon-edit-warning', 'class' => 'warningbox' ], $anonEditWarning
				);
			}
		} elseif ( $request->out->getTitle() != null ) {
			$request->out->setPageTitle( wfMessage( 'badaccess' )->text() );
			$request->out->addWikiTextAsInterface(
				$request->out->formatPermissionsErrorMessage( $permissionErrors, 'edit' )
			);
			$request->out->addHTML( "\n<hr />\n" );
		}

		if ( $wgPageFormsShowExpandAllLink ) {
			$formText .= Html::rawElement( 'p', [ 'id' => 'pf-expand-all' ],
				// @TODO - add an i18n message for this.
				Html::element( 'a', [ 'href' => '#' ], 'Expand all collapsed parts of the form' ) ) . "\n";
		}
		return $formText;
	}

	/**
	 * Render one section of the form definition (the elements between two template tags, in
	 * order) and add it to the form.
	 *
	 * @param list<FormElement> $elements
	 * @param FormRenderContext $context
	 */
	private function renderSection( array $elements, FormRenderContext $context ): void {
		// The HTML for the section is assembled from the elements in $context->section.
		$context->sectionElements = array_map( [ $this, 'freeTextAsField' ], $elements );
		$context->section = ' ';

		foreach ( $context->sectionElements as $element_num => $element ) {
			$context->elementNum = $element_num;
			$this->getElementHandler( $element )->handle( $element, $context );
		}

		$this->pageTextAssembler->insertTemplateCalls( $context );
		$this->sectionLayout->finish( $context );
	}

	/**
	 * @param FormElement $element
	 * @return ElementHandler
	 * @throws ElementHandlerException if the type of the element has no handler
	 */
	private function getElementHandler( FormElement $element ): ElementHandler {
		return $this->elementHandlers[get_class( $element )] ?? throw ElementHandlerException::noHandler( $element );
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
	 * Create a fresh Parser instance for use by formHTML(), titled at $pageTitle.
	 *
	 * @param User $user
	 * @param Title $pageTitle
	 * @return Parser
	 */
	private function createFreshParser( $user, Title $pageTitle ) {
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
		$parser->setTitle( $pageTitle );
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
		global $wgOut, $wgPageFormsScriptPath;

		$renderRequest = new FormRenderRequest(
			(bool)$form_submitted,
			(bool)$source_is_page,
			(bool)$is_query,
			(bool)$is_embedded,
			(bool)$is_autocreate,
			$autocreate_query,
			$page_name,
			$page_name_formula,
			$form_id !== null ? (int)$form_id : null,
			$existing_page_content,
			$request ?? RequestContext::getMain()->getRequest(),
			$user ?? RequestContext::getMain()->getUser(),
			$wgOut,
			(string)$wgPageFormsScriptPath
		);
		$counters = new FormCounters();
		FormCounters::begin( $counters );
		try {
			return $this->renderInContext( $form_def, $renderRequest, $counters );
		} finally {
			$counters->mirrorToGlobals();
			FormCounters::end();
		}
	}

	/**
	 * The body of render(), run with $counters as the current counters.
	 *
	 * @param string $form_def
	 * @param FormRenderRequest $request
	 * @param FormCounters $counters
	 * @return FormRenderResult
	 */
	private function renderInContext(
		$form_def, FormRenderRequest $request, FormCounters $counters
	): FormRenderResult {
		// Disable all form elements if user doesn't have edit permission.
		[ $pageTitle, $permissionErrors, $userCanEditPage ] = $this->resolvePageTitleAndPermissions( $request );
		$formIsDisabled = !( $request->isQuery || $userCanEditPage );

		$formText = $this->openForm( $request, $permissionErrors, $formIsDisabled );

		$context = new FormRenderContext(
			$request, $pageTitle, $this->createFreshParser( $request->user, $pageTitle ), $formIsDisabled, $counters
		);
		$context->formText = $formText;

		$form_definition = FormCache::getFormDefinitionModel( $context->parser, $form_def, $request->formId );
		// Snapshot RL modules registered by parser tag hooks during form-definition
		// parsing. FormField calls $context->parser->clearState() during field rendering,
		// which resets $context->parser->mOutput and discards these modules. We save them
		// here and merge them back into the final ParserOutput before returning.
		$context->formDefParserModules = $context->parser->getOutput()->getModules();
		$context->formDefParserModuleStyles = $context->parser->getOutput()->getModuleStyles();

		$form_def_sections = $this->formDefParser->splitIntoSections( $form_definition );

		// Cycle through the form definition file, and possibly an
		// existing article as well, finding template and field
		// declarations and replacing them with form elements, either
		// blank or pre-populated, as appropriate.

		foreach ( $form_def_sections as $section_elements ) {
			$this->renderSection( $section_elements, $context );
			// A section of a template that allows multiple instances is rendered once per instance.
			while ( $context->tif?->hasInstancesLeftToPrint() ) {
				$context->tif->incrementInstanceNum();
				$this->renderSection( $section_elements, $context );
			}
		}

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
