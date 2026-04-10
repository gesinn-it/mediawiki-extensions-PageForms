<?php

declare( strict_types=1 );

/**
 * @covers \PFTextAreaInput
 * @group Database
 */
class PFTextAreaInputTest extends MediaWikiIntegrationTestCase {

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
		$input = new PFTextAreaInput( 1, $curValue, 'PFTAField01', $isDisabled, $extraArgs );
		return $input->getHtmlText();
	}

	public function testGetHtmlRendersTextareaElement(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( '<textarea ', $html );
		$this->assertStringContainsString( 'name="PFTAField01"', $html );
	}

	public function testGetHtmlDefaultRowsAndStyle(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'rows="5"', $html );
		$this->assertStringContainsString( 'cols="90"', $html );
		$this->assertStringContainsString( 'style="width: 100%"', $html );
	}

	public function testGetHtmlPreloadValueIsRenderedAsContent(): void {
		$html = $this->getHtml( 'PFTAPreloadedContent01' );

		$this->assertStringContainsString( '>PFTAPreloadedContent01</textarea>', $html );
	}

	public function testGetHtmlEmptyValueRendersEmptyTextarea(): void {
		$html = $this->getHtml( '' );

		$this->assertStringContainsString( '></textarea>', $html );
	}

	public function testGetHtmlAutogrowAddsAutoGrowClass(): void {
		$html = $this->getHtml( '', false, false, [ 'autogrow' => true ] );

		$this->assertStringContainsString( 'autoGrow', $html );
	}

	public function testGetHtmlAutogrowSetsWidthAuto(): void {
		$html = $this->getHtml( '', false, false, [ 'autogrow' => true ] );

		$this->assertStringContainsString( 'style="width: auto"', $html );
	}

	public function testGetHtmlAutogrowSetsCols90(): void {
		$html = $this->getHtml( '', false, false, [ 'autogrow' => true ] );

		$this->assertStringContainsString( 'cols="90"', $html );
	}

	public function testGetHtmlCustomRowsAndCols(): void {
		$html = $this->getHtml( '', false, false, [ 'rows' => 10, 'cols' => 60 ] );

		$this->assertStringContainsString( 'rows="10"', $html );
		$this->assertStringContainsString( 'cols="60"', $html );
		$this->assertStringContainsString( 'style="width: auto"', $html );
	}

	public function testGetHtmlMandatoryAddsMandatoryFieldClass(): void {
		$html = $this->getHtml( '', true );

		$this->assertStringContainsString( 'mandatoryField', $html );
		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
	}

	public function testGetHtmlNonMandatoryUsesCreateboxInputClass(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'createboxInput', $html );
		$this->assertStringNotContainsString( 'mandatoryField', $html );
	}

	public function testGetHtmlDisabledAddsDisabledAttribute(): void {
		$html = $this->getHtml( '', false, true );

		$this->assertStringContainsString( 'disabled', $html );
	}

	public function testGetHtmlPlaceholderAttribute(): void {
		$html = $this->getHtml( '', false, false, [ 'placeholder' => 'PFTAEnterText01' ] );

		$this->assertStringContainsString( 'placeholder="PFTAEnterText01"', $html );
	}

	public function testGetHtmlIsSectionAddsPageSectionSpanClass(): void {
		$html = $this->getHtml( '', false, false, [ 'isSection' => true ] );

		$this->assertStringContainsString( 'pageSection', $html );
	}

	public function testGetHtmlSpanWrapsTextarea(): void {
		$html = $this->getHtml();

		// assertMatchesRegularExpression() requires PHPUnit >= 9.1 (not available on MW 1.35).
		// Verify structure by confirming <span appears before <textarea in output.
		$this->assertStringContainsString( 'inputSpan', $html );
		$this->assertGreaterThan( strpos( $html, '<span' ), strpos( $html, '<textarea' ) );
	}

	public function testGetNameReturnsTextarea(): void {
		$this->assertSame( 'textarea', PFTextAreaInput::getName() );
	}

	public function testGetParametersContainsTextareaSpecificParams(): void {
		$params = PFTextAreaInput::getParameters();

		$this->assertArrayHasKey( 'preload', $params );
		$this->assertArrayHasKey( 'autogrow', $params );
		$this->assertArrayHasKey( 'rows', $params );
		$this->assertArrayHasKey( 'cols', $params );
		$this->assertArrayHasKey( 'placeholder', $params );
	}

	public function testValidCovers(): void {
		$this->assertSame( 'textarea', PFTextAreaInput::getName() );
	}
}
