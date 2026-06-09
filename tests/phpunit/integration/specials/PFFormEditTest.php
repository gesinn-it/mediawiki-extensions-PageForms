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
