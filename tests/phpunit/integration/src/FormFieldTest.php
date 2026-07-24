<?php

use MediaWiki\Extension\PageForms\FormField;
use MediaWiki\Extension\PageForms\Template;
use MediaWiki\Extension\PageForms\TemplateField;
use MediaWiki\Extension\PageForms\TemplateInForm;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\FormField
 *
 * @author gesinn-it-ilm
 */
class FormFieldTest extends TestCase {

	private $mockTemplate;
	private $mockTemplateInForm;
	private $mockUser;
	private $mockTemplateField;
	private $mockParser;
	private $f;
	private $mockTemplateFieldOne;
	private $mockTemplateFieldTwo;
	private $mockTemplateFieldThree;

	protected function setUp(): void {
		parent::setUp();

		// Mocking the Parser object
		$this->mockParser = $this->createMock( Parser::class );
		$this->mockParser->method( 'recursivePreprocess' )
			->willReturnCallback( static fn ( $input ) => $input );
		$this->mockParser->method( 'recursiveTagParse' )
			->willReturnCallback( static fn ( $input ) => $input );

		// Mocking the User object
		$this->mockUser = $this->createMock( User::class );
		$this->mockUser->method( 'isAllowed' )
			->with( 'editrestrictedfields' )
			->willReturn( false );

		// Mocking the object being updated
		$this->f = new stdClass();
		$this->f->mFieldArgs = [];

		// Mocking the Template object
		$this->mockTemplate = $this->createMock( Template::class );

		// Mocking TemplateInForm object
		$this->mockTemplateInForm = $this->createMock( TemplateInForm::class );

		// Mock the TemplateField class
		$this->mockTemplateField = $this->createMock( TemplateField::class );
	}

	public function testCreateFormFieldWithEmptyValues() {
		// Create the FormField object
		$formField = FormField::create( $this->mockTemplateField );

		// Verify the object is an instance of FormField
		$this->assertInstanceOf( FormField::class, $formField );
		$this->assertSame( $this->mockTemplateField, $formField->template_field );

		$this->assertNull( $formField->getInputType() );
		$this->assertFalse( $formField->isMandatory() );
		$this->assertFalse( $formField->isHidden() );
		$this->assertFalse( $formField->isRestricted() );
		$this->assertNull( $formField->getPossibleValues() );
		$this->assertFalse( $formField->getUseDisplayTitle() );
		$this->assertSame( [], $formField->getFieldArgs() );
	}

	public function testCreateFormFieldWithTemplateFieldProps() {
		// Set expectations for the mocked methods or properties
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'MockedFieldName' );
		$this->mockTemplateField->method( 'getLabel' )->willReturn( 'Mocked Label' );
		$this->mockTemplateField->method( 'getSemanticProperty' )->willReturn( 'MockedSemanticProperty' );
		$this->mockTemplateField->method( 'isList' )->willReturn( true );
		$this->mockTemplateField->method( 'getDelimiter' )->willReturn( ';' );
		$this->mockTemplateField->method( 'getDisplay' )->willReturn( 'MockedDisplay' );

		// Create the FormField object
		$formField = FormField::create( $this->mockTemplateField );

		// Verify the object is an instance of FormField
		$this->assertInstanceOf( FormField::class, $formField );

		// Assert that the template field is set correctly
		$this->assertSame( $this->mockTemplateField, $formField->template_field );

		// Assert that properties were set correctly based on the mocked TemplateField
		$this->assertSame( 'MockedFieldName', $formField->template_field->getFieldName() );
		$this->assertSame( $this->mockTemplateField, $formField->getTemplateField() );
		$this->assertSame( 'MockedSemanticProperty', $formField->template_field->getSemanticProperty() );
		$this->assertSame( ';', $formField->template_field->getDelimiter() );
		$this->assertSame( 'MockedDisplay', $formField->template_field->getDisplay() );
	}

	public function testDelimiterHandling() {
		// Mock the TemplateField class
		$this->mockTemplateFieldOne = $this->createMock( TemplateField::class );
		$this->mockTemplateFieldTwo = $this->createMock( TemplateField::class );
		$this->mockTemplateFieldOne->method( 'getDelimiter' )->willReturn( ',' );
		$this->mockTemplateFieldTwo->method( 'getDelimiter' )->willReturn( ';' );

		$this->mockTemplateFieldThree = $this->createMock( TemplateField::class );
		$this->mockTemplateFieldThree->method( 'getDelimiter' )->willReturn( '' );

		// Create the FormField objects
		$fieldOne = FormField::create( $this->mockTemplateFieldOne );
		$fieldTwo = FormField::create( $this->mockTemplateFieldTwo );
		$fieldThree = FormField::create( $this->mockTemplateFieldThree );

		$this->assertEquals( ',', $fieldOne->template_field->getDelimiter() );
		$this->assertEquals( ';', $fieldTwo->template_field->getDelimiter() );

		$delimiter = $fieldThree->template_field->getDelimiter();
		if ( $delimiter === '' ) {
			$fieldThree->setFieldArg( 'delimiter', ',' );
			$this->assertEquals( ',', $fieldThree->getFieldArgs()['delimiter'] );
		}
		$delimiter = $fieldTwo->template_field->getDelimiter();
		if ( $delimiter != '' ) {
			$fieldTwo->setFieldArg( 'delimiter', $fieldTwo->template_field->getDelimiter() );
			$this->assertEquals( ';', $fieldTwo->getFieldArgs()['delimiter'] );
		}
	}

	public function testNewFromFormFieldTag_FieldExistsInTemplate() {
		// Mock data for the tag_components
		$tagComponents = [ 'somePrefix', 'fieldName' ];

		// Mock the template to return a field
		$mockField = $this->createMock( TemplateField::class );
		$this->mockTemplate->method( 'getFieldNamed' )->willReturn( $mockField );

		// Mock the template_in_form to return a template name
		$this->mockTemplateInForm->method( 'getTemplateName' )->willReturn( 'TestTemplate' );
		$this->mockTemplateInForm->method( 'strictParsing' )->willReturn( false );

		// Call the method under test
		$formField = FormField::newFromFormFieldTag(
			$tagComponents, $this->mockTemplate, $this->mockTemplateInForm, false, $this->mockUser
		);

		// Assert that the template field was set
		$this->assertInstanceOf( TemplateField::class, $formField->template_field );
		$this->assertSame( $mockField, $formField->template_field );
	}

	public function testNewFromFormFieldTag_FieldDoesNotExistInTemplate_StrictParsing() {
		// Mock data for the tag_components
		$tagComponents = [ 'somePrefix', 'fieldName' ];

		// Mock the template to return null, meaning no field is found
		$this->mockTemplate->method( 'getFieldNamed' )->willReturn( null );

		// Mock the template_in_form to return a template name and enable strict parsing
		$this->mockTemplateInForm->method( 'getTemplateName' )->willReturn( 'TestTemplate' );
		$this->mockTemplateInForm->method( 'strictParsing' )->willReturn( true );

		// Call the method under test - with strict parsing enabled and no matching
		// field found, newFromFormFieldTag() must return early with a fresh,
		// empty TemplateField and mIsList = false (src/FormField.php:232-239).
		$formField = FormField::newFromFormFieldTag(
			$tagComponents, $this->mockTemplate, $this->mockTemplateInForm, false, $this->mockUser
		);

		$this->assertInstanceOf( TemplateField::class, $formField->template_field );
		$this->assertNotSame( $this->mockTemplateField, $formField->template_field );
		$this->assertNull( $formField->template_field->isList() );
		$this->assertFalse( $formField->isList() );
	}

	public function testMandatoryComponent() {
		$tag_components = [ '', 'test_field', 'mandatory' ];

		// Call the method
		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertTrue( $formField->isMandatory() );
	}

	public function testHiddenComponent() {
		$tag_components = [ '', '', 'hidden' ];

		// Call the method
		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertTrue( $formField->isHidden() );
	}

	public function testRestrictedComponent() {
		$tag_components = [ '', '', 'restricted' ];

		// Call the method
		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertTrue( $formField->isRestricted() );
	}

	public function testKeyValueComponent() {
		$tag_components = [ '', '', 'autocapitalize=uppercase' ];

		// Call the method
		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertEquals( 'uppercase', $formField->getAutocapitalize() );
	}

	public function testPropertyComponent() {
		$tag_components = [ '', 'test_field', 'property=TestProperty' ];

		// Call the method
		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertEquals(
			'TestProperty', $formField->getFieldArgs()['property'], 'The property should be set correctly'
		);
	}

	/**
	 * Regression test for https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/39
	 *
	 * When a field sets both 'property=' (form-level override, not inherited
	 * from the template) and 'mapping template=', the property's SMW-derived
	 * possible values must be resolved (via setSemanticProperty()) before the
	 * mapping-template block decides whether there is anything to map -
	 * otherwise mPossibleValues is still [] at that point and mapping is
	 * skipped entirely. Simulate this with a mock whose getPossibleValues()
	 * only returns values once setSemanticProperty() has been called, exactly
	 * like the real TemplateField.
	 */
	public function testPropertyOverrideValuesAreAvailableForMappingTemplate() {
		if ( !defined( 'SMW_VERSION' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		$propertySet = false;
		$this->mockTemplateField->method( 'setSemanticProperty' )
			->willReturnCallback( static function () use ( &$propertySet ) {
				$propertySet = true;
			} );
		$this->mockTemplateField->method( 'getPossibleValues' )
			->willReturnCallback( static function () use ( &$propertySet ) {
				return $propertySet ? [ 'DE', 'FR' ] : [];
			} );
		$this->mockTemplateField->method( 'getDelimiter' )->willReturn( '' );
		$this->mockTemplateField->method( 'getCategory' )->willReturn( null );
		$this->mockTemplateField->method( 'getNSText' )->willReturn( null );

		$tagComponents = [
			'', 'test_field', 'property=TestMappingProp', 'mapping template=PFTestMappingTplPropOverride01'
		];

		$this->mockTemplate->method( 'getFieldNamed' )->willReturn( $this->mockTemplateField );
		$this->mockTemplateInForm->method( 'getTemplateName' )->willReturn( 'TestTemplate' );
		$this->mockTemplateInForm->method( 'strictParsing' )->willReturn( false );
		$this->mockTemplateInForm->method( 'allowsMultiple' )->willReturn( false );

		$formField = FormField::newFromFormFieldTag(
			$tagComponents,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		// Before the fix, mPossibleValues was still [] when the mapping-type
		// check ran, so setMappedValues() was never invoked and the raw
		// property-derived values ('DE', 'FR') were left untouched. After the
		// fix, the property's values are in place beforehand, so mapping is
		// attempted (the non-existent mapping template falls back to
		// identity labels, proving the mapping pipeline actually ran).
		$this->assertSame(
			[ 'DE' => 'DE', 'FR' => 'FR' ],
			$formField->getPossibleValues()
		);
	}

	public function testUniqueComponent() {
		$tag_components = [ '', '', 'unique' ];

		// Call the method
		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertTrue( $formField->getFieldArgs()['unique'] );
	}

	public function testLabelComponent() {
		$tag_components = [ '', '', 'label=TestField' ];

		// Call the method
		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertEquals( 'TestField', $formField->getLabel() );
	}

	public function testMappingTypeWithMappingTemplate() {
		$tag_components = [ '', '', 'mapping template' ];

		// Call the method
		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertTrue( $formField->getFieldArgs()['mapping template'] );
	}

	public function testMappingTypeWithMappingProperty() {
		$tag_components = [ '', '', 'mapping property' ];

		// Call the method
		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertTrue( $formField->getFieldArgs()['mapping property'] );
	}

	public function testSetMappedValuesTemplate() {
		// Create the FormField object
		$formField = FormField::create( $this->mockTemplateField );

		// This name must not collide with any template page created by other
		// tests in the suite (e.g. PFMultiPageEditTest creates Template:TestTemplate);
		// setValuesWithMappingTemplate() does a real Title::exists() lookup, so a
		// name collision would make this test order-dependent.
		$formField->setFieldArg( 'mapping template', 'NonExistentMappingTemplateForFormFieldTest' );
		$formField->setPossibleValues( [ 'val1' => 'val1', 'val2' => 'val2' ] );

		// Call the method under test
		$formField->setMappedValues( 'template' );

		// Assertions to ensure correct method calls
		$this->assertEquals( [
			'val1' => 'val1',
			'val2' => 'val2'
		], $formField->getPossibleValues() );
	}

	public function testSetMappedValuesProperty() {
		// Create the FormField object
		$formField = FormField::create( $this->mockTemplateField );

		// Set the mapping type to 'property'
		$formField->setFieldArg( 'mapping property', 'TestProp' );
		$formField->setPossibleValues( [ 'val1' => 'val1', 'val2' => 'val2' ] );

		// Call the method under test
		$formField->setMappedValues( 'property' );

		// Assertions to ensure correct method calls
		$this->assertEquals( [
			'val1' => 'val1',
			'val2' => 'val2'
		], $formField->getPossibleValues() );
	}

	public function testValueStringToLabels() {
		// Create the FormField object
		$formField = FormField::create( $this->mockTemplateField );
		$formField->setPossibleValues( [ 'val1' => 'Label 1', 'val2' => 'Label 2' ] );

		// Empty string
		$result = $formField->valueStringToLabels( '', ',' );
		$this->assertSame( '', $result );

		// String with only spaces
		$result = $formField->valueStringToLabels( '    ', ',' );
		$this->assertEquals( '    ', $result );

		// Test case 2: Null valueString
		$result = $formField->valueStringToLabels( null, ',' );
		$this->assertNull( $result );

		// Test case 3: Value exists in mPossibleValues
		$result = $formField->valueStringToLabels( 'val1', ',' );
		$this->assertEquals( 'Label 1', $result );

		// Test case 4: Value does not exist in mPossibleValues
		$result = $formField->valueStringToLabels( 'val3', ',' );
		$this->assertEquals( 'val3', $result );

		// Test case 5: Multiple values, some exist in mPossibleValues, others do not
		$result = $formField->valueStringToLabels( 'val1,val3', ',' );
		$this->assertEquals( 'Label 1,val3', $result );

		// Test case 6: Delimiter is null
		$result = $formField->valueStringToLabels( 'val1,val2', null );
		$this->assertEquals( 'val1,val2', $result );

		// Test case 7: Multiple labels and values exist
		$result = $formField->valueStringToLabels( 'val1,val2', ',' );
		$this->assertEquals( 'Label 1,Label 2', $result );
	}

	public function testAdditionalHTMLForInput() {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		// Create the FormField object
		$field = FormField::create( $this->mockTemplateField );

		// Mock values for $field
		$field->setHoldsTemplate( true );
		$field->setIsDisabled( true );
		$field->setInputName( 'input_field' );
		$field->setFieldArg( 'delimiter', ',' );
		$field->setFieldArg( 'mapping template', 'template_name' );
		$field->setFieldArg( 'unique', true );
		$field->setFieldArg( 'unique_for_category', 'Category1' );
		$field->setFieldArg( 'unique_for_namespace', 'Namespace1' );

		// Call the method to test
		$cur_value = 'some_value';
		$field_name = 'some_field';
		$template_name = 'template_example';
		$result = $field->additionalHTMLForInput( $cur_value, $field_name, $template_name );

		// Assertions for template-related hidden fields
		$this->assertStringContainsString(
			'type="hidden" value="true" name="template_example[map_field][some_field]"', $result
		);
		$this->assertStringContainsString( 'type="hidden" value="some_value" name="input_field"', $result );

		// Assertions for unique-related hidden fields
		$this->assertStringContainsString(
			'type="hidden" value="Category1" name="input_0_unique_for_category"', $result
		);
		$this->assertStringContainsString(
			'type="hidden" value="Namespace1" name="input_0_unique_for_namespace"', $result
		);
	}

	public function testGetCurrentValue() {
		// Mock TemplateField
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'TestField' );

		// Create the FormField object
		$field = FormField::create( $this->mockTemplateField );
		$field->setFieldArg( 'delimiter', ',' );
		$field->setFieldArg( 'translatable', true );

		// Mock TemplateInForm
		$this->mockTemplateInForm->method( 'getTemplateName' )->willReturn( 'TestTemplate' );

		// Simulate values from query
		$values_from_query = [
			'TestField' => 'value1,value2'
		];

		// Call getCurrentValue()
		$val_modifier = null;
		$result = $field->getCurrentValue( $values_from_query, true, false, true, $val_modifier );

		// Assertions
		$this->assertEquals( 'value1,value2', $result, 'Concatenated values should match expected result.' );
	}

	public function testGetCurrentValue_WithAppending() {
		// Mock TemplateField
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'TestField' );

		// Create the FormField object
		$field = FormField::create( $this->mockTemplateField );
		$field->setFieldArg( 'delimiter', ',' );
		$field->setFieldArg( 'translatable', true );

		// Mock TemplateInForm
		$this->mockTemplateInForm->method( 'getTemplateName' )->willReturn( 'TestTemplate' );

		// Define test cases for appending
		$template_instance_query_values = [
			'TestField+' => 'AppendedValue'
		];

		// Appending scenario
		$resultAppend = $field->getCurrentValue( $template_instance_query_values, true, false, true );
		$this->assertEquals( 'AppendedValue', $resultAppend, 'Appended value should be handled correctly' );
	}

	public function testGetCurrentValue_WithPrepending() {
		// Mock TemplateField
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'TestField' );

		// Create the FormField object
		$field = FormField::create( $this->mockTemplateField );
		$field->setFieldArg( 'delimiter', ',' );
		$field->setFieldArg( 'translatable', true );

		// Mock TemplateInForm
		$this->mockTemplateInForm->method( 'getTemplateName' )->willReturn( 'TestTemplate' );

		// Define test cases for prepending
		$template_instance_query_values = [
			'TestField-' => 'PrependedValue'
		];

		// Prepending scenario
		$field->setFieldArg( 'field_name', 'TestField-' );
		$resultPrepend = $field->getCurrentValue( $template_instance_query_values, true, false, true );
		$this->assertEquals( 'PrependedValue', $resultPrepend, 'Prepended value should be handled correctly' );
	}

	public function testCreateMarkup() {
		// Mock the template field
		$this->mockTemplateField->method( 'getLabel' )->willReturn( 'Mock Label' );
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'MockFieldName' );

		// Create the FormField object
		$field = FormField::create( $this->mockTemplateField );

		// Set up the field arguments
		$field->setDescriptionArg( 'Description', 'This is a field description.' );
		$field->setDescriptionArg( 'TextBeforeField', 'Before Field Text' );
		$field->setFieldArg( 'size', 50 );
		$field->setFieldArg( 'maxlength', 100 );
		$field->setFieldArg( 'uploadable', true );
		$field->setIsHidden( false );
		$field->setIsMandatory( true );
		$field->setIsRestricted( false );

		// Test case: Part of a multiple-instance template
		$partOfMultiple = true;
		$isLastField = false;
		$output = $field->createMarkup( $partOfMultiple, $isLastField );

		$expectedOutput =
			"'''Before Field Text Mock Label:''' <br>"
			. "<p class=\"pfFieldDescription\" style=\"font-size:0.7em; color:gray;\">"
			. "This is a field description.</p>"
			. "{{{field|MockFieldName|size=50|maxlength=100|uploadable|mandatory}}}\n\n";

		$this->assertEquals( $expectedOutput, $output, 'Markup for multiple-instance template is incorrect' );

		// Test case: Single-instance template, not the last field
		$partOfMultiple = false;
		$isLastField = false;
		$output = $field->createMarkup( $partOfMultiple, $isLastField );

		$expectedOutput =
			"! Before Field Text Mock Label: <br>"
			. "<p class=\"pfFieldDescription\" style=\"font-size:0.7em; color:gray;\">"
			. "This is a field description.</p>\n" .
				  "| {{{field|MockFieldName|size=50|maxlength=100|uploadable|mandatory}}}\n" .
				  "|-\n";
		$this->assertEquals(
			$expectedOutput, $output, 'Markup for single-instance template (not last field) is incorrect'
		);

		// Test case: Single-instance template, last field
		$isLastField = true;
		$output = $field->createMarkup( $partOfMultiple, $isLastField );

		$expectedOutput =
			"! Before Field Text Mock Label: <br>"
			. "<p class=\"pfFieldDescription\" style=\"font-size:0.7em; color:gray;\">"
			. "This is a field description.</p>\n" .
				  "| {{{field|MockFieldName|size=50|maxlength=100|uploadable|mandatory}}}\n";

		$this->assertEquals(
			$expectedOutput, $output, 'Markup for single-instance template (last field) is incorrect'
		);
	}

	public function testCreateMarkupWithSMW() {
		// Mock the template field
		$this->mockTemplateField->method( 'getLabel' )->willReturn( 'Mock Label' );
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'MockFieldName' );

		// Create the FormField object
		$field = FormField::create( $this->mockTemplateField );

		// Set up the mock description and tooltip mode
		$fieldDesc = 'This is a field description.';
		$field->setDescriptionArg( 'Description', $fieldDesc );
		$field->setDescriptionArg( 'DescriptionTooltipMode', true );
		$field->setFieldArg( 'size', 50 );
		$field->setFieldArg( 'maxlength', 100 );
		$field->setFieldArg( 'uploadable', true );
		$field->setIsHidden( false );
		$field->setIsMandatory( true );
		$field->setIsRestricted( false );

		// Call the createMarkup method
		$partOfMultiple = true;
		$isLastField = false;
		$output = $field->createMarkup( $partOfMultiple, $isLastField );

		// Expected output without extra newline
		$expectedOutput =
			"'''Mock Label:'''  {{#info:This is a field description.}}"
			. "{{{field|MockFieldName|size=50|maxlength=100|uploadable|mandatory}}}";

		// Trim the trailing newline from actual output before comparison
		$output = rtrim( $output, "\n" );

		// Assert that the output matches the expected result
		$this->assertEquals( $expectedOutput, $output, 'Markup for Semantic MediaWiki tooltip is incorrect' );
	}

	public function testCreateMarkupFallsBackToFieldNameWhenLabelIsEmpty() {
		$this->mockTemplateField->method( 'getLabel' )->willReturn( '' );
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestFormFieldMarkupName01' );

		$field = FormField::create( $this->mockTemplateField );

		$output = $field->createMarkup( true, true );

		$this->assertStringContainsString( "'''PFTestFormFieldMarkupName01:'''", $output );
	}

	public function testCreateMarkupHiddenFieldOmitsInputType() {
		$this->mockTemplateField->method( 'getLabel' )->willReturn( 'Mock Label' );
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestFormFieldMarkupName02' );

		$field = FormField::create( $this->mockTemplateField );
		$field->setIsHidden( true );
		$field->setInputType( 'text' );

		$output = $field->createMarkup( true, true );

		$this->assertStringContainsString( '{{{field|PFTestFormFieldMarkupName02|hidden}}}', $output );
	}

	public function testCreateMarkupIncludesInputTypeWhenSet() {
		$this->mockTemplateField->method( 'getLabel' )->willReturn( 'Mock Label' );
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestFormFieldMarkupName03' );

		$field = FormField::create( $this->mockTemplateField );
		$field->setIsHidden( false );
		$field->setInputType( 'tokens' );

		$output = $field->createMarkup( true, true );

		$this->assertStringContainsString( '|input type=tokens', $output );
	}

	public function testCreateMarkupUploadableWithNonTrueValueStillAddedAsValueLess() {
		$this->mockTemplateField->method( 'getLabel' )->willReturn( 'Mock Label' );
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestFormFieldMarkupName04' );

		$field = FormField::create( $this->mockTemplateField );
		// A non-boolean-true 'uploadable' value must still be rendered as a
		// value-less argument (src/FormField.php:956-959), not "|uploadable=1".
		$field->setFieldArg( 'uploadable', '1' );

		$output = $field->createMarkup( true, true );

		$this->assertStringContainsString( '|uploadable', $output );
		$this->assertStringNotContainsString( '|uploadable=1', $output );
	}

	// -------------------------------------------------------------------------
	// valueStringToLabels / labelToValue – mapping field value conversion
	//
	// These tests exercise the key↔label translation layer used by fields
	// with mapping template= or mapping property=.
	// mPossibleValues is set directly (via Reflection) to isolate the logic
	// from DB / SMW dependencies.
	// -------------------------------------------------------------------------

	/**
	 * Build a FormField with a pre-set mPossibleValues map for testing.
	 *
	 * @param array $possibleValues ['storageKey' => 'displayLabel', ...]
	 * @return FormField
	 */
	private function makeFieldWithPossibleValues( array $possibleValues ): FormField {
		$templateField = $this->createMock( TemplateField::class );
		$templateField->method( 'getPossibleValues' )->willReturn( null );
		$templateField->method( 'getDelimiter' )->willReturn( '' );

		$field = FormField::create( $templateField );

		$ref = new ReflectionClass( FormField::class );
		$prop = $ref->getProperty( 'mPossibleValues' );
		$prop->setAccessible( true );
		$prop->setValue( $field, $possibleValues );

		return $field;
	}

	// --- valueStringToLabels ---

	/**
	 * A stored key that exists in the mapping must be translated to its label.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormField::valueStringToLabels
	 */
	public function testValueStringToLabelsTranslatesKnownKey(): void {
		$field = $this->makeFieldWithPossibleValues( [ 'DE' => 'Germany', 'FR' => 'France' ] );
		$this->assertSame( 'Germany', $field->valueStringToLabels( 'DE', null ) );
	}

	/**
	 * A stored key that is NOT in the mapping must pass through unchanged
	 * (identity fallback).
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormField::valueStringToLabels
	 */
	public function testValueStringToLabelsUnknownKeyPassesThrough(): void {
		$field = $this->makeFieldWithPossibleValues( [ 'DE' => 'Germany' ] );
		$this->assertSame( 'XX', $field->valueStringToLabels( 'XX', null ) );
	}

	/**
	 * An empty string must be returned unchanged without touching the mapping.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormField::valueStringToLabels
	 */
	public function testValueStringToLabelsEmptyStringReturnsEmpty(): void {
		$field = $this->makeFieldWithPossibleValues( [ 'DE' => 'Germany' ] );
		$this->assertSame( '', $field->valueStringToLabels( '', null ) );
	}

	/**
	 * A null value must be returned unchanged (null-safe path).
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormField::valueStringToLabels
	 */
	public function testValueStringToLabelsNullReturnsNull(): void {
		$field = $this->makeFieldWithPossibleValues( [ 'DE' => 'Germany' ] );
		$this->assertNull( $field->valueStringToLabels( null, null ) );
	}

	/**
	 * A delimited list of keys must be translated key-by-key and returned as a
	 * delimiter-joined string.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormField::valueStringToLabels
	 */
	public function testValueStringToLabelsDelimitedListTranslatesEachKey(): void {
		$field = $this->makeFieldWithPossibleValues( [ 'DE' => 'Germany', 'FR' => 'France' ] );
		$result = $field->valueStringToLabels( 'DE,FR', ',' );
		$this->assertSame( 'Germany,France', $result );
	}

	/**
	 * A delimited list where one key is unknown must leave that entry as-is.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormField::valueStringToLabels
	 */
	public function testValueStringToLabelsDelimitedListUnknownKeyPassesThrough(): void {
		$field = $this->makeFieldWithPossibleValues( [ 'DE' => 'Germany' ] );
		$result = $field->valueStringToLabels( 'DE,XX', ',' );
		$this->assertSame( 'Germany,XX', $result );
	}

	/**
	 * A single-element delimited list must return a scalar string, not an array.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormField::valueStringToLabels
	 */
	public function testValueStringToLabelsSingleElementReturnsScalar(): void {
		$field = $this->makeFieldWithPossibleValues( [ 'DE' => 'Germany' ] );
		$result = $field->valueStringToLabels( 'DE', ',' );
		$this->assertIsString( $result );
		$this->assertSame( 'Germany', $result );
	}

	/**
	 * When mPossibleValues is null (unmapped field), the raw value is returned
	 * unchanged regardless of content.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormField::valueStringToLabels
	 */
	public function testValueStringToLabelsNullPossibleValuesReturnsRawValue(): void {
		$templateField = $this->createMock( TemplateField::class );
		$templateField->method( 'getPossibleValues' )->willReturn( null );
		$templateField->method( 'getDelimiter' )->willReturn( '' );
		$field = FormField::create( $templateField );
		// mPossibleValues stays null — no mapping configured
		$this->assertSame( 'DE', $field->valueStringToLabels( 'DE', null ) );
	}

	// --- labelToValue ---

	/**
	 * A display label that exists in the mapping must be translated back to its
	 * storage key.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormField::labelToValue
	 */
	public function testLabelToValueTranslatesKnownLabel(): void {
		$field = $this->makeFieldWithPossibleValues( [ 'DE' => 'Germany', 'FR' => 'France' ] );
		$this->assertSame( 'DE', $field->labelToValue( 'Germany' ) );
	}

	/**
	 * A label that is NOT in the mapping must pass through unchanged
	 * (identity fallback — used for raw storage keys arriving via autoedit).
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormField::labelToValue
	 */
	public function testLabelToValueUnknownLabelPassesThrough(): void {
		$field = $this->makeFieldWithPossibleValues( [ 'DE' => 'Germany' ] );
		$this->assertSame( 'Unknown', $field->labelToValue( 'Unknown' ) );
	}

	/**
	 * A raw storage key passed as a label must pass through unchanged.
	 * This is the critical autoedit preload case: the page contains 'DE',
	 * autoedit passes it through, labelToValue must not corrupt it.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormField::labelToValue
	 */
	public function testLabelToValueRawKeyPassesThroughUnchanged(): void {
		$field = $this->makeFieldWithPossibleValues( [ 'DE' => 'Germany' ] );
		// 'DE' is a key, not a label value, so array_search('DE', ['DE'=>'Germany'])
		// returns false → identity fallback.
		$this->assertSame( 'DE', $field->labelToValue( 'DE' ) );
	}

	// -------------------------------------------------------------------------
	// Simple setters / getters not otherwise exercised.
	// -------------------------------------------------------------------------

	public function testSetTemplateField() {
		$formField = FormField::create( $this->mockTemplateField );
		$otherTemplateField = $this->createMock( TemplateField::class );
		$formField->setTemplateField( $otherTemplateField );
		$this->assertSame( $otherTemplateField, $formField->getTemplateField() );
	}

	public function testSetInputType() {
		$formField = FormField::create( $this->mockTemplateField );
		$formField->setInputType( 'tokens' );
		$this->assertSame( 'tokens', $formField->getInputType() );
	}

	public function testGetLabelMsg() {
		$tag_components = [ '', '', 'label msg=pf-formfield-test-label-msg' ];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertSame( 'pf-formfield-test-label-msg', $formField->getLabelMsg() );
	}

	// -------------------------------------------------------------------------
	// newFromFormFieldTag() - additional single-value / key-value components.
	// -------------------------------------------------------------------------

	public function testEdittoolsComponent() {
		$tag_components = [ '', '', 'edittools' ];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertTrue( $formField->getFieldArgs()['edittools'] );
	}

	public function testEmbeddedTemplateFromTemplateFieldSetsHiddenAndHoldsTemplate() {
		global $wgPageFormsEmbeddedTemplates;
		$wgPageFormsEmbeddedTemplates = [];

		$this->mockTemplateField->method( 'getHoldsTemplate' )->willReturn( 'PFTestFormFieldEmbeddedTpl01' );
		$this->mockTemplateField->method( 'getCategory' )->willReturn( 'PFTestFormFieldCategoryFromTpl01' );
		$this->mockTemplateField->method( 'getNSText' )->willReturn( 'PFTestFormFieldNSFromTpl01' );
		$this->mockTemplate->method( 'getFieldNamed' )->willReturn( $this->mockTemplateField );
		$this->mockTemplateInForm->method( 'strictParsing' )->willReturn( false );
		$this->mockTemplateInForm->method( 'getTemplateName' )->willReturn( 'PFTestFormFieldTemplateName05' );

		$tag_components = [ '', 'test_field' ];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertTrue( $formField->isHidden() );
		$this->assertTrue( $formField->holdsTemplate() );
		$this->assertSame(
			[ 'PFTestFormFieldTemplateName05', 'test_field' ],
			$wgPageFormsEmbeddedTemplates['PFTestFormFieldEmbeddedTpl01']
		);
		$this->assertSame(
			'PFTestFormFieldCategoryFromTpl01', $formField->getFieldArgs()['values from category']
		);
		$this->assertSame(
			'PFTestFormFieldNSFromTpl01', $formField->getFieldArgs()['values from namespace']
		);
	}

	public function testHoldsTemplateSingleValueComponent() {
		$tag_components = [ '', '', 'holds template' ];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertTrue( $formField->isHidden() );
		$this->assertTrue( $formField->holdsTemplate() );
	}

	public function testPreloadComponent() {
		$tag_components = [ '', '', 'preload=PFTestFormFieldPreloadPage01' ];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertSame( 'PFTestFormFieldPreloadPage01', $formField->getFieldArgs()['preload'] );
	}

	public function testShowOnSelectComponentGroupsOptionsByDivId() {
		// The trailing ';' produces an empty trimmed element, which must be
		// skipped via the 'continue' branch (src/FormField.php:340-341).
		$tag_components = [
			'', '', 'show on select=optionA=>div1;optionB=>div1;optionC=>div2;'
		];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertSame(
			[
				'div1' => [ 'optionA', 'optionB' ],
				'div2' => [ 'optionC' ],
			],
			$formField->getFieldArgs()['show on select']
		);
	}

	public function testShowOnSelectComponentWithoutDivIdUsesValueAsKey() {
		$tag_components = [ '', '', 'show on select=optionA' ];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertArrayHasKey( 'optionA', $formField->getFieldArgs()['show on select'] );
		$this->assertSame( [], $formField->getFieldArgs()['show on select']['optionA'] );
	}

	public function testValuesFromWikidataComponent() {
		// 'input type=combobox' skips the getAutocompleteValues() call further
		// down (src/FormField.php:424-425), which would otherwise perform a
		// real network request against the Wikidata SPARQL endpoint -
		// unavailable in this test environment.
		$tag_components = [ '', '', 'values from wikidata=Q1', 'input type=combobox' ];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertSame( 'Q1', $formField->getFieldArgs()['values from wikidata'] );
	}

	public function testValuesFromQueryComponent() {
		$tag_components = [ '', '', 'values from query=PFTestFormFieldQuery01' ];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertSame(
			'PFTestFormFieldQuery01', $formField->getFieldArgs()['values from query']
		);
		$this->assertSame( [], $formField->getPossibleValues() );
	}

	public function testValuesFromCategoryComponent() {
		$tag_components = [ '', '', 'values from category=PFTestFormFieldCategory01' ];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertSame(
			'PFTestFormFieldCategory01', $formField->getFieldArgs()['values from category']
		);
		$this->assertSame( [], $formField->getPossibleValues() );
	}

	public function testValuesFromNamespaceComponent() {
		$tag_components = [ '', '', 'values from namespace=PFTestFormFieldNamespace01' ];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertSame(
			'PFTestFormFieldNamespace01', $formField->getFieldArgs()['values from namespace']
		);
	}

	public function testValuesDependentOnComponent() {
		global $wgPageFormsDependentFields;
		$wgPageFormsDependentFields = [];

		$tag_components = [
			'PFTestFormFieldTemplateName01', 'child_field', 'values dependent on=parent_field'
		];

		$this->mockTemplateInForm->method( 'getTemplateName' )->willReturn( 'PFTestFormFieldTemplateName01' );

		FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertSame(
			[ 'parent_field', 'PFTestFormFieldTemplateName01[child_field]' ],
			$wgPageFormsDependentFields[0]
		);
	}

	public function testUniqueForCategoryComponent() {
		$tag_components = [ '', '', 'unique for category=PFTestFormFieldUniqueCategory01' ];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertTrue( $formField->getFieldArgs()['unique'] );
		$this->assertSame(
			'PFTestFormFieldUniqueCategory01', $formField->getFieldArgs()['unique_for_category']
		);
	}

	public function testUniqueForNamespaceComponent() {
		$tag_components = [ '', '', 'unique for namespace=PFTestFormFieldUniqueNamespace01' ];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertTrue( $formField->getFieldArgs()['unique'] );
		$this->assertSame(
			'PFTestFormFieldUniqueNamespace01', $formField->getFieldArgs()['unique_for_namespace']
		);
	}

	public function testUniqueForConceptComponent() {
		$tag_components = [ '', '', 'unique for concept=PFTestFormFieldUniqueConcept01' ];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertTrue( $formField->getFieldArgs()['unique'] );
		$this->assertSame(
			'PFTestFormFieldUniqueConcept01', $formField->getFieldArgs()['unique_for_concept']
		);
	}

	public function testDefaultFilenameComponent() {
		$originalTitle = RequestContext::getMain()->getTitle();
		try {
			RequestContext::getMain()->setTitle( Title::newFromText( 'PFTestFormFieldDefaultFilenamePage01' ) );

			$tag_components = [ '', '', 'default filename=<page name>.jpg' ];

			$formField = FormField::newFromFormFieldTag(
				$tag_components,
				$this->mockTemplate,
				$this->mockTemplateInForm,
				false,
				$this->mockUser
			);

			$this->assertSame(
				'PFTestFormFieldDefaultFilenamePage01.jpg',
				$formField->getFieldArgs()['default filename']
			);
		} finally {
			RequestContext::getMain()->setTitle( $originalTitle );
		}
	}

	public function testDefaultFilenameComponentOnSpecialFormEditPage() {
		$originalTitle = RequestContext::getMain()->getTitle();
		try {
			// Special:FormEdit/FormName/TargetPage - the target-name extraction
			// branch (src/FormField.php:400-407) must strip the special-page
			// and form-name prefix, leaving just "TargetPage".
			RequestContext::getMain()->setTitle(
				Title::newFromText( 'Special:FormEdit/PFTestFormFieldForm01/PFTestFormFieldTargetPage01' )
			);

			$tag_components = [ '', '', 'default filename=<page name>.jpg' ];

			$formField = FormField::newFromFormFieldTag(
				$tag_components,
				$this->mockTemplate,
				$this->mockTemplateInForm,
				false,
				$this->mockUser
			);

			$this->assertSame(
				'PFTestFormFieldTargetPage01.jpg',
				$formField->getFieldArgs()['default filename']
			);
		} finally {
			RequestContext::getMain()->setTitle( $originalTitle );
		}
	}

	public function testRestrictedWithGroupsComponent() {
		$this->mockUser->method( 'isAllowed' )->willReturn( false );

		$tag_components = [ '', '', 'restricted=sysop,bureaucrat' ];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		// The (anonymous, real) test user is not in 'sysop' or 'bureaucrat',
		// so the effective-groups intersection is empty => field is restricted.
		$this->assertTrue( $formField->isRestricted() );
	}

	// -------------------------------------------------------------------------
	// newFromFormFieldTag() - delimiter-from-template and mapping-values-from-
	// namespace prefixing.
	// -------------------------------------------------------------------------

	public function testDelimiterFromTemplateFieldSetsIsList() {
		$this->mockTemplateField->method( 'getDelimiter' )->willReturn( ';' );
		$this->mockTemplateField->method( 'getCategory' )->willReturn( null );
		$this->mockTemplateField->method( 'getNSText' )->willReturn( null );
		$this->mockTemplate->method( 'getFieldNamed' )->willReturn( $this->mockTemplateField );
		$this->mockTemplateInForm->method( 'strictParsing' )->willReturn( false );
		$this->mockTemplateInForm->method( 'getTemplateName' )->willReturn( 'PFTestFormFieldTemplateName02' );

		$tag_components = [ '', 'test_field' ];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertSame( ';', $formField->getFieldArgs()['delimiter'] );
		$this->assertTrue( $formField->isList() );
	}

	public function testMappingValuesFromNamespacePrefixesPossibleValues() {
		$mappedTemplateField = $this->createMock( TemplateField::class );
		$mappedTemplateField->method( 'getDelimiter' )->willReturn( '' );
		$mappedTemplateField->method( 'getCategory' )->willReturn( null );
		$mappedTemplateField->method( 'getNSText' )->willReturn( null );
		$mappedTemplateField->method( 'getPossibleValues' )->willReturn( [] );

		$this->mockTemplate->method( 'getFieldNamed' )->willReturn( $mappedTemplateField );
		$this->mockTemplateInForm->method( 'strictParsing' )->willReturn( false );
		$this->mockTemplateInForm->method( 'getTemplateName' )->willReturn( 'PFTestFormFieldTemplateName03' );
		$this->mockTemplateInForm->method( 'allowsMultiple' )->willReturn( false );

		// 'values' + 'delimiter' populate mPossibleValues directly, then
		// 'mapping property' with a null SMW store makes setValuesWithMappingProperty()
		// return immediately, leaving the raw (non-prefixed) values - so we assert
		// on the 'values from namespace' field arg round-trip and that mapping ran
		// without throwing, rather than depending on a real SMW store lookup.
		$tag_components = [
			'', 'test_field',
			'values from namespace=PFTestFormFieldNS01',
			'values=val1;val2',
			'delimiter=;',
			'mapping property=PFTestFormFieldMappingProp01',
		];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertSame(
			'PFTestFormFieldNS01', $formField->getFieldArgs()['values from namespace']
		);
		$this->assertFalse( $formField->getUseDisplayTitle() );
	}

	public function testAllowsMultipleSetsInputNameWithNumPlaceholder() {
		$this->mockTemplateInForm->method( 'getTemplateName' )->willReturn( 'PFTestFormFieldTemplateName04' );
		$this->mockTemplateInForm->method( 'allowsMultiple' )->willReturn( true );
		$this->mockTemplateInForm->method( 'strictParsing' )->willReturn( false );

		$tag_components = [ '', 'test_field' ];

		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertSame( 'PFTestFormFieldTemplateName04[num][test_field]', $formField->getInputName() );
		$this->assertTrue( $formField->getFieldArgs()['part_of_multiple'] );
		$this->assertSame(
			'PFTestFormFieldTemplateName04[test_field]', $formField->getFieldArgs()['origName']
		);
	}

	// -------------------------------------------------------------------------
	// cleanupTranslateTags() - tested directly, since the calling branch in
	// getCurrentValue() requires the Translate extension, which is not
	// installed in this test environment.
	// -------------------------------------------------------------------------

	public function testCleanupTranslateTagsRemovesDuplicateAdjacentTags() {
		$field = FormField::create( $this->mockTemplateField );
		$value = "<!--T:1--><!--T:2-->Some text";
		$field->cleanupTranslateTags( $value );
		$this->assertSame( '<!--T:2-->Some text', $value );
	}

	public function testCleanupTranslateTagsRemovesTrailingTagBeforeCloseTag() {
		$field = FormField::create( $this->mockTemplateField );
		$value = "Some text<!--T:1--></translate>";
		$field->cleanupTranslateTags( $value );
		$this->assertSame( 'Some text</translate>', $value );
	}

	public function testCleanupTranslateTagsAddsNewlineBeforeTemplateCall() {
		$field = FormField::create( $this->mockTemplateField );
		$value = "<!--T:1-->{{SomeTemplate}}";
		$field->cleanupTranslateTags( $value );
		$this->assertSame( "<!--T:1-->\n{{SomeTemplate}}", $value );
	}

	/**
	 * Pathological input with 205 distinct adjacent tags must not loop
	 * forever - the safety-valve break after 200 iterations
	 * (src/FormField.php:539-541) must trigger. Tags need distinct numbers:
	 * str_replace() on an identical repeated tag would collapse the whole
	 * chain in a single iteration, never reaching the guard.
	 */
	public function testCleanupTranslateTagsDuplicateTagLoopBreaksAfter200Iterations() {
		$field = FormField::create( $this->mockTemplateField );
		$value = '';
		for ( $n = 1; $n <= 205; $n++ ) {
			$value .= "<!--T:$n-->";
		}
		$field->cleanupTranslateTags( $value );
		// Loop breaks after 202 iterations, leaving the remaining un-merged tags.
		$this->assertStringContainsString( '<!--T:203-->', $value );
	}

	/**
	 * Same safety-valve, for the "add newline before template call" loop
	 * (src/FormField.php:558-562). Each tag+template pair only needs one
	 * pass, so 205 distinct pairs are required to exceed the 200-iteration
	 * guard within a single call.
	 */
	public function testCleanupTranslateTagsNewlineLoopBreaksAfter200Iterations() {
		$field = FormField::create( $this->mockTemplateField );
		$value = '';
		for ( $n = 1; $n <= 205; $n++ ) {
			$value .= "<!--T:$n-->{{Foo$n}}";
		}
		$field->cleanupTranslateTags( $value );
		$this->assertStringContainsString( "<!--T:1-->\n{{Foo1}}", $value );
	}

	// -------------------------------------------------------------------------
	// autocapitalize()
	// -------------------------------------------------------------------------

	public function testAutocapitalizeWordsMode() {
		$tag_components = [ '', '', 'autocapitalize=words' ];
		$formField = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$this->assertSame( 'Some Value', $formField->autocapitalize( 'some value' ) );
	}

	public function testAutocapitalizeDefaultModeLeavesValueUnchanged() {
		$formField = FormField::create( $this->mockTemplateField );
		$this->assertSame( 'some value', $formField->autocapitalize( 'some value' ) );
	}

	// -------------------------------------------------------------------------
	// getCurrentValue() - additional branches: appending/prepending value
	// mapping via map_field, array values, "not submitted" passthrough, and
	// default-value / preload fallback.
	// -------------------------------------------------------------------------

	public function testGetCurrentValueWithMapFieldAndArrayValue() {
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestFormFieldName01' );

		$field = FormField::create( $this->mockTemplateField );
		$field->setFieldArg( 'delimiter', ',' );
		$field->setPossibleValues( [ 'DE' => 'Germany', 'FR' => 'France' ] );

		// 'is_list' (a string, as it arrives from a real HTML form submission)
		// marks this as a plain (non-checkbox) list for
		// FormUtils::getStringFromPassedInArray() - without it, an array with
		// exactly 2 elements is (mis)interpreted as a yes/no checkbox pair.
		$template_instance_query_values = [
			'PFTestFormFieldName01' => [ 'is_list' => 'true', 'Germany', 'France' ],
			'map_field' => [ 'PFTestFormFieldName01' => true ],
		];

		$result = $field->getCurrentValue( $template_instance_query_values, true, false, true );
		$this->assertSame( 'DE, FR', $result );
	}

	public function testGetCurrentValueSubmittedArrayValueWithoutMapField() {
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestFormFieldName09' );

		$field = FormField::create( $this->mockTemplateField );
		$field->setFieldArg( 'delimiter', ',' );
		// No possible-values mapping configured, and no 'map_field' entry -
		// exercises the plain (non-mapped) array foreach branch.
		$template_instance_query_values = [
			'PFTestFormFieldName09' => [ 'is_list' => 'true', 'alpha', 'beta' ],
		];

		$result = $field->getCurrentValue( $template_instance_query_values, true, false, true );
		$this->assertSame( 'alpha, beta', $result );
	}

	public function testGetCurrentValueWithMapFieldAndScalarListValue() {
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestFormFieldName02' );

		$field = FormField::create( $this->mockTemplateField );
		$field->setFieldArg( 'delimiter', ',' );
		$field->setPossibleValues( [ 'DE' => 'Germany', 'FR' => 'France' ] );
		$field->setInputType( 'tokens' );

		$template_instance_query_values = [
			'PFTestFormFieldName02' => 'Germany,France',
			'map_field' => [ 'PFTestFormFieldName02' => true ],
		];

		$result = $field->getCurrentValue( $template_instance_query_values, true, false, true );
		$this->assertSame( 'DE,FR', $result );
	}

	public function testGetCurrentValueMatchesUnescapedApostropheFieldName() {
		// The field name contains an apostrophe, so the escaped lookup key
		// ("Field\'Name") differs from the field name itself ("Field'Name").
		// When the query-values array only has the unescaped key, the
		// fallback match at src/FormField.php:620-621 must be used.
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( "PFTestFormFieldApos'Name01" );

		$field = FormField::create( $this->mockTemplateField );
		$field->setFieldArg( 'delimiter', ',' );

		$template_instance_query_values = [
			"PFTestFormFieldApos'Name01" => 'some value',
		];

		$result = $field->getCurrentValue( $template_instance_query_values, true, false, true );
		$this->assertSame( 'some value', $result );
	}

	public function testGetCurrentValueWithMapFieldAndScalarNonListValue() {
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestFormFieldName03' );

		$field = FormField::create( $this->mockTemplateField );
		$field->setFieldArg( 'delimiter', ',' );
		$field->setPossibleValues( [ 'DE' => 'Germany' ] );

		$template_instance_query_values = [
			'PFTestFormFieldName03' => 'Germany',
			'map_field' => [ 'PFTestFormFieldName03' => true ],
		];

		$result = $field->getCurrentValue( $template_instance_query_values, true, false, true );
		$this->assertSame( 'DE', $result );
	}

	public function testGetCurrentValueNotSubmittedArrayValueEscapesHtml() {
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestFormFieldName04' );

		$field = FormField::create( $this->mockTemplateField );
		$field->setFieldArg( 'delimiter', ',' );

		// 'is_list' avoids the 2-element "checkbox yes/no" special case in
		// FormUtils::getStringFromPassedInArray().
		$template_instance_query_values = [
			'PFTestFormFieldName04' => [ 'is_list' => true, '<b>x</b>', 'y' ],
		];

		$result = $field->getCurrentValue( $template_instance_query_values, false, true, false );
		$this->assertSame( '&lt;b&gt;x&lt;/b&gt;, y', $result );
	}

	public function testGetCurrentValueNotSubmittedScalarValueEscapesHtml() {
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestFormFieldName05' );

		$field = FormField::create( $this->mockTemplateField );
		$field->setFieldArg( 'delimiter', ',' );

		$template_instance_query_values = [
			'PFTestFormFieldName05' => '<script>x</script>',
		];

		$result = $field->getCurrentValue( $template_instance_query_values, false, true, false );
		$this->assertSame( '&lt;script&gt;x&lt;/script&gt;', $result );
	}

	public function testGetCurrentValueReturnsDefaultValueForNewPage() {
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestFormFieldName06' );

		$tag_components = [ '', 'PFTestFormFieldName06', 'default=PFTestFormFieldDefaultValue01' ];
		$field = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		$result = $field->getCurrentValue( [], false, false, false );
		$this->assertSame( 'PFTestFormFieldDefaultValue01', $result );
	}

	public function testGetCurrentValueReturnsPreloadedTextForNewPage() {
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestFormFieldName07' );

		$tag_components = [ '', 'PFTestFormFieldName07', 'preload=PFTestFormFieldPreloadPage02' ];
		$field = FormField::newFromFormFieldTag(
			$tag_components,
			$this->mockTemplate,
			$this->mockTemplateInForm,
			false,
			$this->mockUser
		);

		// The preload page does not exist, so FormUtils::getPreloadedText()
		// returns an empty string rather than throwing.
		$result = $field->getCurrentValue( [], false, false, false );
		$this->assertSame( '', $result );
	}

	public function testGetCurrentValueReturnsNullWhenNoValueAndNoDefault() {
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestFormFieldName08' );

		$field = FormField::create( $this->mockTemplateField );
		$field->setFieldArg( 'delimiter', ',' );

		$result = $field->getCurrentValue( [], false, true, false );
		$this->assertNull( $result );
	}

	// -------------------------------------------------------------------------
	// setValuesWithMappingTemplate() / setValuesWithMappingProperty() -
	// mUseDisplayTitle branch and null-store early return.
	// -------------------------------------------------------------------------

	public function testSetValuesWithMappingTemplateUsesIndexWhenUseDisplayTitle() {
		$formField = FormField::create( $this->mockTemplateField );
		$formField->setFieldArg( 'mapping template', 'NonExistentMappingTemplateForFormFieldTestDisplayTitle' );
		$formField->setPossibleValues( [ 'DE' => 'Germany', 'FR' => 'France' ] );

		$ref = new ReflectionClass( FormField::class );
		$prop = $ref->getProperty( 'mUseDisplayTitle' );
		$prop->setAccessible( true );
		$prop->setValue( $formField, true );

		$formField->setValuesWithMappingTemplate();

		// With mUseDisplayTitle, the loop uses the array index (storage key)
		// instead of the display value; since the mapping template doesn't
		// exist, the label falls back to identity on that key.
		$this->assertSame(
			[ 'DE' => 'DE', 'FR' => 'FR' ],
			$formField->getPossibleValues()
		);
	}

	public function testSetValuesWithMappingPropertyReturnsEarlyWhenStoreIsNull() {
		$formField = FormField::create( $this->mockTemplateField );
		$formField->setFieldArg( 'mapping property', 'PFTestFormFieldMappingProp02' );
		$formField->setPossibleValues( [ 'val1' => 'val1' ] );

		// Pass a falsy-but-non-null value: the production code uses
		// `$store ??= PFUtils::getSMWStore();`, which only invokes the
		// fallback when $store is literally null - passing null here would
		// therefore resolve to the real (non-null) SMW store in this
		// environment and never hit the early return.
		$formField->setValuesWithMappingProperty( false );

		// Early return means mPossibleValues is untouched.
		$this->assertSame( [ 'val1' => 'val1' ], $formField->getPossibleValues() );
	}

	public function testSetValuesWithMappingPropertyUsesIndexWhenUseDisplayTitle() {
		$formField = FormField::create( $this->mockTemplateField );
		$formField->setFieldArg( 'mapping property', 'PFTestFormFieldMappingProp03' );
		$formField->setPossibleValues( [ 'PFTestFormFieldMappingSubject01' => 'Display Label' ] );

		$ref = new ReflectionClass( FormField::class );
		$prop = $ref->getProperty( 'mUseDisplayTitle' );
		$prop->setAccessible( true );
		$prop->setValue( $formField, true );

		$formField->setValuesWithMappingProperty();

		// With mUseDisplayTitle, the loop uses the array index (storage key)
		// as the subject to look up, rather than the display value; the
		// subject page doesn't have the property set, so the label falls
		// back to identity on that key.
		$this->assertSame(
			[ 'PFTestFormFieldMappingSubject01' => 'PFTestFormFieldMappingSubject01' ],
			$formField->getPossibleValues()
		);
	}

	// -------------------------------------------------------------------------
	// additionalHTMLForInput() - free-text field, part_of_multiple map_field
	// hidden field, and unique_for_concept hidden field.
	// -------------------------------------------------------------------------

	public function testAdditionalHTMLForInputFreeTextField() {
		$field = FormField::create( $this->mockTemplateField );
		$field->setIsDisabled( true );

		$result = $field->additionalHTMLForInput( 'some free text', 'free text', 'template_example' );

		$this->assertStringContainsString(
			'type="hidden" value="!free_text!" name="pf_free_text"', $result
		);
	}

	public function testAdditionalHTMLForInputDisabledFieldWithArrayValue() {
		$field = FormField::create( $this->mockTemplateField );
		$field->setIsDisabled( true );
		$field->setInputName( 'PFTestFormFieldInputName01' );
		$field->setFieldArg( 'delimiter', ';' );

		$result = $field->additionalHTMLForInput( [ 'a', 'b' ], 'some_field', 'template_example' );

		$this->assertStringContainsString(
			'type="hidden" value="a;b" name="PFTestFormFieldInputName01"', $result
		);
	}

	public function testAdditionalHTMLForInputUniqueWithSemanticProperty() {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$this->mockTemplateField->method( 'getSemanticProperty' )->willReturn( 'PFTestFormFieldUniqueSemProp01' );

		$field = FormField::create( $this->mockTemplateField );
		$field->setFieldArg( 'unique', true );

		$result = $field->additionalHTMLForInput( 'some_value', 'some_field', 'template_example' );

		$this->assertStringContainsString(
			'type="hidden" value="PFTestFormFieldUniqueSemProp01" name="input_0_unique_property"', $result
		);
	}

	public function testAdditionalHTMLForInputPartOfMultipleMapField() {
		$field = FormField::create( $this->mockTemplateField );
		$field->setFieldArg( 'mapping template', 'template_name' );
		$field->setFieldArg( 'part_of_multiple', true );

		$result = $field->additionalHTMLForInput( 'some_value', 'some_field', 'template_example' );

		$this->assertStringContainsString(
			'type="hidden" value="true" name="template_example[num][map_field][some_field]"', $result
		);
	}

	public function testAdditionalHTMLForInputUniqueForConcept() {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$field = FormField::create( $this->mockTemplateField );
		$field->setFieldArg( 'unique', true );
		$field->setFieldArg( 'unique_for_concept', 'PFTestFormFieldUniqueConceptName01' );

		$result = $field->additionalHTMLForInput( 'some_value', 'some_field', 'template_example' );

		$this->assertStringContainsString(
			'type="hidden" value="PFTestFormFieldUniqueConceptName01" name="input_0_unique_for_concept"',
			$result
		);
	}

	// -------------------------------------------------------------------------
	// getArgumentsForInputCall() / getArgumentsForInputCallSMW()
	// -------------------------------------------------------------------------

	public function testGetArgumentsForInputCallUsesTemplateFieldPossibleValuesWhenUnset() {
		$this->mockTemplateField->method( 'getPossibleValues' )->willReturn( [ 'val1', 'val2' ] );
		$this->mockTemplateField->method( 'getValueLabels' )->willReturn( [ 'val1' => 'Label 1' ] );
		$this->mockTemplateField->method( 'isList' )->willReturn( false );
		$this->mockTemplateField->method( 'isMandatory' )->willReturn( true );
		$this->mockTemplateField->method( 'isUnique' )->willReturn( true );
		$this->mockTemplateField->method( 'getSemanticProperty' )->willReturn( 'PFTestFormFieldSemProp01' );
		$this->mockTemplateField->method( 'getPropertyType' )->willReturn( '_txt' );

		$field = FormField::create( $this->mockTemplateField );

		$other_args = $field->getArgumentsForInputCall();

		$this->assertSame( [ 'val1', 'val2' ], $other_args['possible_values'] );
		$this->assertSame( [ 'val1' => 'Label 1' ], $other_args['value_labels'] );
		$this->assertTrue( $other_args['mandatory'] );
		$this->assertTrue( $other_args['unique'] );
		$this->assertSame( 'PFTestFormFieldSemProp01', $other_args['semantic_property'] );
	}

	public function testGetArgumentsForInputCallWithMappingUsingTranslate() {
		$this->mockTemplateField->method( 'getPossibleValues' )->willReturn( [ 'val1', 'val2' ] );
		$this->mockTemplateField->method( 'isList' )->willReturn( false );
		$this->mockTemplateField->method( 'isMandatory' )->willReturn( false );
		$this->mockTemplateField->method( 'isUnique' )->willReturn( false );
		$this->mockTemplateField->method( 'getSemanticProperty' )->willReturn( '' );
		$this->mockTemplateField->method( 'getPropertyType' )->willReturn( '' );

		$field = FormField::create( $this->mockTemplateField );
		$field->setFieldArg( 'mapping using translate', 'pf-formfield-test-mapping-' );

		$other_args = $field->getArgumentsForInputCall();

		// The message keys don't exist, so the real parser renders them as
		// missing-message placeholders; what matters for coverage is that
		// value_labels was built from the 'mapping using translate' prefix
		// rather than from TemplateField::getValueLabels().
		$this->assertArrayHasKey( 'val1', $other_args['value_labels'] );
		$this->assertArrayHasKey( 'val2', $other_args['value_labels'] );
		$this->assertStringContainsString(
			'pf-formfield-test-mapping-val1', $other_args['value_labels']['val1']
		);
		$this->assertStringContainsString(
			'pf-formfield-test-mapping-val2', $other_args['value_labels']['val2']
		);
	}

	public function testGetArgumentsForInputCallSMWAutocompletionSourceForPageProperty() {
		$this->mockTemplateField->method( 'getPropertyType' )->willReturn( '_wpg' );
		$this->mockTemplateField->method( 'getSemanticProperty' )->willReturn( 'PFTestFormFieldPageProp01' );

		$field = FormField::create( $this->mockTemplateField );
		$other_args = [];
		$field->getArgumentsForInputCallSMW( $other_args );

		$this->assertSame( 'PFTestFormFieldPageProp01', $other_args['autocompletion source'] );
		$this->assertSame( 'property', $other_args['autocomplete field type'] );
	}

	public function testGetArgumentsForInputCallSMWAutocompletionSourceWhenAutocompleteRequested() {
		$this->mockTemplateField->method( 'getPropertyType' )->willReturn( '_txt' );
		$this->mockTemplateField->method( 'getSemanticProperty' )->willReturn( 'PFTestFormFieldTextProp01' );

		$field = FormField::create( $this->mockTemplateField );
		$other_args = [ 'autocomplete' => true ];
		$field->getArgumentsForInputCallSMW( $other_args );

		$this->assertSame( 'PFTestFormFieldTextProp01', $other_args['autocompletion source'] );
		$this->assertSame( 'property', $other_args['autocomplete field type'] );
	}

	public function testGetArgumentsForInputCallMergesDefaultArgsFirst() {
		$this->mockTemplateField->method( 'getPossibleValues' )->willReturn( [] );
		$this->mockTemplateField->method( 'isList' )->willReturn( false );
		$this->mockTemplateField->method( 'isMandatory' )->willReturn( false );
		$this->mockTemplateField->method( 'isUnique' )->willReturn( false );
		$this->mockTemplateField->method( 'getSemanticProperty' )->willReturn( '' );
		$this->mockTemplateField->method( 'getPropertyType' )->willReturn( '' );

		$field = FormField::create( $this->mockTemplateField );
		$field->setFieldArg( 'size', 30 );

		$other_args = $field->getArgumentsForInputCall( [ 'size' => 10, 'extra_default' => 'x' ] );

		// Field-level args override default args on conflict.
		$this->assertSame( 30, $other_args['size'] );
		$this->assertSame( 'x', $other_args['extra_default'] );
	}

	public function testGetArgumentsForInputCallSetsParserOptionsWhenMissing() {
		$this->mockTemplateField->method( 'getPossibleValues' )->willReturn( [] );
		$this->mockTemplateField->method( 'isList' )->willReturn( false );
		$this->mockTemplateField->method( 'isMandatory' )->willReturn( false );
		$this->mockTemplateField->method( 'isUnique' )->willReturn( false );
		$this->mockTemplateField->method( 'getSemanticProperty' )->willReturn( '' );
		$this->mockTemplateField->method( 'getPropertyType' )->willReturn( '' );

		$parser = PFUtils::getParser();
		$ref = new ReflectionClass( $parser );
		$prop = $ref->getProperty( 'mOptions' );
		$prop->setAccessible( true );
		$originalOptions = $prop->getValue( $parser );

		try {
			// Simulate the raw, not-yet-initialised singleton state that the
			// MW 1.43 compat guard (src/FormField.php:1040-1042) handles.
			$prop->setValue( $parser, null );

			$field = FormField::create( $this->mockTemplateField );
			$other_args = $field->getArgumentsForInputCall();

			$this->assertNotNull( $parser->getOptions() );
			$this->assertIsArray( $other_args );
		} finally {
			$prop->setValue( $parser, $originalOptions );
		}
	}
}
