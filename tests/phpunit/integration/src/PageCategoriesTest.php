<?php

use MediaWiki\Extension\PageForms\PageCategories;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\PageForms\PageCategories
 * @group Database
 * @group PF
 */
class PageCategoriesTest extends MediaWikiIntegrationTestCase {

	public function testForPageReturnsTheCategoriesOfThePage(): void {
		$this->editPage(
			'PFPageCategoriesTestPage',
			'[[Category:PFPageCategoriesTestOne]] [[Category:PFPageCategoriesTestTwo]]'
		);

		$categories = PageCategories::forPage( Title::newFromText( 'PFPageCategoriesTestPage' ) );

		$this->assertEqualsCanonicalizing(
			[ 'PFPageCategoriesTestOne', 'PFPageCategoriesTestTwo' ],
			$categories
		);
	}

	public function testForPageOfAPageWithoutCategoriesIsEmpty(): void {
		$this->editPage( 'PFPageCategoriesTestPlain', 'No category here' );

		$this->assertSame( [], PageCategories::forPage( Title::newFromText( 'PFPageCategoriesTestPlain' ) ) );
	}

	public function testForPageOfAPageThatDoesNotExistIsEmpty(): void {
		$this->assertSame( [], PageCategories::forPage( Title::newFromText( 'PFPageCategoriesTestMissing' ) ) );
	}

	public function testAllReturnsTheCategoriesOfTheWiki(): void {
		$this->editPage( 'PFPageCategoriesTestInAll', '[[Category:PFPageCategoriesTestInAll]]' );

		$this->assertContains( 'PFPageCategoriesTestInAll', PageCategories::all() );
	}
}
