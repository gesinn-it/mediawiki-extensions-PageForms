<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use MediaWiki\Extension\PageForms\FormDefinition\FormElement;
use MediaWiki\Extension\PageForms\FormDefinition\TextSpec;
use MediaWiki\Extension\PageForms\FormRenderContext;

/**
 * The text between the tags of a form definition, which is shown as it is.
 */
class TextHandler implements ElementHandler {

	public function handle( FormElement $element, FormRenderContext $context ): void {
		if ( $element instanceof TextSpec ) {
			$context->section .= $element->getText();
		}
	}
}
