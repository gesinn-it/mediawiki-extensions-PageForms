<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\FormCounters;
use MediaWiki\Extension\PageForms\ParserAccess;
use MediaWiki\Extension\PageForms\StandardInputHtmlBuilder;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use OOUI\BlankTheme;

/**
 * @covers \MediaWiki\Extension\PageForms\StandardInputHtmlBuilder
 *
 * @group Database
 */
class StandardInputHtmlBuilderTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		\OOUI\Theme::setSingleton( new BlankTheme() );
		FormCounters::resetStandalone();
	}

	private function build( string $inputName, string $label ): string {
		$title = Title::makeTitle( NS_MAIN, 'PFTestStandardInputPage' );
		$parser = ParserAccess::ensureParserReadyForTagParse(
			ParserAccess::getParser(), $this->getServiceContainer()->getUserFactory()->newAnonymous(), $title
		);

		return ( new StandardInputHtmlBuilder() )->buildHtml(
			$inputName,
			[ 'standard input', $inputName, "label=$label" ],
			false,
			true,
			new FauxRequest(),
			$parser,
			$title,
			'PFTestStandardInputPage'
		);
	}

	/**
	 * The label goes into the HTML of the form as it is, so the links in it must be finished links
	 * and not the placeholders that the parser replaces at the end of a parse.
	 *
	 * @dataProvider labelsInHtmlProvider
	 */
	public function testLabelWithALinkIsFinishedHtml( string $inputName ): void {
		$html = $this->build( $inputName, '[[PFTestStandardInputLabelLink]]' );

		$this->assertStringNotContainsString( '<!--LINK', $html );
		$this->assertStringNotContainsString( '<p>', $html );
		// preg_match() rather than assertMatchesRegularExpression(), which the PHPUnit of MediaWiki 1.39 lacks.
		$this->assertSame(
			1,
			preg_match(
				'/<a [^>]*href="[^"]*PFTestStandardInputLabelLink[^"]*"[^>]*>PFTestStandardInputLabelLink<\/a>/',
				$html
			)
		);
	}

	public static function labelsInHtmlProvider(): array {
		return [
			'minor edit' => [ 'minor edit' ],
			'watch' => [ 'watch' ],
		];
	}

	/**
	 * @dataProvider labelsInAttributesProvider
	 */
	public function testLabelWithALinkLeavesNoPlaceholderBehind( string $inputName ): void {
		$html = $this->build( $inputName, '[[PFTestStandardInputLabelLink]]' );

		$this->assertStringNotContainsString( 'LINK&#039;', $html );
		$this->assertStringNotContainsString( "LINK'", $html );
		$this->assertStringNotContainsString( '&lt;p&gt;', $html );
	}

	public static function labelsInAttributesProvider(): array {
		return [
			'save' => [ 'save' ],
			'preview' => [ 'preview' ],
			'changes' => [ 'changes' ],
			'summary' => [ 'summary' ],
			'cancel' => [ 'cancel' ],
		];
	}

	/**
	 * The label of a line is not a paragraph.
	 *
	 * @dataProvider labelsInHtmlProvider
	 */
	public function testLabelWithoutALinkIsKeptAsItIs( string $inputName ): void {
		$html = $this->build( $inputName, "Only ''a'' tiny change" );

		$this->assertStringContainsString( 'Only <i>a</i> tiny change', $html );
		$this->assertStringNotContainsString( '<p>', $html );
	}
}
