<?php

declare( strict_types=1 );

use OOUI\BlankTheme;

/**
 * @covers \PFDatePickerInput
 * @group Database
 */
class PFDatePickerInputTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		\OOUI\Theme::setSingleton( new BlankTheme() );

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
		$input = new PFDatePickerInput( 1, $curValue, 'PFDPField01', $isDisabled, $extraArgs );
		return $input->getHtmlText();
	}

	// ---- getName ----

	public function testGetNameReturnsDatepicker(): void {
		$this->assertSame( 'datepicker', PFDatePickerInput::getName() );
	}

	// ---- basic rendering ----

	public function testBasicRenderingContainsPfDatePicker(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'pfDatePicker', $html );
	}

	public function testBasicRenderingContainsPfPickerWrapper(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'pfPickerWrapper', $html );
	}

	public function testBasicRenderingContainsInputId(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'input_1', $html );
	}

	// ---- first date / mustBeAfter ----

	public function testFirstDateParamAddsMustBeAfterToDataOoui(): void {
		$html = $this->getHtml( '', false, false, [ 'first date' => '2024-01-15' ] );

		$this->assertStringContainsString( 'mustBeAfter', $html );
		$this->assertStringContainsString( '2024-01-15', $html );
	}

	public function testFirstDateWithSlashSeparatorIsNormalizedToDash(): void {
		$html = $this->getHtml( '', false, false, [ 'first date' => '2024/01/15' ] );

		$this->assertStringContainsString( 'mustBeAfter', $html );
		$this->assertStringContainsString( '2024-01-15', $html );
		$this->assertStringNotContainsString( '2024/01/15', $html );
	}

	// ---- last date / mustBeBefore ----

	public function testLastDateParamAddsMustBeforeToDataOoui(): void {
		$html = $this->getHtml( '', false, false, [ 'last date' => '2024-12-31' ] );

		$this->assertStringContainsString( 'mustBeBefore', $html );
		$this->assertStringContainsString( '2024-12-31', $html );
	}

	public function testLastDateWithSlashSeparatorIsNormalizedToDash(): void {
		$html = $this->getHtml( '', false, false, [ 'last date' => '2024/12/31' ] );

		$this->assertStringContainsString( 'mustBeBefore', $html );
		$this->assertStringContainsString( '2024-12-31', $html );
		$this->assertStringNotContainsString( '2024/12/31', $html );
	}

	// ---- first date + last date together ----

	public function testFirstAndLastDateBothPresent(): void {
		$html = $this->getHtml( '', false, false, [
			'first date' => '2024-01-01',
			'last date'  => '2024-12-31',
		] );

		$this->assertStringContainsString( 'mustBeAfter', $html );
		$this->assertStringContainsString( '2024-01-01', $html );
		$this->assertStringContainsString( 'mustBeBefore', $html );
		$this->assertStringContainsString( '2024-12-31', $html );
	}

	// ---- mandatory ----

	public function testMandatoryAddsClassToWrapper(): void {
		$html = $this->getHtml( '', true );

		$this->assertStringContainsString( 'mandatory', $html );
		$this->assertStringContainsString( 'pfPickerWrapper', $html );
	}

	public function testNonMandatoryDoesNotHaveMandatoryClass(): void {
		$html = $this->getHtml();

		$this->assertStringNotContainsString( 'mandatory', $html );
	}

	// ---- custom class ----

	public function testCustomClassIsAppendedToWrapper(): void {
		$html = $this->getHtml( '', false, false, [ 'class' => 'myCustomClass' ] );

		$this->assertStringContainsString( 'myCustomClass', $html );
		$this->assertStringContainsString( 'pfPickerWrapper', $html );
	}

	// ---- current value ----

	public function testCurrentValueIsPresentInOutput(): void {
		$html = $this->getHtml( '2024-06-15' );

		$this->assertStringContainsString( '2024-06-15', $html );
	}

	// ---- disabled ----

	public function testDisabledInputIsRendered(): void {
		$html = $this->getHtml( '', false, true );

		// disabled renders without crash; OOUI adds disabled attribute
		$this->assertStringContainsString( 'pfPickerWrapper', $html );
	}
}
