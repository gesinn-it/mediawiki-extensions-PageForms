<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\FormDefinition\FormDefinition;
use MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionReader;

/**
 * @covers \MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionReader
 * @covers \MediaWiki\Extension\PageForms\FormDefinition\FormDefinition
 * @covers \MediaWiki\Extension\PageForms\FormDefinition\TemplateSpec
 * @covers \MediaWiki\Extension\PageForms\FormDefinition\FieldSpec
 * @group PF
 */
class FormDefinitionReaderTest extends MediaWikiIntegrationTestCase {

	private function read( string $formDef ): FormDefinition {
		return ( new FormDefinitionReader() )->read( $formDef );
	}

	public function testReadsTemplatesAndFieldsInFormOrder(): void {
		$definition = $this->read(
			"{{{for template|A}}}\n{{{field|x}}}\n{{{field|y|mandatory}}}\n{{{end template}}}\n"
			. "{{{for template|B|multiple}}}\n{{{field|z}}}\n{{{end template}}}"
		);

		$templates = $definition->getTemplates();
		$this->assertCount( 2, $templates );
		$this->assertSame( 'A', $templates[0]->getRawName() );
		$this->assertSame( [ 'x', 'y' ], $templates[0]->getFieldNames() );
		$this->assertSame( [ 'z' ], $templates[1]->getFieldNames() );
		$this->assertSame( 'multiple', $templates[1]->getComponents()[2] );
	}

	public function testFreeTextIsAFieldOfItsTemplateAndNotInTheFieldNames(): void {
		$definition = $this->read(
			"{{{for template|A}}}\n{{{field|x}}}\n{{{standard input|free text}}}\n{{{end template}}}"
		);

		$fields = $definition->getTemplates()[0]->getFields();
		$this->assertCount( 2, $fields );
		$this->assertTrue( $fields[1]->isFreeText() );
		$this->assertSame( [ 'x' ], $definition->getTemplates()[0]->getFieldNames() );
		$this->assertTrue( $definition->hasFreeText() );
	}

	public function testFreeTextOutsideATemplateIsOnlyRecordedOnTheDefinition(): void {
		$definition = $this->read(
			"{{{for template|A}}}\n{{{field|x}}}\n{{{end template}}}\n{{{standard input|free text}}}"
		);

		$this->assertTrue( $definition->hasFreeText() );
		$this->assertSame( [ 'x' ], $definition->getTemplates()[0]->getFieldNames() );
	}

	public function testFormWithoutFreeText(): void {
		$this->assertFalse( $this->read( "{{{for template|A}}}\n{{{end template}}}" )->hasFreeText() );
	}

	public function testFieldsOutsideATemplateAreIgnored(): void {
		$definition = $this->read(
			"{{{field|lost}}}\n{{{for template|A}}}\n{{{field|x}}}\n{{{end template}}}\n{{{field|alsolost}}}"
		);

		$this->assertCount( 1, $definition->getTemplates() );
		$this->assertSame( [ 'x' ], $definition->getTemplates()[0]->getFieldNames() );
	}

	public function testATemplateWithoutEndTagIsClosedByTheNextOne(): void {
		$definition = $this->read(
			"{{{for template|A}}}\n{{{field|x}}}\n{{{for template|B}}}\n{{{field|y}}}\n{{{end template}}}"
		);

		$this->assertSame( [ 'x' ], $definition->getTemplates()[0]->getFieldNames() );
		$this->assertSame( [ 'y' ], $definition->getTemplates()[1]->getFieldNames() );
	}

	public function testAnUnclosedTagEndsTheScan(): void {
		$definition = $this->read( "{{{for template|A}}}\n{{{field|x}}}\n{{{field|broken" );

		$this->assertSame( [ 'x' ], $definition->getTemplates()[0]->getFieldNames() );
	}

	public function testFieldTagAnalysis(): void {
		$field = $this->read(
			"{{{for template|A}}}{{{field|f|restricted|holds template|mapping template=M|default=a=b}}}"
			. "{{{end template}}}"
		)->getTemplates()[0]->getFields()[0];

		$this->assertSame( 'f', $field->getName() );
		$this->assertTrue( $field->isRestricted() );
		$this->assertTrue( $field->holdsTemplate() );
		$this->assertSame( 'template', $field->getMappingType() );
		$this->assertSame( 'M', $field->getArg( 'mapping template' ) );
		$this->assertSame( 'a=b', $field->getArg( 'default' ) );
		$this->assertSame( '', $field->getArg( 'restricted' ) );
		$this->assertNull( $field->getArg( 'hidden' ) );
	}

	public function testPlainFieldHasNoFlags(): void {
		$field = $this->read( "{{{for template|A}}}{{{field|f}}}{{{end template}}}" )
			->getTemplates()[0]->getFields()[0];

		$this->assertFalse( $field->isRestricted() );
		$this->assertFalse( $field->holdsTemplate() );
		$this->assertNull( $field->getMappingType() );
	}

	public function testMappingProperty(): void {
		$field = $this->read( "{{{for template|A}}}{{{field|f|mapping property=P}}}{{{end template}}}" )
			->getTemplates()[0]->getFields()[0];

		$this->assertSame( 'property', $field->getMappingType() );
	}

	public function testPipesInsideTemplateCallsStayInOneArgument(): void {
		$field = $this->read( "{{{for template|A}}}{{{field|f|default={{#if:a|b|c}}|mandatory}}}{{{end template}}}" )
			->getTemplates()[0]->getFields()[0];

		$this->assertSame( '{{#if:a|b|c}}', $field->getArg( 'default' ) );
		$this->assertTrue( $field->hasArg( 'mandatory' ) );
	}

	public function testSerializationRoundTrip(): void {
		$definition = $this->read(
			"{{{for template|A|multiple}}}{{{field|f|restricted}}}{{{standard input|free text}}}{{{end template}}}"
		);

		$copy = FormDefinition::fromArray( json_decode( json_encode( $definition->toArray() ), true ) );

		$this->assertEquals( $definition, $copy );
		$this->assertSame( $definition->toArray(), $copy->toArray() );
	}
}
