<?php

declare( strict_types=1 );

/**
 * @covers \PFDateInput
 * @group Database
 */
class PFDateInputTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		global $wgPageFormsTabIndex, $wgPageFormsFieldNum, $wgAmericanDates;
		$wgPageFormsTabIndex = 1;
		$wgPageFormsFieldNum = 1;
		$wgAmericanDates = false;
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
		$input = new PFDateInput( 1, $curValue, 'PFDateField01', $isDisabled, $extraArgs );
		return $input->getHtmlText();
	}

	public function testGetHtmlRendersDayMonthYearInputsInOneSpan(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'dayInput', $html );
		$this->assertStringContainsString( 'monthInput', $html );
		$this->assertStringContainsString( 'yearInput', $html );
	}

	public function testGetHtmlWrapsInDateInputSpan(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'class="dateInput"', $html );
	}

	public function testGetHtmlEmptyValueRendersBlankDayAndYear(): void {
		$html = $this->getHtml( '' );

		// null is coerced to empty string in PHP string concatenation
		$this->assertStringContainsString( 'class="dayInput"', $html );
		$this->assertStringContainsString( 'class="yearInput"', $html );
		$this->assertStringNotContainsString( 'value="2024"', $html );
	}

	public function testGetHtmlPreloadsYearFromDateString(): void {
		$html = $this->getHtml( '2024-06-15' );

		$this->assertStringContainsString( 'value="2024"', $html );
	}

	public function testGetHtmlPreloadsDayFromDateString(): void {
		$html = $this->getHtml( '2024-06-15' );

		// size="2" is unique to the dayInput
		$this->assertStringContainsString( 'value="15" size="2"', $html );
	}

	public function testGetHtmlPreloadsMonthFromDateString(): void {
		$html = $this->getHtml( '2024-06-15' );

		// June is month index 6 → padded value "06", selected=""
		$this->assertStringContainsString( '<option value="06" selected="">', $html );
	}

	public function testGetHtmlMandatoryAddsMandatoryFieldSpanClass(): void {
		$html = $this->getHtml( '', true );

		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
	}

	public function testGetHtmlDisabledAddsDisabledAttribute(): void {
		$html = $this->getHtml( '', false, true );

		$this->assertStringContainsString( 'disabled', $html );
	}
}
