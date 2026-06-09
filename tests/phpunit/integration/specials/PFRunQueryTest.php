<?php

use MediaWiki\MediaWikiServices;

/**
 * Integration test class for the PFRunQuery special page.
 *
 * @covers \PFRunQuery
 *
 * @group Database
 *
 * @author gesinn-it-ilm
 */
class PFRunQueryTest extends SpecialPageTestBase {

	protected function setUp(): void {
		parent::setUp();

		if ( !defined( 'PF_NS_FORM' ) ) {
			define( 'PF_NS_FORM', 106 );
		}
	}

	protected function newSpecialPage() {
		return MediaWikiServices::getInstance()->getSpecialPageFactory()->getPage( 'RunQuery' );
	}

	public function testExecuteWithoutQuery() {
		[ $html ] = $this->executeSpecialPage( '', new FauxRequest( [ 'form' => '' ], true ) );

		$this->assertStringContainsString( 'error', $html );
	}

	public function testExecuteWithNonexistentForm() {
		[ $html ] = $this->executeSpecialPage( '', new FauxRequest( [ 'form' => 'NonExistentForm' ] ) );

		$this->assertStringContainsString( 'class="error"', $html );
		$this->assertStringContainsString( 'NonExistentForm', $html );
	}

	public function testExecuteWithValidForm() {
		$formTitle = Title::newFromText( 'ValidForm', PF_NS_FORM );
		$this->insertPage( $formTitle, 'Form content here' );

		[ $html ] = $this->executeSpecialPage( 'ValidForm', new FauxRequest( [ 'form' => 'ValidForm' ] ) );

		$this->assertStringContainsString( 'Form content here', $html );
	}

	public function testSubmitFormQuery() {
		$formTitle = Title::newFromText( 'ValidForm', PF_NS_FORM );
		$this->insertPage( $formTitle, 'Form content here' );

		$request = new FauxRequest( [
			'form' => 'ValidForm',
			'_run' => 'true',
			'wpTextbox1' => 'Some content for query',
		] );

		[ $html ] = $this->executeSpecialPage( 'ValidForm', $request );

		$this->assertStringContainsString( 'Some content for query', $html );
	}
}
