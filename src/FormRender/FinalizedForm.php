<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use ParserOutput;

/**
 * What is left to produce once all elements of the form definition are rendered: the finished
 * HTML of the form, the text of the page, the title from the {{{info}}} tag, and the metadata of
 * the parsed form definition.
 */
class FinalizedForm {

	public function __construct(
		private string $formText,
		private string $pageText,
		private ?string $formPageTitle,
		private ParserOutput $parserOutput
	) {
	}

	public function getFormText(): string {
		return $this->formText;
	}

	public function getPageText(): string {
		return $this->pageText;
	}

	/**
	 * The title the form definition asks for, parsed; null for an embedded form.
	 */
	public function getFormPageTitle(): ?string {
		return $this->formPageTitle;
	}

	public function getParserOutput(): ParserOutput {
		return $this->parserOutput;
	}
}
