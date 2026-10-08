<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use MediaWiki\Extension\PageForms\FormDefinition\FormElement;
use MediaWiki\Extension\PageForms\FormDefinition\TagSpec;
use MediaWiki\Extension\PageForms\FormRenderContext;
use MediaWiki\Extension\PageForms\FormSectionHtmlBuilder;

/**
 * The {{{section}}} tag: a part of the page text that is edited in a field of its own.
 */
class SectionHandler implements ElementHandler {

	private FormSectionHtmlBuilder $htmlBuilder;

	public function __construct( ?FormSectionHtmlBuilder $htmlBuilder = null ) {
		$this->htmlBuilder = $htmlBuilder ?? new FormSectionHtmlBuilder();
	}

	/**
	 * Adds the input for the section, with the text of the section from the page if the form
	 * edits a page.
	 *
	 * @param FormElement $element
	 * @param FormRenderContext $context
	 */
	public function handle( FormElement $element, FormRenderContext $context ): void {
		if ( !$element instanceof TagSpec ) {
			throw ElementHandlerException::wrongElement( $this, $element );
		}
		$context->counters->fieldNum++;
		$context->counters->tabIndex++;

		// The builder takes the section out of the text it is given by reference. A copy is
		// passed on purpose: the page content of the context has always been left as it was,
		// so the text of the section also stays part of the free text.
		$existingPageContent = $context->existingPageContent;

		$context->section .= $this->htmlBuilder->buildHtml(
			$element->getComponents(),
			array_slice( $context->sectionElements, $context->elementNum + 1 ),
			$context->request->sourceIsPage,
			$existingPageContent,
			$context->request->webRequest,
			$context->wikiPage,
			$context->formIsDisabled,
			$context->request->user,
			$context->counters
		);
	}
}
