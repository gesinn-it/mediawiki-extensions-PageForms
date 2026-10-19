<?php

use MediaWiki\Extension\PageForms\CategoryPages;

/**
 * @covers \MediaWiki\Extension\PageForms\CategoryPages
 * @group Database
 * @group PF
 */
class CategoryPagesTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( [
			'wgPageFormsUseDisplayTitle' => false,
			'wgPageFormsMaxAutocompleteValues' => 100,
		] );
	}

	public function addDBData(): void {
		$this->insertPage( 'PFCategoryPagesTestDirect', '[[Category:PFCategoryPagesTestTop]]' );
		$this->insertPage( 'Category:PFCategoryPagesTestSub', '[[Category:PFCategoryPagesTestTop]]' );
		$this->insertPage( 'PFCategoryPagesTestInSub', '[[Category:PFCategoryPagesTestSub]]' );
		$this->insertPage( 'Category:PFCategoryPagesTestSubSub', '[[Category:PFCategoryPagesTestSub]]' );
		$this->insertPage( 'PFCategoryPagesTestInSubSub', '[[Category:PFCategoryPagesTestSubSub]]' );
		$this->insertPage( 'User talk:PFCategoryPagesTestInOtherNamespace', '[[Category:PFCategoryPagesTestTop]]' );
		$this->insertPage( 'PFCategoryPagesTestSpacedMember', '[[Category:PFCategoryPagesTest Spaced]]' );
		// The sort key puts the page before the others, the display title changes its label.
		$this->insertPage( 'PFCategoryPagesTestTitled', '[[Category:PFCategoryPagesTestTop]]' );
	}

	private function insertTitledPage(): void {
		$this->setMwGlobals( [
			'wgRestrictDisplayTitle' => false,
			'wgPageFormsUseDisplayTitle' => true,
		] );
		$this->insertPage(
			'PFCategoryPagesTestTitled',
			'[[Category:PFCategoryPagesTestTop]]{{DISPLAYTITLE:PFCategoryPagesTest Shown}}' .
				'{{DEFAULTSORT:AAAPFCategoryPagesTest}}'
		);
	}

	public function testGetWithoutLevelsReturnsTheCategoryItself(): void {
		$this->assertSame( [ 'PFCategoryPagesTestTop' ], CategoryPages::get( 'PFCategoryPagesTestTop', 0 ) );
	}

	public function testGetReturnsThePagesOfTheCategoryAndNotItsSubcategories(): void {
		$names = array_keys( CategoryPages::get( 'PFCategoryPagesTestTop', 1 ) );

		$this->assertContains( 'PFCategoryPagesTestDirect', $names );
		$this->assertNotContains( 'Category:PFCategoryPagesTestSub', $names );
	}

	public function testGetFollowsTheSubcategoriesDownToTheLevel(): void {
		$twoLevels = array_keys( CategoryPages::get( 'PFCategoryPagesTestTop', 2 ) );
		$threeLevels = array_keys( CategoryPages::get( 'PFCategoryPagesTestTop', 3 ) );

		$this->assertContains( 'PFCategoryPagesTestInSub', $twoLevels );
		$this->assertNotContains( 'PFCategoryPagesTestInSubSub', $twoLevels );
		$this->assertContains( 'PFCategoryPagesTestInSubSub', $threeLevels );
	}

	public function testGetStopsWhenThereAreNoSubcategoriesLeft(): void {
		$names = array_keys( CategoryPages::get( 'PFCategoryPagesTestTop', 50 ) );

		$this->assertContains( 'PFCategoryPagesTestInSubSub', $names );
	}

	public function testGetAcceptsTheNameOfTheCategoryWithSpaces(): void {
		$names = array_keys( CategoryPages::get( 'PFCategoryPagesTest Spaced', 1 ) );

		$this->assertSame( [ 'PFCategoryPagesTestSpacedMember' ], $names );
	}

	public function testGetPrefixesThePagesOfOtherNamespaces(): void {
		$names = array_keys( CategoryPages::get( 'PFCategoryPagesTestTop', 1 ) );

		$this->assertContains( 'User talk:PFCategoryPagesTestInOtherNamespace', $names );
	}

	public function testGetKeepsOnlyThePagesWithTheSubstringButStillFollowsSubcategories(): void {
		$names = array_keys( CategoryPages::get( 'PFCategoryPagesTestTop', 3, 'pfcategorypagestestinsub' ) );

		$this->assertEqualsCanonicalizing(
			[ 'PFCategoryPagesTestInSub', 'PFCategoryPagesTestInSubSub' ],
			$names
		);
	}

	public function testGetFindsTheDisplayTitleAndSortsByTheDefaultSortKey(): void {
		$this->insertTitledPage();

		$result = CategoryPages::get( 'PFCategoryPagesTestTop', 1 );

		$this->assertSame( 'PFCategoryPagesTest Shown', $result['PFCategoryPagesTestTitled'] );
		$this->assertSame( 'PFCategoryPagesTestTitled', array_key_first( $result ) );
		$this->assertSame( 'PFCategoryPagesTestDirect', $result['PFCategoryPagesTestDirect'] );
	}

	public function testGetFindsDisplayTitlesByTheSubstringInTheirText(): void {
		$this->insertTitledPage();

		$names = array_keys( CategoryPages::get( 'PFCategoryPagesTestTop', 1, 'shown' ) );

		$this->assertSame( [ 'PFCategoryPagesTestTitled' ], $names );
	}

	public function testGetKeepsAPageWhoseNameIsTheDisplayTitleOfAnotherPage(): void {
		$this->setMwGlobals( [
			'wgRestrictDisplayTitle' => false,
			'wgPageFormsUseDisplayTitle' => true,
		] );
		// The first page sorts before the second one and shows the name of the second one.
		$this->insertPage(
			'PFCategoryPagesTestClashAlias',
			'[[Category:PFCategoryPagesTestClash]]{{DISPLAYTITLE:PFCategoryPagesTestClashOriginal}}'
		);
		$this->insertPage( 'PFCategoryPagesTestClashOriginal', '[[Category:PFCategoryPagesTestClash]]' );

		$result = CategoryPages::get( 'PFCategoryPagesTestClash', 1 );

		$this->assertSame(
			[
				'PFCategoryPagesTestClashAlias' => 'PFCategoryPagesTestClashOriginal',
				'PFCategoryPagesTestClashOriginal' => 'PFCategoryPagesTestClashOriginal',
			],
			$result
		);
	}

	public function testFixedMultiSortKeepsNumericNames(): void {
		$pages = [ '20@' => 'Twenty', '3@' => 'Three', '100@' => 'Hundred' ];
		$sortkeys = [ '20' => 'b', '3' => 'a', '100' => 'c' ];

		$this->assertSame(
			[ '3' => 'Three', '20' => 'Twenty', '100' => 'Hundred' ],
			CategoryPages::fixedMultiSort( $sortkeys, $pages )
		);
	}
}
