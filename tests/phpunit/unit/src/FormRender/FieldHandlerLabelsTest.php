<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\FieldValueResolver;
use MediaWiki\Extension\PageForms\FormField;
use MediaWiki\Extension\PageForms\FormFieldExtraHtmlBuilder;
use MediaWiki\Extension\PageForms\FormFieldHtmlBuilder;
use MediaWiki\Extension\PageForms\FormRender\FieldHandler;
use MediaWiki\Extension\PageForms\MappingLabels;
use PHPUnit\Framework\TestCase;

/**
 * The step of FieldHandler that shows the value of a field with a mapping as its label.
 *
 * @covers \MediaWiki\Extension\PageForms\FormRender\FieldHandler
 */
class FieldHandlerLabelsTest extends TestCase {

	/**
	 * @param string[] $mappingArgs The field arguments that are set, such as "mapping template"
	 * @param bool $isList
	 * @return FormField
	 */
	private function field( array $mappingArgs, bool $isList = false ): FormField {
		$field = $this->createMock( FormField::class );
		$field->method( 'hasFieldArg' )->willReturnCallback(
			static fn ( $key ): bool => in_array( $key, $mappingArgs, true )
		);
		$field->method( 'getUseDisplayTitle' )->willReturn( false );
		$field->method( 'getFieldArg' )->willReturn( ';' );
		$field->method( 'isList' )->willReturn( $isList );
		return $field;
	}

	/**
	 * @param FormField $field
	 * @param string|array|null $value
	 * @return string|array|null
	 */
	private function valueAsLabels( FormField $field, $value ) {
		$handler = new FieldHandler(
			$this->createMock( FormFieldHtmlBuilder::class ),
			$this->createMock( MappingLabels::class ),
			$this->createMock( FieldValueResolver::class ),
			$this->createMock( FormFieldExtraHtmlBuilder::class )
		);
		$method = new ReflectionMethod( $handler, 'valueAsLabels' );
		$method->setAccessible( true );
		return $method->invoke( $handler, $field, $value );
	}

	public function testStringValueOfAMappedFieldIsShownAsItsLabel(): void {
		$field = $this->field( [ 'mapping template' ] );
		$field->expects( $this->once() )->method( 'valueStringToLabels' )
			->with( 'b', null )->willReturn( 'Label b' );

		$this->assertSame( 'Label b', $this->valueAsLabels( $field, 'b' ) );
	}

	public function testStringValueOfAListFieldIsMappedWithTheDelimiter(): void {
		$field = $this->field( [ 'mapping property' ], true );
		$field->expects( $this->once() )->method( 'valueStringToLabels' )
			->with( 'a;c', ';' )->willReturn( 'Label a;Label c' );

		$this->assertSame( 'Label a;Label c', $this->valueAsLabels( $field, 'a;c' ) );
	}

	public function testArrayValueIsReturnedUnchanged(): void {
		$field = $this->field( [ 'mapping template' ], true );
		$field->expects( $this->never() )->method( 'valueStringToLabels' );
		$value = [ 'a', 'c' ];

		$this->assertSame( $value, $this->valueAsLabels( $field, $value ) );
	}

	public function testFieldWithoutMappingIsUnaffected(): void {
		$field = $this->field( [] );
		$field->expects( $this->never() )->method( 'valueStringToLabels' );

		$this->assertSame( 'b', $this->valueAsLabels( $field, 'b' ) );
		$this->assertNull( $this->valueAsLabels( $field, null ) );
		$this->assertSame( [ 'a' ], $this->valueAsLabels( $field, [ 'a' ] ) );
	}

	public function testEmptyValueOfAMappedFieldIsNotMapped(): void {
		$field = $this->field( [ 'mapping template' ] );
		$field->expects( $this->never() )->method( 'valueStringToLabels' );

		$this->assertSame( '', $this->valueAsLabels( $field, '' ) );
	}
}
