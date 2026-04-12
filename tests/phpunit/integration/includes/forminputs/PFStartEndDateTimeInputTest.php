<?php

declare( strict_types=1 );

/**
 * @covers \PFStartDateTimeInput
 * @covers \PFEndDateTimeInput
 * @group Database
 */
class PFStartEndDateTimeInputTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		global $wgPageFormsTabIndex, $wgPageFormsFieldNum, $wgAmericanDates, $wgPageForms24HourTime;
		$wgPageFormsTabIndex = 1;
		$wgPageFormsFieldNum = 1;
		$wgAmericanDates = false;
		$wgPageForms24HourTime = true;
	}

	private function getStartHtml(
		string $curValue = '',
		bool $isMandatory = false,
		bool $isDisabled = false,
		array $extraArgs = []
	): string {
		if ( $isMandatory ) {
			$extraArgs['mandatory'] = true;
		}
		$input = new PFStartDateTimeInput( 1, $curValue, 'PFSDTField01', $isDisabled, $extraArgs );
		return $input->getHtmlText();
	}

	private function getEndHtml(
		string $curValue = '',
		bool $isMandatory = false,
		bool $isDisabled = false,
		array $extraArgs = []
	): string {
		if ( $isMandatory ) {
			$extraArgs['mandatory'] = true;
		}
		$input = new PFEndDateTimeInput( 1, $curValue, 'PFEDTField01', $isDisabled, $extraArgs );
		return $input->getHtmlText();
	}

	// ---- getName ----

	public function testStartDateTimeGetNameReturnsStartDatetime(): void {
		$this->assertSame( 'start datetime', PFStartDateTimeInput::getName() );
	}

	public function testEndDateTimeGetNameReturnsEndDatetime(): void {
		$this->assertSame( 'end datetime', PFEndDateTimeInput::getName() );
	}

	// ---- getInputClass / span wrapper ----

	public function testStartDateTimeRendersStartDateTimeInputClass(): void {
		$html = $this->getStartHtml();

		$this->assertStringContainsString( 'startDateTimeInput', $html );
		$this->assertStringContainsString( 'dateTimeInput', $html );
	}

	public function testEndDateTimeRendersEndDateTimeInputClass(): void {
		$html = $this->getEndHtml();

		$this->assertStringContainsString( 'endDateTimeInput', $html );
		$this->assertStringContainsString( 'dateTimeInput', $html );
	}

	// ---- field presence ----

	public function testStartDateTimeRendersDayMonthYearAndTimeInputs(): void {
		$html = $this->getStartHtml();

		$this->assertStringContainsString( 'dayInput', $html );
		$this->assertStringContainsString( 'monthInput', $html );
		$this->assertStringContainsString( 'yearInput', $html );
		$this->assertStringContainsString( 'hoursInput', $html );
		$this->assertStringContainsString( 'minutesInput', $html );
	}

	public function testEndDateTimeRendersDayMonthYearAndTimeInputs(): void {
		$html = $this->getEndHtml();

		$this->assertStringContainsString( 'dayInput', $html );
		$this->assertStringContainsString( 'monthInput', $html );
		$this->assertStringContainsString( 'yearInput', $html );
		$this->assertStringContainsString( 'hoursInput', $html );
		$this->assertStringContainsString( 'minutesInput', $html );
	}

	// ---- mandatory ----

	public function testStartDateTimeMandatoryAddsMandatoryFieldSpan(): void {
		$html = $this->getStartHtml( '', true );

		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
	}

	public function testEndDateTimeMandatoryAddsMandatoryFieldSpan(): void {
		$html = $this->getEndHtml( '', true );

		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
	}
}
