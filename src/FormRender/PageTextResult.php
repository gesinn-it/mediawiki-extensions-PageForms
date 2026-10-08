<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

/**
 * The free text and the page text that PageTextAssembler::createPageText() creates.
 */
class PageTextResult {

	private ?string $freeText;
	private string $pageText;

	public function __construct( ?string $freeText, string $pageText ) {
		$this->freeText = $freeText;
		$this->pageText = $pageText;
	}

	/**
	 * The free text of the page, or null if the form has none.
	 */
	public function getFreeText(): ?string {
		return $this->freeText;
	}

	/**
	 * The wikitext of the whole page.
	 */
	public function getPageText(): string {
		return $this->pageText;
	}
}
