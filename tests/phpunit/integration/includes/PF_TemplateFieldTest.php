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
		$field->setNSText( 'MyNamespace' );

		// Act: Call the toWikitext method
		$result = $field->toWikitext();

		// Assert: Check if the result matches the expected output
		$expected = "testField (label=Custom Label;namespace=MyNamespace)";
		$this->assertEquals( $expected, $result );
	}

	public function testToWikitextOmitsSameLabelAsFieldName() {
		$field = PFTemplateField::create( 'testField', 'testField' );
		$this->assertSame( 'testField', $field->toWikitext() );
	}

	public function testToWikitextIncludesListAttribute() {
		$field = PFTemplateField::create( 'myField', null, null, true );
		$this->assertStringContainsString( 'list', $field->toWikitext() );
	}

	public function testToWikitextIncludesCustomDelimiter() {
		$field = PFTemplateField::create( 'myField', null, null, true, ';' );
		$this->assertStringContainsString( 'delimiter=;', $field->toWikitext() );
	}

	public function testToWikitextOmitsDefaultCommaDelimiter() {
		$field = PFTemplateField::create( 'myField', null, null, true, ',' );
		$this->assertStringNotContainsString( 'delimiter', $field->toWikitext() );
	}

	public function testToWikitextIncludesProperty() {
		$field = PFTemplateField::create( 'myField', null, 'SomeProp' );
		$this->assertStringContainsString( 'property=SomeProp', $field->toWikitext() );
	}

	public function testToWikitextIncludesDisplay() {
		$field = PFTemplateField::create( 'myField', null, null, null, null, 'table' );
		$this->assertStringContainsString( 'display=table', $field->toWikitext() );
	}

	public function testToWikitextIncludesNSText() {
		$field = PFTemplateField::create( 'myField', null );
		$field->setNSText( 'User' );
		$this->assertStringContainsString( 'namespace=User', $field->toWikitext() );
	}

	public function testNewFromParamsSetsFieldName() {
		$field = PFTemplateField::newFromParams( 'MyField', [] );
		$this->assertSame( 'MyField', $field->getFieldName() );
	}

	public function testNewFromParamsSetsLabel() {
		$field = PFTemplateField::newFromParams( 'Field', [ 'label' => 'My Label' ] );
		$this->assertSame( 'My Label', $field->getLabel() );
	}

	public function testNewFromParamsListDefaultsDelimiterToComma() {
		$field = PFTemplateField::newFromParams( 'Field', [ 'list' => true ] );
		$this->assertTrue( $field->isList() );
		$this->assertSame( ',', $field->getDelimiter() );
	}

	public function testNewFromParamsSetsCustomDelimiter() {
		$field = PFTemplateField::newFromParams( 'Field', [ 'list' => true, 'delimiter' => ';' ] );
		$this->assertSame( ';', $field->getDelimiter() );
	}

	public function testNewFromParamsSetsHoldsTemplate() {
		$field = PFTemplateField::newFromParams( 'Field', [ 'holds template' => 'TplName' ] );
		$this->assertSame( 'TplName', $field->getHoldsTemplate() );
	}

	public function testNewFromParamsSetsCategory() {
		$field = PFTemplateField::newFromParams( 'Field', [ 'category' => 'MyCategory' ] );
		$this->assertSame( 'MyCategory', $field->getCategory() );
	}

	public function testNewFromParamsSetsDisplay() {
		$field = PFTemplateField::newFromParams( 'Field', [ 'display' => 'table' ] );
		$this->assertSame( 'table', $field->getDisplay() );
	}

	public function testNewFromParamsSetsNamespaceFromText() {
		$field = PFTemplateField::newFromParams( 'Field', [ 'namespace' => 'User' ] );
		$this->assertSame( 'User', $field->getNSText() );
		$this->assertSame( NS_USER, $field->getNamespace() );
	}

	public function testDefaultIsMandatoryIsFalse() {
		$field = PFTemplateField::create( 'Field', null );
		$this->assertFalse( $field->isMandatory() );
	}

	public function testDefaultIsUniqueIsFalse() {
		$field = PFTemplateField::create( 'Field', null );
		$this->assertFalse( $field->isUnique() );
	}

	public function testDefaultRegexIsNull() {
		$field = PFTemplateField::create( 'Field', null );
		$this->assertNull( $field->getRegex() );
	}

	public function testDefaultHoldsTemplateIsNull() {
		$field = PFTemplateField::create( 'Field', null );
		$this->assertNull( $field->getHoldsTemplate() );
	}

	public function testDefaultCategoryIsNull() {
		$field = PFTemplateField::create( 'Field', null );
		$this->assertNull( $field->getCategory() );
	}

	public function testDefaultNamespaceIsZero() {
		$field = PFTemplateField::create( 'Field', null );
		$this->assertSame( 0, $field->getNamespace() );
	}

	public function testSetSemanticPropertyHandlesNull() {
		$field = PFTemplateField::create( 'Field', null );
		$field->setSemanticProperty( null );
		$this->assertSame( '', $field->getSemanticProperty() );
	}

	public function testSetSemanticPropertyStripsBackslashes() {
		$field = PFTemplateField::create( 'Field', null );
		$field->setSemanticProperty( 'Some\\Prop' );
		$this->assertSame( 'SomeProp', $field->getSemanticProperty() );
	}

	public function testSetSemanticPropertyClearsPossibleValues() {
		$field = PFTemplateField::create( 'Field', null );
		$field->setPossibleValues( [ 'a', 'b' ] );
		$field->setSemanticProperty( 'NewProp' );
		$this->assertSame( [], $field->getPossibleValues() );
	}

	public function testSetAndGetPossibleValues() {
		$field = PFTemplateField::create( 'Field', null );
		$field->setPossibleValues( [ 'Alpha', 'Beta' ] );
		$this->assertSame( [ 'Alpha', 'Beta' ], $field->getPossibleValues() );
	}

	public function testGetPossibleValuesReturnsEmptyArrayAfterCreate() {
		$field = PFTemplateField::create( 'Field', null );
		$this->assertSame( [], $field->getPossibleValues() );
	}

	public function testGetExpectedCargoFieldFallsBackToUnderscoreFieldName() {
		$field = PFTemplateField::create( 'My Field', null );
		$this->assertSame( 'My_Field', $field->getExpectedCargoField() );
	}

	public function testGetFullCargoFieldReturnsNullWhenNotSet() {
		$field = PFTemplateField::create( 'Field', null );
		$this->assertNull( $field->getFullCargoField() );
	}

	public function testSetFieldTypeSetsType() {
		$field = PFTemplateField::create( 'Field', null );
		$field->setFieldType( 'Text' );
		$this->assertSame( 'Text', $field->getFieldType() );
	}

	public function testSetNSTextWithKnownNamespaceSetsNamespaceId() {
		$field = PFTemplateField::create( 'Field', null );
		$field->setNSText( 'User' );
		$this->assertSame( 'User', $field->getNSText() );
		$this->assertSame( NS_USER, $field->getNamespace() );
	}

	public function testCreateStripsBackslashesFromFieldName() {
		$field = PFTemplateField::create( 'Field\\Name', null );
		$this->assertSame( 'FieldName', $field->getFieldName() );
	}

	public function testCreateTrimsFieldName() {
		$field = PFTemplateField::create( '  trimmed  ', null );
		$this->assertSame( 'trimmed', $field->getFieldName() );
	}
}
