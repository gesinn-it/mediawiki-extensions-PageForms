<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use MediaWiki\Extension\PageForms\FormDefinition\FormElement;
use MediaWiki\Extension\PageForms\FormRenderContext;

/**
 * Renders one kind of element of a form definition (a tag of one type, or text).
 *
 * A handler works on the context of the render in progress: it appends to the HTML of the
 * current section ($context->section) and updates the state the later elements build on.
 */
interface ElementHandler {

	/**
	 * @param FormElement $element
	 * @param FormRenderContext $context
	 */
	public function handle( FormElement $element, FormRenderContext $context ): void;
}
