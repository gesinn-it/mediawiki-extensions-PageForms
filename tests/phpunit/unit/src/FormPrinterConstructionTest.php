<?php

use MediaWiki\Extension\PageForms\FormDefParser;
use MediaWiki\Extension\PageForms\FormPrinter;
use MediaWiki\Extension\PageForms\InputTypeRegistry;
use MediaWiki\HookContainer\HookContainer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\FormPrinter::__construct
 * @group PF
 */
class FormPrinterConstructionTest extends TestCase {

	private function hookContainer( ?callable $onRun = null ): HookContainer {
		$hookContainer = $this->createMock( HookContainer::class );
		$hookContainer->method( 'run' )->willReturnCallback(
			static function ( $name, $args ) use ( $onRun ) {
				if ( $onRun ) {
					$onRun( $name, $args[0] );
				}
				return true;
			}
		);
		return $hookContainer;
	}

	public function testPrinterIsBuiltFromFakeCollaboratorsWithoutGlobals(): void {
		$registry = new InputTypeRegistry();
		$registry->register( PFTextInput::class );

		$printer = new FormPrinter(
			$registry,
			null, null, null, null,
			$this->createMock( FormDefParser::class ),
			null, null, null, null, null, null,
			$this->hookContainer()
		);

		$this->assertSame( [ 'text' ], $printer->getAllInputTypes() );
	}

	public function testSetupHookRunsOnceWithThePrinterBeingBuilt(): void {
		$calls = [];
		$hookContainer = $this->hookContainer( static function ( $name, $printer ) use ( &$calls ) {
			$calls[] = [ $name, $printer ];
		} );

		$printer = new FormPrinter(
			new InputTypeRegistry(),
			null, null, null, null,
			$this->createMock( FormDefParser::class ),
			null, null, null, null, null, null,
			$hookContainer
		);

		$this->assertCount( 1, $calls );
		$this->assertSame( 'PageForms::FormPrinterSetup', $calls[0][0] );
		$this->assertSame( $printer, $calls[0][1] );
	}
}
