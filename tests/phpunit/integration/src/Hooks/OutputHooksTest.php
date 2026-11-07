<?php

use MediaWiki\Extension\PageForms\Hooks\OutputHooks;

/**
 * @covers \MediaWiki\Extension\PageForms\Hooks\OutputHooks
 */
class OutputHooksTest extends MediaWikiIntegrationTestCase {

	public function testMakeGlobalVariablesScriptAddsPageFormsVariables(): void {
		$vars = [];
		$out = RequestContext::getMain()->getOutput();

		$result = ( new OutputHooks() )->onMakeGlobalVariablesScript( $vars, $out );

		$this->assertTrue( $result );
		$this->assertArrayHasKey( 'wgPageFormsScriptPath', $vars );
		$this->assertArrayHasKey( 'wgPageForms24HourTime', $vars );
		$this->assertIsBool( $vars['wgPageForms24HourTime'] );
		$this->assertIsString( $vars['sf_select'] );
	}

}
