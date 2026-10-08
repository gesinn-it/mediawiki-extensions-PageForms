<?php

use MediaWiki\Extension\PageForms\FormCounters;
use MediaWiki\Extension\PageForms\FormField;
use MediaWiki\Extension\PageForms\FormFieldExtraHtmlBuilder;
use MediaWiki\Extension\PageForms\FormPlaceholder;
use MediaWiki\Extension\PageForms\TemplateField;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\FormFieldExtraHtmlBuilder
 */
class FormFieldExtraHtmlBuilderTest extends TestCase {

	private $templateField;

	private FormFieldExtraHtmlBuilder $builder;

	protected function setUp(): void {
		parent::setUp();
		$this->templateField = $this->createMock( TemplateField::class );
		$this->builder = new FormFieldExtraHtmlBuilder();
		FormCounters::current()->fieldNum = 0;
	}

	private function build( FormField $field, $value = 'some_value', ?FormCounters $counters = null ): string {
		return $this->builder->build( $field, $value, 'some_field', 'template_example', $counters );
	}

	public function testFieldWithoutSpecialsHasNoExtraHtml(): void {
		$this->assertSame( '', $this->build( FormField::create( $this->templateField ) ) );
	}

	public function testFieldThatHoldsATemplateGetsTheMarkerForIt(): void {
		$field = FormField::create( $this->templateField );
		$field->setHoldsTemplate( true );

		$this->assertSame(
			FormPlaceholder::toHtmlMarker( FormPlaceholder::format( 'template_example', 'some_field' ) ),
			$this->build( $field )
		);
	}

	public function testDisabledFieldKeepsItsValueInAHiddenInput(): void {
		$field = FormField::create( $this->templateField );
		$field->setIsDisabled( true );
		$field->setInputName( 'input_field' );

		$this->assertStringContainsString(
			'type="hidden" value="some_value" name="input_field"', $this->build( $field )
		);
	}

	public function testDisabledFieldWithAnArrayValueJoinsItWithTheDelimiter(): void {
		$field = FormField::create( $this->templateField );
		$field->setIsDisabled( true );
		$field->setInputName( 'PFTestFormFieldInputName01' );
		$field->setFieldArg( 'delimiter', ';' );

		$this->assertStringContainsString(
			'type="hidden" value="a;b" name="PFTestFormFieldInputName01"', $this->build( $field, [ 'a', 'b' ] )
		);
	}

	public function testDisabledFreeTextFieldHasTheFreeTextInput(): void {
		$field = FormField::create( $this->templateField );
		$field->setIsDisabled( true );

		$this->assertStringContainsString(
			'type="hidden" value="!free_text!" name="pf_free_text"',
			$this->builder->build( $field, 'some free text', 'free text', 'template_example' )
		);
	}

	public function testMappedFieldIsMarkedForTheSave(): void {
		$field = FormField::create( $this->templateField );
		$field->setFieldArg( 'mapping template', 'template_name' );

		$this->assertStringContainsString(
			'type="hidden" value="true" name="template_example[map_field][some_field]"', $this->build( $field )
		);
	}

	public function testMappedFieldOfAMultipleInstanceTemplateIsMarkedPerInstance(): void {
		$field = FormField::create( $this->templateField );
		$field->setFieldArg( 'mapping template', 'template_name' );
		$field->setFieldArg( 'part_of_multiple', true );

		$this->assertStringContainsString(
			'type="hidden" value="true" name="template_example[num][map_field][some_field]"',
			$this->build( $field )
		);
	}

	public function testUniqueFieldHasTheScopesInHiddenInputs(): void {
		$field = FormField::create( $this->templateField );
		$field->setFieldArg( 'unique', true );
		$field->setFieldArg( 'unique_for_category', 'Category1' );
		$field->setFieldArg( 'unique_for_namespace', 'Namespace1' );
		$field->setFieldArg( 'unique_for_concept', 'PFTestFormFieldUniqueConceptName01' );

		$html = $this->build( $field );

		$this->assertStringContainsString( 'value="Category1" name="input_0_unique_for_category"', $html );
		$this->assertStringContainsString( 'value="Namespace1" name="input_0_unique_for_namespace"', $html );
		$this->assertStringContainsString(
			'value="PFTestFormFieldUniqueConceptName01" name="input_0_unique_for_concept"', $html
		);
	}

	public function testUniqueFieldHasItsSemanticProperty(): void {
		$this->templateField->method( 'getSemanticProperty' )->willReturn( 'PFTestFormFieldUniqueSemProp01' );
		$field = FormField::create( $this->templateField );
		$field->setFieldArg( 'unique', true );

		$this->assertStringContainsString(
			'type="hidden" value="PFTestFormFieldUniqueSemProp01" name="input_0_unique_property"',
			$this->build( $field )
		);
	}

	public function testUniqueFieldUsesTheFieldNumberOfTheCountersPassedIn(): void {
		$field = FormField::create( $this->templateField );
		$field->setFieldArg( 'unique', true );
		$field->setFieldArg( 'unique_for_category', 'Category1' );
		$counters = new FormCounters();
		$counters->fieldNum = 7;

		$this->assertStringContainsString(
			'name="input_7_unique_for_category"', $this->build( $field, 'some_value', $counters )
		);
	}
}
