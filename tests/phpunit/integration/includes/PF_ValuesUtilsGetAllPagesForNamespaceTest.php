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
		$db = $this->db;
		$db->newUpdateQueryBuilder()
			->update( 'page' )
			->set( [ 'page_is_redirect' => 1 ] )
			->where( [
				'page_namespace' => NS_TEMPLATE,
				'page_title' => 'PFValuesUtilsNSTestRedirect',
			] )
			->caller( __METHOD__ )
			->execute();
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
