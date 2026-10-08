<?php

use MediaWiki\Extension\PageForms\FormDefinition\FormElement;
use MediaWiki\Extension\PageForms\FormPrinter;
use MediaWiki\Extension\PageForms\FormRender\ElementHandlerException;
use MediaWiki\Extension\PageForms\FormRender\TextHandler;
use MediaWiki\Extension\PageForms\FormRenderContext;

/**
 * @covers \MediaWiki\Extension\PageForms\FormPrinter
 * @covers \MediaWiki\Extension\PageForms\FormRender\ElementHandlerException
 * @group Database
 */
class FormPrinterElementHandlersTest extends MediaWikiIntegrationTestCase {

	/**
	 * @return array<class-string, mixed>
	 */
	private function handlerTable(): array {
		$property = new ReflectionProperty( FormPrinter::class, 'elementHandlers' );
		$property->setAccessible( true );
		return $property->getValue( new FormPrinter() );
	}

	/**
	 * @return list<class-string<FormElement>>
	 */
	private function concreteElementClasses(): array {
		$classes = [];
		foreach ( glob( __DIR__ . '/../../../../src/FormDefinition/*.php' ) as $file ) {
			$class = 'MediaWiki\\Extension\\PageForms\\FormDefinition\\' . basename( $file, '.php' );
			$reflection = new ReflectionClass( $class );
			if ( $reflection->implementsInterface( FormElement::class ) && $reflection->isInstantiable() ) {
				$classes[] = $class;
			}
		}
		return $classes;
	}

	private function newForeignElement(): FormElement {
		return new class() implements FormElement {

			public function toWikitext(): string {
				return '';
			}

			public function toArray(): array {
				return [];
			}
		};
	}

	public function testEveryConcreteFormElementHasAHandler(): void {
		$classes = $this->concreteElementClasses();
		$this->assertNotEmpty( $classes );
		$this->assertEqualsCanonicalizing( $classes, array_keys( $this->handlerTable() ) );
	}

	public function testElementWithoutHandlerIsAnError(): void {
		$element = $this->newForeignElement();
		$printer = new FormPrinter();
		$method = new ReflectionMethod( $printer, 'getElementHandler' );
		$method->setAccessible( true );

		$this->expectException( ElementHandlerException::class );
		$this->expectExceptionMessage( get_class( $element ) );
		$method->invoke( $printer, $element );
	}

	public function testHandlerRejectsAnElementOfAnotherType(): void {
		$element = $this->newForeignElement();

		$this->expectException( ElementHandlerException::class );
		$this->expectExceptionMessage( TextHandler::class );
		( new TextHandler() )->handle( $element, new FormRenderContext() );
	}
}
