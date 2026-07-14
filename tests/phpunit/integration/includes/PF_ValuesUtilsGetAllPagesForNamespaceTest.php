<?php

/**
 * @covers \PFValuesUtils::getAllPagesForNamespace
 * @group Database
 * @group PF
 */
class PFValuesUtilsGetAllPagesForNamespaceTest extends MediaWikiIntegrationTestCase {

	public function addDBData(): void {
		// Regular (non-redirect) template page
		$this->insertPage( 'Template:PFValuesUtilsNSTestRegular', 'Regular template content' );

		// Create a redirect to simulate a renamed file/template. insertPage()
		// saves this through the normal edit path, which already sets
		// page_is_redirect=1 from the #REDIRECT wikitext — no manual DB
		// update needed.
		$this->insertPage(
			'Template:PFValuesUtilsNSTestRedirect',
			'#REDIRECT [[Template:PFValuesUtilsNSTestRegular]]'
		);
	}

	public function testGetAllPagesForNamespaceExcludesRedirects(): void {
		$GLOBALS['wgPageFormsUseDisplayTitle'] = false;

		$result = PFValuesUtils::getAllPagesForNamespace( 'Template' );

		$titles = array_keys( $result );
		$this->assertContains(
			'PFValuesUtilsNSTestRegular',
			$titles,
			'Regular page must appear in namespace autocomplete'
		);
		$this->assertNotContains(
			'PFValuesUtilsNSTestRedirect',
			$titles,
			'Redirect page must not appear in namespace autocomplete (issue #27)'
		);
	}
}
