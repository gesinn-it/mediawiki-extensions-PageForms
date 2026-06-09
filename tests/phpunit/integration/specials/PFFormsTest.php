<?php

use MediaWiki\MediaWikiServices;

/**
 * @covers \PFForms
 * @group Database
 *
 * @author gesinn-it-gea
 */
class PFFormsTest extends SpecialPageTestBase {

	protected function newSpecialPage() {
		return MediaWikiServices::getInstance()->getSpecialPageFactory()->getPage( 'Forms' );
	}

	public function testExecuteRendersPage() {
		[ $html ] = $this->executeSpecialPage( '' );

		$this->assertIsString( $html );
		$this->assertNotEmpty( $html );
	}

	public function testExecuteWithNoFormsShowsEmptyList() {
		[ $html ] = $this->executeSpecialPage( '' );

		$this->assertStringNotContainsString( '<li>', $html );
	}

	public function testExecuteListsExistingForm() {
		$this->insertPage( Title::newFromText( 'TestForm', PF_NS_FORM ), 'Form content' );

		[ $html ] = $this->executeSpecialPage( '' );

		$this->assertStringContainsString( 'TestForm', $html );
	}

	public function testFormatResultReturnsNullForIgnoredFormName() {
		$this->setMwGlobals( 'wgPageFormsIgnoreTitlePattern', 'IgnoreMe' );

		/** @var PFForms $page */
		$page = $this->newSpecialPage();
		$result = (object)[ 'value' => 'IgnoreMe_SomeForm' ];

		$this->assertNull( $page->formatResult( null, $result ) );
	}

	public function testFormatResultReturnsLinkForValidForm() {
		$this->setMwGlobals( 'wgPageFormsIgnoreTitlePattern', '' );

		/** @var PFForms $page */
		$page = $this->newSpecialPage();
		$result = (object)[ 'value' => 'MyForm' ];

		$html = $page->formatResult( null, $result );

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'MyForm', $html );
	}

	public function testGetQueryInfoReturnsFormNamespaceCondition() {
		/** @var PFForms $page */
		$page = $this->newSpecialPage();
		$info = $page->getQueryInfo();

		$this->assertSame( PF_NS_FORM, $info['conds']['page_namespace'] );
		$this->assertSame( 0, $info['conds']['page_is_redirect'] );
	}

	public function testIsNotExpensive() {
		$this->assertFalse( $this->newSpecialPage()->isExpensive() );
	}

	public function testIsNotSyndicated() {
		$this->assertFalse( $this->newSpecialPage()->isSyndicated() );
	}

	public function testSortAscending() {
		$this->assertFalse( $this->newSpecialPage()->sortDescending() );
	}
}
