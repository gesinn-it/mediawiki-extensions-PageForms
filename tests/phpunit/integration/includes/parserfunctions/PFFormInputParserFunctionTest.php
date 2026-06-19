<?php

use MediaWiki\MediaWikiServices;
use OOUI\BlankTheme;

/**
 * @covers \PFFormInputParserFunction
 * @group Database
 */
class PFFormInputParserFunctionTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		\OOUI\Theme::setSingleton( new BlankTheme() );
	}

	private function parseWikitext( string $wikitext ): \ParserOutput {
		// getServiceContainer() is only available from MW 1.36; fall back to the
		// static service locator on MW 1.35.  Always use getParserFactory()->create()
		// rather than the deprecated getParser() singleton (deprecated since MW 1.42)
		// so each call gets a clean, isolated parser instance.
		if ( version_compare( MW_VERSION, '1.36', '>=' ) ) {
			$parser = $this->getServiceContainer()->getParserFactory()->create();
		} else {
			$parser = MediaWikiServices::getInstance()->getParserFactory()->create();
		}
		return $parser->parse(
			$wikitext,
			Title::makeTitle( NS_MAIN, 'Test' ),
			ParserOptions::newFromAnon()
		);
	}

	/**
	 * When #forminput is parsed normally, ext.pageforms.forminput must be
	 * registered on the ParserOutput.
	 */
	public function testModuleAddedToParserOutput(): void {
		$parserOutput = $this->parseWikitext( '{{#forminput:form=TestForm}}' );

		$this->assertContains(
			'ext.pageforms.forminput',
			$parserOutput->getModules(),
			'ext.pageforms.forminput must be registered on ParserOutput'
		);
	}

	/**
	 * When #forminput is parsed in interface/message context (as happens when a
	 * system message like MediaWiki:Uploadtext embeds #forminput on a Special page),
	 * the module must reach the real OutputPage — not just the throwaway interface
	 * parser's ParserOutput.
	 *
	 * This is the regression test for:
	 * https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/42
	 */
	public function testModuleAddedToOutputPageWhenParsedAsInterfaceMessage(): void {
		$out = RequestContext::getMain()->getOutput();

		// OutputPage requires a title to parse wikitext.
		$out->setTitle( Title::makeTitle( NS_SPECIAL, 'Upload' ) );

		// Record modules registered before the parse so we can detect additions.
		$modulesBefore = $out->getModules();

		// Parse #forminput via the interface-message path, exactly as MW does when
		// a Special page calls $out->addWikiTextAsInterface().
		$out->addWikiTextAsInterface( '{{#forminput:form=TestForm}}' );

		$modulesAfter = $out->getModules();
		$added = array_diff( $modulesAfter, $modulesBefore );

		$this->assertContains(
			'ext.pageforms.forminput',
			$added,
			'ext.pageforms.forminput must be added directly to OutputPage when ' .
			'#forminput is parsed via addWikiTextAsInterface() (system-message context)'
		);
	}

	/**
	 * The rendered HTML must contain the pfFormInputWrapper element that the JS
	 * uses as its mount point.
	 */
	public function testRendersFormInputWrapperDiv(): void {
		$parserOutput = $this->parseWikitext( '{{#forminput:form=TestForm}}' );

		$this->assertStringContainsString(
			'pfFormInputWrapper',
			$parserOutput->getRawText(),
			'Rendered HTML must contain the pfFormInputWrapper mount-point div'
		);
	}
}
