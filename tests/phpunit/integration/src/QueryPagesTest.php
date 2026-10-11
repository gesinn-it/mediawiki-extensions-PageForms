<?php

use MediaWiki\Extension\PageForms\QueryPages;

/**
 * @covers \MediaWiki\Extension\PageForms\QueryPages
 * @group Database
 * @group PF
 */
class QueryPagesTest extends MediaWikiIntegrationTestCase {

	public function testGetReturnsTheDbKeysOfThePagesOfTheQuery(): void {
		if ( !class_exists( '\SMWQueryProcessor' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}
		$this->editPage( 'PFQueryPagesTest Member', '[[Category:PFQueryPagesTestCat]]' );
		\SMW\StoreFactory::getStore()->clear();

		$pages = QueryPages::get( '[[Category:PFQueryPagesTestCat]]' );

		$this->assertSame( [ 'PFQueryPagesTest_Member' ], $pages );
	}
}
