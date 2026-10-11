<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Unit;

use MediaWiki\Extension\PageForms\FormField;
use MediaWiki\Extension\PageForms\TemplateInForm;
use MWException;
use PHPUnit\Framework\TestCase;
use WebRequest;

/**
 * The parts of TemplateInForm that need neither the database nor any service. What reads a template
 * page, a parser or a title is in the integration test of the class.
 *
 * @covers \MediaWiki\Extension\PageForms\TemplateInForm
 */
class TemplateInFormTest extends TestCase {

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

	/**
	 * A request that holds the given arrays under their names and nothing else.
	 *
	 * @param array<string, array> $data
	 * @return WebRequest
	 */
	private function newRequest( array $data ): WebRequest {
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getArray' )->willReturnCallback(
			static fn ( $key ) => $data[$key] ?? null
		);
		return $request;
	}

	/**
	 * What the instance reads is in the test of TemplateSubmittedValues; this is what TemplateInForm hands it.
	 */
	public function testSetFieldValuesFromSubmitReadsTheInstanceOfTheTemplate(): void {
		$template = new TemplateInForm();
		$template->setTemplateName( 'PFTestTemplateInFormSubmit01' );
		$template->setAllowsMultiple( true );
		$template->setInstanceNum( 1 );

		$template->setFieldValuesFromSubmit( $this->newRequest( [ 'PFTestTemplateInFormSubmit01' => [
			'0' => [ 'field1' => 'value0' ],
			'1' => [ 'field1' => 'value1' ],
		] ] ) );

		$this->assertSame( [ 'field1' => 'value1' ], $template->getValuesFromSubmit() );
	}

	public function testSetFieldValuesFromSubmitTellsTheInstancesOnThePage(): void {
		$template = new TemplateInForm();
		$template->setTemplateName( 'PFTestTemplateInFormSubmit02' );
		$template->setAllowsMultiple( true );
		$template->setInstanceNum( 0 );
		$template->setPageRelatedInfo( '{{PFTestTemplateInFormSubmit02|field1=x}}' );

		$template->setFieldValuesFromSubmit( $this->newRequest( [ 'PFTestTemplateInFormSubmit02' => [
			'0' => [ 'field1' => 'value0' ],
			'1' => [ 'field1' => 'value1' ],
		] ] ) );

		$this->assertSame( [ 'field1' => 'value0' ], $template->getValuesFromSubmit() );
	}

	/**
	 * @dataProvider provideUnparsedText
	 * @param string $text
	 * @param string $expectedText
	 * @param string[] $expectedReplacements
	 */
	public function testRemoveUnparsedText( string $text, string $expectedText, array $expectedReplacements ): void {
		$replacements = [];

		$result = TemplateInForm::removeUnparsedText( $text, $replacements );

		$this->assertEquals( $expectedText, $result );
		$this->assertEquals( $expectedReplacements, $replacements );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string[]}>
	 */
	public static function provideUnparsedText(): array {
		return [
			'a single tag' => [
				'This is some text <pre>unparsed content</pre> more text.',
				"This is some text \1" . "0" . "\2 more text.",
				[ '<pre>unparsed content</pre>' ],
			],
			'several tags' => [
				'Text <pre>first</pre> middle <nowiki>second</nowiki> end.',
				"Text \1" . "0" . "\2 middle \1" . "1" . "\2 end.",
				[ '<pre>first</pre>', '<nowiki>second</nowiki>' ],
			],
			'nested tags are replaced as a whole' => [
				'Nested <pre><ref>ignored</ref></pre> content.',
				"Nested \1" . "0" . "\2 content.",
				[ '<pre><ref>ignored</ref></pre>' ],
			],
			'an unclosed tag is left as it is' => [
				'This is <pre>unclosed text.',
				'This is <pre>unclosed text.',
				[],
			],
			'a self-closing tag is ignored' => [
				'Before <ref name="abc" /> <pre>kept</pre> after.',
				"Before <ref name=\"abc\" /> \1" . '0' . "\2 after.",
				[ '<pre>kept</pre>' ],
			],
			// A tag at the very end of the string leaves too little room for another search, which ends
			// the loop through another break than "no further tag".
			'a tag at the end of the string' => [
				'abc<pre>x</pre>',
				"abc\1" . '0' . "\2",
				[ '<pre>x</pre>' ],
			],
		];
	}

	/**
	 * @dataProvider providePageTemplateCalls
	 * @param string $templateName
	 * @param string $existingContent
	 * @param array<int|string, string> $expectedValues
	 */
	public function testSetFieldValuesFromPage(
		string $templateName, string $existingContent, array $expectedValues
	): void {
		$template = new TemplateInForm();
		$template->setPregMatchTemplateStr( $templateName );
		$template->setSearchTemplateStr( $templateName );

		$template->setFieldValuesFromPage( $existingContent );

		foreach ( $expectedValues as $field => $value ) {
			$this->assertSame( $value, $template->getValuesFromPage()[$field] );
		}
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: array}>
	 */
	public static function providePageTemplateCalls(): array {
		return [
			'a simple template call' => [
				'TemplateName',
				'{{TemplateName|field1=value1|field2=value2}}',
				[ 'field1' => 'value1', 'field2' => 'value2' ],
			],
			'a link in a value' => [
				'TemplateName',
				'{{TemplateName|field1=[[Link|Display]]|field2=value2}}',
				[ 'field1' => '[[Link|Display]]', 'field2' => 'value2' ],
			],
			'a pipe inside a pre tag' => [
				'PFTestTemplateInFormPreField01',
				'{{PFTestTemplateInFormPreField01|field1=<pre>raw|pipe</pre>|field2=value2}}',
				[ 'field1' => '<pre>raw|pipe</pre>', 'field2' => 'value2' ],
			],
			'a nested template call' => [
				'PFTestTemplateInFormNestedTpl01',
				'{{PFTestTemplateInFormNestedTpl01|field1={{PFTestTemplateInFormOtherTpl01|x=1}}|field2=value2}}',
				[ 'field1' => '{{PFTestTemplateInFormOtherTpl01|x=1}}', 'field2' => 'value2' ],
			],
			'a positional value without an equals sign' => [
				'PFTestTemplateInFormPositional01',
				'{{PFTestTemplateInFormPositional01|value1|field2=value2}}',
				[ 1 => 'value1', 'field2' => 'value2' ],
			],
			'whitespace before the first pipe' => [
				'PFTestTemplateInFormWhitespace01',
				'{{PFTestTemplateInFormWhitespace01 | field1=value1}}',
				[ 'field1' => 'value1' ],
			],
		];
	}

	public function testSetFieldValuesFromPageThrowsOnMismatchedBrackets(): void {
		$existingContent = '{{PFTestTemplateInFormMismatched01|field1=[[Unclosed link';

		$template = new TemplateInForm();
		$template->setPregMatchTemplateStr( 'PFTestTemplateInFormMismatched01' );
		$template->setSearchTemplateStr( 'PFTestTemplateInFormMismatched01' );

		$this->expectException( MWException::class );
		$template->setFieldValuesFromPage( $existingContent );
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

	// -------------------------------------------------------------------------
	// checkIfAllInstancesPrinted()
	// -------------------------------------------------------------------------

	public function testCheckIfAllInstancesPrintedNotMultiple(): void {
		$template = new TemplateInForm();
		$template->setAllowsMultiple( false );

		$template->checkIfAllInstancesPrinted( false, false );

		$this->assertFalse( $template->allInstancesPrinted() );
	}

	public function testCheckIfAllInstancesPrintedFormSubmittedWithinSubmittedInstances(): void {
		$request = $this->newRequest( [
			'PFTestTemplateInFormAllPrintedA01' => [ '0' => [ 'field1' => 'v0' ], '1' => [ 'field1' => 'v1' ] ],
		] );

		$template = new TemplateInForm();
		$template->setTemplateName( 'PFTestTemplateInFormAllPrintedA01' );
		$template->setAllowsMultiple( true );
		$template->setInstanceNum( 0 );
		$template->setFieldValuesFromSubmit( $request );

		$template->checkIfAllInstancesPrinted( true, false );

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
		$request = $this->newRequest( [ 'PFTestTemplateInFormAllPrintedD01' => [ '0' => [ 'field1' => 'value1' ] ] ] );

		$template = new TemplateInForm();
		$template->setTemplateName( 'PFTestTemplateInFormAllPrintedD01' );
		$template->setAllowsMultiple( true );
		$template->setFieldValuesFromSubmit( $request );

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
