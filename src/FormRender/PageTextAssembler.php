<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use MediaWiki\Extension\PageForms\FormRenderContext;
use MediaWiki\HookContainer\HookContainer;

/**
 * Puts the template calls the form produces into the text of the page.
 */
class PageTextAssembler {

	public function __construct(
		private readonly HookContainer $hookContainer
	) {
	}

	/**
	 * Creates the text of the page from the template calls and the free text, once all elements
	 * of the form definition are processed. The text is created whether or not the form was
	 * submitted, in case this is called from #formredlink.
	 *
	 * @param FormRenderContext $context
	 * @return PageTextResult
	 */
	public function createPageText( FormRenderContext $context ): PageTextResult {
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

		$this->hookContainer->run( 'PageForms::BeforeFreeTextSubst',
			[ &$free_text, $existing_page_content, &$page_text ] );

		// Now that we have the free text, we can create the full page
		// text.
		// The page text needs to be created whether or not the form
		// was submitted, in case this is called from #formredlink.
		$wiki_page->setFreeText( $free_text );
		$page_text = $wiki_page->createPageText( $request );

		return new PageTextResult( $free_text, $page_text );
	}
}
