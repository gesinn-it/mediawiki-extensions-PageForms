<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use IDBAccessObject;
use MediaWiki\Extension\PageForms\Template;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use TemplateTestInjectionProbe;
use Title;

require_once __DIR__ . '/TemplateTestInjectionProbe.php';

/**
 * Integration tests for Template::loadTemplateParams().
 *
 * @covers \MediaWiki\Extension\PageForms\Template
 *
 * @group Database
 */
class TemplateTest extends MediaWikiIntegrationTestCase {

	/**
	 * A valid serialized array payload is unserialized as-is.
	 */
	public function testLoadTemplateParamsWithValidArrayPayload() {
		$title = $this->insertTemplatePageWithParamsProp(
			'PFTestTemplateValidParams01',
			serialize( [ 'Field1' => [ 'mandatory' => true ] ] )
		);

		$template = Template::newFromName( $title->getText() );

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

		$template = Template::newFromName( $title->getText() );

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
			serialize( new TemplateTestInjectionProbe() )
		);

		$template = Template::newFromName( $title->getText() );

		$this->assertNotInstanceOf( TemplateTestInjectionProbe::class, $template->getTemplateParams() );
	}

	private function insertTemplatePageWithParamsProp( string $pageName, string $serializedParams ): Title {
		$title = Title::makeTitle( NS_TEMPLATE, $pageName );
		$this->editPage( $title, 'Template content.' );

		$pageId = $title->getArticleID( IDBAccessObject::READ_LATEST );
		if ( version_compare( MW_VERSION, '1.41', '>=' ) ) {
			$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		} else {
			// MW < 1.41: getConnectionProvider() did not exist; use getDBLoadBalancer()
			$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		}
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
