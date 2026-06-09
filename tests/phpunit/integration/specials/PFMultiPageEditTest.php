<?php

use MediaWiki\MediaWikiServices;

/**
 * Integration tests for the PFMultiPageEdit class.
 *
 * @covers \PFMultiPageEdit
 *
 * @group Database
 *
 * @author gesinn-it-ilm
 */
class PFMultiPageEditTest extends SpecialPageTestBase {

	protected function setUp(): void {
		parent::setUp();
		// Ensure the user has the correct permissions for the test
		$this->setMwGlobals( 'wgGroupPermissions', [
			'*' => [ 'multipageedit' => true ],
			'user' => [ 'multipageedit' => true ]
		] );
	}

	/**
	 * Create an instance of the special page being tested.
	 *
	 * @return SpecialPage
	 */
	protected function newSpecialPage() {
		return MediaWikiServices::getInstance()->getSpecialPageFactory()->getPage( 'MultiPageEdit' );
	}

	public function testExecuteWithTemplateAndForm() {
		$request = new FauxRequest( [ 'template' => 'TestTemplate', 'form' => 'TestForm' ] );

		[ $html ] = $this->executeSpecialPage( '', $request );

		$this->assertNotEmpty( $html );
		$this->assertStringContainsString( 'TestTemplate', $html );
	}

	public function testDisplaySpreadsheet() {
		$this->editPage( 'Template:TestTemplate', 'Field1|Field2' );
		$this->editPage( 'Form:TestForm', '{{{for template|TestTemplate}}}' );

		$request = new FauxRequest( [ 'template' => 'TestTemplate', 'form' => 'TestForm' ] );

		[ $html ] = $this->executeSpecialPage( '', $request );

		$this->assertStringContainsString( 'class="pfSpreadsheet"', $html );
		$this->assertStringContainsString( 'data-template-name="TestTemplate"', $html );
		$this->assertStringContainsString( 'data-form-name="TestForm"', $html );
	}
}
