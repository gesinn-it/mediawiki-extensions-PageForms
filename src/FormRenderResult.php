<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use ParserOutput;

/**
 * Everything FormPrinter::render() produces for one form definition.
 */
class FormRenderResult {

	private string $formText;
	private string $pageText;
	private ?string $formPageTitle;
	private ?string $generatedPageName;
	private ParserOutput $parserOutput;
	private bool $queryFormAtTop;

	public function __construct(
		string $formText,
		string $pageText,
		?string $formPageTitle,
		?string $generatedPageName,
		ParserOutput $parserOutput,
		bool $queryFormAtTop
	) {
		$this->formText = $formText;
		$this->pageText = $pageText;
		$this->formPageTitle = $formPageTitle;
		$this->generatedPageName = $generatedPageName;
		$this->parserOutput = $parserOutput;
		$this->queryFormAtTop = $queryFormAtTop;
	}

	/**
	 * The HTML of the form.
	 */
	public function getFormText(): string {
		return $this->formText;
	}

	/**
	 * The wikitext of the page that the submitted form (or its defaults) describes.
	 */
	public function getPageText(): string {
		return $this->pageText;
	}

	/**
	 * The title the form definition's {{{info}}} tag asks for, if any.
	 */
	public function getFormPageTitle(): ?string {
		return $this->formPageTitle;
	}

	/**
	 * The page name that the form's page name formula yields for the submitted values.
	 */
	public function getGeneratedPageName(): ?string {
		return $this->generatedPageName;
	}

	/**
	 * The metadata (resource loader modules, head items) of the parsed form definition.
	 */
	public function getParserOutput(): ParserOutput {
		return $this->parserOutput;
	}

	/**
	 * Whether the form definition asks, with {{{info|query form at top}}}, for the query form to
	 * be shown above the results.
	 */
	public function isQueryFormAtTop(): bool {
		return $this->queryFormAtTop;
	}

	/**
	 * The result in the form of the list that FormPrinter::formHTML() has always returned.
	 *
	 * @return array{0: string, 1: string, 2: ?string, 3: ?string, 4: ParserOutput, 5: bool}
	 */
	public function toArray(): array {
		return [
			$this->formText,
			$this->pageText,
			$this->formPageTitle,
			$this->generatedPageName,
			$this->parserOutput,
			$this->queryFormAtTop,
		];
	}
}
