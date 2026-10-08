<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionException;
use MediaWiki\Extension\PageForms\FormDefinition\FormElement;
use MediaWiki\Extension\PageForms\FormDefinition\TagSpec;
use MediaWiki\Extension\PageForms\FormMarkup;
use MediaWiki\Extension\PageForms\FormRenderContext;

/**
 * The {{{end template}}} tag, which closes the template opened by {{{for template}}}.
 */
class EndTemplateHandler implements ElementHandler {

	/**
	 * @param FormElement $element
	 * @param FormRenderContext $context
	 * @throws FormDefinitionException if the tag has parameters
	 */
	public function handle( FormElement $element, FormRenderContext $context ): void {
		if ( !$element instanceof TagSpec ) {
			throw ElementHandlerException::wrongElement( $this, $element );
		}
		if ( count( $element->getComponents() ) > 1 ) {
			throw new FormDefinitionException(
				"Error in form definition: 'end template' tag cannot contain any additional parameters."
			);
		}
		if ( $context->sourceIsPage && $context->tif && !$context->tif->allowsMultiple() ) {
			// Add any unhandled template fields
			// in the page as hidden variables.
			$context->formText .= FormMarkup::unhandledFieldsHTML( $context->tif );
		}
		// The tag itself produces no output.
		$context->template = null;
		$context->tif = null;
	}
}
