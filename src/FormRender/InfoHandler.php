<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionException;
use MediaWiki\Extension\PageForms\FormDefinition\FormElement;
use MediaWiki\Extension\PageForms\FormDefinition\TagSpec;
use MediaWiki\Extension\PageForms\FormRenderContext;

/**
 * The {{{info}}} tag: titles of the form page, the free text being part of the page and
 * where the form of a query is shown.
 */
class InfoHandler implements ElementHandler {

	/**
	 * Reads 'create title'/'add title', 'edit title' and 'query title' (the one that applies
	 * becomes the form page title), and applies the side effects of 'includeonly free text'
	 * and 'onlyinclude free text' (on the page text) and 'query form at top'.
	 *
	 * @param FormElement $element
	 * @param FormRenderContext $context
	 * @throws FormDefinitionException if the form definition has more than one info tag
	 */
	public function handle( FormElement $element, FormRenderContext $context ): void {
		if ( !$element instanceof TagSpec ) {
			return;
		}
		if ( $context->infoTagSeen ) {
			throw new FormDefinitionException(
				"Error in form definition: only one 'info' tag is allowed per form."
			);
		}
		$context->infoTagSeen = true;

		foreach ( array_slice( $element->getComponents(), 1 ) as $component ) {
			$sub_components = array_map( 'trim', explode( '=', $component, 2 ) );
			// Tag names are case-insensitive
			$tag = strtolower( $sub_components[0] );
			if ( $tag == 'create title' || $tag == 'add title' ) {
				// Handle this only if we're adding a page.
				if ( !$context->isQuery && !$context->pageTitle->exists() ) {
					$context->formPageTitle = $sub_components[1];
				}
			} elseif ( $tag == 'edit title' ) {
				// Handle this only if we're editing a page.
				if ( !$context->isQuery && $context->pageTitle->exists() ) {
					$context->formPageTitle = $sub_components[1];
				}
			} elseif ( $tag == 'query title' ) {
				// Handle this only if we're in 'RunQuery'.
				if ( $context->isQuery ) {
					$context->formPageTitle = $sub_components[1];
				}
			} elseif ( $tag == 'includeonly free text' || $tag == 'onlyinclude free text' ) {
				$context->wikiPage->makeFreeTextOnlyInclude();
			} elseif ( $tag == 'query form at top' ) {
				$context->runQueryFormAtTop = true;
			}
		}

		// Replace the {{{info}}} tag with a hidden span, instead of a blank, to avoid a
		// potential security issue.
		$context->section .= '<span style="visibility: hidden;"></span>';
	}
}
