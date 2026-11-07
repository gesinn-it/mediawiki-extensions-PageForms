<?php

use MediaWiki\Extension\PageForms\Setup;

/**
 * @covers \MediaWiki\Extension\PageForms\Setup
 */
class SetupTest extends MediaWikiIntegrationTestCase {

	public function testRegisterExtensionReturnsEarlyWhenAlreadyLoaded(): void {
		$this->assertSame( 1, Setup::registerExtension() );
	}

	/**
	 * @dataProvider provideClassAliases
	 */
	public function testEveryOldGlobalClassNameStillResolves( string $alias, string $class ): void {
		$this->assertTrue( class_exists( $alias ), "$alias must still resolve" );
		$this->assertTrue( is_a( $alias, $class, true ), "$alias must resolve to $class" );
	}

	public static function provideClassAliases(): iterable {
		foreach ( Setup::CLASS_ALIASES as $alias => $class ) {
			yield $alias => [ $alias, $class ];
		}
	}

	public function testPFHooksAliasPointsToSetup(): void {
		$this->assertSame( Setup::class, ( new ReflectionClass( 'PFHooks' ) )->getName() );
	}

	public function testInitializeRegistersAdminLinksHandler(): void {
		Setup::initialize();

		$this->assertTrue( $this->getServiceContainer()->getHookContainer()->isRegistered( 'AdminLinks' ) );
		$this->assertNotNull( $GLOBALS['wgPageFormsFormPrinter'] );
	}

}
