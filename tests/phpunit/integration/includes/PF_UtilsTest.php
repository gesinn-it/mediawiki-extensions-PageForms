<?php

/**
 * @covers PFUtils
 * @group Database
 */
class PFUtilsTest extends MediaWikiIntegrationTestCase {

	// The functions that only work on strings and arrays are tested in PFUtilsFunctionsTest, which needs no wiki.

	/**
	 * @dataProvider provideIgnoreFormNameCases
	 * @param array|string $patterns The value of $wgPageFormsIgnoreTitlePattern
	 * @param string $formName
	 * @param bool $expected
	 */
	public function testIgnoreFormName( $patterns, string $formName, bool $expected ) {
		$this->setMwGlobals( 'wgPageFormsIgnoreTitlePattern', $patterns );
		$this->assertSame( $expected, PFUtils::ignoreFormName( $formName ) );
	}

	/**
	 * @return array<string, array{0: array|string, 1: string, 2: bool}>
	 */
	public static function provideIgnoreFormNameCases(): array {
		return [
			'no patterns set' => [ [], 'MyForm', false ],
			'a matching pattern' => [ [ 'Test.*' ], 'TestForm', true ],
			'a pattern that does not match' => [ [ 'Test.*' ], 'ProductionForm', false ],
			// When the global is set to a plain string (not an array), the code wraps it.
			'a plain string as the pattern' => [ 'Ignore', 'IgnoreMe', true ],
		];
	}

	public function testGetWordForYesOrNoReturnsAWordThatDiffersForTrueAndFalse() {
		$yes = PFUtils::getWordForYesOrNo( true );
		$no = PFUtils::getWordForYesOrNo( false );

		$this->assertIsString( $yes );
		$this->assertNotEmpty( $yes );
		$this->assertIsString( $no );
		$this->assertNotEmpty( $no );
		$this->assertNotSame( $yes, $no );
	}

	public function testLinkTextWithoutExplicitText() {
		$result = PFUtils::linkText( NS_MAIN, 'SomePage' );
		$this->assertStringContainsString( 'SomePage', $result );
		$this->assertStringContainsString( '[[', $result );
	}

	public function testLinkTextWithExplicitText() {
		$result = PFUtils::linkText( NS_MAIN, 'SomePage', 'My Label' );
		$this->assertStringContainsString( 'My Label', $result );
		$this->assertStringContainsString( 'SomePage', $result );
	}

	public function testLinkTextReturnsNameWhenTitleIsInvalid() {
		// '<' is not allowed in a page title, so Title::makeTitleSafe() returns
		// null for it on any supported MW version; linkText() must then fall
		// back to returning the raw name as plain text (includes/PF_Utils.php:145-149).
		$invalidName = 'Invalid<Title';
		$result = PFUtils::linkText( NS_MAIN, $invalidName );
		$this->assertSame( $invalidName, $result );
	}

	public function testGetNsText() {
		// NS_MAIN (0) typically returns empty string in content language
		$this->assertIsString( PFUtils::getNsText( NS_MAIN ) );
		$this->assertNotEmpty( PFUtils::getNsText( NS_USER ) );
	}

	/**
	 * Regression test for the SecurityCheck-DoubleEscaped issue reported
	 * in GitHub issue #59: linkForSpecialPage() used to run
	 * htmlspecialchars() on the special page's description and then pass
	 * the already-escaped string as plain text to
	 * LinkRenderer::makeKnownLink(), which escapes its $text argument a
	 * second time (via HtmlArmor::getHtml()) unless it is wrapped in
	 * HtmlArmor. That resulted in HTML metacharacters being escaped
	 * twice (e.g. "&" becoming "&amp;amp;" instead of "&amp;").
	 */
	public function testLinkForSpecialPageDoesNotDoubleEscapeDescription() {
		// Override the interface message backing SpecialVersion's
		// getDescription() so it contains HTML metacharacters.
		$this->setUserLang( 'en' );
		$this->overrideConfigValue( 'UseDatabaseMessages', true );
		$this->tablesUsed[] = 'page';
		$this->insertPage( 'MediaWiki:Version', 'Version & <Info> "quoted" \'stuff\'' );
		$this->getServiceContainer()->getMessageCache()->clear();

		$linkRenderer = $this->getServiceContainer()->getLinkRenderer();
		$html = PFUtils::linkForSpecialPage( $linkRenderer, 'Version' );

		// The link text must be escaped exactly once: "&" -> "&amp;",
		// not double-escaped into "&amp;amp;".
		$this->assertStringContainsString( 'Version &amp; &lt;Info&gt;', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html );
		$this->assertStringNotContainsString( '&amp;lt;', $html );
	}

	/**
	 * ensureParserReadyForTagParse() must reset the output type unconditionally,
	 * even when the parser was already "initialized" (getOutput() non-null) by
	 * other code earlier this request - e.g. PFAutoeditAPI::prepareAction()
	 * leaves the global singleton in Parser::OT_WIKI mode via startExternalParse().
	 * In that mode, recursiveTagParse() would return "{{Template|value}}"
	 * unexpanded instead of the template's actual output.
	 */
	public function testEnsureParserReadyForTagParseResetsOtWikiMode() {
		$this->editPage( 'Template:PFTestUtilsOtWiki01', 'PFTestLabel' );

		$parser = \MediaWiki\MediaWikiServices::getInstance()->getParser();
		$parser->startExternalParse( null, ParserOptions::newFromAnon(), Parser::OT_WIKI );

		$user = RequestContext::getMain()->getUser();
		$readyParser = PFUtils::ensureParserReadyForTagParse( $parser, $user );

		$this->assertSame(
			'PFTestLabel',
			trim( $readyParser->recursiveTagParse( '{{PFTestUtilsOtWiki01}}' ) )
		);
	}

	/**
	 * ensureParserReadyForTagParse() must retitle the parser when its current
	 * title is the "Badtitle" placeholder (e.g. left there by
	 * PFAutoeditAPI::prepareAction()'s startExternalParse( null, ... ) call) -
	 * otherwise title-sensitive content like "{{FULLPAGENAME}}" resolves
	 * against the placeholder instead of the page actually being processed.
	 */
	public function testEnsureParserReadyForTagParseRetitlesBadtitleParser() {
		$parser = \MediaWiki\MediaWikiServices::getInstance()->getParser();
		$parser->setOptions( ParserOptions::newFromAnon() );
		$parser->clearState();

		// clearState() alone leaves the parser resolving titles against the
		// "Badtitle" placeholder - confirm the precondition before fixing it.
		$this->assertTrue( $parser->getTitle()->isSpecial( 'Badtitle' ) );

		$user = RequestContext::getMain()->getUser();
		$realTitle = Title::newFromText( 'PFTestUtilsBadtitleTarget01' );
		$readyParser = PFUtils::ensureParserReadyForTagParse( $parser, $user, $realTitle );

		$this->assertSame(
			'PFTestUtilsBadtitleTarget01',
			trim( $readyParser->recursiveTagParse( '{{FULLPAGENAME}}' ) )
		);
	}

}
