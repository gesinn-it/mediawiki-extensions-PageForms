<?php

use MediaWiki\MediaWikiServices;

/**
 * @covers \PFFormStart
 * @group Database
 *
 * @author gesinn-it-wam
 */
class PFFormStartTest extends SpecialPageTestBase {

	use IntegrationTestHelpers;

	public function setUp(): void {
		parent::setUp();
		$this->requireLanguageCodeEn();
	}

	/**
	 * Create an instance of the special page being tested.
	 *
	 * @return SpecialPage
	 */
	protected function newSpecialPage() {
		return MediaWikiServices::getInstance()->getSpecialPageFactory()->getPage( 'FormStart' );
	}

	public function testEmptyQuery() {
		[ $html ] = $this->executeSpecialPage( '' );

		$this->assertStringContainsString( 'No forms have been defined on this site.', $html );
	}

}
