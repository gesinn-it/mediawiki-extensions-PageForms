<?php

use MediaWiki\Extension\PageForms\NamespacePages;

/**
 * @covers \MediaWiki\Extension\PageForms\NamespacePages
 * @group Database
 * @group PF
 */
class NamespacePagesTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( 'wgPageFormsUseDisplayTitle', false );
	}

	public function addDBData(): void {
		// Regular (non-redirect) template page
		$this->insertPage( 'Template:PFNamespacePagesTestRegular', 'Regular template content' );

		// Create a redirect to simulate a renamed file/template. insertPage()
		// saves this through the normal edit path, which already sets
		// page_is_redirect=1 from the #REDIRECT wikitext — no manual DB
		// update needed.
		$this->insertPage(
			'Template:PFNamespacePagesTestRedirect',
			'#REDIRECT [[Template:PFNamespacePagesTestRegular]]'
		);
		$this->insertPage( 'Help:PFNamespacePagesTestHelp', 'Help content' );
		$this->insertPage( 'PFNamespacePagesTestMain', 'Main content' );
	}

	private function insertPageWithDisplayTitleAndSortKey(): void {
		$this->setMwGlobals( 'wgRestrictDisplayTitle', false );
		$this->setMwGlobals( 'wgPageFormsUseDisplayTitle', true );
		$this->insertPage(
			'Template:PFNamespacePagesTestSorted',
			'{{DISPLAYTITLE:PFNamespacePagesTest Shown}}{{DEFAULTSORT:PFNamespacePagesTestAaa}}'
		);
	}

	public function testGetExcludesRedirects(): void {
		$result = NamespacePages::get( 'Template' );

		$titles = array_keys( $result );
		$this->assertContains(
			'PFNamespacePagesTestRegular',
			$titles,
			'Regular page must appear in namespace autocomplete'
		);
		$this->assertNotContains(
			'PFNamespacePagesTestRedirect',
			$titles,
			'Redirect page must not appear in namespace autocomplete (issue #27)'
		);
	}

	public function testGetOmitsTheNamespacePrefixOfASingleNamespace(): void {
		$result = NamespacePages::get( 'Template' );

		$this->assertSame( 'PFNamespacePagesTestRegular', $result['PFNamespacePagesTestRegular'] );
	}

	public function testGetPrefixesTheNamesOfSeveralNamespaces(): void {
		$titles = array_keys( NamespacePages::get( 'Template,Help' ) );

		$this->assertContains( 'Template:PFNamespacePagesTestRegular', $titles );
		$this->assertContains( 'Help:PFNamespacePagesTestHelp', $titles );
	}

	public function testGetTakesMainAsTheMainNamespace(): void {
		$titles = array_keys( NamespacePages::get( 'Main' ) );

		$this->assertContains( 'PFNamespacePagesTestMain', $titles );
		$this->assertNotContains( 'PFNamespacePagesTestHelp', $titles );
	}

	public function testGetFindsTheNamespaceByItsNameInAnyCase(): void {
		$titles = array_keys( NamespacePages::get( 'template' ) );

		$this->assertContains( 'PFNamespacePagesTestRegular', $titles );
	}

	public function testGetKeepsOnlyThePagesWithTheSubstring(): void {
		$titles = array_keys( NamespacePages::get( 'Template', 'pfnamespacepagestestreg' ) );

		$this->assertSame( [ 'PFNamespacePagesTestRegular' ], $titles );
	}

	public function testGetFindsTheDisplayTitleAndSortsByTheDefaultSortKey(): void {
		$this->insertPageWithDisplayTitleAndSortKey();

		$result = NamespacePages::get( 'Template', 'PFNamespacePagesTest' );

		$this->assertSame( 'PFNamespacePagesTest Shown', $result['PFNamespacePagesTestSorted'] );
		$this->assertSame( 'PFNamespacePagesTestRegular', $result['PFNamespacePagesTestRegular'] );
		$this->assertSame( 'PFNamespacePagesTestSorted', array_key_first( $result ) );
	}

	public function testGetFindsDisplayTitlesOnlyByTheSubstringInTheirText(): void {
		$this->insertPageWithDisplayTitleAndSortKey();

		$titles = array_keys( NamespacePages::get( 'Template', 'Shown' ) );

		$this->assertSame( [ 'PFNamespacePagesTestSorted' ], $titles );
	}
}
