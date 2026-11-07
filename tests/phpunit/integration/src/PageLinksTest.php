<?php

use MediaWiki\Extension\PageForms\PageLinks;

/**
 * @covers \MediaWiki\Extension\PageForms\PageLinks
 * @group Database
 */
class PageLinksTest extends MediaWikiIntegrationTestCase {

	public function testLinkTextWithoutExplicitText() {
		$result = PageLinks::linkText( NS_MAIN, 'SomePage' );
		$this->assertStringContainsString( 'SomePage', $result );
		$this->assertStringContainsString( '[[', $result );
	}

	public function testLinkTextWithExplicitText() {
		$result = PageLinks::linkText( NS_MAIN, 'SomePage', 'My Label' );
		$this->assertStringContainsString( 'My Label', $result );
		$this->assertStringContainsString( 'SomePage', $result );
	}

	public function testLinkTextReturnsNameWhenTitleIsInvalid() {
		// '<' is not allowed in a page title, so Title::makeTitleSafe() returns
		// null for it on any supported MW version; linkText() must then fall
		// back to returning the raw name as plain text (includes/PF_Utils.php:145-149).
		$invalidName = 'Invalid<Title';
		$result = PageLinks::linkText( NS_MAIN, $invalidName );
		$this->assertSame( $invalidName, $result );
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
		$html = PageLinks::linkForSpecialPage( $linkRenderer, 'Version' );

		// The link text must be escaped exactly once: "&" -> "&amp;",
		// not double-escaped into "&amp;amp;".
		$this->assertStringContainsString( 'Version &amp; &lt;Info&gt;', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html );
		$this->assertStringNotContainsString( '&amp;lt;', $html );
	}

}
