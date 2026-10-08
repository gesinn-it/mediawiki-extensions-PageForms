<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionException;
use MediaWiki\Extension\PageForms\FormDefinition\FormElement;
use MediaWiki\Extension\PageForms\FormDefinition\TagSpec;
use MediaWiki\Extension\PageForms\FormRenderContext;
use MediaWiki\Extension\PageForms\StandardInputHtmlBuilder;

/**
 * The {{{standard input}}} tag: save, preview, changes, cancel, summary, minor edit, watch
 * or run query.
 */
class StandardInputHandler implements ElementHandler {

	private StandardInputHtmlBuilder $htmlBuilder;

	public function __construct( ?StandardInputHtmlBuilder $htmlBuilder = null ) {
		$this->htmlBuilder = $htmlBuilder ?? new StandardInputHtmlBuilder();
	}

	/**
	 * @param FormElement $element
	 * @param FormRenderContext $context
	 * @throws FormDefinitionException if the tag has no input name
	 */
	public function handle( FormElement $element, FormRenderContext $context ): void {
		if ( !$element instanceof TagSpec ) {
			throw ElementHandlerException::wrongElement( $this, $element );
		}
		$tag_components = $element->getComponents();
		if ( count( $tag_components ) < 2 ) {
			throw new FormDefinitionException(
				"Error in form definition: 'standard input' tag is missing the input name."
			);
		}
		$input_name = $tag_components[1];

		// if it's a query, ignore all standard inputs except run query
		if ( ( $context->request->isQuery && $input_name != 'run query' )
			|| ( !$context->request->isQuery && $input_name == 'run query' ) ) {
			return;
		}
		// set a flag so that the standard 'form bottom' won't get displayed
		$context->standardInputsIncluded = true;

		$context->section .= $this->htmlBuilder->buildHtml(
			$input_name,
			$tag_components,
			$context->formIsDisabled,
			$context->request->formSubmitted,
			$context->request->webRequest,
			$context->parser,
			$context->pageTitle,
			$context->request->pageName
		);
	}
}
