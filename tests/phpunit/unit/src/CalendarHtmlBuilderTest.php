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

	public function testCalendarHtmlDoesNotDuplicateLoadingDiv(): void {
		// Regression test: the loading-indicator div (id='fullCalendarLoading1')
		// must be nested exactly once inside the FullCalendar container div, not
		// duplicated at both the top level and inside the container. A previous
		// self-referential compound assignment ($text .= ...) caused PHP to
		// evaluate the RHS using the pre-assignment value of $text, duplicating
		// the loading div and its id attribute in the output.
		global $wgPageFormsCalendarParams, $wgPageFormsCalendarValues;
		$wgPageFormsCalendarParams = [];
		$wgPageFormsCalendarValues = [];

		$tif = $this->makeTif( 'MyTemplate', [] );
		$html = $this->builder->calendarHTML( $tif, '/extensions/PageForms' );

		$this->assertSame(
			1,
			substr_count( $html, 'fullCalendarLoading1' ),
			'The loading indicator div must appear exactly once in the output HTML.'
		);
		$this->assertSame(
			1,
			substr_count( $html, 'loading.gif' ),
			'The loading image must appear exactly once in the output HTML.'
		);
		// The loading div must be nested inside the FullCalendar container div,
		// not duplicated outside of it.
		$containerPos = strpos( $html, 'pfFullCalendarJS' );
		$loadingPos = strpos( $html, 'fullCalendarLoading1' );
		$this->assertNotFalse( $containerPos );
		$this->assertNotFalse( $loadingPos );
		$this->assertGreaterThan(
			$containerPos,
			$loadingPos,
			'The loading indicator div must be nested inside the FullCalendar container div.'
		);
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
		$this->assertSame(
			[
				[ 'Name' => '', 'Id' => '' ],
				[ 'Name' => 'A', 'Id' => 'A' ],
				[ 'Name' => 'B', 'Id' => 'B' ],
				[ 'Name' => 'C', 'Id' => 'C' ],
			],
			$params['items']
		);
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
