<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use MediaWiki\Extension\PageForms\Form;
use MediaWiki\Extension\PageForms\FormDefinitionWriter;
use MediaWiki\Extension\PageForms\FormField;
use MediaWiki\Extension\PageForms\TemplateField;
use MediaWiki\Extension\PageForms\TemplateInForm;
use MediaWikiIntegrationTestCase;
use PFPageSection;

/**
 * @covers \MediaWiki\Extension\PageForms\FormDefinitionWriter
 * @group Database
 */
class FormDefinitionWriterTest extends MediaWikiIntegrationTestCase {

	private FormDefinitionWriter $writer;

	private $mockTemplateField;

	protected function setUp(): void {
		parent::setUp();
		$this->writer = new FormDefinitionWriter();
		$this->mockTemplateField = $this->createMock( TemplateField::class );
	}

	public function testCreateMarkupContainsForminput() {
		$form = Form::create( 'My Form', [] );
		$markup = $this->writer->form( $form );
		$this->assertStringContainsString( '{{#forminput:form=My Form}}', $markup );
	}

	public function testCreateMarkupContainsNoinclude() {
		$form = Form::create( 'My Form', [] );
		$markup = $this->writer->form( $form );
		$this->assertStringContainsString( '<noinclude>', $markup );
		$this->assertStringContainsString( '</noinclude>', $markup );
	}

	public function testCreateMarkupContainsIncludeonly() {
		$form = Form::create( 'My Form', [] );
		$markup = $this->writer->form( $form );
		$this->assertStringContainsString( '<includeonly>', $markup );
		$this->assertStringContainsString( '</includeonly>', $markup );
	}

	public function testCreateMarkupWithAssociatedCategory() {
		$form = Form::create( 'My Form', [] );
		$form->setAssociatedCategory( 'MyCategory' );
		$markup = $this->writer->form( $form );
		$this->assertStringContainsString( '|autocomplete on category=MyCategory', $markup );
	}

	public function testCreateMarkupWithPageNameFormula() {
		$form = Form::create( 'My Form', [] );
		$form->setPageNameFormula( '{{PAGENAME}}' );
		$markup = $this->writer->form( $form );
		$this->assertStringContainsString( '|page name={{PAGENAME}}', $markup );
	}

	public function testCreateMarkupWithCreateTitle() {
		$form = Form::create( 'My Form', [] );
		$form->setCreateTitle( 'Add entry' );
		$markup = $this->writer->form( $form );
		$this->assertStringContainsString( '|create title=Add entry', $markup );
	}

	public function testCreateMarkupWithEditTitle() {
		$form = Form::create( 'My Form', [] );
		$form->setEditTitle( 'Edit entry' );
		$markup = $this->writer->form( $form );
		$this->assertStringContainsString( '|edit title=Edit entry', $markup );
	}

	public function testCreateMarkupWithoutFreeTextOmitsFreeTextInput() {
		$form = Form::create( 'My Form', [] );
		$markup = $this->writer->form( $form, false );
		$this->assertStringNotContainsString( '{{{standard input|free text', $markup );
	}

	public function testCreateMarkupWithCustomFreeTextLabel() {
		$form = Form::create( 'My Form', [] );
		$markup = $this->writer->form( $form, true, 'Additional notes' );
		$this->assertStringContainsString( 'Additional notes', $markup );
		$this->assertStringContainsString( '{{{standard input|free text', $markup );
	}

	public function testCreateMarkupDefaultIncludesFreeText() {
		$form = Form::create( 'My Form', [] );
		$markup = $this->writer->form( $form );
		$this->assertStringContainsString( '{{{standard input|free text', $markup );
	}

	public function testFormNameWithCommaEscapedInForminput() {
		$form = Form::create( 'Form,Name', [] );
		$markup = $this->writer->form( $form );
		$this->assertStringContainsString( 'form=Form\,Name', $markup );
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
		$output = $this->writer->field( $field, $partOfMultiple, $isLastField );

		$expectedOutput =
			"'''Before Field Text Mock Label:''' <br>"
			. "<p class=\"pfFieldDescription\" style=\"font-size:0.7em; color:gray;\">"
			. "This is a field description.</p>"
			. "{{{field|MockFieldName|size=50|maxlength=100|uploadable|mandatory}}}\n\n";

		$this->assertEquals( $expectedOutput, $output, 'Markup for multiple-instance template is incorrect' );

		// Test case: Single-instance template, not the last field
		$partOfMultiple = false;
		$isLastField = false;
		$output = $this->writer->field( $field, $partOfMultiple, $isLastField );

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
		$output = $this->writer->field( $field, $partOfMultiple, $isLastField );

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
		$output = $this->writer->field( $field, $partOfMultiple, $isLastField );

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

		$output = $this->writer->field( $field, true, true );

		$this->assertStringContainsString( "'''PFTestFormFieldMarkupName01:'''", $output );
	}

	public function testCreateMarkupHiddenFieldOmitsInputType() {
		$this->mockTemplateField->method( 'getLabel' )->willReturn( 'Mock Label' );
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestFormFieldMarkupName02' );

		$field = FormField::create( $this->mockTemplateField );
		$field->setIsHidden( true );
		$field->setInputType( 'text' );

		$output = $this->writer->field( $field, true, true );

		$this->assertStringContainsString( '{{{field|PFTestFormFieldMarkupName02|hidden}}}', $output );
	}

	public function testCreateMarkupIncludesInputTypeWhenSet() {
		$this->mockTemplateField->method( 'getLabel' )->willReturn( 'Mock Label' );
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestFormFieldMarkupName03' );

		$field = FormField::create( $this->mockTemplateField );
		$field->setIsHidden( false );
		$field->setInputType( 'tokens' );

		$output = $this->writer->field( $field, true, true );

		$this->assertStringContainsString( '|input type=tokens', $output );
	}

	public function testCreateMarkupUploadableWithNonTrueValueStillAddedAsValueLess() {
		$this->mockTemplateField->method( 'getLabel' )->willReturn( 'Mock Label' );
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestFormFieldMarkupName04' );

		$field = FormField::create( $this->mockTemplateField );
		// A non-boolean-true 'uploadable' value must still be rendered as a
		// value-less argument by createMarkup(), not "|uploadable=1".
		$field->setFieldArg( 'uploadable', '1' );

		$output = $this->writer->field( $field, true, true );

		$this->assertStringContainsString( '|uploadable', $output );
		$this->assertStringNotContainsString( '|uploadable=1', $output );
	}

	/**
	 * @dataProvider provideLevels
	 * @param int|string|null $level The level to set, or null to leave the default
	 * @param string $heading The wiki heading markup of the section
	 * @param string $tag The level argument of the section tag
	 */
	public function testCreateMarkupWritesTheHeadingAndTagOfTheLevel( $level, string $heading, string $tag ) {
		$ps = PFPageSection::create( 'Intro' );
		if ( $level !== null ) {
			$ps->setSectionLevel( $level );
		}

		$markup = $this->writer->section( $ps );

		$this->assertStringContainsString( "{$heading}Intro{$heading}", $markup );
		$this->assertStringContainsString( "{{{section|Intro|$tag}}}", $markup );
	}

	/**
	 * @return array<string, array{0: int|string|null, 1: string, 2: string}>
	 */
	public static function provideLevels(): array {
		return [
			'the default level' => [ null, '==', 'level=2' ],
			'level 3' => [ 3, '===', 'level=3' ],
			'an empty level falls back to 2' => [ '', '==', 'level=2' ],
		];
	}

	/**
	 * @dataProvider provideFlagMarkup
	 */
	public function testCreateMarkupWritesAFlag( string $setter, string $expected ) {
		$ps = PFPageSection::create( 'Intro' );
		$ps->$setter( true );

		$this->assertStringContainsString( $expected, $this->writer->section( $ps ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function provideFlagMarkup(): array {
		return [
			'mandatory' => [ 'setIsMandatory', '|mandatory' ],
			'restricted' => [ 'setIsRestricted', '|restricted' ],
			'hidden' => [ 'setIsHidden', '|hidden' ],
		];
	}

	public function testCreateMarkupWithStringArg() {
		$ps = PFPageSection::create( 'Rows' );
		$ps->setSectionArgs( 'rows', '5' );

		$markup = $this->writer->section( $ps );

		$this->assertStringContainsString( '|rows=5', $markup );
	}

	public function testCreateMarkupWithBooleanArg() {
		$ps = PFPageSection::create( 'Autogrow' );
		$ps->setSectionArgs( 'autogrow', true );

		$markup = $this->writer->section( $ps );

		$this->assertStringContainsString( '|autogrow', $markup );
		$this->assertStringNotContainsString( '|autogrow=', $markup );
	}

	public function testCreateMarkupSingleInstanceNoFields(): void {
		$template = TemplateInForm::create( 'PFTestTemplateInFormMarkupSingle01', 'My Label' );

		$markup = $this->writer->template( $template );

		$this->assertStringContainsString( '{{{for template|PFTestTemplateInFormMarkupSingle01', $markup );
		$this->assertStringContainsString( '|label=My Label', $markup );
		$this->assertStringContainsString( '{| class="formtable"', $markup );
		$this->assertStringContainsString( '{{{end template}}}', $markup );
	}

	public function testTemplateWritesItsFieldsAsPartOfAMultipleInstanceTemplate(): void {
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestWriterField01' );
		$this->mockTemplateField->method( 'getLabel' )->willReturn( 'Field' );
		$field = FormField::create( $this->mockTemplateField );
		$template = TemplateInForm::create( 'PFTestTemplateInFormMarkupMultiple01', null, true, null, [ $field ] );

		$markup = $this->writer->template( $template );

		$this->assertStringContainsString( '{{{for template|PFTestTemplateInFormMarkupMultiple01|multiple', $markup );
		$this->assertStringNotContainsString( '{| class="formtable"', $markup );
		$this->assertStringContainsString( "'''Field:''' {{{field|PFTestWriterField01}}}\n", $markup );
	}

	public function testTemplateSeparatesTheRowsOfFieldsButNotAfterTheLastOne(): void {
		$first = $this->createMock( TemplateField::class );
		$first->method( 'getFieldName' )->willReturn( 'PFTestWriterFirst01' );
		$second = $this->createMock( TemplateField::class );
		$second->method( 'getFieldName' )->willReturn( 'PFTestWriterSecond01' );
		$template = TemplateInForm::create(
			'PFTestWriterTemplate01', null, false, null,
			[ FormField::create( $first ), FormField::create( $second ) ]
		);

		$markup = $this->writer->template( $template );

		$this->assertSame(
			"{{{for template|PFTestWriterTemplate01}}}\n{| class=\"formtable\"\n"
			. "! PFTestWriterFirst01: \n| {{{field|PFTestWriterFirst01}}}\n|-\n"
			. "! PFTestWriterSecond01: \n| {{{field|PFTestWriterSecond01}}}\n"
			. "|}\n{{{end template}}}\n",
			$markup
		);
	}

	public function testFormWritesItsTemplatesAndSectionsInOrder(): void {
		$template = TemplateInForm::create( 'PFTestWriterTemplate02', null );
		$section = PFPageSection::create( 'Intro' );
		$form = Form::create( 'My Form', [
			[ 'type' => 'template', 'item' => $template ],
			[ 'type' => 'section', 'item' => $section ],
		] );

		$markup = $this->writer->form( $form, false );

		$this->assertStringContainsString( '{{{section|Intro|level=2}}}', $markup );
		$this->assertLessThan(
			strpos( $markup, '{{{section|Intro' ),
			strpos( $markup, '{{{for template|PFTestWriterTemplate02' )
		);
	}

	public function testFieldWithoutPossibleValuesInTheTemplateFieldHasNoValuesArgument(): void {
		$this->mockTemplateField->method( 'getFieldName' )->willReturn( 'PFTestWriterField03' );
		$this->mockTemplateField->method( 'getPossibleValues' )->willReturn( [] );

		$markup = $this->writer->field( FormField::create( $this->mockTemplateField ), true, true );

		$this->assertStringNotContainsString( '|values=', $markup );
	}
}
