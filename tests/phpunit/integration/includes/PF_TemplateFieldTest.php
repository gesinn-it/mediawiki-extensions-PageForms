<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers \PFTemplateField
 *
 * @author gesinn-it-ilm
 */
class PFTemplateFieldTest extends TestCase {

	public function testCreateWithRequiredFields() {
		$name = "testField";
		$label = "Test Label";

		$field = PFTemplateField::create( $name, $label );

		// Check if the object is an instance of PFTemplateField
		$this->assertInstanceOf( PFTemplateField::class, $field );

		// Check if the field name and label are set correctly
		$this->assertEquals( $name, $field->getFieldName() );
		$this->assertEquals( $label, $field->getLabel() );
	}

	public function testCreateWithOptionalFields() {
		$name = "testField";
		$label = "Test Label";
		$semanticProperty = "SemanticProperty";
		$isList = true;
		$delimiter = ";";
		$display = "DisplayOption";

		$field = PFTemplateField::create( $name, $label, $semanticProperty, $isList, $delimiter, $display );

		// Check if the object is an instance of PFTemplateField
		$this->assertInstanceOf( PFTemplateField::class, $field );

		// Check if optional fields are correctly set
		$this->assertEquals( $semanticProperty, $field->getSemanticProperty() );
		$this->assertEquals( $isList, $field->isList() );
		$this->assertEquals( $delimiter, $field->getDelimiter() );
		$this->assertEquals( $display, $field->getDisplay() );
	}

	public function testCreateWithDefaultDelimiterForList() {
		$name = "testField";
		$label = "Test Label";
		$isList = true;

		$field = PFTemplateField::create( $name, $label, null, $isList );

		// Check if the default delimiter is set when list is true and delimiter is not provided
		$this->assertEquals( ',', $field->getDelimiter() );
	}

	public function testCreateWithNullLabel() {
		$name = "testField";
		$label = null;

		$field = PFTemplateField::create( $name, $label );

		// Check if label is set to null when not provided
		$this->assertNull( $field->getLabel() );
	}

	public function testToWikitextWithDefaultValues() {
		$name = "testField";
		$label = "Test Label";

		$field = PFTemplateField::create( $name, $label );
		$result = $field->toWikitext();

		// Expected Wikitext output when no additional properties are set
		$expected = "testField (label=Test Label)";
		$this->assertEquals( trim( $expected ), trim( $result ) );
	}

	public function testToWikitextWithEmptyLabel() {
		$name = "testField";
		$label = "";

		$field = PFTemplateField::create( $name, $label );
		$result = $field->toWikitext();

		// Test if label is omitted when it's empty
		$expected = "testField";
		$this->assertEquals( trim( $expected ), trim( $result ) );
	}

	public function testToWikitextWithAttributes() {
		// Arrange: create a field and set attributes
		$field = PFTemplateField::create( 'testField', 'Test Label' );
		$field->setLabel( 'Custom Label' );
		$field->isList( true );
		$field->setNamespace( 'MyNamespace' );

		// Act: Call the toWikitext method
		$result = $field->toWikitext();

		// Assert: Check if the result matches the expected output
		$expected = "testField (label=Custom Label;namespace=MyNamespace)";
		$this->assertEquals( $expected, $result );
	}
}
