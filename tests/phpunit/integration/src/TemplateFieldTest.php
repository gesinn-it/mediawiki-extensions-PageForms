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

	// The tests that need neither a property nor a namespace name are in TemplateFieldBasicsTest, which needs
	// no database.

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

	public function testToWikitextWithAttributes() {
		// Arrange: create a field and set attributes
		$field = TemplateField::create( 'testField', 'Test Label' );
		$field->setLabel( 'Custom Label' );
		$field->setNSText( 'MyNamespace' );

		// Act: Call the toWikitext method
		$result = $field->toWikitext();

		// Assert: Check if the result matches the expected output
		$expected = "testField (label=Custom Label;namespace=MyNamespace)";
		$this->assertEquals( $expected, $result );
	}

	public function testToWikitextIncludesProperty() {
		$field = TemplateField::create( 'myField', null, 'SomeProp' );
		$this->assertStringContainsString( 'property=SomeProp', $field->toWikitext() );
	}

	public function testToWikitextIncludesNSText() {
		$field = TemplateField::create( 'myField', null );
		$field->setNSText( 'User' );
		$this->assertStringContainsString( 'namespace=User', $field->toWikitext() );
	}

	public function testNewFromParamsSetsNamespaceFromText() {
		$field = TemplateField::newFromParams( 'Field', [ 'namespace' => 'User' ] );
		$this->assertSame( 'User', $field->getNSText() );
		$this->assertSame( NS_USER, $field->getNamespace() );
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

	public function testSetNSTextWithKnownNamespaceSetsNamespaceId() {
		$field = TemplateField::create( 'Field', null );
		$field->setNSText( 'User' );
		$this->assertSame( 'User', $field->getNSText() );
		$this->assertSame( NS_USER, $field->getNamespace() );
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
		// The namespace is written by its name, as a link needs it - not by its number.
		$fieldString = 'File:' . $fieldParam;
		$expected = "[[$fieldString]] {{#set:PFTemplateFieldEdgeCaseProp03=$fieldString}}";
		$this->assertSame( $expected, $field->createText() );
	}

	public function testCreateTextNonListWithNamespaceNoProperty() {
		$field = TemplateField::create( 'PFTemplateFieldEdgeCaseField04', null );
		$field->setNamespace( NS_FILE );
		$fieldParam = '{{{PFTemplateFieldEdgeCaseField04|}}}';
		$this->assertSame( 'File:' . $fieldParam, $field->createText() );
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
		$expected = '{{#arraymap:{{{PFTemplateFieldEdgeCaseField07|}}}|,|x|[[File'
			. ':x]] {{#set:PFTemplateFieldEdgeCaseProp07=x}} }}' . "\n";
		$this->assertSame( $expected, $field->createText() );
	}

	public function testCreateTextListChoosesAlternateVarWhenXInProperty() {
		$field = TemplateField::create( 'PFTemplateFieldEdgeCaseField08', null, 'x_prop', true );
		$expected = '{{#arraymap:{{{PFTemplateFieldEdgeCaseField08|}}}|,|y|[[x_prop::y]]}}' . "\n";
		$this->assertSame( $expected, $field->createText() );
	}
}
