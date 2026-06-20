<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\CalendarHtmlBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers MediaWiki\Extension\PageForms\CalendarHtmlBuilder
 */
class CalendarHtmlBuilderTest extends TestCase {

	private CalendarHtmlBuilder $builder;

	protected function setUp(): void {
		parent::setUp();
		$this->builder = new CalendarHtmlBuilder();
	}

	public function testCalendarHtmlReturnsDivWithPfFullCalendarJsClass(): void {
		global $wgPageFormsCalendarParams, $wgPageFormsCalendarValues;
		$wgPageFormsCalendarParams = [];
		$wgPageFormsCalendarValues = [];

		$tif = $this->makeTif( 'MyTemplate', [] );
		$html = $this->builder->calendarHTML( $tif, '/extensions/PageForms' );

		$this->assertStringContainsString( 'class="pfFullCalendarJS"', $html );
		$this->assertStringContainsString( 'MyTemplateFullCalendar', $html );
	}

	public function testCalendarHtmlContainsLoadingDiv(): void {
		global $wgPageFormsCalendarParams, $wgPageFormsCalendarValues;
		$wgPageFormsCalendarParams = [];
		$wgPageFormsCalendarValues = [];

		$tif = $this->makeTif( 'T', [] );
		$html = $this->builder->calendarHTML( $tif, '/extensions/PageForms' );

		$this->assertStringContainsString( 'fullCalendarLoading1', $html );
		$this->assertStringContainsString( 'loading.gif', $html );
	}

	public function testCalendarHtmlSetsCalendarParams(): void {
		global $wgPageFormsCalendarParams, $wgPageFormsCalendarValues;
		$wgPageFormsCalendarParams = [];
		$wgPageFormsCalendarValues = [];

		$formField = $this->makeFormField( 'MyField', 'text', null );
		$tif = $this->makeTif( 'MyTemplate', [ $formField ] );

		$this->builder->calendarHTML( $tif, '/extensions/PageForms' );

		$this->assertArrayHasKey( 'MyTemplate', $wgPageFormsCalendarParams );
		$this->assertCount( 1, $wgPageFormsCalendarParams['MyTemplate'] );
		$this->assertSame( 'MyField', $wgPageFormsCalendarParams['MyTemplate'][0]['name'] );
	}

	public function testCalendarHtmlParamsContainTextTypeByDefault(): void {
		global $wgPageFormsCalendarParams, $wgPageFormsCalendarValues;
		$wgPageFormsCalendarParams = [];
		$wgPageFormsCalendarValues = [];

		$formField = $this->makeFormField( 'F', 'text', null );
		$tif = $this->makeTif( 'T', [ $formField ] );

		$this->builder->calendarHTML( $tif, '/extensions/PageForms' );

		$this->assertSame( 'text', $wgPageFormsCalendarParams['T'][0]['type'] );
	}

	public function testCalendarHtmlParamsContainCheckboxType(): void {
		global $wgPageFormsCalendarParams, $wgPageFormsCalendarValues;
		$wgPageFormsCalendarParams = [];
		$wgPageFormsCalendarValues = [];

		$formField = $this->makeFormField( 'BoolField', 'checkbox', null );
		$tif = $this->makeTif( 'T', [ $formField ] );

		$this->builder->calendarHTML( $tif, '/extensions/PageForms' );

		$this->assertSame( 'checkbox', $wgPageFormsCalendarParams['T'][0]['type'] );
	}

	public function testCalendarHtmlParamsContainDateType(): void {
		global $wgPageFormsCalendarParams, $wgPageFormsCalendarValues;
		$wgPageFormsCalendarParams = [];
		$wgPageFormsCalendarValues = [];

		$formField = $this->makeFormField( 'DateField', 'date', null );
		$tif = $this->makeTif( 'T', [ $formField ] );

		$this->builder->calendarHTML( $tif, '/extensions/PageForms' );

		$this->assertSame( 'date', $wgPageFormsCalendarParams['T'][0]['type'] );
	}

	public function testCalendarHtmlParamsContainSelectForPossibleValues(): void {
		global $wgPageFormsCalendarParams, $wgPageFormsCalendarValues;
		$wgPageFormsCalendarParams = [];
		$wgPageFormsCalendarValues = [];

		$formField = $this->makeFormField( 'ChoiceField', 'text', [ 'A', 'B', 'C' ] );
		$tif = $this->makeTif( 'T', [ $formField ] );

		$this->builder->calendarHTML( $tif, '/extensions/PageForms' );

		$params = $wgPageFormsCalendarParams['T'][0];
		$this->assertSame( 'select', $params['type'] );
		$this->assertArrayHasKey( 'items', $params );
	}

	public function testCalendarHtmlParamsIncludeLabelAsTitleWhenSet(): void {
		global $wgPageFormsCalendarParams, $wgPageFormsCalendarValues;
		$wgPageFormsCalendarParams = [];
		$wgPageFormsCalendarValues = [];

		$formField = $this->makeFormField( 'F', 'text', null, 'My Label' );
		$tif = $this->makeTif( 'T', [ $formField ] );

		$this->builder->calendarHTML( $tif, '/extensions/PageForms' );

		$this->assertSame( 'My Label', $wgPageFormsCalendarParams['T'][0]['title'] );
	}

	public function testCalendarHtmlSetsCalendarValues(): void {
		global $wgPageFormsCalendarParams, $wgPageFormsCalendarValues;
		$wgPageFormsCalendarParams = [];
		$wgPageFormsCalendarValues = [];

		$tif = $this->makeTif( 'MyTemplate', [], [ 0 => [ 'F' => 'v' ] ] );

		$this->builder->calendarHTML( $tif, '/extensions/PageForms' );

		$this->assertArrayHasKey( 'MyTemplate', $wgPageFormsCalendarValues );
		$this->assertSame( [ 0 => [ 'F' => 'v' ] ], $wgPageFormsCalendarValues['MyTemplate'] );
	}

	public function testCalendarHtmlUsesScriptPathForLoadingGif(): void {
		global $wgPageFormsCalendarParams, $wgPageFormsCalendarValues;
		$wgPageFormsCalendarParams = [];
		$wgPageFormsCalendarValues = [];

		$tif = $this->makeTif( 'T', [] );
		$html = $this->builder->calendarHTML( $tif, '/custom/path' );

		$this->assertStringContainsString( '/custom/path/skins/loading.gif', $html );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function makeTif(
		string $templateName,
		array $fields,
		array $gridValues = []
	): PFTemplateInForm {
		$tif = $this->createMock( PFTemplateInForm::class );
		$tif->method( 'getTemplateName' )->willReturn( $templateName );
		$tif->method( 'getFields' )->willReturn( $fields );
		$tif->method( 'getGridValues' )->willReturn( $gridValues );
		$tif->method( 'getEventTitleField' )->willReturn( '' );
		$tif->method( 'getEventDateField' )->willReturn( '' );
		$tif->method( 'getEventStartDateField' )->willReturn( '' );
		$tif->method( 'getEventEndDateField' )->willReturn( '' );
		return $tif;
	}

	private function makeFormField(
		string $fieldName,
		string $inputType,
		?array $possibleValues,
		?string $label = null
	): object {
		$templateField = $this->createMock( PFTemplateField::class );
		$templateField->method( 'getFieldName' )->willReturn( $fieldName );

		$formField = $this->createMock( PFFormField::class );
		$formField->template_field = $templateField;
		$formField->method( 'getInputType' )->willReturn( $inputType );
		$formField->method( 'getPossibleValues' )->willReturn( $possibleValues );
		$formField->method( 'getLabel' )->willReturn( $label );
		return $formField;
	}
}
