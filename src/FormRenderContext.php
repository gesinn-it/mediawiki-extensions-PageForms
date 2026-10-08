<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\Extension\PageForms\FormDefinition\FormElement;
use Parser;
use PFWikiPage;
use Title;

/**
 * State of a single FormPrinter::render() call.
 *
 * FormPrinter is a long-lived shared object, so anything that belongs to one render
 * lives here instead: a nested or re-entrant render gets its own context and cannot
 * overwrite the state of the render that is still in progress.
 *
 * What the caller asked for is in $request and does not change. The title, the parser and
 * whether the form is disabled are known before the first element is processed and are
 * passed to the constructor, so none of them can be read before it is set. The rest is the
 * state the elements of the form definition build up while the definition is processed in order.
 */
class FormRenderContext {

	/** What the render was asked to do. */
	public readonly FormRenderRequest $request;
	/** The fresh parser the form definition and the field values are parsed with. */
	public readonly Parser $parser;
	/** The page being edited, or a placeholder title used for permission checks. */
	public readonly Title $pageTitle;
	/** True if the user may not edit the page; all inputs are then disabled. */
	public readonly bool $formIsDisabled;
	/** Tab index and field number of this render. */
	public readonly FormCounters $counters;

	// State built up while the elements are processed.

	/** Whether the form definition has its own "standard input" tag (save, watch, ...). */
	public bool $standardInputsIncluded = false;

	/** The HTML of the form so far. */
	public string $formText = '';
	/** @var list<FormElement> The elements of the section of the form definition that is being processed. */
	public array $sectionElements = [];
	/** The position of the element that is being processed within $sectionElements. */
	public int $elementNum = 0;
	/** The HTML of the section of the form definition that is being processed. */
	public string $section = ' ';
	public ?TemplateInForm $tif = null;
	public ?Template $template = null;
	/** The name of the template of the last {{{for template}}} tag. */
	public ?string $templateName = null;
	/** The page text that has not been taken over into the form yet. */
	public ?string $existingPageContent;
	/** @var list<string> The replaced @<name>@ strings of fields that hold a template. */
	public array $placeholderFields = [];
	public bool $infoTagSeen = false;
	public bool $freeTextWasIncluded = false;
	public ?string $preloadedFreeText = null;
	public bool $sourcePageMatchesThisForm = false;
	public ?string $generatedPageName;
	public ?string $formPageTitle = null;
	public bool $runQueryFormAtTop = false;
	public readonly PFWikiPage $wikiPage;
	/** @var list<string> Modules registered by parser tag hooks while the definition was parsed. */
	public array $formDefParserModules = [];
	/** @var list<string> */
	public array $formDefParserModuleStyles = [];

	public function __construct(
		FormRenderRequest $request,
		Title $pageTitle,
		Parser $parser,
		bool $formIsDisabled,
		FormCounters $counters
	) {
		$this->request = $request;
		$this->pageTitle = $pageTitle;
		$this->parser = $parser;
		$this->formIsDisabled = $formIsDisabled;
		$this->counters = $counters;
		$this->existingPageContent = $request->existingPageContent;
		$this->generatedPageName = $request->pageNameFormula;
		$this->wikiPage = new PFWikiPage();
	}
}
