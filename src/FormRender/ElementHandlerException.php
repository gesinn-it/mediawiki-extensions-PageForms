<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use LogicException;
use MediaWiki\Extension\PageForms\FormDefinition\FormElement;

/**
 * An element of a form definition has been given to the wrong handler, or to none at all.
 *
 * This is a defect of the code, not of the form definition: the definition reader and the
 * handler table of the form printer disagree about the types of element.
 */
class ElementHandlerException extends LogicException {

	public static function noHandler( FormElement $element ): self {
		return new self( 'No handler for form definition element of class ' . get_class( $element ) );
	}

	public static function wrongElement( ElementHandler $handler, FormElement $element ): self {
		return new self( get_class( $handler ) . ' cannot handle form definition element of class '
			. get_class( $element ) );
	}
}
