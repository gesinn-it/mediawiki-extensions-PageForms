<?php

declare( strict_types=1 );

/**
 * @covers \PFDefaultForm
 * @group Database
 */
class PFDefaultFormTest extends MediaWikiIntegrationTestCase {

	private function parse( string $wikitext, Title $title ): array {
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parserOutput = $parser->parse( $wikitext, $title, ParserOptions::newFromAnon() );
		return [
			'text' => Parser::stripOuterParagraph( $parserOutput->getRawText() ),
			'property' => $parserOutput->getPageProperty( 'PFDefaultForm' ),
		];
	}

	public function testOnCategoryPageRendersLinkToFormAndSetsPageProperty(): void {
		$title = Title::makeTitle( NS_CATEGORY, 'PFTestDefaultFormCategory' );

		$result = $this->parse( '{{#default_form:PFTestDefaultFormName}}', $title );

		$this->assertSame( 'PFTestDefaultFormName', $result['property'] );
		$this->assertStringContainsString( 'This category uses the form', $result['text'] );
		$this->assertStringContainsString( 'Form:PFTestDefaultFormName', $result['text'] );
	}

	public function testOnNonCategoryPageSetsPagePropertyButDisplaysNothing(): void {
		$title = Title::makeTitle( NS_MAIN, 'PFTestDefaultFormRegularPage' );

		$result = $this->parse( '{{#default_form:PFTestDefaultFormName}}', $title );

		$this->assertSame( 'PFTestDefaultFormName', $result['property'] );
		$this->assertSame( '', $result['text'] );
	}

	public function testOnCategoryPageWithEmptyFormNameShowsErrorMessage(): void {
		$title = Title::makeTitle( NS_CATEGORY, 'PFTestDefaultFormCategory' );

		// The page property is set unconditionally before the NS_CATEGORY
		// branch runs, so it's still recorded as an empty string here.
		// Title::makeTitleSafe() only returns null for a completely invalid
		// title (e.g. the empty string) — not merely for a form page that
		// doesn't exist yet, which still renders as a redlink.
		$result = $this->parse( '{{#default_form:}}', $title );

		$this->assertSame( '', $result['property'] );
		$this->assertStringContainsString( 'class="error"', $result['text'] );
		$this->assertStringContainsString( 'No form found', $result['text'] );
	}

	public function testCalledWithNoColonIsNotRecognisedAsAFunctionCall(): void {
		// Without a ':' separator, MediaWiki doesn't parse this as a parser
		// function call at all — it's the one wikitext-reachable way to
		// exercise run()'s "no argument" early return (func_get_args()
		// only receives $parser).
		$title = Title::makeTitle( NS_CATEGORY, 'PFTestDefaultFormCategory' );

		$result = $this->parse( '{{#default_form}}', $title );

		$this->assertNull( $result['property'] );
		$this->assertSame( '{{#default_form}}', $result['text'] );
	}
}
