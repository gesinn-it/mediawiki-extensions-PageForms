<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use MediaWiki\Extension\PageForms\FormDefinition\FormElement;
use MediaWiki\Extension\PageForms\FormDefinition\TagSpec;
use MediaWiki\Extension\PageForms\FormRenderContext;
use MediaWiki\Extension\PageForms\StandardInputHtmlBuilder;
use MWException;

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
	 * @throws MWException if the tag has no input name
	 */
	public function handle( FormElement $element, FormRenderContext $context ): void {
		if ( !$element instanceof TagSpec ) {
			return;
		}
		$tag_components = $element->getComponents();
		if ( count( $tag_components ) < 2 ) {
			throw new MWException(
				'<div class="error">Error in form definition:' .
				' \'standard input\' tag is missing the input name.</div>'
			);
		}
		$input_name = $tag_components[1];

		// if it's a query, ignore all standard inputs except run query
		if ( ( $context->isQuery && $input_name != 'run query' )
			|| ( !$context->isQuery && $input_name == 'run query' ) ) {
			return;
		}
		// set a flag so that the standard 'form bottom' won't get displayed
		$context->standardInputsIncluded = true;

		$context->section .= $this->htmlBuilder->buildHtml(
			$input_name,
			$tag_components,
			$context->formIsDisabled,
			$context->formSubmitted,
			$context->request,
			$context->parser,
			$context->pageTitle,
			$context->pageName
		);
	}
}
