<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionException;
use MediaWiki\Extension\PageForms\FormDefinition\FormElement;
use MediaWiki\Extension\PageForms\FormDefinition\TemplateSpec;
use MediaWiki\Extension\PageForms\FormRenderContext;
use MediaWiki\Extension\PageForms\Template;
use MediaWiki\Extension\PageForms\TemplateInForm;

/**
 * The {{{for template}}} tag, which opens a template whose fields follow.
 */
class TemplateHandler implements ElementHandler {

	/**
	 * Makes the template the current one and, if the form edits a page, takes its call out of
	 * the page text that has not been used yet. The tag itself produces no output.
	 *
	 * @param FormElement $element
	 * @param FormRenderContext $context
	 * @throws FormDefinitionException if the tag has no template name
	 */
	public function handle( FormElement $element, FormRenderContext $context ): void {
		if ( !$element instanceof TemplateSpec ) {
			return;
		}
		$tag_components = $element->getComponents();
		if ( count( $tag_components ) < 2 ) {
			throw new FormDefinitionException(
				"Error in form definition: 'for template' tag is missing the template name."
			);
		}
		if ( $context->tif ) {
			$previous_template_name = $context->tif->getTemplateName();
		} else {
			$previous_template_name = '';
		}
		$template_name = str_replace( '_', ' ', $context->parser->recursiveTagParse( $tag_components[1] ) );
		$context->templateName = $template_name;
		$is_new_template = ( $template_name != $previous_template_name );
		if ( $is_new_template ) {
			$context->template = Template::newFromName( $template_name );
			$context->tif = TemplateInForm::newFromFormTag( $element, $context->parser );
		}
		$tif = $context->tif;
		// If we are editing a page, and this template can be found more than once in that page,
		// and multiple values are allowed, repeat this section.
		if ( $context->sourceIsPage ) {
			// Get the first instance of this template on the page being edited, even if there
			// are more, and remove it from the text being edited.
			$context->existingPageContent = $tif->readFirstCallFromPage( $context->existingPageContent );
			if ( $tif->pageCallsThisTemplate() ) {
				// If we've found a match in the source page, there's a good chance that this page
				// was created with this form - note that, so we don't send the user a warning.
				$context->sourcePageMatchesThisForm = true;
			}
		}

		// We get values from the request, regardless of whether the source is the page or a form
		// submit, because even if the source is a page, values can still come from a query string.
		// (Unless it's called from #formredlink.)
		if ( !$context->isAutocreate ) {
			$tif->setFieldValuesFromSubmit( $context->request );
		}

		$tif->checkIfAllInstancesPrinted( $context->formSubmitted, $context->sourceIsPage );

		if ( !$tif->allInstancesPrinted() ) {
			$context->wikiPage->addTemplate( $tif );
		}
	}
}
