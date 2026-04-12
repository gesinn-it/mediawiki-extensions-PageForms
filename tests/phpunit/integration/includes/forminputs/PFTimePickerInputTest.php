<?php

declare( strict_types=1 );

/**
 * @covers \PFTimePickerInput
 * @group Database
 */
class PFTimePickerInputTest extends MediaWikiIntegrationTestCase {

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
		$input = new PFTimePickerInput( 1, $curValue, 'PFTPField01', $isDisabled, $extraArgs );
		return $input->getHtmlText();
	}

	// ---- getName ----

	public function testGetNameReturnsTimepicker(): void {
		$this->assertSame( 'timepicker', PFTimePickerInput::getName() );
	}

	// ---- basic rendering ----

	public function testBasicRenderContainsInputSpanWrapper(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( '<span class="inputSpan">', $html );
	}

	public function testBasicRenderContainsTextInputWithFieldName(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'name="PFTPField01"', $html );
		$this->assertStringContainsString( '<input ', $html );
	}

	// ---- mandatory ----

	public function testMandatoryAddsMandatoryFieldSpan(): void {
		$html = $this->getHtml( '', true );

		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
		$this->assertStringContainsString( 'inputSpan', $html );
	}

	public function testNonMandatoryHasNoMandatoryFieldSpan(): void {
		$html = $this->getHtml();

		$this->assertStringNotContainsString( 'mandatoryFieldSpan', $html );
	}

	// ---- current value ----

	public function testStoredTimeValueIsPresentInOutput(): void {
		$html = $this->getHtml( '14:30' );

		$this->assertStringContainsString( '14:30', $html );
	}

	// ---- disabled ----

	public function testDisabledInputRendersWithDisabledAttribute(): void {
		$html = $this->getHtml( '', false, true );

		$this->assertStringContainsString( 'disabled', $html );
	}

	// ---- part of dtp ----

	public function testPartOfDtpSkipsInputSpanWrapper(): void {
		$html = $this->getHtml( '', false, false, [ 'part of dtp' => true ] );

		$this->assertStringNotContainsString( '<span class="inputSpan">', $html );
		$this->assertStringContainsString( 'name="PFTPField01"', $html );
	}

	// ---- mintime / maxtime boundary validation ----

	public function testInvalidMintimeIsIgnored(): void {
		$html = $this->getHtml( '', false, false, [ 'mintime' => 'not-a-time' ] );

		$this->assertStringContainsString( 'name="PFTPField01"', $html );
	}

	public function testValidMintimeIsAccepted(): void {
		$html = $this->getHtml( '', false, false, [ 'mintime' => '08:00' ] );

		$this->assertStringContainsString( 'name="PFTPField01"', $html );
	}

	// ---- class parameter ----

	public function testClassParamIsAppliedToInput(): void {
		$html = $this->getHtml( '', false, false, [ 'class' => 'myTimepickerClass' ] );

		$this->assertStringContainsString( 'myTimepickerClass', $html );
	}
}
