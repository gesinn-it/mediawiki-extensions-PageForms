<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use MediaWiki\Extension\PageForms\FormField;
use MediaWiki\Extension\PageForms\TemplateInForm;
use MediaWikiIntegrationTestCase;
use MWException;
use Parser;
use ParserOptions;
use PFUtils;
use Title;
use WebRequest;

/**
 * @covers \MediaWiki\Extension\PageForms\TemplateInForm
 * @group Database
 *
 * @author gesinn-it-ilm
 */
class TemplateInFormTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
	}

	protected function tearDown(): void {
		parent::tearDown();
	}

	public function testCreateWithFormFields() {
		// Mock form fields
		$mockField1 = $this->createMock( FormField::class );
		$mockField2 = $this->createMock( FormField::class );
		$formFields = [ $mockField1, $mockField2 ];

		// Call the create method
		$template = TemplateInForm::create(
			'TemplateName',
			'Template Label',
			true,
			5,
			$formFields
		);

		// Assertions
		$this->assertInstanceOf( TemplateInForm::class, $template );
		$this->assertEquals( 'TemplateName', $template->getTemplateName() );
		$this->assertEquals( 'Template Label', $template->getLabel() );
		$this->assertTrue( $template->allowsMultiple() );
		$this->assertEquals( 5, $template->getMaxInstancesAllowed() );
		$this->assertCount( 2, $template->getFields() );
		$this->assertSame( $mockField1, $template->getFields()[0] );
		$this->assertSame( $mockField2, $template->getFields()[1] );
	}

	public function testCreateWithoutFormFields() {
		// TemplateInForm::create() with no $formFields argument loads the fields
		// from the real Template: page via Template::newFromName(). Create an
		// actual template page with two non-semantic placeholder fields.
		$this->insertPage(
			Title::newFromText( 'PFTestTemplateInFormCreate', NS_TEMPLATE ),
			'{{{field1}}} {{{field2}}}'
		);

		$templateInForm = TemplateInForm::create( 'PFTestTemplateInFormCreate' );

		$this->assertInstanceOf( TemplateInForm::class, $templateInForm );
		$this->assertEquals( 'PFTestTemplateInFormCreate', $templateInForm->getTemplateName() );
		$this->assertCount( 2, $templateInForm->getFields() );
		$this->assertSame( 'field1', $templateInForm->getFields()[0]->getTemplateField()->getFieldName() );
		$this->assertSame( 'field2', $templateInForm->getFields()[1]->getTemplateField()->getFieldName() );
	}

	public function testSetFieldValuesFromSubmitSingleInstance(): void {
		// Mock request for a single-instance template.
		$mockRequest = $this->createMock( WebRequest::class );
		$mockRequest->method( 'getArray' )->willReturnCallback( static function ( $key ) {
			if ( $key === 'My_Template' ) {
				return [ 'field1' => 'value1', 'field2' => 'value2' ];
			}
			return null;
		} );

		$template = new TemplateInForm();
		$template->setTemplateName( 'My Template' );
		$template->setAllowsMultiple( false );

		$template->setFieldValuesFromSubmit( $mockRequest );

		$this->assertEquals(
			[ 'field1' => 'value1', 'field2' => 'value2' ],
			$template->getValuesFromSubmit()
		);
	}

	public function testSetFieldValuesFromSubmitMultipleInstances(): void {
		// Mock request for multiple-instance template.
		$mockRequest = $this->createMock( WebRequest::class );
		$mockRequest->method( 'getArray' )->willReturnCallback( static function ( $key ) {
			if ( $key === 'My_Template' ) {
				return [
					'0' => [ 'field1' => 'value1', 'field2' => 'value2' ],
					'1' => [ 'field1' => 'value3', 'field2' => 'value4' ],
				];
			}
			return null;
		} );

		$template = new TemplateInForm();
		$template->setTemplateName( 'My Template' );
		$template->setAllowsMultiple( true );
		$template->setInstanceNum( 1 );

		$template->setFieldValuesFromSubmit( $mockRequest );

		$this->assertEquals(
			[ 'field1' => 'value3', 'field2' => 'value4' ],
			$template->getValuesFromSubmit()
		);
	}

	public function testSetFieldValuesFromSubmitNoData(): void {
		// Mock request with no data for the template.
		$mockRequest = $this->createMock( WebRequest::class );
		$mockRequest->method( 'getArray' )->willReturn( null );

		$template = new TemplateInForm();
		$template->setTemplateName( 'Nonexistent Template' );

		$template->setFieldValuesFromSubmit( $mockRequest );

		$this->assertSame( [], $template->getValuesFromSubmit() );
	}

	public function testSingleTagExtraction(): void {
		$str = "This is some text <pre>unparsed content</pre> more text.";
		$replacements = [];

		$result = TemplateInForm::removeUnparsedText( $str, $replacements );

		// Assert the content within <pre> is replaced with a placeholder.
		$this->assertEquals( "This is some text \1" . "0" . "\2 more text.", $result );

		// Assert the content is stored in the replacements array.
		$this->assertEquals( [ "<pre>unparsed content</pre>" ], $replacements );
	}

	public function testMultipleTagsExtraction(): void {
		$str = "Text <pre>first</pre> middle <nowiki>second</nowiki> end.";
		$replacements = [];

		$result = TemplateInForm::removeUnparsedText( $str, $replacements );

		// Assert the placeholders are correctly inserted.
		$this->assertEquals( "Text \1" . "0" . "\2 middle \1" . "1" . "\2 end.", $result );

		// Assert the content is stored in the replacements array.
		$this->assertEquals(
			[ "<pre>first</pre>", "<nowiki>second</nowiki>" ],
			$replacements
		);
	}

	public function testNestedTags(): void {
		$str = "Nested <pre><ref>ignored</ref></pre> content.";
		$replacements = [];

		$result = TemplateInForm::removeUnparsedText( $str, $replacements );

		// Assert the placeholder replaces the entire <pre> tag with its content.
		$this->assertEquals( "Nested \1" . "0" . "\2 content.", $result );

		// Assert the replacements array includes the full content of <pre>.
		$this->assertEquals( [ "<pre><ref>ignored</ref></pre>" ], $replacements );
	}

	public function testUnclosedTagsAreIgnored(): void {
		$str = "This is <pre>unclosed text.";
		$replacements = [];

		$result = TemplateInForm::removeUnparsedText( $str, $replacements );

		// Assert the string remains unchanged since the tag is unclosed.
		$this->assertEquals( $str, $result );

		// Assert the replacements array remains empty.
		$this->assertSame( [], $replacements );
	}

	public function testSetFieldValuesFromPageSimpleTemplate() {
		$existingContent = "{{TemplateName|field1=value1|field2=value2}}";
		$template = new TemplateInForm();

		$template->setPregMatchTemplateStr( 'TemplateName' );
		$template->setSearchTemplateStr( 'TemplateName' );

		$template->setFieldValuesFromPage( $existingContent );

		$expectedValues = [
			'field1' => 'value1',
			'field2' => 'value2'
		];

		$this->assertEquals( $expectedValues[ 'field1' ], $template->getValuesFromPage()[ 'field1' ] );
		$this->assertEquals( $expectedValues[ 'field2' ], $template->getValuesFromPage()[ 'field2' ] );
	}

	public function testSetFieldValuesFromPageNestedBrackets() {
		$existingContent = "{{TemplateName|field1=[[Link|Display]]|field2=value2}}";
		$template = new TemplateInForm();

		$template->setPregMatchTemplateStr( 'TemplateName' );
		$template->setSearchTemplateStr( 'TemplateName' );

		$template->setFieldValuesFromPage( $existingContent );

		$expectedValues = [
			'field1' => '[[Link|Display]]',
			'field2' => 'value2'
		];

		$this->assertEquals( $expectedValues[ 'field1' ], $template->getValuesFromPage()[ 'field1' ] );
		$this->assertEquals( $expectedValues[ 'field2' ], $template->getValuesFromPage()[ 'field2' ] );
	}

	public function testSetPageRelatedInfo() {
		// Create a mock or instance of the class
		$template = new TemplateInForm();
		$template->setTemplateName( 'Example_Template' );
		$template->setInstanceNum( 1 );

		// Page content contains the template
		$existingPageContent = 'Some content {{Example Template|param1=value1}} more content';
		$template->setPageRelatedInfo( $existingPageContent );

		// Assertions for Case 1
		$this->assertEquals( 'Example Template', $template->getSearchTemplateStr() );
		$this->assertEquals( 'Example Template', $template->getPregMatchTemplateStr() );
		$this->assertNotNull( $template->pageCallsThisTemplate() );
		$this->assertEquals( 2, $template->numSeenInstancesOnThisPage() );
	}

	private function createMockParser(): Parser {
		$mockParser = $this->createMock( Parser::class );
		$mockParser->method( 'recursiveTagParse' )
			->willReturnCallback( static fn ( $input ) => $input );
		return $mockParser;
	}

	public function testNewFromFormTagDefaultParser(): void {
		// $parser === null triggers the PFUtils::getParser()/clearState() path.
		// The shared parser singleton needs ParserOptions before clearState()
		// can call resetOutput() (same MW 1.43 compat requirement as FormField::
		// newFromFormFieldTag()).
		$parser = PFUtils::getParser();
		if ( !$parser->getOptions() ) {
			$parser->setOptions( ParserOptions::newFromAnon() );
		}
		$parser->setOutputType( Parser::OT_HTML );

		$template = TemplateInForm::newFromFormTag(
			[ 'for template', 'PFTestTemplateInFormDefaultParser01' ]
		);

		$this->assertInstanceOf( TemplateInForm::class, $template );
		$this->assertSame( 'PFTestTemplateInFormDefaultParser01', $template->getTemplateName() );
	}

	public function testNewFromFormTagBasicOptions(): void {
		$template = TemplateInForm::newFromFormTag(
			[
				'for template',
				'PFTestTemplateInFormBasic01',
				'multiple',
				'strict',
				'label=My Label',
				'intro=My Intro',
				'minimum instances=2',
				'maximum instances=5',
				'add button text=Add Another',
				'display=custom',
				'height=300px',
				'displayed fields when minimized=field1, field2',
				'event title field=titleField',
				'event date field=dateField',
				'event start date field=startDateField',
				'event end date field=endDateField',
			],
			$this->createMockParser()
		);

		$this->assertTrue( $template->allowsMultiple() );
		$this->assertTrue( $template->strictParsing() );
		$this->assertSame( 'My Label', $template->getLabel() );
		$this->assertSame( 'My Intro', $template->getIntro() );
		$this->assertSame( '2', $template->getMinInstancesAllowed() );
		$this->assertSame( '5', $template->getMaxInstancesAllowed() );
		$this->assertSame( 'Add Another', $template->getAddButtonText() );
		$this->assertSame( 'custom', $template->getDisplay() );
		$this->assertSame( '300px', $template->getHeight() );
		$this->assertSame( 'field1, field2', $template->getDisplayedFieldsWhenMinimized() );
		$this->assertSame( 'titleField', $template->getEventTitleField() );
		$this->assertSame( 'dateField', $template->getEventDateField() );
		$this->assertSame( 'startDateField', $template->getEventStartDateField() );
		$this->assertSame( 'endDateField', $template->getEventEndDateField() );
	}

	public function testNewFromFormTagEmbedInField(): void {
		$template = TemplateInForm::newFromFormTag(
			[
				'for template',
				'PFTestTemplateInFormEmbed01',
				'embed in field=PFTestTemplateInFormEmbedParent01[fieldName]',
			],
			$this->createMockParser()
		);

		$this->assertSame( 'PFTestTemplateInFormEmbedParent01', $template->getEmbedInTemplate() );
		$this->assertSame( 'fieldName', $template->getEmbedInField() );
		$this->assertNotNull( $template->getPlaceholder() );
	}

	public function testNewFromFormTagUsesGlobalEmbeddedTemplates(): void {
		global $wgPageFormsEmbeddedTemplates;
		$previous = $wgPageFormsEmbeddedTemplates;
		$wgPageFormsEmbeddedTemplates['PFTestTemplateInFormGlobalEmbed01'] =
			[ 'PFTestTemplateInFormGlobalEmbedParent01', 'globalFieldName' ];

		try {
			$template = TemplateInForm::newFromFormTag(
				[ 'for template', 'PFTestTemplateInFormGlobalEmbed01' ],
				$this->createMockParser()
			);

			$this->assertSame( 'PFTestTemplateInFormGlobalEmbedParent01', $template->getEmbedInTemplate() );
			$this->assertSame( 'globalFieldName', $template->getEmbedInField() );
			$this->assertNotNull( $template->getPlaceholder() );
		} finally {
			$wgPageFormsEmbeddedTemplates = $previous;
		}
	}

	public function testCreateMarkupSingleInstanceNoFields(): void {
		$template = TemplateInForm::create( 'PFTestTemplateInFormMarkupSingle01', 'My Label' );

		$markup = $template->createMarkup();

		$this->assertStringContainsString( '{{{for template|PFTestTemplateInFormMarkupSingle01', $markup );
		$this->assertStringContainsString( '|label=My Label', $markup );
		$this->assertStringContainsString( '{| class="formtable"', $markup );
		$this->assertStringContainsString( '{{{end template}}}', $markup );
	}

	public function testCreateMarkupMultipleInstanceWithFields(): void {
		$mockField = $this->createMock( FormField::class );
		$mockField->method( 'createMarkup' )
			->with( true, true )
			->willReturn( "|field=\n" );

		$template = TemplateInForm::create(
			'PFTestTemplateInFormMarkupMultiple01',
			null,
			true,
			null,
			[ $mockField ]
		);

		$markup = $template->createMarkup();

		$this->assertStringContainsString( '{{{for template|PFTestTemplateInFormMarkupMultiple01|multiple', $markup );
		$this->assertStringNotContainsString( '{| class="formtable"', $markup );
		$this->assertStringContainsString( "|field=\n", $markup );
	}

	public function testGridValuesAndInstanceNumHelpers(): void {
		$template = new TemplateInForm();

		$this->assertSame( [], $template->getGridValues() );

		$template->addGridValue( 'fieldA', 'valueA' );
		$template->incrementInstanceNum();
		$template->addGridValue( 'fieldA', 'valueB' );

		$this->assertSame( 1, $template->getInstanceNum() );
		$this->assertSame(
			[ 0 => [ 'fieldA' => 'valueA' ], 1 => [ 'fieldA' => 'valueB' ] ],
			$template->getGridValues()
		);
	}

	public function testAddField(): void {
		$template = new TemplateInForm();
		$mockField = $this->createMock( FormField::class );

		$template->addField( $mockField );

		$this->assertCount( 1, $template->getFields() );
		$this->assertSame( $mockField, $template->getFields()[0] );
	}

	public function testChangeFieldValuesWithoutModifier(): void {
		$template = new TemplateInForm();

		$template->changeFieldValues( 'fieldA', 'initialValue' );

		$this->assertSame( 'initialValue', $template->getValuesFromPage()['fieldA'] );
	}

	public function testChangeFieldValuesCleansUpModifiedKey(): void {
		$template = new TemplateInForm();

		$template->changeFieldValues( 'fieldA+', '5' );
		$this->assertArrayHasKey( 'fieldA+', $template->getValuesFromPage() );

		$template->changeFieldValues( 'fieldA', '10', '+' );

		$this->assertSame( '10', $template->getValuesFromPage()['fieldA'] );
		$this->assertArrayNotHasKey( 'fieldA+', $template->getValuesFromPage() );
	}

	public function testSetFieldValuesFromSubmitSpreadsheetUnescapesValues(): void {
		$mockRequest = $this->createMock( WebRequest::class );
		$mockRequest->method( 'getArray' )->willReturnCallback( static function ( $key ) {
			if ( $key === 'PFTestTemplateInFormSpreadsheet01' ) {
				return [ '0' => [ 'field1' => 'a &lt;b&gt; c' ] ];
			}
			if ( $key === 'spreadsheet_templates' ) {
				return [ 'PFTestTemplateInFormSpreadsheet01' => true ];
			}
			return null;
		} );

		$template = new TemplateInForm();
		$template->setTemplateName( 'PFTestTemplateInFormSpreadsheet01' );
		$template->setAllowsMultiple( true );

		$template->setFieldValuesFromSubmit( $mockRequest );

		$this->assertSame( [ 'field1' => 'a <b> c' ], $template->getValuesFromSubmit() );
	}

	public function testSetFieldValuesFromSubmitReturnsExistingInstanceEarly(): void {
		$mockRequest = $this->createMock( WebRequest::class );
		$mockRequest->method( 'getArray' )->willReturnCallback( static function ( $key ) {
			if ( $key === 'PFTestTemplateInFormExistingInstance01' ) {
				return [
					'0' => [ 'field1' => 'value0' ],
					'1' => [ 'field1' => 'value1' ],
				];
			}
			return null;
		} );

		$template = new TemplateInForm();
		$template->setTemplateName( 'PFTestTemplateInFormExistingInstance01' );
		$template->setAllowsMultiple( true );
		$template->setInstanceNum( 0 );
		$template->setPageRelatedInfo( '{{PFTestTemplateInFormExistingInstance01|field1=x}}' );

		$template->setFieldValuesFromSubmit( $mockRequest );

		$this->assertSame( [ 'field1' => 'value0' ], $template->getValuesFromSubmit() );
	}

	public function testSetFieldValuesFromSubmitStillInExistingTemplatesReturnsEarly(): void {
		$mockRequest = $this->createMock( WebRequest::class );
		$mockRequest->method( 'getArray' )->willReturnCallback( static function ( $key ) {
			if ( $key === 'PFTestTemplateInFormStillExisting01' ) {
				return [
					'0' => [ 'field1' => 'value0' ],
					'1' => [ 'field1' => 'value1' ],
				];
			}
			return null;
		} );

		$template = new TemplateInForm();
		$template->setTemplateName( 'PFTestTemplateInFormStillExisting01' );
		$template->setAllowsMultiple( true );
		// mNumSeenInstancesOnThisPage becomes instanceNum + 1 = 3; keys 0 and 1
		// are both below that but neither equals instanceNum (2), so the loop
		// unsets both keys without an early match and falls through to the
		// "still in existing templates" check.
		$template->setInstanceNum( 2 );
		$template->setPageRelatedInfo( '{{PFTestTemplateInFormStillExisting01|field1=x}}' );

		$template->setFieldValuesFromSubmit( $mockRequest );

		$this->assertSame( [], $template->getValuesFromSubmit() );
	}

	public function testSingletonTagIsIgnored(): void {
		$str = "Before <ref name=\"abc\" /> <pre>kept</pre> after.";
		$replacements = [];

		$result = TemplateInForm::removeUnparsedText( $str, $replacements );

		$this->assertSame( "Before <ref name=\"abc\" /> \1" . '0' . "\2 after.", $result );
		$this->assertSame( [ '<pre>kept</pre>' ], $replacements );
	}

	public function testTagAtEndOfStringStopsLoopWithoutFurtherSearch(): void {
		// A tag placed at the very end of the string makes the post-replacement
		// string shorter than the next loop-entry check requires, hitting the
		// "not enough room left to search" break rather than the strpos-false break.
		$str = 'abc<pre>x</pre>';
		$replacements = [];

		$result = TemplateInForm::removeUnparsedText( $str, $replacements );

		$this->assertSame( "abc\1" . '0' . "\2", $result );
		$this->assertSame( [ '<pre>x</pre>' ], $replacements );
	}

	public function testSetFieldValuesFromPageFieldContainingPipeInsidePreTag(): void {
		$existingContent = '{{PFTestTemplateInFormPreField01|field1=<pre>raw|pipe</pre>|field2=value2}}';
		$template = new TemplateInForm();

		$template->setPregMatchTemplateStr( 'PFTestTemplateInFormPreField01' );
		$template->setSearchTemplateStr( 'PFTestTemplateInFormPreField01' );

		$template->setFieldValuesFromPage( $existingContent );

		$this->assertSame( '<pre>raw|pipe</pre>', $template->getValuesFromPage()['field1'] );
		$this->assertSame( 'value2', $template->getValuesFromPage()['field2'] );
	}

	public function testSetFieldValuesFromPageFieldContainingNestedTemplateCall(): void {
		$existingContent =
			'{{PFTestTemplateInFormNestedTpl01|field1={{PFTestTemplateInFormOtherTpl01|x=1}}|field2=value2}}';
		$template = new TemplateInForm();

		$template->setPregMatchTemplateStr( 'PFTestTemplateInFormNestedTpl01' );
		$template->setSearchTemplateStr( 'PFTestTemplateInFormNestedTpl01' );

		$template->setFieldValuesFromPage( $existingContent );

		$this->assertSame(
			'{{PFTestTemplateInFormOtherTpl01|x=1}}',
			$template->getValuesFromPage()['field1']
		);
		$this->assertSame( 'value2', $template->getValuesFromPage()['field2'] );
	}

	public function testSetFieldValuesFromPageFieldWithoutEqualsSign(): void {
		$existingContent = '{{PFTestTemplateInFormPositional01|value1|field2=value2}}';
		$template = new TemplateInForm();

		$template->setPregMatchTemplateStr( 'PFTestTemplateInFormPositional01' );
		$template->setSearchTemplateStr( 'PFTestTemplateInFormPositional01' );

		$template->setFieldValuesFromPage( $existingContent );

		$this->assertSame( 'value1', $template->getValuesFromPage()[1] );
		$this->assertSame( 'value2', $template->getValuesFromPage()['field2'] );
	}

	public function testSetFieldValuesFromPageSkipsLeadingWhitespace(): void {
		$existingContent = "{{PFTestTemplateInFormWhitespace01 | field1=value1}}";
		$template = new TemplateInForm();

		$template->setPregMatchTemplateStr( 'PFTestTemplateInFormWhitespace01' );
		$template->setSearchTemplateStr( 'PFTestTemplateInFormWhitespace01' );

		$template->setFieldValuesFromPage( $existingContent );

		$this->assertSame( 'value1', $template->getValuesFromPage()['field1'] );
	}

	public function testSetFieldValuesFromPageThrowsOnMismatchedBrackets(): void {
		$existingContent = '{{PFTestTemplateInFormMismatched01|field1=[[Unclosed link';
		$template = new TemplateInForm();

		$template->setPregMatchTemplateStr( 'PFTestTemplateInFormMismatched01' );
		$template->setSearchTemplateStr( 'PFTestTemplateInFormMismatched01' );

		$this->expectException( MWException::class );
		$template->setFieldValuesFromPage( $existingContent );
	}

	public function testCheckIfAllInstancesPrintedNotMultiple(): void {
		$template = new TemplateInForm();
		$template->setAllowsMultiple( false );

		$template->checkIfAllInstancesPrinted( false, false );

		$this->assertFalse( $template->allInstancesPrinted() );
	}

	public function testCheckIfAllInstancesPrintedFormSubmittedWithinSubmittedInstances(): void {
		$mockRequest = $this->createMock( WebRequest::class );
		$mockRequest->method( 'getArray' )->willReturnCallback( static function ( $key ) {
			if ( $key === 'PFTestTemplateInFormAllPrintedA01' ) {
				return [ '0' => [ 'field1' => 'v0' ], '1' => [ 'field1' => 'v1' ] ];
			}
			return null;
		} );
		$template = new TemplateInForm();
		$template->setTemplateName( 'PFTestTemplateInFormAllPrintedA01' );
		$template->setAllowsMultiple( true );
		$template->setInstanceNum( 0 );
		$template->setFieldValuesFromSubmit( $mockRequest );

		$template->checkIfAllInstancesPrinted( true, false );

		$this->assertFalse( $template->allInstancesPrinted() );
	}

	public function testCheckIfAllInstancesPrintedBelowMinimumAllowed(): void {
		$template = TemplateInForm::newFromFormTag(
			[ 'for template', 'PFTestTemplateInFormAllPrintedB01', 'multiple', 'minimum instances=3' ],
			$this->createMockParser()
		);

		$template->checkIfAllInstancesPrinted( false, false );

		$this->assertFalse( $template->allInstancesPrinted() );
	}

	public function testCheckIfAllInstancesPrintedPageCallsTemplate(): void {
		$template = new TemplateInForm();
		$template->setTemplateName( 'PFTestTemplateInFormAllPrintedC01' );
		$template->setAllowsMultiple( true );
		$template->setPageRelatedInfo( '{{PFTestTemplateInFormAllPrintedC01|field1=x}}' );

		$template->checkIfAllInstancesPrinted( false, true );

		$this->assertFalse( $template->allInstancesPrinted() );
	}

	public function testCheckIfAllInstancesPrintedValuesFromSubmitNotNull(): void {
		$mockRequest = $this->createMock( WebRequest::class );
		$mockRequest->method( 'getArray' )->willReturnCallback( static function ( $key ) {
			if ( $key === 'PFTestTemplateInFormAllPrintedD01' ) {
				return [ 'field1' => 'value1' ];
			}
			return null;
		} );
		$template = new TemplateInForm();
		$template->setTemplateName( 'PFTestTemplateInFormAllPrintedD01' );
		$template->setAllowsMultiple( true );
		$template->setFieldValuesFromSubmit( $mockRequest );

		$template->checkIfAllInstancesPrinted( false, false );

		$this->assertFalse( $template->allInstancesPrinted() );
	}

	public function testCheckIfAllInstancesPrintedSetsTrueWhenNoConditionMatches(): void {
		$template = new TemplateInForm();
		$template->setAllowsMultiple( true );
		$template->setInstanceNum( 5 );

		$template->checkIfAllInstancesPrinted( true, false );

		$this->assertTrue( $template->allInstancesPrinted() );
	}

}
