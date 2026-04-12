<?php

declare( strict_types=1 );

/**
 * @covers \PFRatingInput
 * @group Database
 */
class PFRatingInputTest extends MediaWikiIntegrationTestCase {

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
		$input = new PFRatingInput( 1, $curValue, 'PFRatingField01', $isDisabled, $extraArgs );
		return $input->getHtmlText();
	}

	// ---- getName ----

	public function testGetNameReturnsRating(): void {
		$this->assertSame( 'rating', PFRatingInput::getName() );
	}

	// ---- basic rendering ----

	public function testBasicRenderContainsPfRatingClass(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'pfRating', $html );
	}

	public function testBasicRenderContainsPfRatingWrapper(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'pfRatingWrapper', $html );
	}

	// ---- default parameters ----

	public function testDefaultNumStarsIsFive(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'data-numstars="5"', $html );
	}

	public function testDefaultStarWidthIs24px(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'data-starwidth="24px"', $html );
	}

	// ---- custom parameters ----

	public function testCustomNumStarsIsRendered(): void {
		$html = $this->getHtml( '', false, false, [ 'num stars' => 10 ] );

		$this->assertStringContainsString( 'data-numstars="10"', $html );
	}

	public function testCustomStarWidthIsRendered(): void {
		$html = $this->getHtml( '', false, false, [ 'star width' => '32px' ] );

		$this->assertStringContainsString( 'data-starwidth="32px"', $html );
	}

	public function testAllowHalfStarsAddsDataAttribute(): void {
		$html = $this->getHtml( '', false, false, [ 'allow half stars' => true ] );

		$this->assertStringContainsString( 'data-allows-half', $html );
	}

	public function testWithoutAllowHalfStarsHasNoHalfAttr(): void {
		$html = $this->getHtml();

		$this->assertStringNotContainsString( 'data-allows-half', $html );
	}

	// ---- stored value ----

	public function testStoredValueAppearsInDataCurvalue(): void {
		$html = $this->getHtml( '3' );

		$this->assertStringContainsString( 'data-curvalue="3"', $html );
	}

	// ---- mandatory ----

	public function testMandatoryAddsMandatoryFieldClass(): void {
		$html = $this->getHtml( '', true );

		$this->assertStringContainsString( 'mandatoryField', $html );
	}

	public function testNonMandatoryHasNoMandatoryFieldClass(): void {
		$html = $this->getHtml();

		$this->assertStringNotContainsString( 'mandatoryField', $html );
	}

	// ---- disabled ----

	public function testDisabledAddsDisabledAttribute(): void {
		$html = $this->getHtml( '', false, true );

		$this->assertStringContainsString( 'disabled=""', $html );
	}
}
