<?php

use MediaWiki\Extension\PageForms\ParserAccess;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\PageForms\ParserAccess
 * @group Database
 */
class ParserAccessTest extends MediaWikiIntegrationTestCase {

	/**
	 * ensureParserReadyForTagParse() must reset the output type unconditionally,
	 * even when the parser was already "initialized" (getOutput() non-null) by
	 * other code earlier this request - e.g. AutoeditApi::prepareAction()
	 * leaves the global singleton in Parser::OT_WIKI mode via startExternalParse().
	 * In that mode, recursiveTagParse() would return "{{Template|value}}"
	 * unexpanded instead of the template's actual output.
	 */
	public function testEnsureParserReadyForTagParseResetsOtWikiMode() {
		$this->editPage( 'Template:PFTestUtilsOtWiki01', 'PFTestLabel' );

		$parser = \MediaWiki\MediaWikiServices::getInstance()->getParser();
		$parser->startExternalParse( null, ParserOptions::newFromAnon(), Parser::OT_WIKI );

		$user = RequestContext::getMain()->getUser();
		$readyParser = ParserAccess::ensureParserReadyForTagParse( $parser, $user );

		$this->assertSame(
			'PFTestLabel',
			trim( $readyParser->recursiveTagParse( '{{PFTestUtilsOtWiki01}}' ) )
		);
	}

	/**
	 * ensureParserReadyForTagParse() must retitle the parser when its current
	 * title is the "Badtitle" placeholder (e.g. left there by
	 * AutoeditApi::prepareAction()'s startExternalParse( null, ... ) call) -
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
		$readyParser = ParserAccess::ensureParserReadyForTagParse( $parser, $user, $realTitle );

		$this->assertSame(
			'PFTestUtilsBadtitleTarget01',
			trim( $readyParser->recursiveTagParse( '{{FULLPAGENAME}}' ) )
		);
	}

	public function testEnsureParserInitializedDoesNotAskAFreshParserForItsOutput() {
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$deprecations = [];
		set_error_handler( static function ( int $errno, string $message ) use ( &$deprecations ): bool {
			$deprecations[] = $message;
			return true;
		}, E_USER_DEPRECATED );
		try {
			ParserAccess::ensureParserInitialized( $parser, RequestContext::getMain()->getUser() );
		} finally {
			restore_error_handler();
		}

		$this->assertSame( [], $deprecations );
		$this->assertInstanceOf( ParserOutput::class, $parser->getOutput() );
	}

	public function testEnsureParserInitializedKeepsTheOutputOfAParserThatIsInitialized() {
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$user = RequestContext::getMain()->getUser();
		ParserAccess::ensureParserInitialized( $parser, $user );
		$output = $parser->getOutput();
		$output->setExtensionData( 'PFTestKept', 'kept' );

		ParserAccess::ensureParserInitialized( $parser, $user );

		$this->assertSame( $output, $parser->getOutput() );
		$this->assertSame( 'kept', $parser->getOutput()->getExtensionData( 'PFTestKept' ) );
	}

}
