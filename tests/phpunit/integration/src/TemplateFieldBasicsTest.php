<?php

use MediaWiki\Extension\PageForms\TemplateField;
use PHPUnit\Framework\TestCase;

/**
 * The parts of TemplateField that need neither the database nor a semantic property nor a namespace
 * given by its name: creating a field, its defaults, its plain setters and getters, and the wikitext
 * of a field without extras. A field with a property asks the semantic store when it is created, so those
 * tests are in TemplateFieldTest, which has a test database.
 *
 * @covers \MediaWiki\Extension\PageForms\TemplateField
 */
class TemplateFieldBasicsTest extends TestCase {

	public function testCreateWithRequiredFields() {
		$name = "testField";
		$label = "Test Label";

		$field = TemplateField::create( $name, $label );

		// Check if the object is an instance of TemplateField
		$this->assertInstanceOf( TemplateField::class, $field );

		// Check if the field name and label are set correctly
		$this->assertEquals( $name, $field->getFieldName() );
		$this->assertEquals( $label, $field->getLabel() );
	}

	public function testCreateWithDefaultDelimiterForList() {
		$name = "testField";
		$label = "Test Label";
		$isList = true;

		$field = TemplateField::create( $name, $label, null, $isList );

		// Check if the default delimiter is set when list is true and delimiter is not provided
		$this->assertEquals( ',', $field->getDelimiter() );
	}

	public function testCreateWithNullLabel() {
		$name = "testField";
		$label = null;

		$field = TemplateField::create( $name, $label );

		// Check if label is set to null when not provided
		$this->assertNull( $field->getLabel() );
	}

	/**
	 * @dataProvider provideFieldNames
	 */
	public function testCreateCleansTheFieldName( string $given, string $expected ) {
		$field = TemplateField::create( $given, null );
		$this->assertSame( $expected, $field->getFieldName() );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function provideFieldNames(): array {
		return [
			'backslashes are stripped' => [ 'Field\\Name', 'FieldName' ],
			'surrounding whitespace is trimmed' => [ '  trimmed  ', 'trimmed' ],
		];
	}

	/**
	 * @dataProvider provideDefaults
	 * @param callable $read
	 * @param mixed $expected
	 */
	public function testANewFieldHasTheDefault( callable $read, $expected ) {
		$field = TemplateField::create( 'Field', null );
		$this->assertSame( $expected, $read( $field ) );
	}

	/**
	 * @return array<string, array{0: callable, 1: mixed}>
	 */
	public static function provideDefaults(): array {
		return [
			'not mandatory' => [ static fn ( TemplateField $field ) => $field->isMandatory(), false ],
			'not unique' => [ static fn ( TemplateField $field ) => $field->isUnique(), false ],
			'no regex' => [ static fn ( TemplateField $field ) => $field->getRegex(), null ],
			'no template held' => [ static fn ( TemplateField $field ) => $field->getHoldsTemplate(), null ],
			'no category' => [ static fn ( TemplateField $field ) => $field->getCategory(), null ],
			'main namespace' => [ static fn ( TemplateField $field ) => $field->getNamespace(), 0 ],
			'no hierarchy structure' => [ static fn ( TemplateField $field ) => $field->getHierarchyStructure(), null ],
			'no possible values' => [ static fn ( TemplateField $field ) => $field->getPossibleValues(), [] ],
		];
	}

	/**
	 * @dataProvider provideWikitextWithoutExtras
	 */
	public function testToWikitextOfAFieldWithoutExtras( string $name, string $label, string $expected ) {
		$field = TemplateField::create( $name, $label );
		$this->assertSame( $expected, trim( $field->toWikitext() ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function provideWikitextWithoutExtras(): array {
		return [
			'with a label' => [ 'testField', 'Test Label', 'testField (label=Test Label)' ],
			'with an empty label, which is left out' => [ 'testField', '', 'testField' ],
			'with the same label as the name, which is left out' => [ 'testField', 'testField', 'testField' ],
		];
	}

	/**
	 * @dataProvider provideWikitextAttributes
	 * @param array $createArguments The arguments of TemplateField::create() after the name
	 */
	public function testToWikitextIncludesTheAttribute( array $createArguments, string $attribute ) {
		$field = TemplateField::create( 'myField', ...$createArguments );
		$this->assertStringContainsString( $attribute, $field->toWikitext() );
	}

	/**
	 * @return array<string, array{0: array, 1: string}>
	 */
	public static function provideWikitextAttributes(): array {
		return [
			'list' => [ [ null, null, true ], 'list' ],
			'a delimiter other than the comma' => [ [ null, null, true, ';' ], 'delimiter=;' ],
			'display' => [ [ null, null, null, null, 'table' ], 'display=table' ],
		];
	}

	public function testToWikitextOmitsDefaultCommaDelimiter() {
		$field = TemplateField::create( 'myField', null, null, true, ',' );
		$this->assertStringNotContainsString( 'delimiter', $field->toWikitext() );
	}

	/**
	 * @dataProvider provideParams
	 * @param array $params
	 * @param callable $read
	 * @param mixed $expected
	 */
	public function testNewFromParamsReadsTheParameter( array $params, callable $read, $expected ) {
		$field = TemplateField::newFromParams( 'MyField', $params );
		$this->assertSame( $expected, $read( $field ) );
	}

	/**
	 * @return array<string, array{0: array, 1: callable, 2: mixed}>
	 */
	public static function provideParams(): array {
		return [
			'the field name' => [ [], static fn ( TemplateField $field ) => $field->getFieldName(), 'MyField' ],
			'label' => [
				[ 'label' => 'My Label' ],
				static fn ( TemplateField $field ) => $field->getLabel(),
				'My Label',
			],
			'list, which defaults the delimiter to a comma' => [
				[ 'list' => true ],
				static fn ( TemplateField $field ) => [ $field->isList(), $field->getDelimiter() ],
				[ true, ',' ],
			],
			'list with a custom delimiter' => [
				[ 'list' => true, 'delimiter' => ';' ],
				static fn ( TemplateField $field ) => $field->getDelimiter(),
				';',
			],
			'holds template' => [
				[ 'holds template' => 'TplName' ],
				static fn ( TemplateField $field ) => $field->getHoldsTemplate(),
				'TplName',
			],
			'category' => [
				[ 'category' => 'MyCategory' ],
				static fn ( TemplateField $field ) => $field->getCategory(),
				'MyCategory',
			],
			'display' => [
				[ 'display' => 'table' ],
				static fn ( TemplateField $field ) => $field->getDisplay(),
				'table',
			],
		];
	}

	public function testSetAndGetPossibleValues() {
		$field = TemplateField::create( 'Field', null );
		$field->setPossibleValues( [ 'Alpha', 'Beta' ] );
		$this->assertSame( [ 'Alpha', 'Beta' ], $field->getPossibleValues() );
	}

	public function testSetFieldTypeSetsType() {
		$field = TemplateField::create( 'Field', null );
		$field->setFieldType( 'Text' );
		$this->assertSame( 'Text', $field->getFieldType() );
	}

	public function testSetAndGetHierarchyStructure() {
		$field = TemplateField::create( 'Field', null );
		$structure = [ 'Root' => [ 'Child1', 'Child2' ] ];
		$field->setHierarchyStructure( $structure );
		$this->assertSame( $structure, $field->getHierarchyStructure() );
	}

	public function testCreateTextNonListNoPropertyDefaultNamespace() {
		$field = TemplateField::create( 'PFTemplateFieldEdgeCaseField01', null );
		$this->assertSame( '{{{PFTemplateFieldEdgeCaseField01|}}}', $field->createText() );
	}
}
