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

	public function testDisplaySpreadsheetEscapesAddRowInstructionsExactlyOnce() {
		// MW's test bootstrap sets $wgUseDatabaseMessages = false for
		// isolation/speed; re-enable it so the on-wiki message override
		// below is actually picked up by wfMessage().
		$this->setMwGlobals( 'wgUseDatabaseMessages', true );

		// Override the message with content containing HTML-significant
		// characters, to prove displaySpreadsheet() does not double-escape it
		// (regression test: it used to combine Message::parse(), which
		// returns already-escaped HTML, with Html::element(), which escapes
		// its contents again).
		$this->editPage(
			'MediaWiki:Pf-spreadsheet-addrowinstructions',
			'Add a row & press <Enter>'
		);

		$this->editPage( 'Template:TestTemplate', 'Field1|Field2' );
		$this->editPage( 'Form:TestForm', '{{{for template|TestTemplate}}}' );

		$request = new FauxRequest( [ 'template' => 'TestTemplate', 'form' => 'TestForm' ] );

		// executeSpecialPage() defaults to the 'qqx' pseudo-language, which
		// would render the message key instead of its text; use 'en' so the
		// overridden message content is actually rendered.
		[ $html ] = $this->executeSpecialPage( '', $request, 'en' );

		// Html::element() escapes "&" -> "&amp;" and "<" -> "&lt;" exactly
		// once in element content. The message text must appear escaped
		// exactly once, not double-escaped into "&amp;amp;"/"&amp;lt;".
		$this->assertStringContainsString( 'Add a row &amp; press &lt;Enter>', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html );
		$this->assertStringNotContainsString( '&amp;lt;', $html );
	}
}
