<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use MediaWiki\Extension\PageForms\FormDefinition\FormElement;
use MediaWiki\Extension\PageForms\FormDefinition\UnknownTagSpec;
use MediaWiki\Extension\PageForms\FormRenderContext;

/**
 * A tag that is not one of the tags of a form definition: it is shown as text.
 */
class UnknownTagHandler implements ElementHandler {

	public function handle( FormElement $element, FormRenderContext $context ): void {
		// Ignore the tag, other than to HTML-escape it.
		$context->section .= htmlspecialchars( $element instanceof UnknownTagSpec ? $element->getRaw() : '' );
	}
}
