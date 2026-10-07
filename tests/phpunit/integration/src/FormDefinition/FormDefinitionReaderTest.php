<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\FormDefinition\EndTemplateSpec;
use MediaWiki\Extension\PageForms\FormDefinition\FieldSpec;
use MediaWiki\Extension\PageForms\FormDefinition\FormDefinition;
use MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionReader;
use MediaWiki\Extension\PageForms\FormDefinition\InfoSpec;
use MediaWiki\Extension\PageForms\FormDefinition\SectionSpec;
use MediaWiki\Extension\PageForms\FormDefinition\StandardInputSpec;
use MediaWiki\Extension\PageForms\FormDefinition\TemplateSpec;
use MediaWiki\Extension\PageForms\FormDefinition\TextSpec;
use MediaWiki\Extension\PageForms\FormDefinition\UnknownTagSpec;

/**
 * @covers \MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionReader
 * @covers \MediaWiki\Extension\PageForms\FormDefinition\FormDefinition
 * @covers \MediaWiki\Extension\PageForms\FormDefinition\TagSpec
 * @covers \MediaWiki\Extension\PageForms\FormDefinition\TemplateSpec
 * @covers \MediaWiki\Extension\PageForms\FormDefinition\FieldSpec
 * @covers \MediaWiki\Extension\PageForms\FormDefinition\SectionSpec
 * @covers \MediaWiki\Extension\PageForms\FormDefinition\StandardInputSpec
 * @covers \MediaWiki\Extension\PageForms\FormDefinition\InfoSpec
 * @covers \MediaWiki\Extension\PageForms\FormDefinition\UnknownTagSpec
 * @covers \MediaWiki\Extension\PageForms\FormDefinition\TextSpec
 * @group PF
 */
class FormDefinitionReaderTest extends MediaWikiIntegrationTestCase {

	private function read( string $formDef ): FormDefinition {
		return ( new FormDefinitionReader() )->read( $formDef );
	}

	private function field( string $tag ): FieldSpec {
		return $this->read( "{{{for template|A}}}$tag{{{end template}}}" )->getTemplates()[0]->getFields()[0];
	}

	private function template( string $tag ): TemplateSpec {
		return $this->read( $tag . '{{{end template}}}' )->getTemplates()[0];
	}

	public function testElementsComeInFormOrderWithTheTextBetweenTheTags(): void {
		$elements = $this->read(
			"intro\n{{{for template|A}}}\n{{{field|x}}}\n{{{end template}}}\n{{{standard input|save}}}\nend"
		)->getElements();

		$this->assertSame(
			[
				TextSpec::class, TemplateSpec::class, TextSpec::class, FieldSpec::class, TextSpec::class,
				EndTemplateSpec::class, TextSpec::class, StandardInputSpec::class, TextSpec::class,
			],
			array_map( 'get_class', $elements )
		);
		$this->assertSame( "intro\n", $elements[0]->getText() );
		$this->assertSame( "\nend", $elements[8]->getText() );
	}

	public function testAFormWithoutTagsIsOneText(): void {
		$elements = $this->read( 'just text' )->getElements();

		$this->assertCount( 1, $elements );
		$this->assertSame( 'just text', $elements[0]->getText() );
	}

	public function testAnEmptyFormHasNoElements(): void {
		$this->assertSame( [], $this->read( '' )->getElements() );
	}

	public function testFieldsBelongToTheirTemplateInFormOrder(): void {
		$definition = $this->read(
			"{{{for template|A}}}\n{{{field|x}}}\n{{{field|y|mandatory}}}\n{{{end template}}}\n"
			. "{{{for template|B|multiple}}}\n{{{field|z}}}\n{{{end template}}}"
		);

		$templates = $definition->getTemplates();
		$this->assertCount( 2, $templates );
		$this->assertSame( 'A', $templates[0]->getRawName() );
		$this->assertSame( [ 'x', 'y' ], $templates[0]->getFieldNames() );
		$this->assertSame( [ 'z' ], $templates[1]->getFieldNames() );
	}

	public function testFieldsOutsideATemplateBelongToNone(): void {
		$definition = $this->read(
			"{{{field|lost}}}\n{{{for template|A}}}\n{{{field|x}}}\n{{{end template}}}\n{{{field|alsolost}}}"
		);

		$this->assertSame( [ 'x' ], $definition->getTemplates()[0]->getFieldNames() );
		$fields = array_filter(
			$definition->getElements(),
			static fn ( $e ) => $e instanceof FieldSpec
		);
		$this->assertCount( 3, $fields );
	}

	public function testATemplateWithoutEndTagIsClosedByTheNextOne(): void {
		$definition = $this->read(
			"{{{for template|A}}}\n{{{field|x}}}\n{{{for template|B}}}\n{{{field|y}}}\n{{{end template}}}"
		);

		$this->assertSame( [ 'x' ], $definition->getTemplates()[0]->getFieldNames() );
		$this->assertSame( [ 'y' ], $definition->getTemplates()[1]->getFieldNames() );
	}

	public function testAnUnclosedTagIsAnError(): void {
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( 'missing its closing' );

		$this->read( "{{{for template|A}}}\n{{{field|x}}}\n{{{field|broken" );
	}

	public function testMoreThanThreeClosingBracesEndTheTagAtTheLastThree(): void {
		$field = $this->field( '{{{field|f|default={{PAGENAME}}}}}' );

		$this->assertSame( '{{PAGENAME}}', $field->getDefault() );
	}

	public function testFreeTextIsAStandardInputAndNotAField(): void {
		$definition = $this->read(
			"{{{for template|A}}}\n{{{field|x}}}\n{{{end template}}}\n{{{standard input|free text}}}"
		);

		$this->assertTrue( $definition->hasFreeText() );
		$this->assertSame( [ 'x' ], $definition->getTemplates()[0]->getFieldNames() );
		$this->assertCount( 1, $definition->getStandardInputs() );
		$this->assertTrue( $definition->getStandardInputs()[0]->isFreeText() );
	}

	public function testStandardInputs(): void {
		$inputs = $this->read(
			'{{{standard input|summary}}}{{{standard input|save|label=Go}}}{{{standard input|run query}}}'
		)->getStandardInputs();

		$this->assertSame( [ 'summary', 'save', 'run query' ], array_map(
			static fn ( StandardInputSpec $input ): string => $input->getInputName(),
			$inputs
		) );
		$this->assertSame( 'Go', $inputs[1]->getArg( 'label' ) );
		$this->assertFalse( $inputs[1]->isFreeText() );
	}

	public function testFormWithoutFreeText(): void {
		$this->assertFalse( $this->read( "{{{for template|A}}}{{{end template}}}" )->hasFreeText() );
	}

	public function testSections(): void {
		$sections = $this->read(
			'{{{section|Intro|level=3|mandatory|hide if empty}}}{{{section|Other|hidden|restricted}}}'
		)->getSections();

		$this->assertCount( 2, $sections );
		$this->assertSame( 'Intro', $sections[0]->getName() );
		$this->assertSame( '3', $sections[0]->getLevel() );
		$this->assertTrue( $sections[0]->isMandatory() );
		$this->assertTrue( $sections[0]->hidesIfEmpty() );
		$this->assertFalse( $sections[0]->isHidden() );
		$this->assertTrue( $sections[1]->isHidden() );
		$this->assertTrue( $sections[1]->isRestricted() );
		$this->assertNull( $sections[1]->getLevel() );
	}

	public function testInfoArgumentsAreCaseInsensitiveAndStartAtTheFirstComponent(): void {
		$info = $this->read(
			'{{{info|Add Title=New thing|edit title=Change it|query title=Ask|query form at top'
			. '|onlyinclude free text}}}'
		)->getInfo();

		$this->assertInstanceOf( InfoSpec::class, $info );
		$this->assertSame( 'New thing', $info->getCreateTitle() );
		$this->assertSame( 'Change it', $info->getEditTitle() );
		$this->assertSame( 'Ask', $info->getQueryTitle() );
		$this->assertTrue( $info->hasQueryFormAtTop() );
		$this->assertTrue( $info->isFreeTextOnlyInclude() );
	}

	public function testInfoCreateTitleAlsoAcceptsAddTitle(): void {
		$info = $this->read( '{{{info|create title=C}}}' )->getInfo();
		$this->assertSame( 'C', $info->getCreateTitle() );

		$info = $this->read( '{{{info|add title=A}}}' )->getInfo();
		$this->assertSame( 'A', $info->getCreateTitle() );
	}

	public function testNoInfoTag(): void {
		$this->assertNull( $this->read( '{{{field|x}}}' )->getInfo() );
	}

	public function testUnknownTagsKeepTheirTextAsWritten(): void {
		$tag = $this->read( 'a{{{mystery|x=1}}}b' )->getElements()[1];

		$this->assertInstanceOf( UnknownTagSpec::class, $tag );
		$this->assertSame( '{{{mystery|x=1}}}', $tag->getRaw() );
		$this->assertSame( 'mystery', $tag->getTagName() );
	}

	public function testTemplateTagArguments(): void {
		$template = $this->template(
			'{{{for template|A|multiple|strict|label=My A|intro=Hello|minimum instances=1|maximum instances=5'
			. '|add button text=More|display=table|embed in field=Outer[f]}}}'
		);

		$this->assertTrue( $template->isMultiple() );
		$this->assertTrue( $template->isStrict() );
		$this->assertSame( 'My A', $template->getLabel() );
		$this->assertSame( 'Hello', $template->getIntro() );
		$this->assertSame( '1', $template->getMinimumInstances() );
		$this->assertSame( '5', $template->getMaximumInstances() );
		$this->assertSame( 'More', $template->getAddButtonText() );
		$this->assertSame( 'table', $template->getDisplay() );
		$this->assertSame( [ 'Outer', 'f' ], $template->getEmbedInField() );
	}

	public function testPlainTemplateTag(): void {
		$template = $this->template( '{{{for template|A}}}' );

		$this->assertFalse( $template->isMultiple() );
		$this->assertFalse( $template->isStrict() );
		$this->assertNull( $template->getLabel() );
		$this->assertNull( $template->getEmbedInField() );
	}

	public function testEmbedInFieldWithoutBracketsIsIgnored(): void {
		$this->assertNull( $this->template( '{{{for template|A|embed in field=Outer}}}' )->getEmbedInField() );
	}

	public function testFieldTagAnalysis(): void {
		$field = $this->field(
			'{{{field|f|restricted|holds template|mandatory|hidden|list|unique|input type=tokens'
			. '|mapping template=M|default=a=b}}}'
		);

		$this->assertSame( 'f', $field->getName() );
		$this->assertTrue( $field->isRestricted() );
		$this->assertTrue( $field->holdsTemplate() );
		$this->assertTrue( $field->isMandatory() );
		$this->assertTrue( $field->isHidden() );
		$this->assertTrue( $field->isList() );
		$this->assertTrue( $field->isUnique() );
		$this->assertSame( 'tokens', $field->getInputType() );
		$this->assertSame( 'template', $field->getMappingType() );
		$this->assertSame( 'M', $field->getArg( 'mapping template' ) );
		$this->assertSame( 'a=b', $field->getDefault() );
		$this->assertSame( '', $field->getArg( 'restricted' ) );
		$this->assertNull( $field->getArg( 'nope' ) );
	}

	public function testPlainFieldHasNoFlags(): void {
		$field = $this->field( '{{{field|f}}}' );

		$this->assertFalse( $field->isRestricted() );
		$this->assertFalse( $field->holdsTemplate() );
		$this->assertFalse( $field->isMandatory() );
		$this->assertNull( $field->getMappingType() );
		$this->assertNull( $field->getInputType() );
		$this->assertNull( $field->getDefault() );
	}

	public function testMappingProperty(): void {
		$this->assertSame( 'property', $this->field( '{{{field|f|mapping property=P}}}' )->getMappingType() );
	}

	public function testALaterArgumentOverridesAnEarlierOne(): void {
		$this->assertSame( 'second', $this->field( '{{{field|f|default=first|default=second}}}' )->getDefault() );
	}

	public function testPipesInsideTemplateCallsStayInOneArgument(): void {
		$field = $this->field( '{{{field|f|default={{#if:a|b|c}}|mandatory}}}' );

		$this->assertSame( '{{#if:a|b|c}}', $field->getDefault() );
		$this->assertTrue( $field->isMandatory() );
	}

	public function testSerializationRoundTrip(): void {
		$definition = $this->read(
			"intro{{{info|create title=T}}}{{{for template|A|multiple}}}{{{field|f|restricted}}}"
			. "{{{end template}}}{{{section|S|level=2}}}{{{standard input|free text}}}{{{mystery|x}}}tail"
		);

		$copy = FormDefinition::fromArray( json_decode( json_encode( $definition->toArray() ), true ) );

		$this->assertEquals( $definition, $copy );
		$this->assertSame( $definition->toArray(), $copy->toArray() );
		$this->assertSame( [ 'f' ], $copy->getTemplates()[0]->getFieldNames() );
	}

	public function testSectionClassIsUsedForSectionTags(): void {
		$this->assertInstanceOf( SectionSpec::class, $this->read( '{{{section|S}}}' )->getElements()[0] );
	}

	public function testRebuildingFromAnUnknownElementTypeFails(): void {
		$this->expectException( InvalidArgumentException::class );

		FormDefinition::fromArray( [ 'elements' => [ [ 'type' => 'bogus' ] ] ] );
	}

	public function testFindNextTagReportsPositionsAndComponents() {
		$text = 'ab {{{field|x|size=5}}} cd {{{end template}}}';

		$tag = ( new FormDefinitionReader() )->findNextTag( $text );
		$this->assertSame( 3, $tag['start'] );
		$this->assertSame( 23, $tag['end'] );
		$this->assertSame( [ 'field', 'x', 'size=5' ], $tag['components'] );

		$next = ( new FormDefinitionReader() )->findNextTag( $text, $tag['end'] );
		$this->assertSame( [ 'end template' ], $next['components'] );
		$this->assertNull( ( new FormDefinitionReader() )->findNextTag( $text, $next['end'] ) );
	}

	public function testFindNextTagTakesTheLastThreeOfMoreClosingBraces() {
		$tag = ( new FormDefinitionReader() )->findNextTag( '{{{field|x}}}}' );

		$this->assertSame( 14, $tag['end'] );
	}

	public function testFindNextTagRejectsATagWithoutClosingBraces() {
		$this->expectException( MWException::class );

		( new FormDefinitionReader() )->findNextTag( 'text {{{field|x' );
	}

	public function testToWikitextGivesTheFormDefinitionBack() {
		$formDef = "intro {{{for template|T|multiple}}}\n{{{field|a|default={{Foo|x}}}}}\n{{{bogus|y}}}"
			. "{{{end template}}} {{{standard input|save}}}\ntail";

		$definition = ( new FormDefinitionReader() )->read( $formDef );

		$this->assertSame( $formDef, $definition->toWikitext() );
		$this->assertSame( $formDef, FormDefinition::fromArray( $definition->toArray() )->toWikitext() );
	}
}
