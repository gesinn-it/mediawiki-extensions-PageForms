<?php

declare( strict_types=1 );

/**
 * @covers \PFDateTimeInput
 * @group Database
 */
class PFDateTimeInputTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		global $wgPageFormsTabIndex, $wgPageFormsFieldNum;
		$wgPageFormsTabIndex = 1;
		$wgPageFormsFieldNum = 1;
	}

	private function getHtml(
		string $curValue = '',
		bool $isMandatory = false,
		bool $isDisabled = false,
		array $extraArgs = []
	): string {
		if ( $isMandatory ) {
			$extraArgs['mandatory'] = true;
		}
		$input = new PFDateTimeInput( 1, $curValue, 'PFDTField01', $isDisabled, $extraArgs );
		return $input->getHtmlText();
	}

	public function testGetHtmlEmptyValueRendersHourMinuteSecondInputs(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'class="hoursInput"', $html );
		$this->assertStringContainsString( 'class="minutesInput"', $html );
		$this->assertStringContainsString( 'class="secondsInput"', $html );
	}

	public function testGetHtml12HourModeRendersAmPmSelect(): void {
		global $wgPageForms24HourTime;
		$wgPageForms24HourTime = false;

		$html = $this->getHtml();

		$this->assertStringContainsString( 'class="ampmInput"', $html );
		$this->assertStringContainsString( '<option value="AM">', $html );
		$this->assertStringContainsString( '<option value="PM">', $html );
	}

	public function testGetHtml24HourModeOmitsAmPmSelect(): void {
		global $wgPageForms24HourTime;
		$wgPageForms24HourTime = true;

		$html = $this->getHtml();

		$this->assertStringNotContainsString( 'class="ampmInput"', $html );
		$this->assertStringNotContainsString( 'AM', $html );
		$this->assertStringNotContainsString( 'PM', $html );
	}

	public function testGetHtml12HourModeParsesPm(): void {
		global $wgPageForms24HourTime;
		$wgPageForms24HourTime = false;

		// 15:30 = 3:30 PM in 12h
		$html = $this->getHtml( '2024-01-15 15:30:00' );

		$this->assertStringContainsString( 'value="3"', $html );
		$this->assertStringContainsString( '<option value="PM" selected="selected">', $html );
	}

	public function testGetHtml24HourModeParsesHourAsIs(): void {
		global $wgPageForms24HourTime;
		$wgPageForms24HourTime = true;

		// 15:30 = hour 15 in 24h
		$html = $this->getHtml( '2024-01-15 15:30:00' );

		$this->assertStringContainsString( 'value="15"', $html );
		$this->assertStringNotContainsString( 'ampmInput', $html );
	}

	public function testGetHtml12HourModePreselectsAm(): void {
		global $wgPageForms24HourTime;
		$wgPageForms24HourTime = false;

		// 09:00 = 9:00 AM in 12h
		$html = $this->getHtml( '2024-01-15 09:00:00' );

		$this->assertStringContainsString( '<option value="AM" selected="selected">', $html );
		$this->assertStringNotContainsString( '<option value="PM" selected="selected">', $html );
	}

	public function testGetHtmlMandatoryAddsMandatoryFieldSpanClass(): void {
		$html = $this->getHtml( '', true );

		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
	}

	public function testGetHtmlNonMandatoryOmitsMandatoryFieldSpanClass(): void {
		$html = $this->getHtml();

		$this->assertStringNotContainsString( 'mandatoryFieldSpan', $html );
	}

	public function testGetHtmlCustomClassIsAddedToSpan(): void {
		$html = $this->getHtml( '', false, false, [ 'class' => 'PFDTCustomClass01' ] );

		$this->assertStringContainsString( 'PFDTCustomClass01', $html );
	}

	public function testGetHtmlIncludeTimezoneAddsTimezoneInput(): void {
		$html = $this->getHtml( '2024-01-15 09:00:00', false, false, [ 'include timezone' => true ] );

		$this->assertStringContainsString( '[timezone]', $html );
	}

	public function testGetHtmlWithoutIncludeTimezoneOmitsTimezoneInput(): void {
		$html = $this->getHtml( '2024-01-15 09:00:00' );

		$this->assertStringNotContainsString( '[timezone]', $html );
	}

	public function testGetNameReturnsDatetime(): void {
		$this->assertSame( 'datetime', PFDateTimeInput::getName() );
	}
}
