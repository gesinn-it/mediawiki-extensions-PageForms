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

		// Create a redirect to simulate a renamed file/template
		$this->insertPage(
			'Template:PFValuesUtilsNSTestRedirect',
			'#REDIRECT [[Template:PFValuesUtilsNSTestRegular]]'
		);

		// Force the redirect flag in the DB — insertPage() creates the page but may not
		// set page_is_redirect=1 automatically depending on MW version.
		$this->db->update(
			'page',
			[ 'page_is_redirect' => 1 ],
			[ 'page_namespace' => NS_TEMPLATE, 'page_title' => 'PFValuesUtilsNSTestRedirect' ],
			__METHOD__
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
