<?php

use MediaWiki\Extension\PageForms\WikiAccess;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\PageForms\WikiAccess
 * @group Database
 */
class WikiAccessTest extends MediaWikiIntegrationTestCase {

	public function testGetPageTextReturnsTheTextOfAnExistingPage(): void {
		$title = Title::newFromText( 'PFTestWikiAccessPage01' );
		$this->insertPage( $title, 'Some text' );

		$this->assertSame( 'Some text', WikiAccess::getPageText( $title ) );
	}

	public function testGetPageTextReturnsNullForAMissingPage(): void {
		$this->assertNull( WikiAccess::getPageText( Title::newFromText( 'PFTestWikiAccessMissing01' ) ) );
	}

	public function testNewWikiPageFromTitleReturnsAWikiPageForTheTitle(): void {
		$title = Title::newFromText( 'PFTestWikiAccessPage02' );

		$this->assertTrue( WikiAccess::newWikiPageFromTitle( $title )->getTitle()->equals( $title ) );
	}

	public function testGetAllFormsListsTheFormsWithSpacesAndSkipsRedirects(): void {
		$this->insertPage( Title::makeTitle( PF_NS_FORM, 'PFTestWikiAccess Form01' ), '{{{standard input|save}}}' );
		$this->insertPage( Title::makeTitle( PF_NS_FORM, 'PFTestWikiAccessRedirect01' ), '#REDIRECT [[Form:Other]]' );

		$forms = WikiAccess::getAllForms();

		$this->assertContains( 'PFTestWikiAccess Form01', $forms );
		$this->assertNotContains( 'PFTestWikiAccessRedirect01', $forms );
	}

	public function testGetFormDropdownLabelStartsWithTheFormNamespaceName(): void {
		$label = WikiAccess::getFormDropdownLabel();

		$this->assertStringStartsWith( 'Form', $label );
		$this->assertGreaterThan( strlen( 'Form' ), strlen( $label ) );
	}

}
