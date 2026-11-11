<?php

use MediaWiki\Extension\PageForms\Hooks\RegistrationHooks;

/**
 * @covers \MediaWiki\Extension\PageForms\Hooks\RegistrationHooks
 * @group Database
 */
class RegistrationHooksTest extends MediaWikiIntegrationTestCase {

	public function testParserFirstCallInitRegistersParserFunctions(): void {
		$parser = $this->getServiceContainer()->getParserFactory()->create();

		$this->assertTrue( ( new RegistrationHooks() )->onParserFirstCallInit( $parser ) );
		$this->assertContains( 'forminput', $parser->getFunctionHooks() );
		$this->assertContains( 'template_display', $parser->getFunctionHooks() );
	}

	public function testCanonicalNamespacesAddsFormNamespaces(): void {
		$list = [];

		( new RegistrationHooks() )->onCanonicalNamespaces( $list );

		$this->assertSame( 'Form', $list[PF_NS_FORM] );
		$this->assertSame( 'Form_talk', $list[PF_NS_FORM_TALK] );
	}

	public function testCanonicalNamespacesEnablesSubpagesForFormTalk(): void {
		global $wgNamespacesWithSubpages;
		$list = [];

		( new RegistrationHooks() )->onCanonicalNamespaces( $list );

		$this->assertTrue( $wgNamespacesWithSubpages[PF_NS_FORM_TALK] );
	}

	public function testResourceLoaderRegisterModulesRegistersMapsModuleWithNonOfflineScript(): void {
		$resourceLoader = $this->getServiceContainer()->getResourceLoader();

		( new RegistrationHooks() )->onResourceLoaderRegisterModules( $resourceLoader );

		$this->assertContains( 'ext.pageforms.maps', $resourceLoader->getModuleNames() );
		$this->assertNotNull( $resourceLoader->getModule( 'ext.pageforms.maps' ) );
	}

	public function testResourceLoaderRegisterModulesUsesOfflineScriptWhenOpenLayersIsLoaded(): void {
		$registry = $this->createStub( ExtensionRegistry::class );
		$registry->method( 'isLoaded' )->willReturnCallback(
			static fn ( string $name ): bool => $name === 'OpenLayers'
		);
		$registered = [];
		$resourceLoader = $this->createMock( \MediaWiki\ResourceLoader\ResourceLoader::class );
		$resourceLoader->method( 'register' )->willReturnCallback(
			static function ( $info ) use ( &$registered ) {
				$registered = $info;
			}
		);

		( new RegistrationHooks( $registry ) )->onResourceLoaderRegisterModules( $resourceLoader );

		$attrs = $registered['ext.pageforms.maps'];
		$this->assertSame( '/libs/PF_maps.offline.js', $attrs['scripts'] );
		$this->assertContains( 'ext.openlayers.main', $attrs['dependencies'] );
		$this->assertFileExists( $attrs['localBasePath'] . $attrs['scripts'] );
	}

}
