<?php

use MediaWiki\MediaWikiServices;

require_once __DIR__ . '/../src/PFTemplateTestInjectionProbe.php';

/**
 * Integration tests for PFTemplate::loadTemplateParams().
 *
 * @covers \PFTemplate
 *
 * @group Database
 */
class PFTemplateTest extends MediaWikiIntegrationTestCase {

	/**
	 * A valid serialized array payload is unserialized as-is.
	 */
	public function testLoadTemplateParamsWithValidArrayPayload() {
		$title = $this->insertTemplatePageWithParamsProp(
			'PFTestTemplateValidParams01',
			serialize( [ 'Field1' => [ 'mandatory' => true ] ] )
		);

		$template = PFTemplate::newFromName( $title->getText() );

		$this->assertSame( [ 'Field1' => [ 'mandatory' => true ] ], $template->getTemplateParams() );
	}

	/**
	 * A malformed (non-serialized) payload does not throw or emit warnings;
	 * getTemplateParams() falls back to null.
	 *
	 * @see https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/12
	 */
	public function testLoadTemplateParamsWithInvalidPayloadDoesNotThrow() {
		$title = $this->insertTemplatePageWithParamsProp(
			'PFTestTemplateInvalidParams01',
			'this is not a serialized value'
		);

		$template = PFTemplate::newFromName( $title->getText() );

		$this->assertNull( $template->getTemplateParams() );
	}

	/**
	 * A payload attempting PHP object injection via unserialize() must not
	 * be instantiated as an object; allowed_classes is disabled so the
	 * result is either false/null, never an instance of the injected class.
	 *
	 * @see https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/129
	 */
	public function testLoadTemplateParamsWithObjectInjectionPayloadIsNotInstantiated() {
		$title = $this->insertTemplatePageWithParamsProp(
			'PFTestTemplateObjectInjectionParams01',
			serialize( new PFTemplateTestInjectionProbe() )
		);

		$template = PFTemplate::newFromName( $title->getText() );

		$this->assertNotInstanceOf( PFTemplateTestInjectionProbe::class, $template->getTemplateParams() );
	}

	private function insertTemplatePageWithParamsProp( string $pageName, string $serializedParams ): Title {
		$title = Title::makeTitle( NS_TEMPLATE, $pageName );
		$this->editPage( $title, 'Template content.' );

		$pageId = $title->getArticleID( IDBAccessObject::READ_LATEST );
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$dbw->upsert(
			'page_props',
			[ 'pp_page' => $pageId, 'pp_propname' => 'PageFormsTemplateParams', 'pp_value' => $serializedParams ],
			[ [ 'pp_page', 'pp_propname' ] ],
			[ 'pp_value' => $serializedParams ],
			__METHOD__
		);

		return $title;
	}
}
