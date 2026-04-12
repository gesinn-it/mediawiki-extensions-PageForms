<?php

declare( strict_types=1 );

use OOUI\BlankTheme;

/**
 * @covers \PFDateTimePicker
 * @group Database
 */
class PFDateTimePickerTest extends MediaWikiIntegrationTestCase {

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
		$input = new PFDateTimePicker( 1, $curValue, 'PFDTPField01', $isDisabled, $extraArgs );
		return $input->getHtmlText();
	}

	// ---- getName ----

	public function testGetNameReturnsDatetimepicker(): void {
		$this->assertSame( 'datetimepicker', PFDateTimePicker::getName() );
	}

	// ---- basic rendering ----

	public function testBasicRenderContainsPfDateTimePicker(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'pfDateTimePicker', $html );
	}

	public function testBasicRenderContainsPfPickerWrapper(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'pfPickerWrapper', $html );
	}

	public function testBasicRenderContainsInputId(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'input_1', $html );
	}

	public function testBasicRenderContains24HourLabel(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'oo-ui-inline-help', $html );
		$this->assertStringContainsString( 'input_1', $html );
	}

	public function testBasicRenderContainsFieldName(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'PFDTPField01', $html );
	}

	// ---- mandatory ----

	public function testMandatoryAddsClassToWrapper(): void {
		$html = $this->getHtml( '', true );

		$this->assertStringContainsString( 'mandatory', $html );
		$this->assertStringContainsString( 'pfPickerWrapper', $html );
	}

	public function testNonMandatoryDoesNotHaveMandatoryClass(): void {
		$html = $this->getHtml();

		$this->assertStringNotContainsString( ' mandatory', $html );
	}

	// ---- custom class ----

	public function testCustomClassIsAppendedToWrapper(): void {
		$html = $this->getHtml( '', false, false, [ 'class' => 'myDTPClass' ] );

		$this->assertStringContainsString( 'myDTPClass', $html );
		$this->assertStringContainsString( 'pfPickerWrapper', $html );
	}

	// ---- current value — stored datetime ISO format ----

	public function testStoredDatetimeValueIsPresentInOutput(): void {
		$html = $this->getHtml( '2024-06-15 14:30:00' );

		$this->assertStringContainsString( '2024-06-15', $html );
	}

	public function testStoredDatetimeIsNormalizedToIsoWithT(): void {
		$html = $this->getHtml( '2024-06-15 14:30:00' );

		$this->assertStringContainsString( '2024-06-15T14:30:00Z', $html );
	}

	// ---- empty value renders without crash ----

	public function testEmptyValueRendersWithoutCrash(): void {
		$html = $this->getHtml( '' );

		$this->assertStringContainsString( 'pfDateTimePicker', $html );
	}

	// ---- disabled ----

	public function testDisabledInputRendersWithoutCrash(): void {
		$html = $this->getHtml( '', false, true );

		$this->assertStringContainsString( 'pfPickerWrapper', $html );
	}
}
