<?php

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

	/**
	 * When #forminput is parsed normally, ext.pageforms.forminput must be
	 * registered on the ParserOutput.
	 */
	public function testModuleAddedToParserOutput(): void {
		$parserOutput = $this->getServiceContainer()->getParser()->parse(
			'{{#forminput:form=TestForm}}',
			Title::makeTitle( NS_MAIN, 'Test' ),
			ParserOptions::newFromAnon()
		);

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
		$parserOutput = $this->getServiceContainer()->getParser()->parse(
			'{{#forminput:form=TestForm}}',
			Title::makeTitle( NS_MAIN, 'Test' ),
			ParserOptions::newFromAnon()
		);

		$this->assertStringContainsString(
			'pfFormInputWrapper',
			$parserOutput->getRawText(),
			'Rendered HTML must contain the pfFormInputWrapper mount-point div'
		);
	}
}
