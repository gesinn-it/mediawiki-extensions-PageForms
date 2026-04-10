<?php

declare( strict_types=1 );

/**
 * @covers \PFStartDateInput
 * @covers \PFEndDateInput
 * @group Database
 */
class PFStartEndDateInputTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		global $wgPageFormsTabIndex, $wgPageFormsFieldNum, $wgAmericanDates;
		$wgPageFormsTabIndex = 1;
		$wgPageFormsFieldNum = 1;
		$wgAmericanDates = false;
	}

	private function getStartDateHtml(
		string $curValue = '',
		bool $isMandatory = false,
		bool $isDisabled = false,
		array $extraArgs = []
	): string {
		if ( $isMandatory ) {
			$extraArgs['mandatory'] = true;
		}
		$input = new PFStartDateInput( 1, $curValue, 'PFSDField01', $isDisabled, $extraArgs );
		return $input->getHtmlText();
	}

	private function getEndDateHtml(
		string $curValue = '',
		bool $isMandatory = false,
		bool $isDisabled = false,
		array $extraArgs = []
	): string {
		if ( $isMandatory ) {
			$extraArgs['mandatory'] = true;
		}
		$input = new PFEndDateInput( 1, $curValue, 'PFEDField01', $isDisabled, $extraArgs );
		return $input->getHtmlText();
	}

	// ---- getName ----

	public function testStartDateGetNameReturnsStartDate(): void {
		$this->assertSame( 'start date', PFStartDateInput::getName() );
	}

	public function testEndDateGetNameReturnsEndDate(): void {
		$this->assertSame( 'end date', PFEndDateInput::getName() );
	}

	// ---- CSS classes ----

	public function testStartDateSpanHasStartDateInputClass(): void {
		$html = $this->getStartDateHtml();

		$this->assertStringContainsString( 'startDateInput', $html );
		$this->assertStringContainsString( 'dateInput', $html );
	}

	public function testEndDateSpanHasEndDateInputClass(): void {
		$html = $this->getEndDateHtml();

		$this->assertStringContainsString( 'endDateInput', $html );
		$this->assertStringContainsString( 'dateInput', $html );
	}

	public function testStartDateDoesNotHaveEndDateInputClass(): void {
		$html = $this->getStartDateHtml();

		$this->assertStringNotContainsString( 'endDateInput', $html );
	}

	public function testEndDateDoesNotHaveStartDateInputClass(): void {
		$html = $this->getEndDateHtml();

		$this->assertStringNotContainsString( 'startDateInput', $html );
	}

	// ---- basic rendering ----

	public function testStartDateRendersYearMonthDayInputs(): void {
		$html = $this->getStartDateHtml();

		$this->assertStringContainsString( 'class="yearInput"', $html );
		$this->assertStringContainsString( 'class="dayInput"', $html );
		$this->assertStringContainsString( 'class="monthInput"', $html );
	}

	public function testEndDateRendersYearMonthDayInputs(): void {
		$html = $this->getEndDateHtml();

		$this->assertStringContainsString( 'class="yearInput"', $html );
		$this->assertStringContainsString( 'class="dayInput"', $html );
		$this->assertStringContainsString( 'class="monthInput"', $html );
	}

	// ---- re-edit: stored value is pre-filled ----

	public function testStartDateReEditPrefillsYear(): void {
		$html = $this->getStartDateHtml( '2024-06-15' );

		$this->assertStringContainsString( 'value="2024"', $html );
	}

	public function testEndDateReEditPrefillsYear(): void {
		$html = $this->getEndDateHtml( '2025-12-31' );

		$this->assertStringContainsString( 'value="2025"', $html );
	}

	// ---- mandatory ----

	public function testStartDateMandatoryAddsMandatoryFieldSpanClass(): void {
		$html = $this->getStartDateHtml( '', true );

		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
	}

	public function testEndDateMandatoryAddsMandatoryFieldSpanClass(): void {
		$html = $this->getEndDateHtml( '', true );

		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
	}

	public function testStartDateNonMandatoryOmitsMandatoryFieldSpanClass(): void {
		$html = $this->getStartDateHtml();

		$this->assertStringNotContainsString( 'mandatoryFieldSpan', $html );
	}

	// ---- custom class ----

	public function testStartDateCustomClassIsAddedToSpan(): void {
		$html = $this->getStartDateHtml( '', false, false, [ 'class' => 'PFSDCustomClass01' ] );

		$this->assertStringContainsString( 'PFSDCustomClass01', $html );
	}

	public function testEndDateCustomClassIsAddedToSpan(): void {
		$html = $this->getEndDateHtml( '', false, false, [ 'class' => 'PFEDCustomClass01' ] );

		$this->assertStringContainsString( 'PFEDCustomClass01', $html );
	}
}
