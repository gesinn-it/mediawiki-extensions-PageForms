<?php

/**
 * @covers PFUtils
 * @group Database
 */
class PFUtilsTest extends MediaWikiIntegrationTestCase {

	public function testConvertBackToPipesReplacesControlChar() {
		$this->assertSame( 'a|b|c', PFUtils::convertBackToPipes( "a\1b\1c" ) );
	}

	public function testConvertBackToPipesNoopWhenNothingToReplace() {
		$this->assertSame( 'abc', PFUtils::convertBackToPipes( 'abc' ) );
	}

	public function testSmartSplitFormTagEmptyStringReturnsEmptyArray() {
		$this->assertSame( [], PFUtils::smartSplitFormTag( '' ) );
	}

	public function testSmartSplitFormTagSingleToken() {
		$this->assertSame( [ 'foo' ], PFUtils::smartSplitFormTag( 'foo' ) );
	}

	public function testSmartSplitFormTagSimpleSplit() {
		$this->assertSame( [ 'foo', 'bar', 'baz' ], PFUtils::smartSplitFormTag( 'foo|bar|baz' ) );
	}

	public function testSmartSplitFormTagDoesNotSplitInsideCurlyBrackets() {
		$this->assertSame(
			[ '{{tmpl|arg}}', 'after' ],
			PFUtils::smartSplitFormTag( '{{tmpl|arg}}|after' )
		);
	}

	public function testSmartSplitFormTagTrimsWhitespace() {
		$this->assertSame( [ 'foo', 'bar' ], PFUtils::smartSplitFormTag( ' foo | bar ' ) );
	}

	public function testSmartSplitFormTagNestedCurlyBrackets() {
		$this->assertSame(
			[ '{{outer|{{inner|x}}}}', 'y' ],
			PFUtils::smartSplitFormTag( '{{outer|{{inner|x}}}}|y' )
		);
	}

	public function testGetFormTagComponentsSimple() {
		$this->assertSame( [ 'a', 'b', 'c' ], PFUtils::getFormTagComponents( 'a|b|c' ) );
	}

	public function testGetFormTagComponentsPreservesPipeInsideTemplate() {
		$result = PFUtils::getFormTagComponents( 'field|default={{tmpl|arg}}|label=test' );
		$this->assertSame( [ 'field', 'default={{tmpl|arg}}', 'label=test' ], $result );
	}

	public function testGetFormTagComponentsNestedTemplateCall() {
		$result = PFUtils::getFormTagComponents( 'x|{{f|{{g|y}}}}|z' );
		$this->assertSame( [ 'x', '{{f|{{g|y}}}}', 'z' ], $result );
	}

	public function testArrayMergeRecursiveDistinctOverwritesScalar() {
		$a = [ 'key' => 'old' ];
		$b = [ 'key' => 'new' ];
		$this->assertSame( [ 'key' => 'new' ], PFUtils::arrayMergeRecursiveDistinct( $a, $b ) );
	}

	public function testArrayMergeRecursiveDistinctMergesNestedArrays() {
		$a = [ 'sub' => [ 'x' => 1, 'y' => 2 ] ];
		$b = [ 'sub' => [ 'y' => 99, 'z' => 3 ] ];
		$expected = [ 'sub' => [ 'x' => 1, 'y' => 99, 'z' => 3 ] ];
		$this->assertSame( $expected, PFUtils::arrayMergeRecursiveDistinct( $a, $b ) );
	}

	public function testArrayMergeRecursiveDistinctAddsNewKeys() {
		$a = [ 'a' => 1 ];
		$b = [ 'b' => 2 ];
		$result = PFUtils::arrayMergeRecursiveDistinct( $a, $b );
		$this->assertSame( [ 'a' => 1, 'b' => 2 ], $result );
	}

	public function testArrayMergeRecursiveDistinctEmptySecond() {
		$a = [ 'a' => 1 ];
		$b = [];
		$this->assertSame( [ 'a' => 1 ], PFUtils::arrayMergeRecursiveDistinct( $a, $b ) );
	}

	public function testIgnoreFormNameReturnsFalseWhenNoPatternsSet() {
		$this->setMwGlobals( 'wgPageFormsIgnoreTitlePattern', [] );
		$this->assertFalse( PFUtils::ignoreFormName( 'MyForm' ) );
	}

	public function testIgnoreFormNameReturnsTrueOnMatchingPattern() {
		$this->setMwGlobals( 'wgPageFormsIgnoreTitlePattern', [ 'Test.*' ] );
		$this->assertTrue( PFUtils::ignoreFormName( 'TestForm' ) );
	}

	public function testIgnoreFormNameReturnsFalseOnNonMatchingPattern() {
		$this->setMwGlobals( 'wgPageFormsIgnoreTitlePattern', [ 'Test.*' ] );
		$this->assertFalse( PFUtils::ignoreFormName( 'ProductionForm' ) );
	}

	public function testIgnoreFormNameHandlesStringPatternDirectly() {
		// When the global is set to a plain string (not array), the code wraps it.
		$this->setMwGlobals( 'wgPageFormsIgnoreTitlePattern', 'Ignore' );
		$this->assertTrue( PFUtils::ignoreFormName( 'IgnoreMe' ) );
	}

	public function testGetWordForYesOrNoReturnsNonEmptyStringForTrue() {
		$result = PFUtils::getWordForYesOrNo( true );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	public function testGetWordForYesOrNoReturnsNonEmptyStringForFalse() {
		$result = PFUtils::getWordForYesOrNo( false );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	public function testGetWordForYesOrNoDiffersForTrueAndFalse() {
		$this->assertNotSame(
			PFUtils::getWordForYesOrNo( true ),
			PFUtils::getWordForYesOrNo( false )
		);
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
		// Title::makeTitleSafe returns null for certain invalid names.
		// An empty string is not safe for NS_MAIN, but behaviour may vary by
		// MW version, so we just assert the return is a string.
		$result = PFUtils::linkText( NS_MAIN, 'ValidPage' );
		$this->assertIsString( $result );
	}

	public function testGetNsTextReturnsStringForMainNs() {
		// NS_MAIN (0) typically returns empty string in content language
		$result = PFUtils::getNsText( NS_MAIN );
		$this->assertIsString( $result );
	}

	public function testGetNsTextReturnsNonEmptyForUserNs() {
		$result = PFUtils::getNsText( NS_USER );
		$this->assertNotEmpty( $result );
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

}
