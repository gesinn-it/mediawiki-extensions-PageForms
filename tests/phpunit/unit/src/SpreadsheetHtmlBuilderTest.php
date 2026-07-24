<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\FormCounters;
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

	public function testTableHtmlTemplateFieldClosesAndReopensTable(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$formField = $this->makeTemplateHoldingFormField( 'PFTestSHBSubTemplateField01', 'input_sub_template', [] );
		$tif = $this->makeTifForTable( 'PFTestSHBOuterTemplate01', [ $formField ], [] );

		$html = $this->builder->tableHTML( $tif, 0, static fn ( $f, $v ) => '' );

		$this->assertStringContainsString( '</table>', $html );
		$this->assertStringContainsString( 'type="hidden"', $html );
		$this->assertSame( 2, substr_count( $html, 'formtable' ) );
	}

	public function testTableHtmlTemplateFieldUsesClassAttribute(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$formField = $this->makeTemplateHoldingFormField(
			'PFTestSHBSubTemplateField02', 'input_sub_template2', [ 'class' => 'pfTemplateFieldClass' ]
		);
		$tif = $this->makeTifForTable( 'PFTestSHBOuterTemplate02', [ $formField ], [] );

		$html = $this->builder->tableHTML( $tif, 0, static fn ( $f, $v ) => '' );

		$this->assertStringContainsString( 'pfTemplateFieldClass', $html );
	}

	public function testTableHtmlHiddenFieldUsesClassAttribute(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$formField = $this->makeHiddenFormField(
			'PFTestSHBHiddenField01', 'input_hidden_with_class', [ 'class' => 'pfHiddenFieldClass' ]
		);
		$tif = $this->makeTifForTable( 'PFTestSHBOuterTemplate03', [ $formField ], [] );

		$html = $this->builder->tableHTML( $tif, 0, static fn ( $f, $v ) => '' );

		$this->assertStringContainsString( 'pfHiddenFieldClass', $html );
	}

	public function testTableHtmlUsesFormCountersWhenProvided(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$formField = $this->makeVisibleFormField(
			'PFTestSHBCounterField01', 'PFTestSHBCounterField01: ', 'input_counter_field'
		);
		$tif = $this->makeTifForTable( 'PFTestSHBOuterTemplate04', [ $formField ], [] );

		$counters = new FormCounters( 5, 0 );
		$this->builder->tableHTML( $tif, 0, static fn ( $f, $v ) => '', $counters );

		$this->assertSame( 6, $counters->fieldNum );
		$this->assertSame( 6, $wgPageFormsFieldNum );
	}

	public function testTableHtmlUsesLabelMsgWhenLabelIsNull(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$templateField = $this->createMock( TemplateField::class );
		$templateField->method( 'getFieldName' )->willReturn( 'PFTestSHBLabelMsgField01' );
		$templateField->method( 'getLabel' )->willReturn( null );

		$formField = $this->createMock( FormField::class );
		$formField->template_field = $templateField;
		$formField->method( 'holdsTemplate' )->willReturn( false );
		$formField->method( 'isHidden' )->willReturn( false );
		$formField->method( 'getLabel' )->willReturn( null );
		$formField->method( 'getLabelMsg' )->willReturn( 'pf-test-shb-label-msg-01' );
		$formField->method( 'hasFieldArg' )->willReturn( false );
		$formField->method( 'getInputName' )->willReturn( 'input_label_msg_field' );
		$formField->method( 'additionalHTMLForInput' )->willReturn( '' );

		$tif = $this->makeTifForTable( 'PFTestSHBOuterTemplate05', [ $formField ], [] );
		$html = $this->builder->tableHTML( $tif, 0, static fn ( $f, $v ) => '' );

		$this->assertStringContainsString( '<label', $html );
	}

	public function testTableHtmlUsesTemplateFieldLabelWhenFieldLabelAndMsgAreNull(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$templateField = $this->createMock( TemplateField::class );
		$templateField->method( 'getFieldName' )->willReturn( 'PFTestSHBTemplateFieldLabel01' );
		$templateField->method( 'getLabel' )->willReturn( 'PFTestSHBTemplateFieldLabelText01' );

		$formField = $this->createMock( FormField::class );
		$formField->template_field = $templateField;
		$formField->method( 'holdsTemplate' )->willReturn( false );
		$formField->method( 'isHidden' )->willReturn( false );
		$formField->method( 'getLabel' )->willReturn( null );
		$formField->method( 'getLabelMsg' )->willReturn( null );
		$formField->method( 'hasFieldArg' )->willReturn( false );
		$formField->method( 'getInputName' )->willReturn( 'input_template_field_label' );
		$formField->method( 'additionalHTMLForInput' )->willReturn( '' );

		$tif = $this->makeTifForTable( 'PFTestSHBOuterTemplate06', [ $formField ], [] );
		$html = $this->builder->tableHTML( $tif, 0, static fn ( $f, $v ) => '' );

		$this->assertStringContainsString( 'PFTestSHBTemplateFieldLabelText01:', $html );
	}

	public function testTableHtmlFallsBackToFieldNameWhenNoLabelAvailable(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$templateField = $this->createMock( TemplateField::class );
		$templateField->method( 'getFieldName' )->willReturn( 'PFTestSHBFallbackFieldName01' );
		$templateField->method( 'getLabel' )->willReturn( null );

		$formField = $this->createMock( FormField::class );
		$formField->template_field = $templateField;
		$formField->method( 'holdsTemplate' )->willReturn( false );
		$formField->method( 'isHidden' )->willReturn( false );
		$formField->method( 'getLabel' )->willReturn( null );
		$formField->method( 'getLabelMsg' )->willReturn( null );
		$formField->method( 'hasFieldArg' )->willReturn( false );
		$formField->method( 'getInputName' )->willReturn( 'input_fallback_field_name' );
		$formField->method( 'additionalHTMLForInput' )->willReturn( '' );

		$tif = $this->makeTifForTable( 'PFTestSHBOuterTemplate07', [ $formField ], [] );
		$html = $this->builder->tableHTML( $tif, 0, static fn ( $f, $v ) => '' );

		$this->assertStringContainsString( 'PFTestSHBFallbackFieldName01: ', $html );
	}

	public function testTableHtmlUsesTooltipDataAttribute(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$templateField = $this->createMock( TemplateField::class );
		$templateField->method( 'getFieldName' )->willReturn( 'PFTestSHBTooltipField01' );
		$templateField->method( 'getLabel' )->willReturn( null );

		$formField = $this->createMock( FormField::class );
		$formField->template_field = $templateField;
		$formField->method( 'holdsTemplate' )->willReturn( false );
		$formField->method( 'isHidden' )->willReturn( false );
		$formField->method( 'getLabel' )->willReturn( 'PFTestSHBTooltipField01: ' );
		$formField->method( 'getLabelMsg' )->willReturn( null );
		$formField->method( 'hasFieldArg' )->willReturnCallback(
			static fn ( $key ) => $key === 'tooltip'
		);
		$formField->method( 'getFieldArg' )->willReturnCallback(
			static fn ( $key ) => $key === 'tooltip' ? 'PFTestSHBTooltipText01' : null
		);
		$formField->method( 'getInputName' )->willReturn( 'input_tooltip_field' );
		$formField->method( 'additionalHTMLForInput' )->willReturn( '' );

		$tif = $this->makeTifForTable( 'PFTestSHBOuterTemplate08', [ $formField ], [] );
		$html = $this->builder->tableHTML( $tif, 0, static fn ( $f, $v ) => '' );

		$this->assertStringContainsString( 'data-tooltip="PFTestSHBTooltipText01"', $html );
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

	public function testSpreadsheetHtmlGridParamsContainLabelWhenPresent(): void {
		global $wgPageFormsGridParams, $wgPageFormsGridValues;
		$wgPageFormsGridParams = [];
		$wgPageFormsGridValues = [];

		$formField = $this->makeAdvancedSpreadsheetFormField( 'PFTestSHBLabelField01', [
			'label' => 'PFTestSHBLabelText01',
		] );
		$tif = $this->makeTifForSpreadsheet( 'PFTestSHBGridTemplate01', [ $formField ], [] );

		$out = $this->createMock( OutputPage::class );
		$out->method( 'addModules' );

		$this->builder->spreadsheetHTML( $tif, $out, '/extensions/PageForms' );

		$this->assertSame(
			'PFTestSHBLabelText01', $wgPageFormsGridParams['PFTestSHBGridTemplate01'][0]['label']
		);
	}

	public function testSpreadsheetHtmlGridParamsContainDefaultWhenPresent(): void {
		global $wgPageFormsGridParams, $wgPageFormsGridValues;
		$wgPageFormsGridParams = [];
		$wgPageFormsGridValues = [];

		$formField = $this->makeAdvancedSpreadsheetFormField( 'PFTestSHBDefaultField01', [
			'defaultValue' => 'PFTestSHBDefaultValue01',
		] );
		$tif = $this->makeTifForSpreadsheet( 'PFTestSHBGridTemplate02', [ $formField ], [] );

		$out = $this->createMock( OutputPage::class );
		$out->method( 'addModules' );

		$this->builder->spreadsheetHTML( $tif, $out, '/extensions/PageForms' );

		$this->assertSame(
			'PFTestSHBDefaultValue01', $wgPageFormsGridParams['PFTestSHBGridTemplate02'][0]['default']
		);
	}

	public function testSpreadsheetHtmlListFieldUsesPlainTextType(): void {
		global $wgPageFormsGridParams, $wgPageFormsGridValues;
		$wgPageFormsGridParams = [];
		$wgPageFormsGridValues = [];

		$formField = $this->makeAdvancedSpreadsheetFormField( 'PFTestSHBListField01', [
			'isList' => true,
			'possibleValues' => [ 'X', 'Y' ],
		] );
		$tif = $this->makeTifForSpreadsheet( 'PFTestSHBGridTemplate03', [ $formField ], [] );

		$out = $this->createMock( OutputPage::class );
		$out->method( 'addModules' );

		$this->builder->spreadsheetHTML( $tif, $out, '/extensions/PageForms' );

		$params = $wgPageFormsGridParams['PFTestSHBGridTemplate03'][0];
		$this->assertSame( 'text', $params['type'] );
		$this->assertSame( '', $params['autocompletedatatype'] );
		$this->assertSame( '', $params['autocompletesettings'] );
	}

	public function testSpreadsheetHtmlTokensInputTypeUsesPlainTextType(): void {
		global $wgPageFormsGridParams, $wgPageFormsGridValues;
		$wgPageFormsGridParams = [];
		$wgPageFormsGridValues = [];

		$formField = $this->makeAdvancedSpreadsheetFormField( 'PFTestSHBTokensField01', [
			'inputType' => 'tokens',
		] );
		$tif = $this->makeTifForSpreadsheet( 'PFTestSHBGridTemplate04', [ $formField ], [] );

		$out = $this->createMock( OutputPage::class );
		$out->method( 'addModules' );

		$this->builder->spreadsheetHTML( $tif, $out, '/extensions/PageForms' );

		$this->assertSame( 'text', $wgPageFormsGridParams['PFTestSHBGridTemplate04'][0]['type'] );
	}

	public function testSpreadsheetHtmlTextareaInputTypeSetsTextareaType(): void {
		global $wgPageFormsGridParams, $wgPageFormsGridValues;
		$wgPageFormsGridParams = [];
		$wgPageFormsGridValues = [];

		$formField = $this->makeAdvancedSpreadsheetFormField( 'PFTestSHBTextareaField01', [
			'inputType' => 'textarea',
		] );
		$tif = $this->makeTifForSpreadsheet( 'PFTestSHBGridTemplate06', [ $formField ], [] );

		$out = $this->createMock( OutputPage::class );
		$out->method( 'addModules' );

		$this->builder->spreadsheetHTML( $tif, $out, '/extensions/PageForms' );

		$this->assertSame( 'textarea', $wgPageFormsGridParams['PFTestSHBGridTemplate06'][0]['type'] );
	}

	public function testSpreadsheetHtmlDateInputTypeSetsDateType(): void {
		global $wgPageFormsGridParams, $wgPageFormsGridValues;
		$wgPageFormsGridParams = [];
		$wgPageFormsGridValues = [];

		$formField = $this->makeAdvancedSpreadsheetFormField( 'PFTestSHBDateField01', [
			'inputType' => 'date',
		] );
		$tif = $this->makeTifForSpreadsheet( 'PFTestSHBGridTemplate07', [ $formField ], [] );

		$out = $this->createMock( OutputPage::class );
		$out->method( 'addModules' );

		$this->builder->spreadsheetHTML( $tif, $out, '/extensions/PageForms' );

		$this->assertSame( 'date', $wgPageFormsGridParams['PFTestSHBGridTemplate07'][0]['type'] );
	}

	public function testSpreadsheetHtmlDatetimeInputTypeSetsDatetimeType(): void {
		global $wgPageFormsGridParams, $wgPageFormsGridValues;
		$wgPageFormsGridParams = [];
		$wgPageFormsGridValues = [];

		$formField = $this->makeAdvancedSpreadsheetFormField( 'PFTestSHBDatetimeField01', [
			'inputType' => 'datetime',
		] );
		$tif = $this->makeTifForSpreadsheet( 'PFTestSHBGridTemplate08', [ $formField ], [] );

		$out = $this->createMock( OutputPage::class );
		$out->method( 'addModules' );

		$this->builder->spreadsheetHTML( $tif, $out, '/extensions/PageForms' );

		$this->assertSame( 'datetime', $wgPageFormsGridParams['PFTestSHBGridTemplate08'][0]['type'] );
	}

	public function testSpreadsheetHtmlPropertyAutocompleteWithPossibleValuesUsesSelectType(): void {
		global $wgPageFormsGridParams, $wgPageFormsGridValues;
		$wgPageFormsGridParams = [];
		$wgPageFormsGridValues = [];

		$formField = $this->makeAdvancedSpreadsheetFormField( 'PFTestSHBPropertySelectField01', [
			'possibleValues' => [ 'Alpha', 'Beta' ],
			'fieldArgs' => [ 'values from property' => 'PFTestSHBSomeProperty01' ],
		] );
		$tif = $this->makeTifForSpreadsheet( 'PFTestSHBGridTemplate09', [ $formField ], [] );

		$out = $this->createMock( OutputPage::class );
		$out->method( 'addModules' );

		$this->builder->spreadsheetHTML( $tif, $out, '/extensions/PageForms' );

		$params = $wgPageFormsGridParams['PFTestSHBGridTemplate09'][0];
		$this->assertSame( 'select', $params['type'] );
		$this->assertSame( 'Id', $params['valueField'] );
		$this->assertSame( 'Name', $params['textField'] );
		$this->assertSame(
			[
				[ 'Name' => '', 'Id' => '' ],
				[ 'Name' => 'Alpha', 'Id' => 'Alpha' ],
				[ 'Name' => 'Beta', 'Id' => 'Beta' ],
			],
			$params['items']
		);
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function makeAdvancedSpreadsheetFormField( string $fieldName, array $options = [] ): object {
		$templateField = $this->createMock( TemplateField::class );
		$templateField->method( 'getFieldName' )->willReturn( $fieldName );

		$fieldArgs = $options['fieldArgs'] ?? [];

		$formField = $this->createMock( FormField::class );
		$formField->template_field = $templateField;
		$formField->method( 'getInputType' )->willReturn( $options['inputType'] ?? 'text' );
		$formField->method( 'getFieldArgs' )->willReturn( $fieldArgs );
		$formField->method( 'getPossibleValues' )->willReturn( $options['possibleValues'] ?? null );
		$formField->method( 'getLabel' )->willReturn( $options['label'] ?? null );
		$formField->method( 'getDefaultValue' )->willReturn( $options['defaultValue'] ?? null );
		$formField->method( 'isList' )->willReturn( $options['isList'] ?? false );
		$formField->method( 'getFieldArg' )->willReturnCallback(
			static fn ( $key ) => $fieldArgs[$key] ?? null
		);
		return $formField;
	}

	private function makeTifForTable( string $templateName, array $fields, array $gridValues ): TemplateInForm {
		$tif = $this->createMock( TemplateInForm::class );
		$tif->method( 'getTemplateName' )->willReturn( $templateName );
		$tif->method( 'getFields' )->willReturn( $fields );
		$tif->method( 'getGridValues' )->willReturn( $gridValues );
		return $tif;
	}

	private function makeHiddenFormField( string $fieldName, string $inputName, array $fieldArgs = [] ): object {
		$templateField = $this->createMock( TemplateField::class );
		$templateField->method( 'getFieldName' )->willReturn( $fieldName );

		$formField = $this->createMock( FormField::class );
		$formField->template_field = $templateField;
		$formField->method( 'holdsTemplate' )->willReturn( false );
		$formField->method( 'isHidden' )->willReturn( true );
		$formField->method( 'hasFieldArg' )->willReturnCallback(
			static fn ( $key ) => array_key_exists( $key, $fieldArgs )
		);
		$formField->method( 'getFieldArg' )->willReturnCallback(
			static fn ( $key ) => $fieldArgs[$key] ?? null
		);
		$formField->method( 'getInputName' )->willReturn( $inputName );
		return $formField;
	}

	private function makeTemplateHoldingFormField(
		string $fieldName, string $inputName, array $fieldArgs = []
	): object {
		$templateField = $this->createMock( TemplateField::class );
		$templateField->method( 'getFieldName' )->willReturn( $fieldName );

		$formField = $this->createMock( FormField::class );
		$formField->template_field = $templateField;
		$formField->method( 'holdsTemplate' )->willReturn( true );
		$formField->method( 'hasFieldArg' )->willReturnCallback(
			static fn ( $key ) => array_key_exists( $key, $fieldArgs )
		);
		$formField->method( 'getFieldArg' )->willReturnCallback(
			static fn ( $key ) => $fieldArgs[$key] ?? null
		);
		$formField->method( 'getInputName' )->willReturn( $inputName );
		$formField->method( 'additionalHTMLForInput' )->willReturn( '' );
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
