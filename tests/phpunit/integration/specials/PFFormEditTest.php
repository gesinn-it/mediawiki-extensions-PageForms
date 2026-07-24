<?php

use MediaWiki\MediaWikiServices;

/**
 * @covers \PFFormEdit
 * @covers \PFAutoeditAPI
 * @group Database
 * @group medium
 *
 * @author gesinn-it-wam
 */
class PFFormEditTest extends SpecialPageTestBase {

	use IntegrationTestHelpers;

	public function setUp(): void {
		parent::setUp();
		$this->requireLanguageCodeEn();
		$this->tablesUsed[] = 'page';
	}

	/**
	 * Create an instance of the special page being tested.
	 *
	 * @return SpecialPage
	 */
	protected function newSpecialPage() {
		return MediaWikiServices::getInstance()->getSpecialPageFactory()->getPage( 'FormEdit' );
	}

	public function testEmptyQuery() {
		[ $html ] = $this->executeSpecialPage( '' );

		$this->assertStringContainsString( 'No target page specified.', $html );
	}

	public function testInvalidForm() {
		[ $html ] = $this->executeSpecialPage( 'InvalidForm/X' );

		$this->assertStringContainsString( '<b>InvalidForm</b> is not a valid form.', $html );
	}

	public function testTargetWithInvalidTitleCharactersDoesNotFatal() {
		[ $html ] = $this->executeSpecialPage( '/Foo[Bar' );

		$this->assertStringContainsString( 'The specified target page <b>Foo[Bar</b> is invalid.', $html );
	}

	public function testValidForm() {
		$formText = <<<EOF
			{{{for template|Thing|label=Thing}}}
			{| class="formtable"
			! Name:
			| {{{field|Name|input type=text}}}
			|}
			{{{end template}}}
		EOF;
		$this->insertPage( 'Form:Thing', $formText );

		[ $html ] = $this->executeSpecialPage( 'Thing/Thing1' );

		$this->assertStringContainsString( '<legend>Thing</legend>', $html );
		$this->assertStringContainsString( 'Thing[Name]', $html );
	}

	public function testSubmittingFormWithUniqueNumberFormulaAndNoTargetGeneratesNewPageTitle() {
		// Regression test: PFAutoeditAPI::generateTargetName() is only reached
		// when the form is being submitted (wpSave/wpPreview/wpDiff) with no
		// explicit target and a page name formula containing a "{num}" tag.
		// It then probes $targetTitle->getArticleID( IDBAccessObject::READ_LATEST )
		// to find a free page name. Fixed-target-name tests such as
		// testValidForm() never submit the form, so they do not cover it.
		//
		// PFFormEdit builds its own PFAutoeditAPI/ApiMain without passing a
		// request through, so PFAutoeditAPI::prepareAction() reads
		// RequestContext::getMain()->getRequest() rather than the WebRequest
		// passed to executeSpecialPage(); the wpPreview param must be set
		// there. Restored in finally to avoid leaking into later tests.
		// The formula's "{num}" tag sits directly against the info tag's
		// closing "}}}" (4 closing braces in a row) - a regression test for
		// PF_AutoeditAPI::doAction()'s 'page name' extraction regex, which
		// used to match the first "}}}" it found and truncate the formula
		// to "Thing_{num" instead of "Thing_{num}".
		$formText = <<<EOF
			{{{info|page name=Thing_{num}}}}
			{{{for template|Thing|label=Thing}}}
			{| class="formtable"
			! Name:
			| {{{field|Name|input type=text}}}
			|}
			{{{end template}}}
		EOF;
		$this->insertPage( 'Form:Thing', $formText );

		$mainContext = RequestContext::getMain();
		$originalRequest = $mainContext->getRequest();
		$fauxRequest = new FauxRequest( [ 'wpPreview' => true ], true );
		$mainContext->setRequest( $fauxRequest );
		try {
			[ $html ] = $this->executeSpecialPage( 'Thing', $fauxRequest );
		} finally {
			$mainContext->setRequest( $originalRequest );
		}

		$this->assertStringContainsString( '<legend>Thing</legend>', $html );
	}

	public function testValidFormWithInvalidTargetTitleDoesNotFatal() {
		// A non-empty target that is not a syntactically valid MediaWiki title
		// (e.g. "Foo[Bar") makes Title::newFromText( $page_name ) return null in
		// FormPrinter::formHTML(), which previously fataled when passed to
		// PermissionManager::getPermissionErrors() (a non-nullable LinkTarget
		// parameter). Uses a valid, non-empty form name so execution reaches
		// formHTML() instead of short-circuiting on an invalid form.
		$formText = <<<EOF
			{{{for template|Thing|label=Thing}}}
			{| class="formtable"
			! Name:
			| {{{field|Name|input type=text}}}
			|}
			{{{end template}}}
		EOF;
		$this->insertPage( 'Form:Thing', $formText );

		[ $html ] = $this->executeSpecialPage( 'Thing/Foo[Bar' );

		$this->assertStringContainsString( '<legend>Thing</legend>', $html );
	}

	public function testNullTargetTitleDoesNotFatalOnExists() {
		// PFFormEdit::printForm() derives $targetTitle from $result['target']
		// (PFAutoeditAPI::getOptions(), read after $module->execute() has run) and
		// calls $targetTitle->exists() when $targetName (the original request
		// value) is non-empty. $result['target'] can be a string for which
		// Title::newFromText() returns null (e.g. via the 'PageForms::SetTargetName'
		// hook, or a page-name formula with no "{num}" tag, which
		// PFAutoeditAPI::generateTargetName() returns unvalidated). There is no
		// realistic end-to-end request that reaches this guard without first
		// hitting an unrelated, unguarded Title::newFromText() call in
		// FormPrinter::formHTML() (src/FormPrinter.php:444, out of scope here) -
		// so this exercises the guarded expression directly.
		$targetTitle = Title::newFromText( 'Foo[Bar' );
		$this->assertNull( $targetTitle );
		$this->assertFalse( (bool)( $targetTitle && $targetTitle->exists() ) );
	}

	public function testPrintAltFormsList() {
		// Create an instance of PFFormEdit
		$formEdit = $this->newSpecialPage();

		// Prepare input for the test
		$alt_forms = [ 'FormA', 'FormB', 'FormC' ];
		$target_name = 'SampleTarget';

		// Expected URL for the special page
		$fe_url = SpecialPage::getTitleFor( 'FormEdit' )->getFullURL();

		// Manually construct the expected output
		$expected_output = '<a href="' . $fe_url . '/FormA/SampleTarget">FormA</a>, ' .
						   '<a href="' . $fe_url . '/FormB/SampleTarget">FormB</a>, ' .
						   '<a href="' . $fe_url . '/FormC/SampleTarget">FormC</a>';

		// Call the method being tested
		$result = $formEdit->printAltFormsList( $alt_forms, $target_name );

		// Assert that the result matches the expected output
		$this->assertEquals( $expected_output, $result );
	}
}
