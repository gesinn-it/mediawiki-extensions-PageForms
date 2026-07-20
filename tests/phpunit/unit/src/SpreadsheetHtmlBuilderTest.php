<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\FormField;
use MediaWiki\Extension\PageForms\SpreadsheetHtmlBuilder;
use MediaWiki\Extension\PageForms\TemplateField;
use MediaWiki\Extension\PageForms\TemplateInForm;
use PHPUnit\Framework\TestCase;

/**
 * @covers MediaWiki\Extension\PageForms\SpreadsheetHtmlBuilder
 */
class SpreadsheetHtmlBuilderTest extends TestCase {

	private SpreadsheetHtmlBuilder $builder;

	protected function setUp(): void {
		parent::setUp();
		$this->builder = new SpreadsheetHtmlBuilder();
	}

	// -----------------------------------------------------------------------
	// getSpreadsheetAutocompleteAttributes
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider provideAutocompleteAttributes
	 */
	public function testGetSpreadsheetAutocompleteAttributes( array $args, array $expected ): void {
		$this->assertSame( $expected, $this->builder->getSpreadsheetAutocompleteAttributes( $args ) );
	}

	public static function provideAutocompleteAttributes(): array {
		return [
			'empty args returns empty pair' => [
				[],
				[ '', '' ],
			],
			'values from category' => [
				[ 'values from category' => 'MyCategory' ],
				[ 'category', 'MyCategory' ],
			],
			'values from property' => [
				[ 'values from property' => 'MyProp' ],
				[ 'property', 'MyProp' ],
			],
			'values from concept' => [
				[ 'values from concept' => 'MyConcept' ],
				[ 'concept', 'MyConcept' ],
			],
			'values dependent on' => [
				[ 'values dependent on' => 'OtherField' ],
				[ 'dep_on', '' ],
			],
			'values from external data' => [
				[ 'values from external data' => 'ignored', 'origName' => 'MySource' ],
				[ 'external data', 'MySource' ],
			],
			'values from wikidata' => [
				[ 'values from wikidata' => 'SELECT ?item WHERE { ?item wdt:P31 wd:Q5 }' ],
				[ 'wikidata', 'SELECT ?item WHERE { ?item wdt:P31 wd:Q5 }' ],
			],
			'unrecognised key returns empty pair' => [
				[ 'values from namespace' => 'Help' ],
				[ '', '' ],
			],
		];
	}

	// -----------------------------------------------------------------------
	// tableHTML
	// -----------------------------------------------------------------------

	public function testTableHtmlReturnsFormtable(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$tif = $this->makeTifForTable( 'MyTemplate', [], [] );
		$html = $this->builder->tableHTML( $tif, 0, static fn ( $f, $v ) => '<input/>' );

		$this->assertStringContainsString( 'class="formtable"', $html );
	}

	public function testTableHtmlHiddenFieldIsHiddenInput(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$formField = $this->makeHiddenFormField( 'HiddenField', 'input_hidden' );
		$tif = $this->makeTifForTable( 'T', [ $formField ], [ 0 => [ 'HiddenField' => 'hiddenValue' ] ] );

		$html = $this->builder->tableHTML( $tif, 0, static fn ( $f, $v ) => '' );

		$this->assertStringContainsString( 'type="hidden"', $html );
	}

	public function testTableHtmlVisibleFieldUsesCallback(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$formField = $this->makeVisibleFormField( 'MyField', 'MyField: ', 'input_my_field' );
		$tif = $this->makeTifForTable( 'T', [ $formField ], [] );

		$html = $this->builder->tableHTML( $tif, 0, static fn ( $f, $v ) => '<span class="injected"/>' );

		$this->assertStringContainsString( '<span class="injected"/>', $html );
	}

	public function testTableHtmlPassesInstanceValues(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$formField = $this->makeVisibleFormField( 'Color', 'Color: ', 'input_color' );
		$tif = $this->makeTifForTable( 'T', [ $formField ], [ 1 => [ 'Color' => 'blue' ] ] );

		$receivedValue = null;
		$this->builder->tableHTML( $tif, 1, static function ( $f, $v ) use ( &$receivedValue ) {
			$receivedValue = $v;
			return '';
		} );

		$this->assertSame( 'blue', $receivedValue );
	}

	public function testTableHtmlNullValueWhenInstanceNumAbsent(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$formField = $this->makeVisibleFormField( 'Color', 'Color: ', 'input_color' );
		$tif = $this->makeTifForTable( 'T', [ $formField ], [] );

		$receivedValue = 'not-null';
		$this->builder->tableHTML( $tif, 0, static function ( $f, $v ) use ( &$receivedValue ) {
			$receivedValue = $v;
			return '';
		} );

		$this->assertNull( $receivedValue );
	}

	// -----------------------------------------------------------------------
	// spreadsheetHTML
	// -----------------------------------------------------------------------

	public function testSpreadsheetHtmlReturnsNullWhenNoFields(): void {
		$tif = $this->createMock( TemplateInForm::class );
		$tif->method( 'getFields' )->willReturn( [] );

		$out = $this->createMock( OutputPage::class );
		$out->expects( $this->never() )->method( 'addModules' );

		$result = $this->builder->spreadsheetHTML( $tif, $out, '/extensions/PageForms' );

		$this->assertNull( $result );
	}

	public function testSpreadsheetHtmlAddsModule(): void {
		global $wgPageFormsGridParams, $wgPageFormsGridValues;
		$wgPageFormsGridParams = [];
		$wgPageFormsGridValues = [];

		$formField = $this->makeSpreadsheetFormField( 'MyField', 'text', null, [] );
		$tif = $this->makeTifForSpreadsheet( 'MyTemplate', [ $formField ], [] );

		$out = $this->createMock( OutputPage::class );
		$out->expects( $this->once() )->method( 'addModules' )->with( 'ext.pageforms.spreadsheet' );

		$this->builder->spreadsheetHTML( $tif, $out, '/extensions/PageForms' );
	}

	public function testSpreadsheetHtmlReturnsDivWithPfSpreadsheetClass(): void {
		global $wgPageFormsGridParams, $wgPageFormsGridValues;
		$wgPageFormsGridParams = [];
		$wgPageFormsGridValues = [];

		$formField = $this->makeSpreadsheetFormField( 'MyField', 'text', null, [] );
		$tif = $this->makeTifForSpreadsheet( 'MyTemplate', [ $formField ], [] );

		$out = $this->createMock( OutputPage::class );
		$out->method( 'addModules' );

		$html = $this->builder->spreadsheetHTML( $tif, $out, '/extensions/PageForms' );

		$this->assertNotNull( $html );
		$this->assertStringContainsString( 'class="pfSpreadsheet"', $html );
		$this->assertStringContainsString( 'MyTemplateGrid', $html );
	}

	public function testSpreadsheetHtmlSetsGridParams(): void {
		global $wgPageFormsGridParams, $wgPageFormsGridValues;
		$wgPageFormsGridParams = [];
		$wgPageFormsGridValues = [];

		$formField = $this->makeSpreadsheetFormField( 'MyField', 'text', null, [] );
		$tif = $this->makeTifForSpreadsheet( 'MyTemplate', [ $formField ], [] );

		$out = $this->createMock( OutputPage::class );
		$out->method( 'addModules' );

		$this->builder->spreadsheetHTML( $tif, $out, '/extensions/PageForms' );

		$this->assertArrayHasKey( 'MyTemplate', $wgPageFormsGridParams );
		$this->assertCount( 1, $wgPageFormsGridParams['MyTemplate'] );
		$this->assertSame( 'MyField', $wgPageFormsGridParams['MyTemplate'][0]['name'] );
	}

	public function testSpreadsheetHtmlGridParamsContainCheckboxType(): void {
		global $wgPageFormsGridParams, $wgPageFormsGridValues;
		$wgPageFormsGridParams = [];
		$wgPageFormsGridValues = [];

		$formField = $this->makeSpreadsheetFormField( 'BoolField', 'checkbox', null, [] );
		$tif = $this->makeTifForSpreadsheet( 'T', [ $formField ], [] );

		$out = $this->createMock( OutputPage::class );
		$out->method( 'addModules' );

		$this->builder->spreadsheetHTML( $tif, $out, '/extensions/PageForms' );

		$this->assertSame( 'checkbox', $wgPageFormsGridParams['T'][0]['type'] );
	}

	public function testSpreadsheetHtmlGridParamsContainSelectForPossibleValues(): void {
		global $wgPageFormsGridParams, $wgPageFormsGridValues;
		$wgPageFormsGridParams = [];
		$wgPageFormsGridValues = [];

		$formField = $this->makeSpreadsheetFormField( 'ChoiceField', 'text', null, [ 'A', 'B', 'C' ] );
		$tif = $this->makeTifForSpreadsheet( 'T', [ $formField ], [] );

		$out = $this->createMock( OutputPage::class );
		$out->method( 'addModules' );

		$this->builder->spreadsheetHTML( $tif, $out, '/extensions/PageForms' );

		$params = $wgPageFormsGridParams['T'][0];
		$this->assertSame( [ 'A', 'B', 'C' ], $params['values'] );
	}

	public function testSpreadsheetHtmlHeightAttributeSetWhenPresent(): void {
		global $wgPageFormsGridParams, $wgPageFormsGridValues;
		$wgPageFormsGridParams = [];
		$wgPageFormsGridValues = [];

		$formField = $this->makeSpreadsheetFormField( 'F', 'text', null, [] );
		$tif = $this->makeTifForSpreadsheet( 'T', [ $formField ], [], '400px' );

		$out = $this->createMock( OutputPage::class );
		$out->method( 'addModules' );

		$html = $this->builder->spreadsheetHTML( $tif, $out, '/extensions/PageForms' );

		$this->assertStringContainsString( 'height="400px"', $html );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function makeTifForTable( string $templateName, array $fields, array $gridValues ): TemplateInForm {
		$tif = $this->createMock( TemplateInForm::class );
		$tif->method( 'getTemplateName' )->willReturn( $templateName );
		$tif->method( 'getFields' )->willReturn( $fields );
		$tif->method( 'getGridValues' )->willReturn( $gridValues );
		return $tif;
	}

	private function makeHiddenFormField( string $fieldName, string $inputName ): object {
		$templateField = $this->createMock( TemplateField::class );
		$templateField->method( 'getFieldName' )->willReturn( $fieldName );

		$formField = $this->createMock( FormField::class );
		$formField->template_field = $templateField;
		$formField->method( 'holdsTemplate' )->willReturn( false );
		$formField->method( 'isHidden' )->willReturn( true );
		$formField->method( 'hasFieldArg' )->willReturn( false );
		$formField->method( 'getInputName' )->willReturn( $inputName );
		return $formField;
	}

	private function makeVisibleFormField( string $fieldName, string $labelText, string $inputName ): object {
		$templateField = $this->createMock( TemplateField::class );
		$templateField->method( 'getFieldName' )->willReturn( $fieldName );
		$templateField->method( 'getLabel' )->willReturn( null );

		$formField = $this->createMock( FormField::class );
		$formField->template_field = $templateField;
		$formField->method( 'holdsTemplate' )->willReturn( false );
		$formField->method( 'isHidden' )->willReturn( false );
		$formField->method( 'getLabel' )->willReturn( $labelText );
		$formField->method( 'getLabelMsg' )->willReturn( null );
		$formField->method( 'hasFieldArg' )->willReturn( false );
		$formField->method( 'getInputName' )->willReturn( $inputName );
		$formField->method( 'additionalHTMLForInput' )->willReturn( '' );
		return $formField;
	}

	private function makeSpreadsheetFormField(
		string $fieldName,
		string $inputType,
		?string $defaultValue,
		array $possibleValues
	): object {
		$templateField = $this->createMock( TemplateField::class );
		$templateField->method( 'getFieldName' )->willReturn( $fieldName );

		$formField = $this->createMock( FormField::class );
		$formField->template_field = $templateField;
		$formField->method( 'getInputType' )->willReturn( $inputType );
		$formField->method( 'getFieldArgs' )->willReturn( [] );
		$formField->method( 'getPossibleValues' )->willReturn( $possibleValues ?: null );
		$formField->method( 'getLabel' )->willReturn( null );
		$formField->method( 'getDefaultValue' )->willReturn( $defaultValue );
		$formField->method( 'isList' )->willReturn( false );
		return $formField;
	}

	private function makeTifForSpreadsheet(
		string $templateName,
		array $fields,
		array $gridValues,
		?string $height = null
	): TemplateInForm {
		$tif = $this->createMock( TemplateInForm::class );
		$tif->method( 'getTemplateName' )->willReturn( $templateName );
		$tif->method( 'getFields' )->willReturn( $fields );
		$tif->method( 'getGridValues' )->willReturn( $gridValues );
		$tif->method( 'getHeight' )->willReturn( $height );
		return $tif;
	}
}
