<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use Parser;
use PFWikiPage;
use Title;
use User;
use WebRequest;

/**
 * State of a single FormPrinter::render() call.
 *
 * FormPrinter is a long-lived shared object, so anything that belongs to one render
 * lives here instead: a nested or re-entrant render gets its own context and cannot
 * overwrite the state of the render that is still in progress.
 *
 * The first group is what the caller asked for and does not change during the render,
 * the second is the state the elements of the form definition build up while the
 * definition is processed in order.
 */
class FormRenderContext {

	// What the render was asked to do.

	public bool $formSubmitted = false;
	public bool $sourceIsPage = false;
	public bool $isQuery = false;
	public bool $isEmbedded = false;
	/** True when called by #formredlink with "create page". */
	public bool $isAutocreate = false;
	/** @var array Query parameters from #formredlink */
	public array $autocreateQuery = [];
	public ?string $pageName = null;
	public ?string $pageNameFormula = null;
	public ?int $formId = null;
	public WebRequest $request;
	public User $user;
	/** The fresh parser the form definition and the field values are parsed with. */
	public Parser $parser;
	/** True if the user may not edit the page; all inputs are then disabled. */
	public bool $formIsDisabled = false;

	// State built up while the elements are processed.

	/** Tab index and field number of this render. */
	public FormCounters $counters;
	/** The page being edited, or a placeholder title used for permission checks. */
	public ?Title $pageTitle = null;
	/** Whether the form definition has its own "standard input" tag (save, watch, ...). */
	public bool $standardInputsIncluded = false;

	/** The HTML of the form so far. */
	public string $formText = '';
	/** The HTML of the section of the form definition that is being processed. */
	public string $section = ' ';
	public ?TemplateInForm $tif = null;
	public ?Template $template = null;
	/** The page text that has not been taken over into the form yet. */
	public ?string $existingPageContent = null;
	/** @var list<string> The replaced @<name>@ strings of fields that hold a template. */
	public array $placeholderFields = [];
	public bool $infoTagSeen = false;
	public bool $freeTextWasIncluded = false;
	public ?string $preloadedFreeText = null;
	public bool $sourcePageMatchesThisForm = false;
	public ?string $generatedPageName = null;
	public ?string $formPageTitle = null;
	public bool $runQueryFormAtTop = false;
	public PFWikiPage $wikiPage;
	/** @var list<string> Modules registered by parser tag hooks while the definition was parsed. */
	public array $formDefParserModules = [];
	/** @var list<string> */
	public array $formDefParserModuleStyles = [];

	public function __construct() {
		$this->counters = new FormCounters();
		$this->wikiPage = new PFWikiPage();
	}
}
