<?php

use MediaWiki\Extension\PageForms\FormAssets;

/**
 * @covers \MediaWiki\Extension\PageForms\FormAssets
 */
class FormAssetsTest extends MediaWikiIntegrationTestCase {

	public function testAddFormRLModulesAddsTheFormModulesToTheParserOutput(): void {
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->setOptions( ParserOptions::newFromAnon() );
		$parser->clearState();

		FormAssets::addFormRLModules( $parser );

		$output = $parser->getOutput();
		$this->assertContains( 'ext.pageforms.main', $output->getModules() );
		$this->assertContains( 'ext.pageforms.main.styles', $output->getModuleStyles() );
	}

	public function testAddFormRLModulesAddsModulesFromTheAddRLModulesHook(): void {
		$this->setTemporaryHook(
			'PageForms::AddRLModules',
			static function ( array &$modules ) {
				$modules[] = 'ext.pageforms.test.hookmodule';
			}
		);
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->setOptions( ParserOptions::newFromAnon() );
		$parser->clearState();

		FormAssets::addFormRLModules( $parser );

		$this->assertContains( 'ext.pageforms.test.hookmodule', $parser->getOutput()->getModules() );
	}

}
