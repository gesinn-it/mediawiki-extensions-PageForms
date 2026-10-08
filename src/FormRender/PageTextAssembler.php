<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use MediaWiki\Extension\PageForms\FormRenderContext;
use MediaWiki\MediaWikiServices;

/**
 * Puts the template calls the form produces into the text of the page.
 */
class PageTextAssembler {

	/**
	 * Takes the place of one of the two braces of a template call that is inserted into the page
	 * text, so that the call is not read as a template call again. This is the Unicode replacement
	 * character; it is spelled out here because it was once written into the source as a literal
	 * character that an editor or an encoding change can damage.
	 */
	private const BRACE_MARKER = "\u{FFFD}";

	/**
	 * Once all instances of the current template are printed, adds its template calls to the
	 * page text that has not been used yet, if that text has a {{{insertionpoint}}} (which is
	 * the case when the form replaces a part of a page instead of editing the whole page).
	 *
	 * @param FormRenderContext $context
	 */
	public function insertTemplateCalls( FormRenderContext $context ): void {
		$tif = $context->tif;
		if ( $tif && ( !$tif->allowsMultiple() || $tif->allInstancesPrinted() ) ) {
			$template_text = $context->wikiPage->createTemplateCallsForTemplateName(
				$tif->getTemplateName(), $context->request->webRequest
			);
			// Escape the '$' characters for the preg_replace() call.
			$template_text = str_replace( '$', '\$', $template_text );

			// If there is a placeholder in the text, we
			// know that we are doing a replace.
			if ( $context->existingPageContent
				&& str_contains( $context->existingPageContent, '{{{insertionpoint}}}' ) ) {
				$context->existingPageContent = preg_replace( '/\{\{\{insertionpoint\}\}\}(\r?\n?)/',
					preg_replace( '/\}\}/m', '}' . self::BRACE_MARKER,
						preg_replace( '/\{\{/m', self::BRACE_MARKER . '{', $template_text ) ) .
					"{{{insertionpoint}}}",
					$context->existingPageContent );
			}
		}
	}

	/**
	 * Creates the text of the page from the template calls and the free text, once all elements
	 * of the form definition are processed. The text is created whether or not the form was
	 * submitted, in case this is called from #formredlink.
	 *
	 * @param FormRenderContext $context
	 * @return array [ string|null $freeText, string $pageText ]
	 */
	public function createPageText( FormRenderContext $context ): array {
		$existing_page_content = $context->existingPageContent;
		$request = $context->request->webRequest;
		$wiki_page = $context->wikiPage;

		if ( $context->request->sourceIsPage ) {
			// If the page is the source, free_text will just be
			// whatever in the page hasn't already been inserted
			// into the form.
			$free_text = trim( $existing_page_content );
		// ...or get it from the form submission, if it's not called from #formredlink
		} elseif ( !$context->request->isAutocreate && $request->getCheck( 'pf_free_text' ) ) {
			$free_text = $request->getVal( 'pf_free_text' );
			if ( !$context->freeTextWasIncluded ) {
				$wiki_page->addFreeTextSection();
			}
		} elseif ( $context->preloadedFreeText != null ) {
			$free_text = $context->preloadedFreeText;
		} else {
			$free_text = null;
		}

		if ( $free_text !== null && $wiki_page->freeTextOnlyInclude() ) {
			$free_text = str_replace( "<onlyinclude>", '', $free_text );
			$free_text = str_replace( "</onlyinclude>", '', $free_text );
			$free_text = trim( $free_text );
		}

		$page_text = '';

		MediaWikiServices::getInstance()->getHookContainer()->run( 'PageForms::BeforeFreeTextSubst',
			[ &$free_text, $existing_page_content, &$page_text ] );

		// Now that we have the free text, we can create the full page
		// text.
		// The page text needs to be created whether or not the form
		// was submitted, in case this is called from #formredlink.
		$wiki_page->setFreeText( $free_text );
		$page_text = $wiki_page->createPageText( $request );

		return [ $free_text, $page_text ];
	}
}
