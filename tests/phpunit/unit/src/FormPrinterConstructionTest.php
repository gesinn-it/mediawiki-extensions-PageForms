<?php

use MediaWiki\Extension\PageForms\FormDefParser;
use MediaWiki\Extension\PageForms\FormPrinter;
use MediaWiki\Extension\PageForms\FormPrinterFactory;
use MediaWiki\Extension\PageForms\InputTypeRegistry;
use MediaWiki\Extension\PageForms\RenderServices;
use MediaWiki\HookContainer\HookContainer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\FormPrinterFactory
 * @covers \MediaWiki\Extension\PageForms\FormPrinter::__construct
 * @group PF
 */
class FormPrinterConstructionTest extends TestCase {

	private function services( ?callable $onRun = null ): RenderServices {
		$hookContainer = $this->createMock( HookContainer::class );
		$hookContainer->method( 'run' )->willReturnCallback(
			static function ( $name, $args ) use ( $onRun ) {
				if ( $onRun ) {
					$onRun( $name, $args[0] );
				}
				return true;
			}
		);
		$services = $this->createMock( RenderServices::class );
		$services->method( 'hookContainer' )->willReturn( $hookContainer );
		return $services;
	}

	public function testPrinterIsBuiltFromFakeCollaboratorsWithoutGlobals(): void {
		$registry = new InputTypeRegistry();
		$registry->register( PFTextInput::class );

		$printer = FormPrinterFactory::create(
			$registry,
			$this->createMock( FormDefParser::class ),
			null,
			$this->services()
		);

		$this->assertSame( [ 'text' ], $printer->getAllInputTypes() );
	}

	public function testSetupHookRunsOnceWithThePrinterBeingBuilt(): void {
		$calls = [];
		$services = $this->services( static function ( $name, $printer ) use ( &$calls ) {
			$calls[] = [ $name, $printer ];
		} );

		$printer = FormPrinterFactory::create(
			new InputTypeRegistry(), $this->createMock( FormDefParser::class ), null, $services
		);

		$this->assertCount( 1, $calls );
		$this->assertSame( 'PageForms::FormPrinterSetup', $calls[0][0] );
		$this->assertSame( $printer, $calls[0][1] );
	}

	public function testConstructorRunsNoHookWhenItGetsItsParts(): void {
		$calls = [];
		$services = $this->services( static function ( $name ) use ( &$calls ) {
			$calls[] = $name;
		} );
		$parts = FormPrinterFactory::newParts(
			new InputTypeRegistry(), $this->createMock( FormDefParser::class ), null, $services
		);

		new FormPrinter( $parts );

		$this->assertSame( [], $calls );
	}
}
