<?php

use MediaWiki\MediaWikiServices;

/**
 * @covers \PFTemplates
 * @group Database
 *
 * @author gesinn-it-gea
 */
class PFTemplatesTest extends SpecialPageTestBase {

	protected function newSpecialPage() {
		return MediaWikiServices::getInstance()->getSpecialPageFactory()->getPage( 'Templates' );
	}

	public function testExecuteRendersPage() {
		[ $html ] = $this->executeSpecialPage( '' );

		$this->assertIsString( $html );
		$this->assertNotEmpty( $html );
	}

	public function testExecuteListsExistingTemplate() {
		$this->insertPage( Title::newFromText( 'PFTestTemplate', NS_TEMPLATE ), 'Template content' );

		[ $html ] = $this->executeSpecialPage( '' );

		$this->assertStringContainsString( 'PFTestTemplate', $html );
	}

	public function testGetQueryInfoReturnsTemplateNamespaceCondition() {
		/** @var PFTemplates $page */
		$page = $this->newSpecialPage();
		$info = $page->getQueryInfo();

		$this->assertSame( NS_TEMPLATE, $info['conds']['page_namespace'] );
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

	public function testGetCategoryDefinedByTemplateReturnsCategoryName() {
		$templateTitle = Title::newFromText( 'PFTestCatTemplate', NS_TEMPLATE );
		$this->insertPage( $templateTitle, '[[Category:PFTestCategory]]' );

		/** @var PFTemplates $page */
		$page = $this->newSpecialPage();
		$category = $page->getCategoryDefinedByTemplate( $templateTitle );

		$this->assertSame( 'PFTestCategory', $category );
	}

	public function testGetCategoryDefinedByTemplateIgnoresNoinclude() {
		$templateTitle = Title::newFromText( 'PFTestNoIncludeTemplate', NS_TEMPLATE );
		$this->insertPage( $templateTitle, '<noinclude>[[Category:HiddenCat]]</noinclude>Visible content' );

		/** @var PFTemplates $page */
		$page = $this->newSpecialPage();
		$category = $page->getCategoryDefinedByTemplate( $templateTitle );

		$this->assertSame( '', $category );
	}

	public function testGetCategoryDefinedByTemplateReturnsEmptyStringWhenNone() {
		$templateTitle = Title::newFromText( 'PFTestNoCatTemplate', NS_TEMPLATE );
		$this->insertPage( $templateTitle, 'No category here' );

		/** @var PFTemplates $page */
		$page = $this->newSpecialPage();
		$category = $page->getCategoryDefinedByTemplate( $templateTitle );

		$this->assertSame( '', $category );
	}

	public function testGetCategoryDefinedByTemplateStripsPipe() {
		$templateTitle = Title::newFromText( 'PFTestPipeCatTemplate', NS_TEMPLATE );
		$this->insertPage( $templateTitle, '[[Category:PFTestPipedCat|sort key]]' );

		/** @var PFTemplates $page */
		$page = $this->newSpecialPage();
		$category = $page->getCategoryDefinedByTemplate( $templateTitle );

		$this->assertSame( 'PFTestPipedCat', $category );
	}

	public function testFormatResultReturnsLinkForTemplate() {
		/** @var PFTemplates $page */
		$page = $this->newSpecialPage();
		$result = (object)[ 'value' => 'PFTestFormatTemplate' ];

		$html = $page->formatResult( null, $result );

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'PFTestFormatTemplate', $html );
	}
}
