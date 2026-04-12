<?php

declare( strict_types=1 );

/**
 * @covers \PFYearInput
 * @group Database
 */
class PFYearInputTest extends MediaWikiIntegrationTestCase {

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
		return PFYearInput::getHTML(
			$curValue,
			'PFYearField01',
			$isMandatory,
			$isDisabled,
			$extraArgs
		);
	}

	// ---- getName ----

	public function testGetNameReturnsYear(): void {
		$this->assertSame( 'year', PFYearInput::getName() );
	}

	// ---- basic rendering ----

	public function testBasicRenderContainsInputWithFieldName(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'name="PFYearField01"', $html );
		$this->assertStringContainsString( '<input ', $html );
	}

	public function testBasicRenderHasFixedSizeOfFour(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'size="4"', $html );
	}

	// ---- size is always 4 regardless of user param ----

	public function testSizeParamIsOverriddenToFour(): void {
		$html = $this->getHtml( '', false, false, [ 'size' => 10 ] );

		$this->assertStringContainsString( 'size="4"', $html );
		$this->assertStringNotContainsString( 'size="10"', $html );
	}

	// ---- mandatory ----

	public function testMandatoryAddsMandatoryFieldSpan(): void {
		$html = $this->getHtml( '', true );

		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
	}

	public function testNonMandatoryHasNoMandatoryFieldSpan(): void {
		$html = $this->getHtml();

		$this->assertStringNotContainsString( 'mandatoryFieldSpan', $html );
	}

	// ---- current value ----

	public function testStoredYearValueIsPresentInOutput(): void {
		$html = $this->getHtml( '2024' );

		$this->assertStringContainsString( '2024', $html );
	}

	// ---- disabled ----

	public function testDisabledInputRendersWithDisabledAttribute(): void {
		$html = $this->getHtml( '', false, true );

		$this->assertStringContainsString( 'disabled', $html );
	}

	// ---- class parameter ----

	public function testClassParamIsAppliedToInput(): void {
		$html = $this->getHtml( '', false, false, [ 'class' => 'myYearClass' ] );

		$this->assertStringContainsString( 'myYearClass', $html );
	}
}
