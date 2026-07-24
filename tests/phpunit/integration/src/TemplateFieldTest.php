<?php

use MediaWiki\Extension\PageForms\FormLinker;
use MediaWiki\Extension\PageForms\TemplateField;

/**
 * @covers \MediaWiki\Extension\PageForms\TemplateField
 * @group Database
 *
 * @author gesinn-it-ilm
 */
class TemplateFieldTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Reset FormLinker's static namespace form cache before each test.
		$reflProp = new ReflectionProperty( FormLinker::class, 'formPerNamespace' );
		$reflProp->setAccessible( true );
		$reflProp->setValue( null, [] );
	}

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

	public function testCreateWithOptionalFields() {
		$name = "testField";
		$label = "Test Label";
		$semanticProperty = "SemanticProperty";
		$isList = true;
		$delimiter = ";";
		$display = "DisplayOption";

		$field = TemplateField::create( $name, $label, $semanticProperty, $isList, $delimiter, $display );

		// Check if the object is an instance of TemplateField
		$this->assertInstanceOf( TemplateField::class, $field );

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

	public function testToWikitextWithDefaultValues() {
		$name = "testField";
		$label = "Test Label";

		$field = TemplateField::create( $name, $label );
		$result = $field->toWikitext();

		// Expected Wikitext output when no additional properties are set
		$expected = "testField (label=Test Label)";
		$this->assertEquals( trim( $expected ), trim( $result ) );
	}

	public function testToWikitextWithEmptyLabel() {
		$name = "testField";
		$label = "";

		$field = TemplateField::create( $name, $label );
		$result = $field->toWikitext();

		// Test if label is omitted when it's empty
		$expected = "testField";
		$this->assertEquals( trim( $expected ), trim( $result ) );
	}

	public function testToWikitextWithAttributes() {
		// Arrange: create a field and set attributes
		$field = TemplateField::create( 'testField', 'Test Label' );
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
		$field = TemplateField::create( 'testField', 'testField' );
		$this->assertSame( 'testField', $field->toWikitext() );
	}

	public function testToWikitextIncludesListAttribute() {
		$field = TemplateField::create( 'myField', null, null, true );
		$this->assertStringContainsString( 'list', $field->toWikitext() );
	}

	public function testToWikitextIncludesCustomDelimiter() {
		$field = TemplateField::create( 'myField', null, null, true, ';' );
		$this->assertStringContainsString( 'delimiter=;', $field->toWikitext() );
	}

	public function testToWikitextOmitsDefaultCommaDelimiter() {
		$field = TemplateField::create( 'myField', null, null, true, ',' );
		$this->assertStringNotContainsString( 'delimiter', $field->toWikitext() );
	}

	public function testToWikitextIncludesProperty() {
		$field = TemplateField::create( 'myField', null, 'SomeProp' );
		$this->assertStringContainsString( 'property=SomeProp', $field->toWikitext() );
	}

	public function testToWikitextIncludesDisplay() {
		$field = TemplateField::create( 'myField', null, null, null, null, 'table' );
		$this->assertStringContainsString( 'display=table', $field->toWikitext() );
	}

	public function testToWikitextIncludesNSText() {
		$field = TemplateField::create( 'myField', null );
		$field->setNSText( 'User' );
		$this->assertStringContainsString( 'namespace=User', $field->toWikitext() );
	}

	public function testNewFromParamsSetsFieldName() {
		$field = TemplateField::newFromParams( 'MyField', [] );
		$this->assertSame( 'MyField', $field->getFieldName() );
	}

	public function testNewFromParamsSetsLabel() {
		$field = TemplateField::newFromParams( 'Field', [ 'label' => 'My Label' ] );
		$this->assertSame( 'My Label', $field->getLabel() );
	}

	public function testNewFromParamsListDefaultsDelimiterToComma() {
		$field = TemplateField::newFromParams( 'Field', [ 'list' => true ] );
		$this->assertTrue( $field->isList() );
		$this->assertSame( ',', $field->getDelimiter() );
	}

	public function testNewFromParamsSetsCustomDelimiter() {
		$field = TemplateField::newFromParams( 'Field', [ 'list' => true, 'delimiter' => ';' ] );
		$this->assertSame( ';', $field->getDelimiter() );
	}

	public function testNewFromParamsSetsHoldsTemplate() {
		$field = TemplateField::newFromParams( 'Field', [ 'holds template' => 'TplName' ] );
		$this->assertSame( 'TplName', $field->getHoldsTemplate() );
	}

	public function testNewFromParamsSetsCategory() {
		$field = TemplateField::newFromParams( 'Field', [ 'category' => 'MyCategory' ] );
		$this->assertSame( 'MyCategory', $field->getCategory() );
	}

	public function testNewFromParamsSetsDisplay() {
		$field = TemplateField::newFromParams( 'Field', [ 'display' => 'table' ] );
		$this->assertSame( 'table', $field->getDisplay() );
	}

	public function testNewFromParamsSetsNamespaceFromText() {
		$field = TemplateField::newFromParams( 'Field', [ 'namespace' => 'User' ] );
		$this->assertSame( 'User', $field->getNSText() );
		$this->assertSame( NS_USER, $field->getNamespace() );
	}

	public function testDefaultIsMandatoryIsFalse() {
		$field = TemplateField::create( 'Field', null );
		$this->assertFalse( $field->isMandatory() );
	}

	public function testDefaultIsUniqueIsFalse() {
		$field = TemplateField::create( 'Field', null );
		$this->assertFalse( $field->isUnique() );
	}

	public function testDefaultRegexIsNull() {
		$field = TemplateField::create( 'Field', null );
		$this->assertNull( $field->getRegex() );
	}

	public function testDefaultHoldsTemplateIsNull() {
		$field = TemplateField::create( 'Field', null );
		$this->assertNull( $field->getHoldsTemplate() );
	}

	public function testDefaultCategoryIsNull() {
		$field = TemplateField::create( 'Field', null );
		$this->assertNull( $field->getCategory() );
	}

	public function testDefaultNamespaceIsZero() {
		$field = TemplateField::create( 'Field', null );
		$this->assertSame( 0, $field->getNamespace() );
	}

	public function testSetSemanticPropertyHandlesNull() {
		$field = TemplateField::create( 'Field', null );
		$field->setSemanticProperty( null );
		$this->assertSame( '', $field->getSemanticProperty() );
	}

	public function testSetSemanticPropertyStripsBackslashes() {
		$field = TemplateField::create( 'Field', null );
		$field->setSemanticProperty( 'Some\\Prop' );
		$this->assertSame( 'SomeProp', $field->getSemanticProperty() );
	}

	public function testSetSemanticPropertyClearsPossibleValues() {
		$field = TemplateField::create( 'Field', null );
		$field->setPossibleValues( [ 'a', 'b' ] );
		$field->setSemanticProperty( 'NewProp' );
		$this->assertSame( [], $field->getPossibleValues() );
	}

	public function testSetAndGetPossibleValues() {
		$field = TemplateField::create( 'Field', null );
		$field->setPossibleValues( [ 'Alpha', 'Beta' ] );
		$this->assertSame( [ 'Alpha', 'Beta' ], $field->getPossibleValues() );
	}

	public function testGetPossibleValuesReturnsEmptyArrayAfterCreate() {
		$field = TemplateField::create( 'Field', null );
		$this->assertSame( [], $field->getPossibleValues() );
	}

	public function testSetFieldTypeSetsType() {
		$field = TemplateField::create( 'Field', null );
		$field->setFieldType( 'Text' );
		$this->assertSame( 'Text', $field->getFieldType() );
	}

	public function testSetNSTextWithKnownNamespaceSetsNamespaceId() {
		$field = TemplateField::create( 'Field', null );
		$field->setNSText( 'User' );
		$this->assertSame( 'User', $field->getNSText() );
		$this->assertSame( NS_USER, $field->getNamespace() );
	}

	public function testCreateStripsBackslashesFromFieldName() {
		$field = TemplateField::create( 'Field\\Name', null );
		$this->assertSame( 'FieldName', $field->getFieldName() );
	}

	public function testCreateTrimsFieldName() {
		$field = TemplateField::create( '  trimmed  ', null );
		$this->assertSame( 'trimmed', $field->getFieldName() );
	}

	// --- setTypeAndPossibleValues via injected mock store ---

	private function makeDataItem( string $sortKey ): object {
		$item = $this->createMock( \SMW\DataItemFactory::class );
		// Use a simple anonymous stub: getSortKey() returns the value,
		// and it is not SMWDIUri or SMW\DIWikiPage, so PFValuesUtils takes the else branch.
		return new class( $sortKey ) {
			public function __construct( private string $key ) {
			}

			public function getSortKey(): string {
				return $this->key;
			}
		};
	}

	private function makeStore( array $allowsValue, array $allowsValueList = [] ): \SMW\Store {
		$store = $this->createMock( \SMW\Store::class );
		$store->method( 'getPropertyValues' )
			->willReturnCallback( function ( $page, \SMW\DIProperty $prop ) use ( $allowsValue, $allowsValueList ) {
				$label = $prop->getLabel();
				if ( $label === 'Allows value' ) {
					return array_map( fn ( $v ) => $this->makeDataItem( $v ), $allowsValue );
				}
				if ( $label === 'Allows value list' ) {
					return array_map( fn ( $v ) => $this->makeDataItem( $v ), $allowsValueList );
				}
				return [];
			} );
		return $store;
	}

	public function testSetTypeAndPossibleValuesWithAllowsValue() {
		$store = $this->makeStore( [ 'Zebra', 'Mango', 'Apple' ] );
		$field = TemplateField::create( 'Field', null );
		$field->setSemanticProperty( 'SomeProp', $store );
		$this->assertSame( [ 'Apple', 'Mango', 'Zebra' ], $field->getPossibleValues() );
		$this->assertSame( 'enumeration', $field->getPropertyType() );
	}

	public function testSetTypeAndPossibleValuesWithAllowsValueListFallback() {
		$store = $this->makeStore( [], [ 'Charlie', 'Alpha', 'Bravo' ] );
		$field = TemplateField::create( 'Field', null );
		$field->setSemanticProperty( 'SomeProp', $store );
		$this->assertSame( [ 'Alpha', 'Bravo', 'Charlie' ], $field->getPossibleValues() );
		$this->assertSame( 'enumeration', $field->getPropertyType() );
	}

	public function testSetTypeAndPossibleValuesEmptyAllowedValues() {
		$store = $this->makeStore( [], [] );
		$field = TemplateField::create( 'Field', null );
		$field->setSemanticProperty( 'SomeProp', $store );
		$this->assertSame( [], $field->getPossibleValues() );
		$this->assertNotSame( 'enumeration', $field->getPropertyType() );
	}

	public function testSetTypeAndPossibleValuesInversePropertyGuard() {
		$store = $this->createMock( \SMW\Store::class );
		$store->expects( $this->never() )->method( 'getPropertyValues' );
		$field = TemplateField::create( 'Field', null );
		$field->setSemanticProperty( '-InverseProp', $store );
		$this->assertSame( [], $field->getPossibleValues() );
	}

	public function testSetTypeAndPossibleValuesAppliesLabelFormat() {
		$store = $this->createMock( \SMW\Store::class );
		$store->method( 'getPropertyValues' )
			->willReturnCallback( function ( $page, \SMW\DIProperty $prop ) {
				$label = $prop->getLabel();
				if ( $label === 'Allows value' ) {
					return array_map( fn ( $v ) => $this->makeDataItem( $v ), [ '10', '20' ] );
				}
				if ( $label === 'Has field label format' ) {
					return array_map( fn ( $v ) => $this->makeDataItem( $v ), [ '-n' ] );
				}
				return [];
			} );
		$field = TemplateField::create( 'Field', null );
		$field->setSemanticProperty( 'PFTemplateFieldEdgeCaseNumericProp01', $store );
		$this->assertNotNull( $field->getValueLabels() );
		$this->assertSame( [ '10', '20' ], $field->getPossibleValues() );
	}

	public function testNewFromParamsSetsSemanticProperty() {
		$field = TemplateField::newFromParams( 'Field', [ 'property' => 'PFTemplateFieldEdgeCaseProp01' ] );
		$this->assertSame( 'PFTemplateFieldEdgeCaseProp01', $field->getSemanticProperty() );
	}

	public function testDefaultHierarchyStructureIsNull() {
		$field = TemplateField::create( 'Field', null );
		$this->assertNull( $field->getHierarchyStructure() );
	}

	public function testSetAndGetHierarchyStructure() {
		$field = TemplateField::create( 'Field', null );
		$structure = [ 'Root' => [ 'Child1', 'Child2' ] ];
		$field->setHierarchyStructure( $structure );
		$this->assertSame( $structure, $field->getHierarchyStructure() );
	}

	public function testSetNamespaceSetsNSTextAndNamespaceId() {
		$field = TemplateField::create( 'Field', null );
		$field->setNamespace( NS_USER );
		$this->assertSame( 'User', $field->getNSText() );
		$this->assertSame( NS_USER, $field->getNamespace() );
	}

	public function testSetFieldTypeFileSetsFileNamespace() {
		$field = TemplateField::create( 'Field', null );
		$field->setFieldType( 'File' );
		$this->assertSame( 'File', $field->getFieldType() );
		$this->assertSame( 'File', $field->getNSText() );
		$this->assertSame( NS_FILE, $field->getNamespace() );
	}

	// --- getForm() ---

	public function testGetFormReturnsAlreadySetFormWithoutLookup() {
		$field = TemplateField::create( 'Field', null );
		$reflProp = new ReflectionProperty( TemplateField::class, 'mForm' );
		$reflProp->setAccessible( true );
		$reflProp->setValue( $field, 'PFTemplateFieldEdgeCasePreSetForm01' );

		$this->assertSame( 'PFTemplateFieldEdgeCasePreSetForm01', $field->getForm() );
	}

	public function testGetFormReturnsNullWhenNoNamespaceOrCategoryDefaultForm() {
		$field = TemplateField::create( 'Field', null );
		$field->setNamespace( NS_TALK );
		$this->assertNull( $field->getForm() );
	}

	public function testGetFormReturnsNamespaceDefaultForm() {
		$namespaceLabel = PFUtils::getContLang()->getNamespaces()[NS_USER];
		$nsPageTitle = Title::makeTitleSafe( NS_PROJECT, $namespaceLabel );
		$this->insertPage( $nsPageTitle, '{{#default_form:PFTemplateFieldEdgeCaseNSForm01}}' );

		$field = TemplateField::create( 'Field', null );
		$field->setNamespace( NS_USER );

		$this->assertSame( 'PFTemplateFieldEdgeCaseNSForm01', $field->getForm() );

		// insertPage() commits outside this test's DB transaction, so this
		// namespace default form would otherwise leak into later tests.
		$this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( $nsPageTitle )
			->doDeleteArticleReal( 'test cleanup', $this->getTestUser()->getUser() );
	}

	public function testGetFormReturnsCategoryDefaultFormWhenNoNamespaceDefaultForm() {
		$catName = 'PFTemplateFieldEdgeCaseCategory01';
		$catTitle = Title::makeTitleSafe( NS_CATEGORY, $catName );
		$this->insertPage( $catTitle, '{{#default_form:PFTemplateFieldEdgeCaseCatForm01}}' );

		$field = TemplateField::create( 'Field', null );
		$field->setNamespace( NS_TALK );
		$reflProp = new ReflectionProperty( TemplateField::class, 'mCategory' );
		$reflProp->setAccessible( true );
		$reflProp->setValue( $field, $catName );

		$this->assertSame( 'PFTemplateFieldEdgeCaseCatForm01', $field->getForm() );
	}

	public function testGetFormReturnsNullWhenCategoryHasNoDefaultForm() {
		$catName = 'PFTemplateFieldEdgeCaseEmptyCategory01';
		$this->insertPage( Title::makeTitleSafe( NS_CATEGORY, $catName ), 'Plain category page.' );

		$field = TemplateField::create( 'Field', null );
		$field->setNamespace( NS_TALK );
		$reflProp = new ReflectionProperty( TemplateField::class, 'mCategory' );
		$reflProp->setAccessible( true );
		$reflProp->setValue( $field, $catName );

		$this->assertNull( $field->getForm() );
	}

	// --- createText() ---

	public function testCreateTextNonListNoPropertyDefaultNamespace() {
		$field = TemplateField::create( 'PFTemplateFieldEdgeCaseField01', null );
		$this->assertSame( '{{{PFTemplateFieldEdgeCaseField01|}}}', $field->createText() );
	}

	public function testCreateTextNonListWithPropertyDefaultNamespace() {
		$field = TemplateField::create( 'PFTemplateFieldEdgeCaseField02', null, 'PFTemplateFieldEdgeCaseProp02' );
		$this->assertSame(
			'[[PFTemplateFieldEdgeCaseProp02::{{{PFTemplateFieldEdgeCaseField02|}}}]]',
			$field->createText()
		);
	}

	public function testCreateTextNonListWithPropertyAndNamespace() {
		$field = TemplateField::create( 'PFTemplateFieldEdgeCaseField03', null, 'PFTemplateFieldEdgeCaseProp03' );
		$field->setNamespace( NS_FILE );
		$fieldParam = '{{{PFTemplateFieldEdgeCaseField03|}}}';
		$fieldString = NS_FILE . ':' . $fieldParam;
		$expected = "[[$fieldString]] {{#set:PFTemplateFieldEdgeCaseProp03=$fieldString}}";
		$this->assertSame( $expected, $field->createText() );
	}

	public function testCreateTextNonListWithNamespaceNoProperty() {
		$field = TemplateField::create( 'PFTemplateFieldEdgeCaseField04', null );
		$field->setNamespace( NS_FILE );
		$fieldParam = '{{{PFTemplateFieldEdgeCaseField04|}}}';
		$this->assertSame( NS_FILE . ':' . $fieldParam, $field->createText() );
	}

	public function testCreateTextListWithPropertyDefaultNamespaceAndCommaDelimiter() {
		$field = TemplateField::create(
			'PFTemplateFieldEdgeCaseField05', null, 'PFTemplateFieldEdgeCaseProp05', true
		);
		$expected = '{{#arraymap:{{{PFTemplateFieldEdgeCaseField05|}}}|,|x|[[PFTemplateFieldEdgeCaseProp05::x]]}}'
			. "\n";
		$this->assertSame( $expected, $field->createText() );
	}

	public function testCreateTextListWithPropertyAndNonCommaDelimiter() {
		$field = TemplateField::create(
			'PFTemplateFieldEdgeCaseField06', null, 'PFTemplateFieldEdgeCaseProp06', true, ';'
		);
		$expected = '{{#arraymap:{{{PFTemplateFieldEdgeCaseField06|}}}|;|x|[[PFTemplateFieldEdgeCaseProp06::x]]|;\s}}'
			. "\n";
		$this->assertSame( $expected, $field->createText() );
	}

	public function testCreateTextListWithPropertyAndNamespace() {
		$field = TemplateField::create(
			'PFTemplateFieldEdgeCaseField07', null, 'PFTemplateFieldEdgeCaseProp07', true
		);
		$field->setNamespace( NS_FILE );
		$expected = '{{#arraymap:{{{PFTemplateFieldEdgeCaseField07|}}}|,|x|[[' . NS_FILE
			. ':x]] {{#set:PFTemplateFieldEdgeCaseProp07=x}} }}' . "\n";
		$this->assertSame( $expected, $field->createText() );
	}

	public function testCreateTextListChoosesAlternateVarWhenXInProperty() {
		$field = TemplateField::create( 'PFTemplateFieldEdgeCaseField08', null, 'x_prop', true );
		$expected = '{{#arraymap:{{{PFTemplateFieldEdgeCaseField08|}}}|,|y|[[x_prop::y]]}}' . "\n";
		$this->assertSame( $expected, $field->createText() );
	}
}
